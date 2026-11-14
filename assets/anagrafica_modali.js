'use strict';
(()=>{
 const dialog=document.querySelector('#enti-manage-dialog'),frame=document.querySelector('#enti-manage-frame');
 if(!dialog||!frame)return;
 const title=document.querySelector('#enti-manage-title'),status=document.querySelector('#enti-manage-status');
 const labels={Mail:'Email / PEC',Telefono:'Recapiti telefonici',LR:'Legali rappresentanti',AS:'Altri soggetti',runts:'RUNTS',dati:'Dati ente'};
 let dirty=false,busy=false,lastId='',lastMode='';
 const reset=()=>{dirty=false;busy=false;status.textContent='';dialog.dataset.busy='false';dialog.dataset.dirty='false';frame.src='about:blank';};
 function close(){
  if(busy){status.textContent='Attendere il completamento del salvataggio.';return;}
  if(dirty&&!window.confirm('Chiudere senza salvare le modifiche nella modale?'))return;
  // Il frame non lascia una pagina modificata tramite navigazione: viene rimosso dal documento.
  try{frame.contentWindow.dispatchEvent(new Event('anagrafica:discard'));}catch{}
  dialog.close();
 }
 document.addEventListener('click',event=>{
  const link=event.target.closest('a[data-card-modal]');if(!link)return;
  const url=new URL(link.href,location.origin);
  if(url.origin!==location.origin||url.pathname!=='/anagrafica_singola.php'||!Object.hasOwn(labels,link.dataset.cardModal))return;
  if(typeof dialog.showModal!=='function')return;
  event.preventDefault();reset();lastId=link.dataset.entityId;lastMode=link.dataset.cardModal;
  title.textContent=labels[lastMode]+' · '+link.dataset.entityName;frame.title=title.textContent;
  status.textContent='Caricamento…';frame.src=url.href;dialog.showModal();
 });
 frame.addEventListener('load',()=>{if(frame.src!=='about:blank'){status.textContent='';try{if(frame.contentWindow.location.pathname==='/login.php'){dialog.close();location.assign('/login.php');}}catch{status.textContent='Impossibile aprire la gestione.';}}});
 document.querySelector('#enti-manage-close').addEventListener('click',close);
 dialog.addEventListener('cancel',event=>{event.preventDefault();close();});
 dialog.addEventListener('click',event=>{if(event.target===dialog){const r=dialog.getBoundingClientRect();if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom)close();}});
 dialog.addEventListener('close',()=>{
  reset();const link=[...document.querySelectorAll('a[data-card-modal]')].find(a=>a.dataset.entityId===lastId&&a.dataset.cardModal===lastMode);
  (link||document.querySelector('.enti-filters input[name=q]'))?.focus();
 });
 window.addEventListener('message',event=>{
  if(event.origin!==location.origin||event.source!==frame.contentWindow||event.data?.type!=='anagrafica-state')return;
  dirty=Boolean(event.data.dirty);busy=Boolean(event.data.busy);dialog.dataset.busy=String(busy);dialog.dataset.dirty=String(dirty);
  status.textContent=busy?'Salvataggio in corso…':dirty?'Modifiche non salvate.':'';
  if(event.data.saved){window.dispatchEvent(new Event('anagrafica:changed'));status.textContent='Salvato: card aggiornata.';}
  if(event.data.deleted){dirty=false;busy=false;dialog.close();}
 });
})();
