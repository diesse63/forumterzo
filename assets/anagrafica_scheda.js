(()=>{
 const feedback=document.querySelector('#scheda-feedback');
 if(!feedback||!document.querySelector('#scheda-main-form'))return;
 let dirty=false,busy=false;
 const values=form=>JSON.stringify(['SOGGETTO','CODICEFISCALE','ID_TIPOLOGIA','TAVOLO','DIRETTIVO_DELEGAZIONE'].map(k=>[k,new FormData(form).get(k)]));
 let baseline=values(document.querySelector('#scheda-main-form'));
 const notify=(text,error=false)=>{feedback.replaceChildren();const p=document.createElement('aside');p.textContent=text;p.className=error?'error':'';p.setAttribute('role',error?'alert':'status');feedback.append(p);};
 const toggle=()=>{
  const form=document.querySelector('#scheda-main-form'),box=document.querySelector('#scheda-incarichi');
  if(!form||!box)return;
  const active=JSON.parse(form.dataset.ets||'[]').includes(Number(form.elements.ID_TIPOLOGIA.value));
  box.hidden=!active;if(!active)box.querySelectorAll('input').forEach(i=>{i.checked=false;});
 };
 document.addEventListener('input',e=>{if(e.target.closest('#scheda-main-form'))dirty=true;});
 document.addEventListener('change',e=>{if(e.target.closest('#scheda-main-form')){dirty=true;toggle();}});
 window.addEventListener('beforeunload',e=>{if(dirty||busy){e.preventDefault();e.returnValue='';}});
 document.addEventListener('submit',async e=>{
  const form=e.target;
  if(!(form instanceof HTMLFormElement)||!form.matches('.scheda-form'))return;
  e.preventDefault();if(busy)return;
  if(form.dataset.confirm&&!window.confirm(form.dataset.confirm))return;
  if(!form.reportValidity())return;
  const data=new FormData(form),main=form.id==='scheda-main-form';
  busy=true;const buttons=[...document.querySelectorAll('.scheda-form button')];buttons.forEach(b=>b.disabled=true);
  try{
   const response=await fetch(form.action,{method:'POST',body:data,credentials:'same-origin'});
   const html=await response.text(),doc=new DOMParser().parseFromString(html,'text/html');
   if(new URL(response.url).pathname==='/login.php'){dirty=false;busy=false;location.assign('/login.php');return;}
   if(data.get('action')==='entity_delete'&&response.ok&&new URL(response.url).pathname==='/anagrafica.php'){dirty=false;busy=false;location.assign(response.url);return;}
   const error=doc.querySelector('#scheda-feedback [role=alert]');
   if(!response.ok||error){notify(error?.textContent||'Operazione non completata. Ricaricare la scheda prima di riprovare.',true);return;}
   const live=doc.querySelector('.scheda-live'),fresh=doc.querySelector('#scheda-main-form');
   if(!live||!fresh||!doc.querySelector('[data-save-ok]'))throw Error('Risposta inattesa: verificare l’esito prima di riprovare.');
   document.querySelector('.scheda-live').replaceWith(live);
   const current=document.querySelector('#scheda-main-form');
   if(main||!dirty){current.replaceWith(fresh);baseline=values(fresh);dirty=false;toggle();}
   else if(values(fresh)===baseline){current.elements.revision.value=fresh.elements.revision.value;}
   else{notify('Operazione salvata. I dati principali sono cambiati: prima di salvarli, ricaricare e confrontare le modifiche.',true);return;}
   notify('Operazione salvata correttamente.');
  }catch(error){notify(error.message||'Connessione interrotta. Verificare l’esito ricaricando la pagina prima di ripetere.',true);}
  finally{busy=false;buttons.forEach(b=>b.disabled=false);}
 });
 toggle();
})();
