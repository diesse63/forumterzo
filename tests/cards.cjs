const {chromium}=require('playwright'),{spawn}=require('child_process');
(async()=>{
 const server=spawn('php',['-S','127.0.0.1:8104','-t','.'],{stdio:'ignore'});let browser;
 const base='http://127.0.0.1:8104',tag=process.env.SCHEDA_CIFRATA==='1'?'CIFRATA':'NORMALE',mail='cardmodal.'+tag.toLowerCase()+'@example.invalid';
 const check=(v,m)=>{if(!v)throw Error(m)};
 try{
  for(let n=0;n<50;n++){try{if((await fetch(base+'/login.php')).ok)break;}catch{}await new Promise(r=>setTimeout(r,100));}
  browser=await chromium.launch({channel:'chrome',headless:true,args:['--no-sandbox']});const page=await browser.newPage({viewport:{width:1360,height:1000}});
  await page.goto(base+'/login.php');await page.locator('input[name=nome]').fill('admin');await page.locator('input[name=password]').fill('1234567.');
  await Promise.all([page.waitForURL('**/pannello.php'),page.getByRole('button',{name:'Accedi',exact:true}).click()]);
  await page.goto(base+'/anagrafica.php?q='+encodeURIComponent('SCHEDA BROWSER '+tag));
  check(await page.locator('.enti-card').count()===1&&await page.locator('.enti-table').count()===0,'Elenco non interamente a card');
  check(await page.locator('.enti-card-section').count()===4&&await page.locator('.enti-card-runts').count()===1,'Sezioni card mancanti');
  check((await page.locator('.enti-card').textContent()).includes('persona.browser.'+tag.toLowerCase()+'@example.invalid'),'Recapiti persona mancanti nella card');
  const frame=page.frameLocator('#enti-manage-frame');
  const open=async mode=>{await page.locator(`.enti-card a[data-card-modal="${mode}"]`).click();await frame.locator('#scheda-embed-mode').waitFor({state:'attached'});await frame.locator('#scheda-feedback').waitFor({state:'attached'});await page.waitForFunction(()=>document.querySelector('#enti-manage-frame').contentDocument?.readyState==='complete');};
  const close=async()=>{await page.waitForFunction(()=>document.querySelector('#enti-manage-dialog').dataset.busy==='false');await page.getByRole('button',{name:'Chiudi gestione'}).click();await page.locator('#enti-manage-dialog').waitFor({state:'hidden'});};
  const save=async form=>{await form.locator('button').last().click();await frame.getByRole('status').filter({hasText:'Operazione salvata correttamente.'}).waitFor();};
  await open('Mail');check(await frame.locator('#sezione-mail').isVisible()&&!await frame.locator('#sezione-telefono').isVisible(),'Modale email non focalizzata');
  await frame.locator('form[data-new-contact=Mail] input[name=value]').fill(mail);await save(frame.locator('form[data-new-contact=Mail]'));await close();
  await page.locator('.enti-card [data-card-section=Mail]').getByText(mail,{exact:true}).waitFor();
  await open('Telefono');await frame.locator('form[data-new-contact=Telefono] input[name=value]').fill('055 333444');await save(frame.locator('form[data-new-contact=Telefono]'));await close();
  await page.locator('.enti-card [data-card-section=Telefono]').getByText('055 333444',{exact:true}).waitFor();
  for(const mode of ['LR','AS']){
   await open(mode);const form=frame.locator(`form[data-new-person=${mode}]`);await form.locator('input[name=name]').fill('MODALE '+mode+' '+tag);await save(form);await close();
   await page.locator(`.enti-card [data-card-section=${mode}]`).getByText('MODALE '+mode+' '+tag,{exact:true}).waitFor();
  }
  await open('runts');check(await frame.locator('.scheda-runts').isVisible()&&!await frame.locator('#sezione-mail').isVisible(),'RUNTS non separato');await close();
  await open('Mail');await frame.locator('form[data-new-contact=Mail] input[name=value]').fill('non-salvata@example.invalid');
  await page.waitForFunction(()=>document.querySelector('#enti-manage-dialog').dataset.dirty==='true');
  page.once('dialog',d=>d.accept());await close();
  await open('Mail');check(await frame.locator('form[data-new-contact=Mail] input[name=value]').inputValue()==='','Bozza scartata non azzerata');await close();
  await page.setViewportSize({width:375,height:900});check(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),'Overflow card mobile');
  await open('LR');check(!await frame.locator('body').evaluate(()=>document.documentElement.scrollWidth>innerWidth),'Overflow modale mobile');await close();
  await page.goto(base+'/logout.php');await page.getByRole('button',{name:'Conferma uscita'}).click();await page.locator('input[name=nome]').fill('user');await page.locator('input[name=password]').fill('1234567.');
  await Promise.all([page.waitForURL('**/pannello.php'),page.getByRole('button',{name:'Accedi',exact:true}).click()]);
  await page.goto(base+'/anagrafica.php?q='+encodeURIComponent('SCHEDA BROWSER '+tag));await open('LR');
  check(await frame.locator('#persone-LR').isVisible()&&await frame.locator('form[data-new-person=LR]').count()===0,'Modale lettura permette scrittura');await close();
  console.log('OK: card '+tag+', tutte le informazioni, quattro modali, RUNTS separato, aggiornamento elenco, bozze, mobile e lettura.');
 }finally{if(browser)await browser.close();server.kill();}
})().catch(e=>{console.error(e);process.exit(1)});
