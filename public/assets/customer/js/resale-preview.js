(() => {
 'use strict';
 const form=document.querySelector('[data-resale-publish-form]');if(!form)return;
 form.addEventListener('submit',async event=>{
  event.preventDefault();if(!form.reportValidity())return;const button=form.querySelector('[type=submit]'),error=form.querySelector('[data-publish-error]');if(button.disabled)return;
  button.disabled=true;button.textContent='Verifying and submitting...';error.hidden=true;
  try{const result=await window.CustomerPortalRequest(form.dataset.endpoint,{challenge_id:form.dataset.challenge,confirmed:form.querySelector('[name=confirmed]').checked});location.href=result.redirect;}
  catch(e){error.textContent=e.message;error.hidden=false;button.disabled=false;button.textContent='Confirm & Submit';}
 });
})();
