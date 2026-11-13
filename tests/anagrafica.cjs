const {chromium}=require('playwright');
const {spawn}=require('child_process');
(async()=>{
 const server=spawn('php',['-S','127.0.0.1:8099','-t','.'],{stdio:'ignore'});
 let browser;
 try{
  for(let n=0;n<50;n++){try{if((await fetch('http://127.0.0.1:8099/login.php')).ok)break;}catch{}await new Promise(r=>setTimeout(r,100));}
  browser=await chromium.launch({channel:'chrome',headless:true,args:['--no-sandbox']});
  const page=await browser.newPage({viewport:{width:1280,height:900}});
  function check(ok,msg){if(!ok)throw Error(msg);}
  await page.goto('http://127.0.0.1:8099/login.php');
  await page.locator('input[name=nome]').fill('admin');await page.locator('input[name=password]').fill('1234567.');
  await Promise.all([page.waitForURL('**/pannello.php'),page.getByRole('button',{name:'Accedi',exact:true}).click()]);
  await page.getByRole('button',{name:'Anagrafiche'}).click();
  await page.getByRole('link',{name:'Enti',exact:true}).click();
  check((await page.locator('h1').textContent())==='Anagrafica enti','Intestazione anagrafica');
  await page.locator('input[name=q]').fill('ricercabile');
  await page.waitForFunction(()=>document.querySelector('.enti-count strong')?.textContent==='1');
  await page.locator('.enti-table .enti-open').click();
  await page.waitForURL('**/anagrafica_singola.php?*');
  check((await page.locator('body').textContent()).includes('ente@example.invalid'),'Scheda non mostra email');
  await page.goto('http://127.0.0.1:8099/anagrafica.php');
  await page.getByRole('link',{name:'＋ Nuovo ente',exact:true}).click();
  await page.locator('#ente-dialog').waitFor({state:'visible'});
  await page.locator('#ente-type').selectOption('100');
  await page.locator('#ente-incarichi input[name=TAVOLO]').check();
  await page.locator('#ente-type').selectOption('101');
  check(!await page.locator('#ente-incarichi').isVisible(),'Incarichi visibili non ETS');
  await page.locator('#ente-type').selectOption('100');
  check(!await page.locator('#ente-incarichi input[name=TAVOLO]').isChecked(),'Incarichi non azzerati');
  await page.locator('#runts-query').fill('ROSSI');
  await page.getByRole('button',{name:'Clone RUNTS ente',exact:false}).click();
  check(await page.locator('#ente-name').inputValue()==='Clone RUNTS ente','Precompilazione RUNTS');
  await page.locator('#ente-name').fill('CLONE BROWSER ENTE');
  await Promise.all([page.waitForURL('**/anagrafica_singola.php?*'),page.getByRole('button',{name:'Salva ente',exact:true}).click()]);
  check((await page.locator('body').textContent()).includes('Ente salvato correttamente'),'Salvataggio browser');
  await page.setViewportSize({width:375,height:900});await page.goto('http://127.0.0.1:8099/anagrafica.php?q=CLONE%20BROWSER');
  check(await page.locator('.enti-cards').isVisible()&&!await page.locator('.enti-table-wrap').isVisible(),'Schede mobile');
  check(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),'Overflow mobile');
  await page.goto('http://127.0.0.1:8099/logout.php');await page.getByRole('button',{name:'Conferma uscita'}).click();
  await page.locator('input[name=nome]').fill('user');await page.locator('input[name=password]').fill('1234567.');
  await Promise.all([page.waitForURL('**/pannello.php'),page.getByRole('button',{name:'Accedi',exact:true}).click()]);
  await page.goto('http://127.0.0.1:8099/anagrafica.php');
  check(await page.locator('.enti-primary').count()===0,'Nuovo ente visibile a lettura');
  const denied=await page.request.post('http://127.0.0.1:8099/anagrafica.php',{form:{SOGGETTO:'VIETATO',ID_TIPOLOGIA:'100'}});
  check(denied.status()===403,'POST user lettura ammesso');
  const csrfResponse=await page.request.get('http://127.0.0.1:8099/runts_ricerca.php?q=ROSSI');
  check(csrfResponse.status()===403,'RUNTS user lettura ammesso');
  console.log('OK: navigazione, filtro dinamico, scheda, modale, RUNTS, creazione, mobile e blocco user lettura.');
 }finally{if(browser)await browser.close();server.kill();}
})().catch(e=>{console.error(e);process.exit(1)});
