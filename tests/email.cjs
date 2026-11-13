const {chromium}=require('playwright'),{spawn,execFileSync}=require('child_process');
(async()=>{
 const server=spawn('php',['-S','127.0.0.1:8101','-t','.'],{stdio:'ignore'});let browser;
 function check(ok,msg){if(!ok)throw Error(msg);}
 try{
  for(let i=0;i<50;i++){try{if((await fetch('http://127.0.0.1:8101/login.php')).ok)break;}catch{}await new Promise(r=>setTimeout(r,100));}
  browser=await chromium.launch({channel:'chrome',headless:true,args:['--no-sandbox']});
  const page=await browser.newPage({viewport:{width:1280,height:950}}),origin='http://127.0.0.1:8101';
  await page.goto(origin+'/login.php');await page.locator('input[name=nome]').fill('admin');await page.locator('input[name=password]').fill('1234567.');
  await Promise.all([page.waitForURL('**/pannello.php'),page.getByRole('button',{name:'Accedi',exact:true}).click()]);
  await page.goto(origin+'/gestione_email.php');
  check(await page.locator('h1').textContent()==='Gestione email','Titolo email');
  await page.locator('.email-contact input:enabled').first().check();
  await page.locator('#email-subject').fill('BOZZA BROWSER');
  await page.locator('#email-editor').fill('Buongiorno {nome}, prova senza spedizione.');
  await page.getByRole('button',{name:'Anteprima',exact:true}).click();
  check(await page.locator('#email-preview-dialog').isVisible(),'Anteprima');
  await page.getByRole('button',{name:'Chiudi',exact:true}).click();
  await Promise.all([page.waitForURL('**/gestione_email.php?tab=componi&id=*&salvato=1'),page.getByRole('button',{name:'Salva bozza e destinatari'}).click()]);
  check((await page.locator('body').textContent()).includes('Salvataggio completato'),'Salvataggio bozza');
  await page.goto(origin+'/gestione_email.php?tab=config');
  await page.locator('#email-generate-secret').click();
  check(/^[a-f0-9]{64}$/.test(await page.locator('#email-secret').inputValue()),'Generazione segreto');
  check(!await page.getByRole('button',{name:'Invia email di prova',exact:true}).isEnabled(),'Prova attiva senza verifica');
  await page.setViewportSize({width:375,height:900});await page.goto(origin+'/gestione_email.php');
  check(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),'Overflow email mobile');
  const tokenCode="require 'includes/email.php'; $p=poimanager_db(); $r=$p->query(\"SELECT ID FROM email_invii WHERE Stato='inviato' AND ConfermatoIl IS NULL LIMIT 1\")->fetch(); if(!$r)exit(1); echo json_encode(['id'=>$r['ID'],'token'=>em_token($r['ID'])]);";
  const job=JSON.parse(execFileSync('php',['-r',tokenCode],{encoding:'utf8'}));
  const stateCode="require 'includes/database.php'; $q=poimanager_db()->prepare('SELECT ConfermatoIl FROM email_invii WHERE ID=?');$q->execute([$argv[1]]);echo $q->fetchColumn()?'yes':'no';";
  await page.goto(origin+'/email_conferma.php?t='+job.token);
  check(execFileSync('php',['-r',stateCode,job.id],{encoding:'utf8'})==='no','GET registra conferma');
  await page.getByRole('button',{name:'Confermo la lettura'}).click();
  check((await page.locator('body').textContent()).includes('Conferma registrata'),'POST conferma fallisce');
  await page.goto(origin+'/logout.php');await page.getByRole('button',{name:'Conferma uscita'}).click();
  await page.locator('input[name=nome]').fill('user');await page.locator('input[name=password]').fill('1234567.');
  await Promise.all([page.waitForURL('**/pannello.php'),page.getByRole('button',{name:'Accedi',exact:true}).click()]);
  await page.goto(origin+'/gestione_email.php');
  check(await page.getByRole('button',{name:'Salva bozza e destinatari'}).count()===0,'User lettura può salvare');
  const forbidden=await page.request.post(origin+'/gestione_email.php',{form:{action:'send',id:'1'}});
  check(forbidden.status()===403,'Invio user lettura ammesso');
  const config=await page.request.get(origin+'/gestione_email.php?tab=config');check(config.status()===403,'Configurazione user ammessa');
  console.log('OK: pannello email, selezione, editor, bozza, anteprima, configurazione, mobile, conferma solo POST e permessi. Nessun invio reale.');
 }finally{if(browser)await browser.close();server.kill();}
})().catch(e=>{console.error(e);process.exit(1)});
