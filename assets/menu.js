'use strict';
document.querySelectorAll('[data-menubar]').forEach(bar => {
 const toggles = [...bar.querySelectorAll('[data-menu-toggle]')];
 function closeAll(except) {
  toggles.forEach(toggle => {
   if(toggle === except) return;
   toggle.setAttribute('aria-expanded','false');
   document.getElementById(toggle.getAttribute('aria-controls')).hidden = true;
  });
 }
 toggles.forEach(toggle => {
  const panel = document.getElementById(toggle.getAttribute('aria-controls'));
  toggle.addEventListener('click', () => {
   const open = toggle.getAttribute('aria-expanded') !== 'true';
   closeAll();
   toggle.setAttribute('aria-expanded',String(open));
   panel.hidden = !open;
  });
  toggle.addEventListener('keydown', event => {
   if(event.key === 'ArrowDown') {
    event.preventDefault(); closeAll(toggle);
    toggle.setAttribute('aria-expanded','true'); panel.hidden = false;
    panel.querySelector('a')?.focus();
   }
  });
 });
 bar.addEventListener('click', event => {
  if(event.target.closest('a')) closeAll();
 });
 bar.addEventListener('keydown', event => {
  if(event.key === 'Escape') {
   const open = toggles.find(t => t.getAttribute('aria-expanded') === 'true');
   closeAll(); open?.focus();
  }
 });
 document.addEventListener('click', event => { if(!bar.contains(event.target)) closeAll(); });
 document.addEventListener('focusin', event => { if(!bar.contains(event.target)) closeAll(); });
});
