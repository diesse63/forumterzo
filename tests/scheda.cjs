const {chromium}=require('playwright'),{spawn}=require('child_process');
(async()=>{
 const server=spawn('php',['-S','127.0.0.1:8103','-t','.'],{stdio:'ignore'});let browser;
 const base='http://127.0.0.1:8103',tag=process.env.SCHEDA_CIFRATA==='1'?'CIFRATA':'NORMALE';
 const check=(v,m)=>{if(!v)throw Error(m)};
 try{
  for(let n=0;n<50;n++){try{if((await fetch(base+'/login.php')).ok)break;}catch{}await new Promise(r=>setTimeout(r,100));}
  browser=await chromium.launch({channel:'chrome',headless:true,args:['--no-sandbox']});const page=await browser.newPage({viewport:{width:1280,height:1000}});
  await page.goto(base+'/login.php');await page.locator('input[name=nome]').fill('admin');await page.locator('input[name=password]').fill('1234567.');
  await Promise.all([page.waitForURL('**/pannello.php'),page.getByRole('button',{name:'Accedi',exact:true}).click()]);
  await page.goto(base+'/dashboard_anagrafica.php');check(await page.locator('.anagrafiche-card').count()===3,'Dashboard incompleta');
  await page.getByRole('link',{name:/Elenco enti/}).click();
  await page.locator('input[name=q]').fill('SCHEDA BROWSER '+tag);await page.waitForFunction(()=>document.querySelector('.enti-count strong')?.textContent==='1');
  await page.locator('.enti-table .enti-open').click();await page.waitForURL('**/anagrafica_singola.php?*');const url=page.url();
  const save=async form=>{await form.locator('button').last().click();await page.waitForFunction(()=>document.querySelector('#scheda-feedback [role=alert]')||document.querySelector('#scheda-feedback [role=status]')?.textContent.includes('Operazione salvata correttamente.'));check(!await page.locator('#scheda-feedback [role=alert]').count(),await page.locator('#scheda-feedback').textContent());};
  await page.locator('#scheda-main-form input[name=SOGGETTO]').fill('SCHEDA BROWSER '+tag+' MODIFICATA');
  let contact=page.locator('form[data-new-contact=Mail]');await contact.locator('input[name=value]').fill('browser.'+tag.toLowerCase()+'@example.invalid');await save(contact);
  check(await page.locator('#scheda-main-form input[name=SOGGETTO]').inputValue()==='SCHEDA BROWSER '+tag+' MODIFICATA','Bozza principale persa al salvataggio recapito');
  check((await page.locator('#sezione-mail').textContent()).includes('browser.'+tag.toLowerCase()+'@example.invalid'),'Mail non aggiunta');
  // Il vecchio toast viene rimosso prima dell'operazione successiva.
  await page.evaluate(()=>document.querySelector('#scheda-feedback').replaceChildren());await save(page.locator('#scheda-main-form'));
  let person=page.locator('form[data-new-person=LR]');await person.locator('input[name=name]').fill('PERSONA BROWSER '+tag);await person.locator('input[name=email]').fill('persona.browser.'+tag.toLowerCase()+'@example.invalid');
  await page.evaluate(()=>document.querySelector('#scheda-feedback').replaceChildren());await save(person);
  await page.locator('#persone-LR .scheda-list strong').filter({hasText:'PERSONA BROWSER '+tag}).waitFor();
  let phone=page.locator('form[data-new-contact=Telefono]');await phone.locator('select[name=owner]').selectOption({label:'PERSONA BROWSER '+tag+' (LR)'});await phone.locator('input[name=value]').fill('055 111222');
  await page.evaluate(()=>document.querySelector('#scheda-feedback').replaceChildren());await save(phone);
  check((await page.locator('#sezione-telefono').textContent()).includes('055 111222'),'Telefono non aggiunto');
  const csrf=await page.locator('#scheda-main-form input[name=csrf]').inputValue(),revision=await page.locator('#scheda-main-form input[name=revision]').inputValue();
  const deniedCsrf=await page.request.post(url,{form:{action:'entity_save',csrf:'errato',revision,SOGGETTO:'VIETATO',ID_TIPOLOGIA:'100'}});check(deniedCsrf.status()===400,'CSRF invalido accettato');
  await page.reload();check(await page.locator('#scheda-main-form input[name=SOGGETTO]').inputValue()==='SCHEDA BROWSER '+tag+' MODIFICATA','Dati principali non persistiti');
  await page.setViewportSize({width:375,height:950});check(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),'Overflow scheda mobile');
  await page.goto(base+'/gestione_referenti.php?q='+encodeURIComponent('PERSONA BROWSER '+tag));check((await page.locator('tbody').textContent()).includes('PERSONA BROWSER '+tag),'Archivio referenti non collegato');
  await page.goto(base+'/logout.php');await page.getByRole('button',{name:'Conferma uscita'}).click();await page.locator('input[name=nome]').fill('user');await page.locator('input[name=password]').fill('1234567.');
  await Promise.all([page.waitForURL('**/pannello.php'),page.getByRole('button',{name:'Accedi',exact:true}).click()]);
  await page.goto(url);check(await page.locator('#scheda-save-main').count()===0,'Salvataggio principale visibile a lettura');
  const denied=await page.request.post(url,{form:{action:'entity_save',csrf,revision,SOGGETTO:'VIETATO',ID_TIPOLOGIA:'100'}});check(denied.status()===403,'POST user lettura ammesso');
  const deniedPerson=await page.request.post(url,{form:{action:'person_save',csrf,revision,name:'VIETATO',type:'LR',email:'a@example.invalid'}});check(deniedPerson.status()===403,'POST persona senza permessi ammesso');
  await page.goto(base+'/dashboard_anagrafica.php');check(await page.locator('.anagrafiche-card').count()===1,'Pannelli persone non autorizzati visibili');
  console.log('OK: scheda '+tag+', dashboard, salvataggi indipendenti, bozza conservata, persone, mail, telefoni, mobile, CSRF e permessi server.');
 }finally{if(browser)await browser.close();server.kill();}
})().catch(e=>{console.error(e);process.exit(1)});
