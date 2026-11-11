(() => {
 'use strict';
 const dialog=document.getElementById('customerConfirmDialog');
 if(dialog){
  let pending=null;
  const modal=bootstrap.Modal.getOrCreateInstance(dialog),accept=dialog.querySelector('[data-confirm-accept]');
  window.CustomerConfirm=({title,message,label})=>new Promise(resolve=>{
   if(pending){resolve(false);return;}
   pending={resolve,trigger:document.activeElement};
   dialog.querySelector('#customerConfirmTitle').textContent=title;
   dialog.querySelector('#customerConfirmText').textContent=message;
   accept.textContent=label;modal.show();
  });
  accept.addEventListener('click',()=>{if(pending){const {resolve}=pending;pending.accepted=true;resolve(true);modal.hide();}});
  dialog.addEventListener('hidden.bs.modal',()=>{if(pending){if(!pending.accepted)pending.resolve(false);pending.trigger?.focus();pending=null;}});
 }
 document.querySelectorAll('[data-customer-logout]').forEach(form=>form.addEventListener('submit',()=>{
  try{Object.keys(sessionStorage).filter(key=>key.startsWith('smp-resale-draft:')).forEach(key=>sessionStorage.removeItem(key));}catch{}
 }));
})();
