(() => {
 'use strict';
 document.addEventListener('click',async event=>{
  const button=event.target.closest('[data-listing-transition]');if(!button||button.disabled)return;
  const action=button.dataset.listingTransition;if(action!=='withdraw')return;
  const message='Withdraw this listing? It will be hidden publicly. Your property and listing history will be preserved.';
  const confirmed=window.CustomerConfirm?await window.CustomerConfirm({title:'Withdraw this listing?',message,label:'Withdraw listing'}):window.confirm(message);
  if(!confirmed)return;button.disabled=true;const error=document.querySelector('[data-lifecycle-error]');error.hidden=true;
  try{const result=await window.CustomerPortalRequest(window.CustomerPortal.routes.resaleBase+'/'+button.dataset.id+'/'+action,{confirmed:true,version:Number(button.dataset.version)});location.href=result.redirect;}catch(e){error.textContent=e.message;error.hidden=false;button.disabled=false;}
 });
})();
