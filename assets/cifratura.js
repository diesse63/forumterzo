'use strict';
(() => {
 const form=document.getElementById('crypto-step'),start=document.getElementById('crypto-auto'),stop=document.getElementById('crypto-stop'),status=document.getElementById('crypto-status');
 if(!form||!start)return;
 let running=false,stopRequested=false;
 stop.addEventListener('click',()=>{stopRequested=true;status.textContent='Arresto al termine del lotto attuale…';});
 start.addEventListener('click',async()=>{
  if(running)return;running=true;stopRequested=false;start.disabled=true;stop.disabled=false;form.querySelector('button').disabled=true;
  try{
   while(!stopRequested){
    const data=new FormData(form);
    const r=await fetch('/cifratura_dati.php',{method:'POST',body:data,headers:{Accept:'application/json'}});
    const result=await r.json();
    if(!r.ok||!result.ok)throw Error(result.message||'Operazione non riuscita');
    status.textContent=result.message;
    if(result.done){location.href='/cifratura_dati.php';return;}
    await new Promise(resolve=>setTimeout(resolve,150));
   }
  }catch(error){status.textContent=error.message+' Puoi riprendere dalla stessa pagina.';}
  finally{running=false;start.disabled=false;stop.disabled=true;form.querySelector('button').disabled=false;}
 });
})();
