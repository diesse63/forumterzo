'use strict';
(() => {
 document.addEventListener('click',event=>{
  const row=event.target.closest('[data-entity-url]');
  if(row&&!event.target.closest('a,button,input,select')&&!window.getSelection()?.toString())window.location.href=row.dataset.entityUrl;
 });
 const filters=document.querySelector('.enti-filters');
 let filterTimer,filterRequest;
 async function refreshList(){
  if(!filters)return;
  filterRequest?.abort();filterRequest=new AbortController();
  const url=new URL('/anagrafica.php',location.origin);
  new FormData(filters).forEach((v,k)=>url.searchParams.set(k,v));
  const activeRequest=filterRequest;
  try{
   const response=await fetch(url,{signal:activeRequest.signal});
   if(!response.ok)throw Error('Ricerca non disponibile');
   const parsed=new DOMParser().parseFromString(await response.text(),'text/html');
   const results=parsed.getElementById('enti-results');
   if(!results){window.location.href=url;return;}
   document.getElementById('enti-results')?.replaceWith(document.importNode(results,true));
   history.replaceState(null,'',url);
   const newLink=document.querySelector('.enti-primary');
   if(newLink){const newUrl=new URL(url);newUrl.searchParams.set('nuovo','1');newLink.href=newUrl;}
  }catch(error){if(error.name!=='AbortError'){window.location.href=url;}}
 }
 filters?.addEventListener('submit',event=>{event.preventDefault();clearTimeout(filterTimer);refreshList();});
 filters?.querySelector('input[name=q]')?.addEventListener('input',()=>{clearTimeout(filterTimer);filterTimer=setTimeout(refreshList,400);});
 filters?.querySelector('select[name=tipo]')?.addEventListener('change',()=>{clearTimeout(filterTimer);refreshList();});
 const dialog=document.getElementById('ente-dialog');
 if(!dialog)return;
 if(dialog.open&&typeof dialog.showModal==='function'){dialog.close();dialog.showModal();}
 dialog.addEventListener('click',event=>{if(event.target===dialog){const r=dialog.getBoundingClientRect();if(event.clientX<r.left||event.clientX>r.right||event.clientY<r.top||event.clientY>r.bottom)dialog.close();}});
 const type=document.getElementById('ente-type'),incarichi=document.getElementById('ente-incarichi');
 function syncType(reset){
  const ets=type.selectedOptions[0]?.dataset.ets==='1';
  incarichi.hidden=!ets;incarichi.disabled=!ets;
  if(reset||!ets)incarichi.querySelectorAll('input').forEach(input=>{input.checked=false;});
 }
 type.addEventListener('change',()=>syncType(true));syncType(false);
 const importPerson=document.getElementById('ente-import-person'),personType=document.getElementById('ente-person-type');
 function syncPerson(){personType.required=importPerson.checked;personType.disabled=!importPerson.checked;}
 importPerson.addEventListener('change',syncPerson);syncPerson();
 const query=document.getElementById('runts-query'),results=document.getElementById('runts-results'),status=document.getElementById('runts-status');
 let timer,request;
 function choose(item){
  document.getElementById('ente-name').value=item.Denominazione||'';
  document.getElementById('ente-cf').value=item.CodiceFiscale||'';
  document.getElementById('ente-runts-cf').value=item.CodiceFiscale||'';
  const ets=[...type.options].find(option=>option.dataset.ets==='1');
  if(ets)type.value=ets.value;syncType(true);
  const hasPerson=Boolean(item.Cognome&&item.Nome);
  document.getElementById('ente-runts-person').hidden=!hasPerson;
  document.getElementById('ente-person-info').textContent=hasPerson?'Persona proposta: '+item.Cognome+' '+item.Nome:'';
  importPerson.checked=false;personType.value='';syncPerson();
  results.replaceChildren();status.textContent='Dati precompilati. Verifica i campi prima di salvare.';
 }
 async function search(){
  request?.abort();results.replaceChildren();
  const term=query.value.trim();
  if(term.length<3){status.textContent='Inserisci almeno 3 caratteri.';return;}
  request=new AbortController();status.textContent='Ricerca in corso…';
  try{
   const response=await fetch('/runts_ricerca.php?q='+encodeURIComponent(term),{signal:request.signal});
   if(!response.ok)throw Error('Ricerca RUNTS non disponibile.');
   const list=await response.json();
   status.textContent=list.length?'Seleziona un risultato.':'Nessun risultato. Puoi compilare l’ente manualmente.';
   list.forEach(item=>{
    const button=document.createElement('button');button.type='button';button.className='runts-result';
    const title=document.createElement('strong');title.textContent=item.Denominazione||'';
    const subtitle=document.createElement('small');subtitle.textContent=(item.CodiceFiscale||'')+(item.Provincia?' · '+item.Provincia:'');
    const person=document.createElement('span');person.textContent=[item.Cognome,item.Nome].filter(Boolean).join(' ');
    button.append(title,subtitle,person);button.addEventListener('click',()=>choose(item));results.append(button);
   });
  }catch(error){if(error.name!=='AbortError')status.textContent='Ricerca RUNTS non disponibile. Puoi compilare l’ente manualmente.';}
 }
 query.addEventListener('input',()=>{clearTimeout(timer);request?.abort();timer=setTimeout(search,400);});
 document.getElementById('runts-search').addEventListener('submit',event=>{event.preventDefault();clearTimeout(timer);search();});
})();
