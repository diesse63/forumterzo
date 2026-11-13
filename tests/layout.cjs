const {chromium}=require('playwright');
const http=require('http');
const fs=require('fs');
const path=require('path');
(async()=>{
 const base=path.resolve(__dirname,'..');
 const html=fs.readFileSync('/tmp/poimanager-layout.html');
 const server=http.createServer((req,res)=>{
  if(req.url==='/assets/layout.css'){res.setHeader('Content-Type','text/css');res.end(fs.readFileSync(path.join(base,'assets/layout.css')));}
  else if(req.url==='/assets/menu.js'){res.setHeader('Content-Type','application/javascript');res.end(fs.readFileSync(path.join(base,'assets/menu.js')));}
  else if(req.url==='/assets/utenti.js'){res.setHeader('Content-Type','application/javascript');res.end(fs.readFileSync(path.join(base,'assets/utenti.js')));}
  else {res.setHeader('Content-Type','text/html');res.end(html);}
 });
 await new Promise(r=>server.listen(0,'127.0.0.1',r));
 const browser=await chromium.launch({channel:'chrome',headless:true,args:['--no-sandbox']});
 try{
  const page=await browser.newPage({viewport:{width:1280,height:900}});
  await page.goto('http://127.0.0.1:'+server.address().port+'/utenti.php');
  const styles=await page.evaluate(()=>Object.fromEntries(['.app-name','.app-context','.menu-toggle','.page-title','.page-description'].map(s=>{const c=getComputedStyle(document.querySelector(s));return [s,{size:c.fontSize,weight:c.fontWeight,style:c.fontStyle}]})));
  function check(value,label){if(!value)throw Error(label);}
  check(styles['.app-name'].size==='30px'&&styles['.app-name'].weight==='700','Gestione 30px bold');
  check(styles['.app-context'].size==='15px'&&styles['.app-context'].style==='italic','Contesto 15px corsivo');
  check(styles['.menu-toggle'].size==='15px'&&styles['.menu-toggle'].style==='italic','Menu 15px corsivo');
  check(styles['.page-title'].size==='25px'&&styles['.page-title'].weight==='700','Titolo 25px bold');
  check(styles['.page-description'].size==='15px'&&styles['.page-description'].style==='italic','Descrizione 15px corsivo');
  check(await page.locator('.app-context').textContent()==='Servizi › Utenti','Percorso selezionato');
  const services=page.getByRole('button',{name:'Servizi'});
  const panel=page.locator('#'+await services.getAttribute('aria-controls'));
  await services.click();check(await panel.isVisible(),'Apertura dropdown');
  await page.locator('.page-title').click();check(!await panel.isVisible(),'Chiusura esterna');
  await services.click();
  const users=panel.getByRole('link',{name:'Utenti',exact:true});
  await users.evaluate(a=>a.addEventListener('click',e=>e.preventDefault()));
  await users.click();check(!await panel.isVisible(),'Chiusura selezione');
  await services.click();await page.getByRole('button',{name:'Sessione'}).click();
  check(!await panel.isVisible(),'Un solo dropdown');
  await page.keyboard.press('Escape');
  check(await page.locator('.menu-toggle[aria-expanded=true]').count()===0,'Escape');
  await services.focus();await page.keyboard.press('ArrowDown');
  check(await users.evaluate(a=>a===document.activeElement),'Focus tastiera');
  const role=page.locator('#user-role'), permissions=page.locator('#user-permissions');
  check(await permissions.isVisible(),'Permessi user visibili');
  check(await page.locator('select[name="permessi[associazioni]"]').inputValue()==='lettura','Permesso caricato');
  check(await page.locator('select[name="permessi[nuovo]"]').inputValue()==='nessuno','Nuovo pannello negato');
  const pw=page.locator('input[name=password]');
  check((await pw.locator('..').textContent()).includes('(Minimo 7 caratteri e uno speciale)'),'Requisito password nel label');
  for(const value of ['admin','esternobase','esternoevoluto']){
   await role.selectOption(value);
   check(!await permissions.isVisible()&&await permissions.evaluate(field=>field.disabled),'Permessi nascosti e disabilitati');
   check(await page.locator('select[name="permessi[associazioni]"]').inputValue()==='nessuno','Permessi azzerati');
   check(await page.locator('form').evaluate(form=>![...new FormData(form).keys()].some(k=>k.startsWith('permessi['))),'Permessi esclusi dal POST');
   await role.selectOption('user');
   check(await permissions.isVisible()&&!await permissions.isDisabled(),'Ritorno user');
   check(await page.locator('select[name="permessi[associazioni]"]').inputValue()==='nessuno','Permessi non ripristinati');
   await page.locator('select[name="permessi[associazioni]"]').selectOption('scrittura');
  }
  await page.setViewportSize({width:375,height:900});
  check(!await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth),'Overflow mobile');
  console.log('OK: layout PHP comune, font, percorso Servizi > Utenti, menu click esterno/selezione, tastiera e mobile.');
 }finally{await browser.close();server.close();}
})().catch(e=>{console.error(e);process.exit(1)});
