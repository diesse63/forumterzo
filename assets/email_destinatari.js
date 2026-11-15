'use strict';
window.initEmailRecipients=(form,editable)=>{
 const bootstrap=JSON.parse(document.getElementById('email-book-data').textContent),frozen=form.dataset.frozen==='1';
 let book=bootstrap.contacts,selected=new Map(bootstrap.initial.map(c=>[c.Indirizzo.toLowerCase().trim(),c]));
 const container=document.getElementById('email-contacts'),chosen=document.getElementById('email-selected-list'),inputs=document.getElementById('email-selection-inputs'),count=document.getElementById('email-count'),status=document.getElementById('email-book-status');
 const controls=Object.fromEntries(['search','type','list','role','view'].map(k=>[k,document.getElementById('email-'+k)]));
 const element=(tag,text,cls)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(cls)e.className=cls;return e;};
 const signature=()=>JSON.stringify([...selected.values()].map(c=>[Number(c.ID),c.Indirizzo,c.Nome,c.Ente]).sort((a,b)=>a[0]-b[0]));
 const baseline=signature();
 function visible(){
  const q=controls.search.value.trim().toLocaleLowerCase(),type=controls.type.value,list=controls.list.value,role=controls.role.value;
  return book.filter(c=>(!q||[c.Nome,c.Ente,c.Indirizzo,...c.Riferimenti.map(p=>p.nome)].join(' ').toLocaleLowerCase().includes(q))&&(!type||String(c.ID_TIPOLOGIA)===type)&&(!list||c.Liste.some(n=>String(n)===list))&&(!role||c.Ruolo===role)).sort((a,b)=>controls.view.value==='enti'?(a.Ente.localeCompare(b.Ente,'it')||a.Nome.localeCompare(b.Nome,'it')):a.Nome.localeCompare(b.Nome,'it'));
 }
 function render(){
  container.replaceChildren();chosen.replaceChildren();inputs.replaceChildren();count.textContent='('+selected.size+' indirizzi)';
  const rows=visible();let lastEntity=null,group=container;
  for(const c of rows){
   if(controls.view.value==='enti'&&lastEntity!==c.EnteID){
    group=element('section',undefined,'email-entity-group');group.append(element('h3',c.Ente));group.append(element('p',c.Riferimenti.map(p=>p.nome+' · '+p.ruolo).join(' — ')||'Nessun riferimento LR/AS registrato.','email-muted'));container.append(group);lastEntity=c.EnteID;
   }
   const key=c.Indirizzo.toLowerCase().trim(),existing=selected.get(key),duplicate=existing&&Number(existing.ID)!==Number(c.ID),label=element('label',undefined,'email-contact');
   label.dataset.email=key;label.classList.toggle('is-selected',Boolean(existing));label.classList.toggle('is-duplicate-selected',Boolean(duplicate));
   const box=element('input');box.type='checkbox';box.value=c.ID;box.checked=Boolean(existing);box.disabled=!editable||Boolean(duplicate);box.setAttribute('aria-label',c.Nome+' · '+c.Ruolo+' · '+c.Indirizzo);
   box.addEventListener('change',()=>{if(box.checked)selected.set(key,c);else selected.delete(key);render();});
   const body=element('span');body.append(element('strong',c.Nome+' · '+c.Ruolo),element('small',c.Indirizzo+(Number(c.PEC)?' · PEC':'')),element('small','Ente: '+c.Ente));
   if(c.Ruolo==='ENTE')body.append(element('small',c.Riferimenti.map(p=>p.nome+' ('+p.ruolo+')').join(' · ')));
   if(existing)body.append(element('small',duplicate?'Indirizzo già selezionato con '+existing.Nome:'Selezionato','email-selected-badge'));
   label.append(box,body);group.append(label);
  }
  if(!rows.length)container.append(element('p','Nessun nominativo con email corrisponde ai filtri.'));
  const list=element('ul',undefined,'email-selected-items');
  for(const c of selected.values()){
   const li=element('li');li.append(element('strong',c.Nome+' · '+c.Ruolo),element('small',c.Indirizzo),element('small','Ente: '+c.Ente));
   if(editable){const remove=element('button','Rimuovi');remove.type='button';remove.setAttribute('aria-label','Rimuovi '+c.Indirizzo);remove.addEventListener('click',()=>{selected.delete(c.Indirizzo.toLowerCase().trim());render();});li.append(remove);const input=element('input');input.type='hidden';input.name='mail_ids[]';input.value=c.ID;inputs.append(input);}
   list.append(li);
  }
  chosen.append(selected.size?list:element('p','Nessun indirizzo selezionato.'));
 }
 Object.entries(controls).forEach(([k,c])=>c.addEventListener(k==='search'?'input':'change',render));
 document.getElementById('email-all')?.addEventListener('click',()=>{for(const c of visible()){const key=c.Indirizzo.toLowerCase().trim();if(!selected.has(key))selected.set(key,c);}render();});
 document.getElementById('email-none')?.addEventListener('click',()=>{selected.clear();render();});
 let busy=false,lastRefresh=Date.now();
 async function refresh(){
  if(busy)return;busy=true;document.getElementById('email-refresh').disabled=true;
  try{
   const response=await fetch('/email_rubrica.php',{credentials:'same-origin',cache:'no-store'});if(new URL(response.url).pathname==='/login.php'){location.assign('/login.php');return;}
   if(!response.ok)throw Error('Rubrica non disponibile');const data=await response.json();if(!Array.isArray(data.contacts))throw Error('Risposta rubrica non valida');book=data.contacts;
   let removed=0;
   if(!frozen){const byId=new Map(book.map(c=>[Number(c.ID),c])),fresh=new Map();for(const old of selected.values()){const current=byId.get(Number(old.ID));if(current){if(!fresh.has(current.Indirizzo))fresh.set(current.Indirizzo,current);}else removed++;}selected=fresh;}
   render();lastRefresh=Date.now();status.textContent='Rubrica aggiornata alle '+new Date().toLocaleTimeString('it-IT')+'.'+(removed?' '+removed+' selezioni rimosse perché non più presenti o valide.':'');
  }catch{status.textContent='Aggiornamento non riuscito. Premi Aggiorna rubrica prima di confermare i destinatari.';}
  finally{busy=false;document.getElementById('email-refresh').disabled=false;}
 }
 document.getElementById('email-refresh').addEventListener('click',refresh);
 setInterval(()=>{if(!document.hidden)refresh();},30000);window.addEventListener('focus',()=>{if(Date.now()-lastRefresh>5000)refresh();});
 render();return {values:()=>[...selected.values()],hasChanged:()=>signature()!==baseline};
};
