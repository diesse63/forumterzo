'use strict';
(() => {
 const secret=document.getElementById('email-secret');
 document.getElementById('email-generate-secret')?.addEventListener('click',()=>{
  const bytes=new Uint8Array(32);crypto.getRandomValues(bytes);secret.value=[...bytes].map(n=>n.toString(16).padStart(2,'0')).join('');secret.type='text';
 });
 document.getElementById('email-show-secret')?.addEventListener('click',()=>{secret.type=secret.type==='password'?'text':'password';});
 document.querySelectorAll('[data-confirm-action]').forEach(form=>form.addEventListener('submit',event=>{if(!window.confirm(form.dataset.confirmAction)){event.preventDefault();return;}if(form.hasAttribute('data-prompt-subject')){const subject=window.prompt('Impostare il nome della nuova lista:',form.querySelector('[name="duplicate_subject"]').value||'');if(subject===null){event.preventDefault();return;}form.querySelector('[name="duplicate_subject"]').value=subject;}if(form.hasAttribute('data-confirm-copy-recipients')&&!window.confirm('Copiare anche i nomi dei destinatari nella nuova bozza?'))form.querySelector('[name="copy_recipients"]').value='0';else if(form.hasAttribute('data-confirm-copy-recipients'))form.querySelector('[name="copy_recipients"]').value='1';}));
 const archiveDetail=document.querySelector('.email-archive-detail'),archiveActions=archiveDetail?.querySelector('.email-archive-actions'),prepareForm=archiveDetail?.querySelector('form[data-confirm-action]'),viewActions=archiveDetail?.querySelectorAll('.email-archive-actions');if(archiveActions&&prepareForm&&!archiveActions.contains(prepareForm))archiveActions.prepend(prepareForm);if(archiveActions&&viewActions?.length>1){const view=viewActions[1].querySelector('.email-view-message');if(view)archiveActions.append(view);viewActions[1].remove();}const archivePrepared=document.getElementById('email-archive-prepared');if(archivePrepared?.value==='0'){archiveActions?.querySelector('form input[name="action"][value="send"]')?.closest('form')?.remove();archiveActions?.querySelector(':scope>button')?.remove();}const archiveResults=document.getElementById('email-archive-results');if(archiveResults?.value!=='1')archiveActions?.querySelector('.email-results-button')?.remove();
 const autoScroll=document.getElementById('email-archive-auto-scroll');if(autoScroll){const go=()=>document.getElementById(autoScroll.dataset.scrollTo)?.scrollIntoView({block:'center',behavior:'auto'});if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',()=>requestAnimationFrame(go),{once:true});else requestAnimationFrame(go);setTimeout(go,250);}
 const form=document.getElementById('email-compose');if(!form)return;
 const editable=form.dataset.editable==='1';
 const editor=document.getElementById('email-editor'),plain=document.getElementById('email-text'),html=document.getElementById('email-html');
 editor.hidden=false;editor.contentEditable=String(editable);
 document.getElementById('email-text-label').hidden=true;plain.required=false;
 document.getElementById('email-toolbar').hidden=!editable;
 const rich=document.createElement('input');rich.type='hidden';rich.name='rich';rich.value='1';form.append(rich);
 form.addEventListener('submit',()=>{plain.value=editor.innerText;html.value=editor.innerHTML;});
 const selection=window.initEmailRecipients(form,editable);
 const initialSubject=document.getElementById('email-subject').value,initialHTML=editor.innerHTML;
 const hasUnsavedChanges=()=>selection.hasChanged()||editor.innerHTML!==initialHTML||document.getElementById('email-subject').value!==initialSubject;
 document.getElementById('email-open-saved')?.addEventListener('submit',event=>{if(editable&&hasUnsavedChanges()&&!window.confirm('Aprire la mail selezionata senza salvare le modifiche attuali?'))event.preventDefault();});
 document.querySelectorAll('[data-email-focus]').forEach(link=>link.addEventListener('click',event=>{event.preventDefault();const target=document.getElementById(link.dataset.emailFocus);target?.scrollIntoView({block:'center'});target?.focus({preventScroll:true});}));
 document.getElementById('email-prepare-form')?.addEventListener('submit',event=>{if(hasUnsavedChanges()){event.preventDefault();alert('Salva la bozza prima di preparare: testo o destinatari sono stati modificati.');}});
 let savedRange=null;
 function saveRange(){const s=window.getSelection();if(s.rangeCount&&editor.contains(s.anchorNode))savedRange=s.getRangeAt(0).cloneRange();}
 function restore(){editor.focus();if(savedRange){const s=window.getSelection();s.removeAllRanges();s.addRange(savedRange);}}
 ['keyup','mouseup','input'].forEach(event=>editor.addEventListener(event,saveRange));
 document.getElementById('email-toolbar').addEventListener('mousedown',event=>{if(event.target.closest('button'))event.preventDefault();});
 document.querySelectorAll('[data-command]').forEach(button=>button.addEventListener('click',()=>{if(!editable)return;restore();document.execCommand(button.dataset.command,false,null);saveRange();}));
 document.getElementById('email-name').addEventListener('click',()=>{if(!editable)return;restore();document.execCommand('insertText',false,'{nome}');saveRange();});
 document.getElementById('email-link').addEventListener('click',()=>{
  if(!editable)return;const value=prompt('Indirizzo completo https://');if(!value||!/^https?:\/\/[^\s]+$/i.test(value))return;
  restore();if(window.getSelection().isCollapsed)document.execCommand('insertText',false,value);else document.execCommand('createLink',false,value);saveRange();
 });
 document.getElementById('email-color').addEventListener('change',event=>{if(!editable)return;restore();document.execCommand('foreColor',false,event.target.value);saveRange();});
 editor.addEventListener('paste',event=>{if(!editable)return;event.preventDefault();document.execCommand('insertText',false,event.clipboardData.getData('text/plain'));});
 editor.addEventListener('drop',event=>event.preventDefault());
 document.getElementById('email-preview').addEventListener('click',()=>{
  const first=selection.values()[0];
  const name=first?.Nome||'Destinatario di esempio';
  const clone=editor.cloneNode(true);
  clone.querySelectorAll('script,style,iframe,object,embed,svg,math,img,form,input,link,meta').forEach(e=>e.remove());
  clone.querySelectorAll('*').forEach(e=>[...e.attributes].forEach(a=>{if(!['href','style'].includes(a.name)||a.name==='href'&&!/^https?:\/\//i.test(a.value))e.removeAttribute(a.name);}));
  const walker=document.createTreeWalker(clone,NodeFilter.SHOW_TEXT);let node;while(node=walker.nextNode())node.textContent=node.textContent.replaceAll('{nome}',name);
  document.getElementById('email-preview-who').textContent=name+' · '+document.getElementById('email-subject').value;
  const receipt=document.querySelector('input[name="conferma_lettura"][type="checkbox"]')?.checked,receiptButton=receipt?'<p><span style="display:inline-block;padding:14px;background:#175cd3;color:white">Conferma la lettura</span></p>':'';
  document.getElementById('email-preview-frame').srcdoc='<html><body style="font:16px Arial;line-height:1.6;padding:18px">'+clone.innerHTML+receiptButton+'</body></html>';
  document.getElementById('email-preview-dialog').showModal();
 });
 document.getElementById('email-preview-close').addEventListener('click',()=>document.getElementById('email-preview-dialog').close());
})();


