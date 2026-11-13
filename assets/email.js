'use strict';
(() => {
 const secret=document.getElementById('email-secret');
 document.getElementById('email-generate-secret')?.addEventListener('click',()=>{
  const bytes=new Uint8Array(32);crypto.getRandomValues(bytes);secret.value=[...bytes].map(n=>n.toString(16).padStart(2,'0')).join('');secret.type='text';
 });
 document.getElementById('email-show-secret')?.addEventListener('click',()=>{secret.type=secret.type==='password'?'text':'password';});
 const form=document.getElementById('email-compose');if(!form)return;
 const editable=form.dataset.editable==='1';
 const editor=document.getElementById('email-editor'),plain=document.getElementById('email-text'),html=document.getElementById('email-html');
 editor.hidden=false;editor.contentEditable=String(editable);
 document.getElementById('email-text-label').hidden=true;plain.required=false;
 document.getElementById('email-toolbar').hidden=!editable;
 const rich=document.createElement('input');rich.type='hidden';rich.name='rich';rich.value='1';form.append(rich);
 form.addEventListener('submit',()=>{plain.value=editor.innerText;html.value=editor.innerHTML;});
 const entries=[...document.querySelectorAll('.email-contact')],count=document.getElementById('email-count');
 function selectedCount(){count.textContent='('+entries.filter(e=>e.querySelector('input').checked).length+' selezionati)';}
 function filter(){
  const q=document.getElementById('email-search').value.toLocaleLowerCase().trim(),type=document.getElementById('email-type').value,list=document.getElementById('email-list').value;
  entries.forEach(entry=>{entry.hidden=!(entry.dataset.search.toLocaleLowerCase().includes(q)&&(!type||entry.dataset.type===type)&&(!list||entry.dataset.lists.split(',').includes(list)));});
 }
 ['email-search','email-type','email-list'].forEach(id=>document.getElementById(id).addEventListener(id==='email-search'?'input':'change',filter));
 entries.forEach(e=>e.querySelector('input').addEventListener('change',selectedCount));
 document.getElementById('email-all')?.addEventListener('click',()=>{entries.filter(e=>!e.hidden).forEach(e=>{const b=e.querySelector('input');if(!b.disabled)b.checked=true;});selectedCount();});
 document.getElementById('email-none')?.addEventListener('click',()=>{entries.forEach(e=>{const b=e.querySelector('input');if(!b.disabled)b.checked=false;});selectedCount();});
 selectedCount();
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
  const first=entries.find(e=>e.querySelector('input').checked);
  const name=first?.querySelector('strong')?.textContent||'Destinatario di esempio';
  const clone=editor.cloneNode(true);
  clone.querySelectorAll('script,style,iframe,object,embed,svg,math,img,form,input,link,meta').forEach(e=>e.remove());
  clone.querySelectorAll('*').forEach(e=>[...e.attributes].forEach(a=>{if(!['href','style'].includes(a.name)||a.name==='href'&&!/^https?:\/\//i.test(a.value))e.removeAttribute(a.name);}));
  const walker=document.createTreeWalker(clone,NodeFilter.SHOW_TEXT);let node;while(node=walker.nextNode())node.textContent=node.textContent.replaceAll('{nome}',name);
  document.getElementById('email-preview-who').textContent=name+' · '+document.getElementById('email-subject').value;
  document.getElementById('email-preview-frame').srcdoc='<html><body style="font:16px Arial;line-height:1.6;padding:18px">'+clone.innerHTML+'<p><span style="display:inline-block;padding:14px;background:#175cd3;color:white">Conferma la lettura</span></p></body></html>';
  document.getElementById('email-preview-dialog').showModal();
 });
 document.getElementById('email-preview-close').addEventListener('click',()=>document.getElementById('email-preview-dialog').close());
})();
