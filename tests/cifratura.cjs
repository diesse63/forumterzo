const {chromium}=require('playwright'),{spawn}=require('child_process'),fs=require('fs');
(async()=>{
 const server=spawn('php',['-S','127.0.0.1:8102','-t','.'],{stdio:'ignore'});let browser;
 function check(v,m){if(!v)throw Error(m);}
 try{
  for(let i=0;i<50;i++){try{if((await fetch('http://127.0.0.1:8102/login.php')).ok)break;}catch{}await new Promise(r=>setTimeout(r,100));}
  browser=await chromium.launch({channel:'chrome',headless:true,args:['--no-sandbox']});const page=await browser.newPage(),base='http://127.0.0.1:8102';
  await page.goto(base+'/login.php');await page.locator('input[name=nome]').fill('admin');await page.locator('input[name=password]').fill('1234567.');
  await Promise.all([page.waitForURL('**/pannello.php'),page.getByRole('button',{name:'Accedi',exact:true}).click()]);
  await page.goto(base+'/anagrafica.php?q=ricercabile');
  check(await page.locator('.enti-count strong').textContent()==='1','Ricerca cifrata browser');
  await page.locator('.enti-table .enti-open').click();await page.waitForURL('**/anagrafica_singola.php?*');
  check((await page.locator('body').textContent()).includes('ente@example.invalid'),'Recapito cifrato non leggibile');
  await page.goto(base+'/anagrafica.php?nuovo=1');
  await page.locator('#ente-name').fill('BROWSER CIFRATO');await page.locator('#ente-type').selectOption('100');
  await Promise.all([page.waitForURL('**/anagrafica_singola.php?*'),page.getByRole('button',{name:'Salva ente',exact:true}).click()]);
  check((await page.locator('body').textContent()).includes('BROWSER CIFRATO'),'Scrittura cifrata browser');
  await page.goto(base+'/gestione_email.php');
  await page.locator('.email-contact input:enabled').first().check();await page.locator('#email-subject').fill('BOZZA CIFRATA BROWSER');await page.locator('#email-editor').fill('Testo cifrato {nome}');
  await Promise.all([page.waitForURL('**/gestione_email.php?tab=componi&id=*&salvato=1'),page.getByRole('button',{name:'Salva bozza e destinatari'}).click()]);
  check(await page.locator('#email-subject').inputValue()==='BOZZA CIFRATA BROWSER','Rilettura bozza cifrata');
  const key='private/dati_security.php',hold=key+'.test-hold';
  fs.renameSync(key,hold);
  try{
   await page.goto(base+'/anagrafica.php');
   const text=await page.locator('body').textContent();
   check(text.includes('Chiave dei dati non disponibile')&&!text.includes('PERSONA RICERCABILE'),'Chiave mancante non blocca i dati');
  }finally{fs.renameSync(hold,key);}
  await page.goto(base+'/cifratura_dati.php');check((await page.locator('body').textContent()).includes('pronto'),'Stato migrazione non pronto');
  console.log('OK: browser su dati cifrati, ricerca, recapiti, inserimento, bozza email e fail-closed senza chiave.');
 }finally{if(browser)await browser.close();server.kill();}
})().catch(e=>{console.error(e);process.exit(1)});
