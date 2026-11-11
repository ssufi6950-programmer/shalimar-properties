(() => {
'use strict';
const config=window.CustomerPortal||{}, $=(s,root=document)=>root.querySelector(s), $$=(s,root=document)=>[...root.querySelectorAll(s)];
try{sessionStorage.removeItem('shalimar-customer-preview-v1');}catch{}
const escape=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const friendly=value=>{let text=String(value??'');Object.entries(config.uiCopy||{}).forEach(([from,to])=>{text=text.split(from).join(to);});return text.replace(/\b(SGC|Zalmi)\s+ERP\b/gi,'$1').replace(/\bERP\b/gi,'property service');};
window.CustomerPortalText=friendly;
const compactViewport=window.matchMedia?.('(max-width: 767px)');
$$('[data-compact-filters]').forEach(panel=>{
 const adapt=()=>{panel.open=!(compactViewport?.matches??false);};adapt();panel.dataset.enhanced='true';compactViewport?.addEventListener?.('change',adapt);
 const fields=$$('input[type="search"],select',panel),count=$('[data-filter-count]',panel);
 const update=()=>{const selected=fields.filter(field=>field.value.trim()!==(field.dataset.filterDefault||'')).length;if(count){count.textContent=selected+' selected';count.hidden=selected===0;}};
 fields.forEach(field=>field.addEventListener(field.type==='search'?'input':'change',update));update();
});
const listingTable=$('#customerResaleTable');
if(listingTable){const adapt=()=>{const mobile=!!compactViewport?.matches;listingTable.setAttribute('role',mobile?'list':'table');$('thead',listingTable).setAttribute('aria-hidden',String(mobile));$('tbody',listingTable).setAttribute('role',mobile?'presentation':'rowgroup');$$('[data-listing-row]',listingTable).forEach(row=>{row.setAttribute('role',mobile?'listitem':'row');$$('th,td',row).forEach(cell=>cell.setAttribute('role',mobile?'presentation':cell.tagName==='TH'?'rowheader':'cell'));});};adapt();compactViewport?.addEventListener?.('change',adapt);}
function csrf(value){if(!value)return;config.csrf=value;$$('input[name="'+value.name+'"]').forEach(input=>input.value=value.hash);}
async function api(url,data,signal){
 // JSON responses are selected by Accept; the legacy AJAX header is unnecessary.
 const options={credentials:'same-origin',headers:{Accept:'application/json'},signal};
 if(data!==undefined){
  options.method='POST';options.headers['X-CSRF-TOKEN']=config.csrf.hash;
  // Authentication accepts standard form fields, including the session CSRF token.
  if([config.routes.request,config.routes.verifyOtp,config.routes.resend].includes(url)){
   options.headers['Content-Type']='application/x-www-form-urlencoded;charset=UTF-8';
   options.body=new URLSearchParams({...data,[config.csrf.name]:config.csrf.hash}).toString();
  }else{options.headers['Content-Type']='application/json';options.body=JSON.stringify(data);}
 }
 const response=await fetch(url,options);let body;try{body=await response.json();}catch{const error=new Error('This service is temporarily unavailable. Please try again.');error.status=response.status;throw error;}csrf(body.csrf);
 if(response.status===401){location.href=body.login||config.routes.login;throw new Error('Please sign in again.');}
 if(!response.ok){const error=new Error(response.status===403&&!body.code?'Your request expired or could not be completed. Refresh and try again.':(friendly(body.message)||'The request could not be completed.'));error.code=body.code;error.status=response.status;throw error;}
 return body;
}
function message(element,text){if(element){element.textContent=friendly(text);element.hidden=false;}}
window.CustomerPortalRequest=api;
window.CustomerPortalUpdateCsrf=csrf;
function busy(button,on,label){if(!button)return;if(on){button.dataset.originalText=button.textContent;button.textContent=label||'Please wait...';}else button.textContent=button.dataset.originalText||button.textContent;button.disabled=on;}
function retryDelay(milliseconds,signal){return new Promise((resolve,reject)=>{const abort=()=>{clearTimeout(timer);reject(new DOMException('The request was cancelled.','AbortError'));};const timer=setTimeout(()=>{signal.removeEventListener('abort',abort);resolve();},milliseconds);if(signal.aborted)abort();else signal.addEventListener('abort',abort,{once:true});});}
$('#identityForm')?.addEventListener('submit',async event=>{
 event.preventDefault();const form=event.currentTarget,error=$('#identityError'),button=$('[type=submit]',form),cnic=$('#cnic').value.trim(),mobile=$('#mobile').value.trim();error.hidden=true;
 if(!/^(?:\d{13}|\d{5}-\d{7}-\d)$/.test(cnic)){message(error,'Enter a valid 13-digit CNIC, with or without hyphens.');return;}
 const digits=mobile.replace(/[^0-9]/g,'');if(!/^(?:03\d{9}|923\d{9})$/.test(digits)||!/^[+0-9 ()-]+$/.test(mobile)){message(error,'Enter your registered mobile as 03XXXXXXXXX or +923XXXXXXXXX.');return;}
 busy(button,true,'Verifying your details...');try{const result=await api(config.routes.request,{cnic,mobile});location.href=result.redirect;}catch(e){message(error,e.message);}finally{busy(button,false);}
});
const otpForm=$('#otpForm');
if(otpForm){
 const inputs=$$('.otp-inputs input'),submit=$('[type=submit]',otpForm),resend=$('#resendOtp'),error=$('#otpError');let expires=Number(otpForm.dataset.expires),resendAfter=Number(otpForm.dataset.resendAfter),sending=false,terminal=false;
 // Countdown display uses the issuing server's clock. Client wall-clock skew must not hide a valid code.
 let serverAnchor=Number(otpForm.dataset.serverNow),monotonicAnchor=performance.now();
 if(!Number.isFinite(serverAnchor)||serverAnchor<=0)serverAnchor=Date.now()/1000;
 const serverNow=()=>serverAnchor+Math.max(0,performance.now()-monotonicAnchor)/1000;
 function syncClock(value){const stamp=Number(value);if(Number.isFinite(stamp)&&stamp>0){serverAnchor=stamp;monotonicAnchor=performance.now();}}

 inputs.forEach((input,index)=>{input.addEventListener('input',()=>{input.value=input.value.replace(/\D/g,'').slice(-1);if(input.value&&inputs[index+1])inputs[index+1].focus();});input.addEventListener('keydown',event=>{if(event.key==='Backspace'&&!input.value&&index)inputs[index-1].focus();if(event.key==='ArrowLeft'&&index)inputs[index-1].focus();if(event.key==='ArrowRight'&&index<5)inputs[index+1].focus();});input.addEventListener('paste',event=>{event.preventDefault();const value=event.clipboardData.getData('text').replace(/\D/g,'').slice(0,6);inputs.forEach((item,i)=>item.value=value[i]||'');inputs[Math.min(value.length,5)].focus();});});
 function tick(){const now=Math.floor(serverNow()),remaining=Math.max(0,resendAfter-now);resend.disabled=terminal||sending||remaining>0||now>=expires;$('#otpCountdown').textContent=now>=expires?'This code has expired. Start sign-in again.':remaining?'Resend in '+remaining+'s':'You can request a new code.';if(now>=expires||terminal){submit.disabled=true;$$('[data-local-test-otp],[data-local-test-otp-note]').forEach(preview=>{preview.hidden=true;preview.textContent='';});}}
 const timer=setInterval(tick,1000);tick();window.addEventListener('pagehide',()=>clearInterval(timer));
 otpForm.addEventListener('submit',async event=>{event.preventDefault();error.hidden=true;const otp=inputs.map(i=>i.value).join('');if(!/^\d{6}$/.test(otp)){message(error,'Enter the six-digit verification code.');return;}busy(submit,true,'Checking your code...');try{const result=await api(config.routes.verifyOtp,{otp});location.href=result.redirect;}catch(e){message(error,e.message);if(e.status===410||e.code==='no_properties'){terminal=true;submit.disabled=true;resend.disabled=true;}inputs[0].focus();}finally{busy(submit,false);tick();}});
 resend.addEventListener('click',async()=>{sending=true;tick();busy(resend,true,'Sending...');try{const result=await api(config.routes.resend,{});expires=result.challenge.expires_at;resendAfter=result.challenge.resend_after;syncClock(result.challenge.server_now);const preview=$('[data-local-test-otp]');if(preview){preview.textContent=result.challenge.local_test_otp?'Testing OTP: '+result.challenge.local_test_otp:'';preview.hidden=!result.challenge.local_test_otp;}const note=$('[data-local-test-otp-note]');if(note){note.textContent=result.challenge.local_test_otp_note||'';note.hidden=!note.textContent;}inputs.forEach(input=>input.value='');error.hidden=true;inputs[0].focus();}catch(e){message(error,e.message);}finally{sending=false;busy(resend,false);tick();}});
}
async function prepare(button){const error=$('[data-resale-error]',button.closest('li,tr,article,section'))||$('#portfolioActionError');if(error)error.hidden=true;button.setAttribute('aria-busy','true');busy(button,true,'Verifying ownership...');try{const result=await api(config.routes.prepare,{reference:button.dataset.resaleRef,listing_purpose:button.dataset.listingPurpose||'sale'});location.href=result.redirect+(button.hasAttribute('data-picker-select')?'&from=picker':'');}catch(e){message(error,e.message);}finally{busy(button,false);button.removeAttribute('aria-busy');}}
document.addEventListener('click',event=>{const button=event.target.closest('[data-resale-ref]');if(button){event.preventDefault();prepare(button);}});
$('[data-agency-selection-form]')?.addEventListener('submit',async event=>{event.preventDefault();const form=event.currentTarget,button=$('[type=submit]',form),result=$('[data-agency-result]',form);result.hidden=true;busy(button,true,'Checking agency...');try{const response=await api(form.dataset.endpoint,{reference:form.dataset.reference,agency_link_id:$('[name=agency_link_id]',form).value});message(result,response.message);}catch(error){message(result,error.message);}finally{busy(button,false);}});
const portfolio=$('[data-live-portfolio]');
if(portfolio){
 const selection=portfolio.hasAttribute('data-property-selection'),picker=$('[data-property-picker]'),requestedPurpose=selection?portfolio.dataset.purpose:new URLSearchParams(location.search).get('list_for'),listingPurpose=(selection||picker)&&['sale','rent'].includes(requestedPurpose)?requestedPurpose:null;
 if(listingPurpose&&picker){picker.hidden=false;$('[data-property-picker-title]').textContent='Choose a property for '+(listingPurpose==='rent'?'Rent':'Resale');$('[data-property-picker-help]').textContent='Choose a property below, then tap List for '+(listingPurpose==='rent'?'Rent':'Resale')+'.';}
 let properties=[],controller,generation=0,sourceStates=[],currentPage=1;
 const body=$('[data-live-property-rows]'),status=$('[data-source-status]');
 const pageSize=()=>Number($('#propertyPageSize')?.value||10);
 function purposeAction(p,purpose){
  const action=p.listing_actions?.[purpose]||{eligible:purpose==='sale'&&p.eligible,reason:p.reason};
  const title=purpose==='rent'?'Rent':'Sale',label=purpose==='rent'?'List for Rent':'List for Resale';
  if(action.listing_id)return '<a class="btn btn-outline btn-sm" data-listing-action="'+purpose+'" href="'+escape(config.routes.resaleBase+'/'+action.listing_id)+'">View '+title+' Status</a>';
  if(action.eligible)return '<button class="btn '+(purpose==='rent'?'btn-outline app-rent-action':'btn-soft')+(listingPurpose===purpose?' app-purpose-selected':'')+' btn-sm" type="button" data-listing-action="'+purpose+'" data-listing-purpose="'+purpose+'" data-resale-ref="'+escape(p.id)+'">'+label+'</button>';
  return '<button class="btn btn-outline btn-sm" type="button" disabled title="'+escape(friendly(action.reason))+'">'+label+'</button><small class="text-muted app-property-action-reason">'+escape(title+': '+friendly(action.reason||'Listing is not available for this property.'))+'</small>';
 }
 function actionReasons(p){
  const reasons=['sale','rent'].map(purpose=>({purpose,...p.listing_actions?.[purpose]})).filter(a=>!a.eligible&&!a.listing_id&&a.reason);
  if(!reasons.length)return '';
  const items=[...new Set(reasons.map(a=>friendly(a.reason)))];
  return '<details class="app-action-reasons"><summary>Listing information</summary>'+items.map(reason=>'<p>'+escape(reason)+'</p>').join('')+'</details>';
 }
 function canList(p){return p.listing_actions?Object.values(p.listing_actions).some(a=>a.eligible):p.eligible;}
 function selectionRow(p){
  const action=p.listing_actions?.[listingPurpose]||{eligible:listingPurpose==='sale'&&p.eligible,reason:p.reason},type=typeof p.type==='string'&&p.type.trim()?p.type:'Property';
  const button=action.listing_id?'<a class="btn btn-outline btn-sm" data-picker-existing href="'+escape(config.routes.resaleBase+'/'+action.listing_id)+'">View Listing</a>':action.eligible?'<button type="button" class="btn btn-primary btn-sm" data-picker-select data-listing-purpose="'+listingPurpose+'" data-resale-ref="'+escape(p.id)+'">Select</button>':'<button type="button" class="btn btn-outline btn-sm" disabled>Select</button>';
  return '<li class="app-property-choice" data-property-choice><div><h3>'+escape(friendly(p.plot))+'</h3><p><span data-property-type>'+escape(friendly(type))+'</span>'+(p.size?' &middot; <span data-property-size>'+escape(p.size)+'</span>':'')+'</p><p class="app-choice-project">'+escape(friendly(p.project))+'</p></div><div class="app-choice-action">'+button+'</div>'+(!action.eligible&&!action.listing_id?'<p class="app-choice-reason">'+escape(friendly(action.reason||'Listing is not available for this property.'))+'</p>':'')+'<p class="form-error" data-resale-error hidden role="alert"></p></li>';
 }
 function row(p,index){
  const details=escape(config.routes.property+'/'+p.id);
  const state=p.booking_processing_status||'Current booking',type=typeof p.type==='string'&&p.type.trim()&&!['not provided','not recorded','unavailable','unknown'].includes(p.type.trim().toLowerCase())?p.type:'Property';
  return `<tr role="row" data-property-row ${listingPurpose?'data-property-picker-row':''}>
   <td role="cell" data-label="#" class="app-record-index">${index}</td>
   <th role="rowheader" scope="row" data-label="Property / Plot" class="app-record-title"><a class="app-table-title" href="${details}">${escape(friendly(p.plot))}</a><small>${escape([p.floor,p.booking?'Booking '+p.booking:''].filter(Boolean).join(' / '))}</small></th>
   <td role="cell" data-label="Project">${escape(friendly(p.project))}<small class="d-block text-muted">${escape(friendly(p.subhead))}</small></td>
   <td role="cell" data-label="Type / Size"><span data-property-type>${escape(friendly(type))}</span><small class="d-block text-muted ${p.size?'':'app-property-size-missing'}" data-property-size>${escape(p.size||'Not provided')}</small></td>
   <td role="cell" data-label="Construction Status"><span class="app-status ${p.construction_status==='Constructed'?'app-status-green':p.construction_status==='Under Construction'?'app-status-amber':'app-status-neutral'}">${escape(p.construction_status||'Not recorded')}</span></td>
   <td role="cell" data-label="Portfolio"><span class="app-source-tag">${escape(friendly(p.erp))}</span></td>
   <td role="cell" data-label="Current Status" class="app-record-status"><span class="app-status ${canList(p)?'app-status-green':'app-status-neutral'}">${escape(state)}</span><small class="d-block text-muted mt-2">${escape(canList(p)?'Listing options available':friendly(p.reason))}</small></td>
   <td role="cell" data-label="Actions" class="app-record-actions"><div class="app-record-action-content"><div class="app-row-actions"><a class="btn btn-outline btn-sm app-property-detail-action" href="${details}" aria-label="View details for ${escape(friendly(p.plot))}">View Details <i class="bi bi-arrow-up-right"></i></a>${purposeAction(p,'sale')}${purposeAction(p,'rent')}</div>${actionReasons(p)}</div><p class="form-error mt-2" data-resale-error hidden role="alert"></p></td>
  </tr>`;
 }
 function options(selector,values){const select=$(selector);if(!select)return;const current=select.value;while(select.options.length>1)select.remove(1);[...new Set(values)].filter(Boolean).sort().forEach(value=>{const option=document.createElement('option');option.value=value;option.textContent=friendly(value);select.append(option);});select.value=current;}
 function responsiveProperties(){
  const table=$('#customerPropertiesTable');if(!table)return;const mobile=!!compactViewport?.matches;
  table.setAttribute('role',mobile?'list':'table');table.setAttribute('aria-labelledby','propertyTableTitle');
  $$('#propertyPageSize option').forEach(option=>{option.textContent=option.value+(mobile?' / page':' per page');});
  const header=$('thead',table);if(mobile)header.setAttribute('aria-hidden','true');else header.removeAttribute('aria-hidden');
  body.setAttribute('role',mobile?'presentation':'rowgroup');
  $$('tr',body).forEach(row=>{row.setAttribute('role',mobile?'listitem':'row');$$('th,td',row).forEach(cell=>cell.setAttribute('role',mobile?'presentation':cell.tagName==='TH'?'rowheader':'cell'));});
 }
 compactViewport?.addEventListener?.('change',()=>{if(body)render();});
 function render(){
  const q=($('#propertySearch')?.value||'').toLowerCase(),project=$('#projectFilter')?.value||'',eligibility=$('#eligibilityFilter')?.value||'';
  const rows=properties.filter(p=>(!q||[p.project,p.head,p.subhead,p.plot,p.floor,p.booking].join(' ').toLowerCase().includes(q))&&(!project||p.project===project)&&(!eligibility||(eligibility==='eligible'?canList(p):!canList(p))));
  const size=pageSize(),pages=Math.max(1,Math.ceil(rows.length/size));currentPage=Math.max(1,Math.min(currentPage,pages));const offset=(currentPage-1)*size;
  const loading=sourceStates.some(s=>s.state==='loading'),incomplete=sourceStates.some(s=>s.state!=='done');
  if(body){
   const emptyMessage=loading?'Loading your properties...':incomplete?'Some property information could not be loaded. Refresh to try again.':'No properties are available for this search.';
   if(selection){body.innerHTML=rows.length?rows.slice(offset,offset+size).map(selectionRow).join(''):'<li class="app-table-state" role="status"><strong>'+escape(emptyMessage)+'</strong><a class="btn btn-outline btn-sm mt-3" href="#customerListProperty" role="button" data-bs-toggle="offcanvas">Back to listing options</a></li>';}
   else{
   body.innerHTML=rows.length?rows.slice(offset,offset+size).map((p,i)=>row(p,offset+i+1)).join(''):`<tr role="row" class="app-record-state"><td role="cell" colspan="8"><div class="app-table-state" role="status">${loading?'<span class="spinner-border spinner-border-sm"></span> Checking your properties...':'<i class="bi bi-inbox"></i><strong>No properties to show</strong><p>'+escape(incomplete?'Some property information could not be loaded. Refresh to try again.':'No currently owned properties match these filters.')+'</p>'}</div></td></tr>`;
   }
   $('[data-property-page-info]').textContent=(rows.length?offset+1:0)+'-'+Math.min(offset+size,rows.length)+' of '+rows.length+' properties'+(incomplete?' / partial results':'');
   const label=$('[data-property-page-label]');label.textContent=(compactViewport?.matches?'':'Page ')+currentPage+' / '+pages;label.setAttribute('aria-label','Page '+currentPage+' of '+pages);
   $('[data-property-prev]').disabled=currentPage<=1;$('[data-property-next]').disabled=currentPage>=pages;
  }
  const counts={owned:properties.length,sgc:properties.filter(p=>p.erp_code==='SGC').length,zalmi:properties.filter(p=>p.erp_code==='ZALMI').length,eligible:properties.filter(p=>canList(p)).length};
  Object.entries(counts).forEach(([key,value])=>{const states=key==='sgc'||key==='zalmi'?sourceStates.filter(s=>s.code===key.toUpperCase()):sourceStates;const partial=states.some(s=>s.state!=='done');const label=partial?(value?value+'+':'\u2014'):String(value);$$('[data-stat="'+key+'"]').forEach(node=>{node.textContent=label;node.title=partial?'Some properties are still loading or unavailable.':'All your current properties have loaded.';});});
  const availability=$('[data-picker-availability]');if(availability){const selectable=properties.some(p=>p.listing_actions?.[listingPurpose]?.eligible);availability.textContent='No properties are currently available for a new '+(listingPurpose==='rent'?'Rent':'Resale')+' listing. You can view existing listings or return to listing options.';availability.hidden=loading||selectable||!properties.length;}
  if($('#propertyResultCount'))$('#propertyResultCount').textContent=rows.length+(rows.length===1?' property':' properties')+(incomplete?' / results are incomplete':'');
  portfolio.dataset.portfolioState=loading?'loading':incomplete?'partial':'ready';
  if(status){
   status.innerHTML=(loading&&!properties.length?'<div class="alert alert-info" role="status">Loading your properties...</div>':'')+(sourceStates.some(s=>s.state==='error')?'<div class="alert alert-warning" role="status">Some property information could not be loaded. Refresh to try again.</div>':'')+((config.sourceWarnings||[]).length?'<div class="alert alert-warning">Some portfolios could not be verified at sign-in. Open My Profile to verify your account.</div>':'');
  }
  const dashboardStatus=$('[data-dashboard-load-status]');if(dashboardStatus){dashboardStatus.textContent='Some property information could not be loaded. Open My Properties to refresh.';dashboardStatus.hidden=!sourceStates.some(s=>s.state==='error');}
  if(body)responsiveProperties();
 }
 async function refresh(){
  if(controller)controller.abort();controller=new AbortController();const signal=controller.signal,mine=++generation;properties=[];currentPage=1;sourceStates=(config.sources||[]).map(s=>({id:s.id,name:s.name,code:s.code,state:'loading',message:'Loading current properties...'}));render();const seen=new Set();
  async function readPage(source,state,page){for(let attempt=0;attempt<3;attempt++){try{return await api(config.routes.propertiesApi+'/'+source.id+'?page='+page,undefined,signal);}catch(error){if(error.name==='AbortError'||signal.aborted||mine!==generation)throw error;const temporary=!error.status||[408,500,502,503,504].includes(error.status);if(!temporary||attempt===2)throw error;state.message='Connection interrupted. Retrying current properties...';render();await retryDelay(400*(attempt+1),signal);}}}
  // Reads retain pagination, composite deduplication and retries; mutation requests never retry.
  await Promise.all((config.sources||[]).map(async source=>{const state=sourceStates.find(s=>s.id===source.id);let count=0;try{for(let page=1;page<=100;page++){const data=await readPage(source,state,page);if(mine!==generation)return;for(const p of data.properties){const key=p.erp_source_id+':'+p.erp_property_id;if(!seen.has(key)){properties.push(p);seen.add(key);count++;}}options('#projectFilter',properties.map(p=>p.project));render();if(!data.has_more){state.state='done';state.count=count;state.message=count+' currently owned properties loaded.';break;}if(page===100)throw new Error('This source reached its page limit. Results are incomplete.');}}catch(e){if(e.name==='AbortError'||mine!==generation)return;state.state='error';state.message=e.message+' Use Refresh Properties to retry.';}if(mine===generation)render();}));
 }
 ['#propertySearch','#projectFilter','#eligibilityFilter','#propertyPageSize'].forEach(selector=>$(selector)?.addEventListener(selector==='#propertySearch'?'input':'change',()=>{currentPage=1;render();}));
 $('[data-property-prev]')?.addEventListener('click',()=>{currentPage--;render();});$('[data-property-next]')?.addEventListener('click',()=>{currentPage++;render();});
 $$('[data-refresh-properties]').forEach(button=>button.addEventListener('click',refresh));window.addEventListener('pageshow',event=>{if(event.persisted)refresh();});refresh();
}
})();
