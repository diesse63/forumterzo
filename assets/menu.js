'use strict';
document.querySelectorAll('[data-menubar]').forEach(bar => {
 const toggles = [...bar.querySelectorAll('[data-menu-toggle]')];
 function closeBranch(toggle) {
  const panel = document.getElementById(toggle.getAttribute('aria-controls'));
  [toggle,...panel.querySelectorAll('[data-menu-toggle]')].forEach(item => {
   item.setAttribute('aria-expanded','false');
   document.getElementById(item.getAttribute('aria-controls')).hidden = true;
  });
 }
 function closeAll() {
  toggles.forEach(toggle => {
   toggle.setAttribute('aria-expanded','false');
   document.getElementById(toggle.getAttribute('aria-controls')).hidden = true;
  });
 }
 function closeSiblings(toggle) {
  const list = toggle.closest('li').parentElement;
  list.querySelectorAll(':scope > li > [data-menu-toggle]').forEach(sibling => {
   if(sibling !== toggle) closeBranch(sibling);
  });
 }
 toggles.forEach(toggle => {
  const panel = document.getElementById(toggle.getAttribute('aria-controls'));
  toggle.addEventListener('click', () => {
   const open = toggle.getAttribute('aria-expanded') !== 'true';
   if(open) {
    closeSiblings(toggle);
    toggle.setAttribute('aria-expanded','true');
    panel.hidden = false;
   } else closeBranch(toggle);
  });
  toggle.addEventListener('keydown', event => {
   if(event.key === 'ArrowDown') {
    event.preventDefault(); closeSiblings(toggle);
    toggle.setAttribute('aria-expanded','true'); panel.hidden = false;
    panel.querySelector(':scope > li > .menu-link, :scope > li > .menu-toggle')?.focus();
   }
  });
 });
 bar.addEventListener('click', event => {
  if(event.target.closest('a')) closeAll();
 });
 bar.addEventListener('keydown', event => {
  if(event.key === 'Escape') {
   const open = toggles.filter(t => t.getAttribute('aria-expanded') === 'true');
   closeAll(); open[open.length - 1]?.focus();
  }
 });
 document.addEventListener('click', event => { if(!bar.contains(event.target)) closeAll(); });
 document.addEventListener('focusin', event => { if(!bar.contains(event.target)) closeAll(); });
});
