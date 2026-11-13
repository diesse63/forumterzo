'use strict';
(() => {
 const role=document.getElementById('user-role');
 const permissions=document.getElementById('user-permissions');
 if(!role||!permissions)return;
 function sync(reset){
  if(reset)permissions.querySelectorAll('select').forEach(select=>{select.value='nessuno';});
  const isUser=role.value==='user';
  permissions.hidden=!isUser;
  permissions.disabled=!isUser;
 }
 role.addEventListener('change',()=>sync(true));
 role.form?.addEventListener('reset',()=>setTimeout(()=>sync(false),0));
 sync(false);
})();
