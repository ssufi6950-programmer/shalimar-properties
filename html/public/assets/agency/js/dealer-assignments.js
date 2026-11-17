(() => {
 'use strict';
 const root=document.getElementById('dealerAssignmentModal');if(!root)return;
 const modal=bootstrap.Modal.getOrCreateInstance(root), el=s=>root.querySelector(s);
 const member=el('#assignmentMember'),message=el('[data-dealer-message]'),save=el('[data-dealer-save]');
 let listing=null,checkpoint='',assignment=null,mode='assign',page=1,pages=1,busy=false,loading=false,serial=0;
 let csrf={name:root.dataset.csrfName,hash:root.dataset.csrfHash};
 const text=value=>{message.textContent=value;};
 function refreshCsrf(token){if(!token)return;csrf=token;document.querySelectorAll('input[type="hidden"]').forEach(input=>{if(input.name===csrf.name)input.value=csrf.hash;});}
 async function request(action,params=null){
  const url=root.dataset.base+'/'+listing+'/'+action;
  const options={credentials:'same-origin',headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}};
  let target=url;
  if(params){options.method='POST';options.body=new URLSearchParams({...params,[csrf.name]:csrf.hash});}
  else if(action==='dealers')target+='?'+new URLSearchParams({q:el('#assignmentSearch').value,page});
  const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),20000);options.signal=controller.signal;
  try {const response=await fetch(target,options);const raw=await response.text();let data;try{data=JSON.parse(raw);}catch{throw new Error('Server returned an incomplete response. Reload this page before retrying.');}refreshCsrf(data.csrf);if(!response.ok)throw new Error(data.message||'Request could not be completed.');return data;}finally{clearTimeout(timer);}
 }
 function buttons(){save.disabled=busy||loading||!checkpoint||(mode==='assign'&&!member.value);el('[data-confirm-save]').disabled=save.disabled;member.disabled=busy;root.querySelectorAll('[data-bs-dismiss]').forEach(b=>b.disabled=busy);}
 async function load(){loading=true;buttons();const generation=serial;try {const data=await request('dealers');if(generation!==serial)return;pages=data.pages;page=data.page;member.replaceChildren();data.rows.forEach(row=>{const opt=document.createElement('option');opt.value=row.membership_id;opt.textContent=row.name+' · '+(row.mobile||'Mobile not recorded')+' · '+row.source+(row.locked==='yes'?' · Locked':'')+(row.staff_overlap==='yes'?' · Staff overlap':'');member.append(opt);});member.value='';text(data.rows.length?'Choose a dealer.':'No current matching dealers found. Please contact administration.');el('[data-dealer-page]').textContent=page+' / '+pages;el('[data-dealer-prev]').disabled=page<=1;el('[data-dealer-next]').disabled=page>=pages;}catch(error){text(error.message);}finally{loading=false;buttons();}}
 async function open(id,remove){if(busy||loading)return;listing=id;mode=remove?'unassign':'assign';checkpoint='';assignment=null;page=1;serial++;member.replaceChildren();el('#assignmentSearch').value='';el('[data-confirmation]').hidden=true;el('[data-picker]').hidden=remove;save.textContent=remove?'Unassign Dealer':'Save Assignment';el('#dealerAssignmentTitle').textContent=remove?'Unassign Dealer':'Assign / Change Dealer';el('[data-assignment-current]').textContent='Loading current assignment…';text('');buttons();modal.show();loading=true;try {const current=await request('assignment');assignment=current.assignment;checkpoint=current.checkpoint;el('[data-assignment-current]').textContent=assignment?'Current: '+assignment.name+' · '+assignment.source:'No current effective dealer assignment.';}catch(error){text(error.message);}finally{loading=false;buttons();}if(checkpoint&&!remove)await load();}
 document.addEventListener('click',event=>{const assign=event.target.closest('[data-dealer-open]'),remove=event.target.closest('[data-dealer-remove]');if(assign)open(assign.dataset.dealerOpen,false);else if(remove)open(remove.dataset.dealerRemove,true);});
 member.addEventListener('change',()=>{el('[data-confirmation]').hidden=true;buttons();});
 el('[data-dealer-search]').onsubmit=event=>{event.preventDefault();if(!busy&&!loading){page=1;load();}};
 el('[data-dealer-prev]').onclick=()=>{if(!busy&&!loading&&page>1){page--;load();}};
 el('[data-dealer-next]').onclick=()=>{if(!busy&&!loading&&page<pages){page++;load();}};
 save.onclick=()=>{if(save.disabled)return;el('[data-confirmation-text]').textContent=mode==='unassign'?'Remove this dealer assignment? The assignment history will be preserved.':(assignment?'Replace the current assignment with ':'Assign this listing to ')+member.selectedOptions[0].textContent+'?';el('[data-confirmation]').hidden=false;};
 el('[data-confirm-cancel]').onclick=()=>{if(!busy)el('[data-confirmation]').hidden=true;};
 el('[data-confirm-save]').onclick=async()=>{if(busy||!checkpoint)return;busy=true;buttons();try {const params={checkpoint};if(mode==='assign')params.membership_id=member.value;const data=await request(mode,params);checkpoint=data.checkpoint;assignment=data.assignment;const row=document.querySelector('[data-agency-listing-id="'+listing+'"]');if(row){row.querySelector('[data-dealer-summary]').textContent=assignment?assignment.name+' · '+assignment.source:'No dealer assigned';row.querySelector('[data-dealer-open]').textContent=assignment?'Change Dealer':'Assign Dealer';row.querySelector('[data-dealer-remove]').hidden=!assignment;}el('[data-confirmation]').hidden=true;text(data.message);el('[data-assignment-current]').textContent=assignment?'Current: '+assignment.name+' · '+assignment.source:'No dealer assigned.';}catch(error){checkpoint='';text(error.message+' Close and reopen assignment details before trying again.');}finally{busy=false;buttons();}};
 root.addEventListener('hide.bs.modal',event=>{if(busy||loading)event.preventDefault();else serial++;});
})();
