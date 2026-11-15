const {chromium}=require('playwright'),{spawn,execFileSync}=require('child_process');
(async()=>{
 const server=spawn('php',['-S','127.0.0.1:8105','-t','.'],{stdio:'ignore'});let browser;
 const base='http://127.0.0.1:8105',tag=process.env.FLUSSO_CIFRATO==='1'?'E':'N',check=(v,m)=>{if(!v)throw Error(m)};
 try{
  for(let i=0;i<50;i++){try{if((await fetch(base+'/login.php')).ok)break;}catch{}await new Promise(r=>setTimeout(r,100));}
  browser=await chromium.launch({channel:'chrome',headless:true,args:['--no-sandbox']});const page=await browser.newPage({viewport:{width:1350,height:1000}});
  await page.goto(base+'/login.php');await page.locator('input[name=nome]').fill('admin');await page.locator('input[name=password]').fill('1234567.');await Promise.all([page.waitForURL('**/pannello.php'),page.getByRole('button',{name:'Accedi',exact:true}).click()]);
  await page.goto(base+'/invio_email.php?tab=componi');
  const top=await page.locator('#email-message-card').boundingBox(),left=await page.locator('#email-address-card').boundingBox(),right=await page.locator('#email-selection-card').boundingBox();check(top.y<left.y&&Math.abs(left.y-right.y)<4&&right.x>left.x,'Layout messaggio/rubrica/selezionati');
  await page.locator('#email-subject').fill('SOLO MESSAGGIO BROWSER '+tag);await page.locator('#email-editor').fill('Testo senza destinatari');await Promise.all([page.waitForURL('**/invio_email.php?tab=registro&id=*&salvato=1'),page.getByRole('button',{name:'Salva bozza e destinatari'}).click()]);await page.goto(base+'/invio_email.php?tab=componi&id='+new URL(page.url()).searchParams.get('id'));check((await page.locator('#email-count').textContent()).includes('0 indirizzi'),'Bozza senza destinatari non salvata');
  await page.goto(base+'/invio_email.php?tab=componi');await page.locator('#email-search').fill('FLUSSO BROWSER '+tag);await page.locator('#email-role').selectOption('LR');
  await page.locator('.email-contact:visible input:enabled').first().check();await page.locator('#email-role').selectOption('');
  check((await page.locator('#email-count').textContent()).includes('1 indirizzi'),'Indirizzi duplicati selezionati');check(await page.locator('.email-contact.is-selected:visible').count()===2&&await page.locator('.email-contact.is-duplicate-selected input:disabled').count()>=1,'Evidenza duplicati mancante');
  await page.locator('#email-view').selectOption('enti');check((await page.locator('.email-entity-group:visible').textContent()).includes('COGNOME NOME LR '+tag),'Ente privo di riferimenti LR');
  await page.locator('#email-search').fill('COGNOME NOME LR '+tag);check(await page.locator('.email-contact:visible').count()===1,'Ricerca nominativo include altri soggetti dello stesso ente');
  check((await page.locator('.email-contact:visible strong').textContent())==='FLUSSO BROWSER '+tag&&(await page.locator('.email-contact:visible').textContent()).includes('COGNOME NOME LR '+tag+' · LR'),'Vista ente non mostra singolo riferimento e ruolo');
  await page.locator('#email-view').selectOption('persone');check((await page.locator('.email-contact:visible strong').textContent()).includes('COGNOME NOME LR '+tag+' · LR')&&(await page.locator('.email-contact:visible').textContent()).includes('Ente: FLUSSO BROWSER '+tag),'Vista nominativo senza ruolo/ente');
  check((await page.locator('.email-contact:visible .email-address-value').textContent()).includes('flusso.'+tag.toLowerCase()+'@example.invalid'),'Indirizzo assente nella riga');
  await page.locator('#email-search').fill('FLUSSO BROWSER '+tag);await page.locator('#email-type').selectOption('101');check(await page.locator('.email-contact:visible').count()===0,'Tipologia non filtra in tempo reale');await page.locator('#email-type').selectOption('100');check(await page.locator('.email-contact:visible').count()===2,'Tipologia non ripristina righe');
  await page.locator('#email-address-card').scrollIntoViewIfNeeded();const bookBox=await page.locator('#email-address-card').boundingBox(),selectionBox=await page.locator('#email-selection-card').boundingBox();check(Math.abs(bookBox.y-selectionBox.y)<4&&selectionBox.x>bookBox.x,'Riquadri disallineati durante lo scroll');
  check(await page.locator('script[src^="/assets/email_destinatari.js?v="]').count()===1,'Script senza versione cache');
  const chosen=await page.locator('#email-selection-inputs input').inputValue();await page.locator('#email-subject').fill('DESTINATARI BROWSER '+tag);await page.locator('#email-editor').fill('Contenuto iniziale');await Promise.all([page.waitForURL('**/invio_email.php?tab=registro&id=*&salvato=1'),page.getByRole('button',{name:'Salva bozza e destinatari'}).click()]);
  await page.goto(base+'/invio_email.php?tab=componi&id='+new URL(page.url()).searchParams.get('id'));check(await page.locator('#email-subject').inputValue()==='DESTINATARI BROWSER '+tag,'Bozza con contenuto e destinatari non salvata');
  await page.locator('#email-subject').fill('MAIL FLUSSO BROWSER '+tag);await page.locator('#email-editor').fill('Testo conservato durante aggiornamento');
  const code="require 'includes/database.php';require 'includes/dati.php';$p=poimanager_db();$m=dc_find($p,'Mail',['ID'=>(int)$argv[1]]);dc_update($p,'Mail',['ID'=>$m['ID']],['Indirizzo'=>$argv[2]]);dc_update($p,'Soggetti',['ID'=>$m['IDSoggetto']],['Nome'=>$argv[3]]);";
  const live='live.'+tag.toLowerCase()+'@example.invalid',updated='auto.'+tag.toLowerCase()+'@example.invalid',name='NUOVO COGNOME NOME '+tag;execFileSync('php',['-r',code,chosen,live,name]);
  await page.locator('#email-search').fill(name);await page.locator('#email-selected-list').getByText(name+' · LR',{exact:true}).waitFor();check((await page.locator('#email-selected-list').textContent()).includes(live)&&await page.locator('#email-subject').inputValue()==='MAIL FLUSSO BROWSER '+tag,'Aggiornamento perde testo o selezione');
  execFileSync('php',['-r',code,chosen,updated,name]);await page.locator('#email-selected-list').getByText(updated,{exact:true}).waitFor({timeout:12000});
  await Promise.all([page.waitForURL('**/invio_email.php?tab=registro&id=*&salvato=1'),page.getByRole('button',{name:'Salva bozza e destinatari'}).click()]);
  const source=new URL(page.url()).searchParams.get('id');
  await page.goto(base+'/invio_email.php?tab=registro&id='+source);
  check(await page.locator('.email-archive-detail input[name=action][value=send]').count()===0,'Invio disponibile prima della preparazione');
  check(await page.locator('.email-results-button').count()===0,'Esiti disponibili per una bozza');
  page.once('dialog',d=>d.accept());await Promise.all([page.waitForURL('**/invio_email.php?tab=registro&id=*'),page.getByRole('button',{name:'Prepara',exact:true}).click()]);
  check((await page.locator('.email-archive-detail').textContent()).includes('Elaborata'),'Stato elaborata assente');
  check(await page.getByRole('button',{name:'Invia',exact:true}).isEnabled(),'Invio campagna pronta non disponibile');
  check(await page.locator('.email-results-button').count()===0,'Esiti disponibili per campagna non inviata');
  await page.getByRole('link',{name:'Visualizza messaggio',exact:true}).click();
  check(await page.locator('#email-subject').inputValue()==='MAIL FLUSSO BROWSER '+tag&&!await page.locator('#email-subject').isDisabled(),'Campagna non inviata non consultabile/modificabile');
  await page.goto(base+'/invio_email.php?tab=registro&id='+source);
  const dialogs=async d=>d.type()==='prompt'?d.accept('COPIA BROWSER '+tag):d.accept();page.on('dialog',dialogs);
  await Promise.all([page.waitForURL('**/invio_email.php?tab=registro&id=*'),page.getByRole('button',{name:'Duplica',exact:true}).click()]);page.off('dialog',dialogs);
  const copy=new URL(page.url()).searchParams.get('id');check(copy!==source&&(await page.locator('.email-archive-detail').textContent()).includes('COPIA BROWSER '+tag),'Duplicazione incompleta');
  await page.getByRole('link',{name:'Visualizza messaggio',exact:true}).click();check((await page.locator('#email-selected-list').textContent()).includes(updated),'Destinatari non copiati');
  await page.goto(base+'/invio_email.php?tab=registro&id='+copy);page.once('dialog',d=>d.accept());
  await Promise.all([page.waitForURL('**/invio_email.php?tab=registro&id=0'),page.getByRole('button',{name:'Elimina',exact:true}).click()]);
  check(await page.locator('#lista-invio-'+copy).count()===0&&await page.locator('#lista-invio-'+source).count()===1,'Eliminazione su ID sbagliato');
  await page.setViewportSize({width:375,height:950});await page.goto(base+'/invio_email.php?tab=componi');check(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),'Overflow flusso mobile');const mobileLeft=await page.locator('#email-address-card').boundingBox(),mobileRight=await page.locator('#email-selection-card').boundingBox();check(Math.abs(mobileLeft.y-mobileRight.y)<4&&mobileRight.x>mobileLeft.x,'Riquadri non affiancati su mobile');
  await page.goto(base+'/logout.php');await page.getByRole('button',{name:'Conferma uscita'}).click();await page.locator('input[name=nome]').fill('user');await page.locator('input[name=password]').fill('1234567.');await Promise.all([page.waitForURL('**/pannello.php'),page.getByRole('button',{name:'Accedi',exact:true}).click()]);
  const denied=await page.request.post(base+'/invio_email.php?tab=componi',{form:{action:'delete',id:source,confirm:'1'}});check(denied.status()===403,'Eliminazione permessa in lettura');const deniedCopy=await page.request.post(base+'/invio_email.php?tab=componi',{form:{action:'duplicate',id:source}});check(deniedCopy.status()===403,'Duplicazione permessa in lettura');
  console.log('OK: flusso browser '+tag+', ordine libero, layout, deduplica, nomi/enti, rubrica viva, copie, eliminazione, mobile e permessi. Nessuna spedizione.');
 }finally{if(browser)await browser.close();server.kill();}
})().catch(e=>{console.error(e);process.exit(1)});



