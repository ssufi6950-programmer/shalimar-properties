<section class="card p-3 mb-3" id="dealer-batches" data-url="<?= esc(site_url('admin/agencies/dealer-batches'),'attr') ?>" data-csrf-name="<?= esc(csrf_token(),'attr') ?>" data-csrf-hash="<?= esc(csrf_hash(),'attr') ?>">
<div class="d-flex gap-2 align-items-center flex-wrap"><button type="button" class="btn btn-primary" data-start>Sync Dealers</button><button type="button" class="btn btn-outline-secondary" data-pause disabled>Pause</button><button type="button" class="btn btn-outline-primary" data-resume disabled>Resume</button><button type="button" class="btn btn-outline-secondary" data-retry disabled>Retry failed / skipped</button></div>
<p class="small text-muted mt-2">Processes one mapped agency ERP link per request. Counts are ERP links, not unique agencies. Keep this browser open; Pause takes effect after the current request. Reload and Resume within two hours using this Admin session.</p>
<p data-progress role="status" aria-live="polite">Ready.</p><div data-sources></div><ul data-issues class="small"></ul>
</section>
<script>
(() => {
 const panel=document.getElementById('dealer-batches');
 const button=name=>panel.querySelector('[data-'+name+']');
 const progress=button('progress'), sources=button('sources'), issues=button('issues');
 let run=null, active=false, inFlight=false, pause=false, lastError='';
 let csrf={name:panel.dataset.csrfName,hash:panel.dataset.csrfHash};
 function controls(){button('start').disabled=inFlight||active||Boolean(run&&!run.done);button('pause').disabled=!active;button('resume').disabled=inFlight||active||!run||run.done;button('retry').disabled=inFlight||active||!run||!run.done||!run.issues.length;}
 function render(){if(!run)return;progress.textContent=(run.done?'Pass complete. ':pause?'Paused. ':'')+run.processed+'/'+run.total+' links processed; '+run.counts.success+' successful, '+run.counts.failed+' failed, '+run.counts.skipped+' busy/skipped.'+(run.current?' Next: '+run.current:'');sources.replaceChildren();Object.entries(run.sources).forEach(([code,s])=>{const p=document.createElement('p');p.className='small mb-1';p.textContent=code+': '+s.processed+'/'+s.total+' links; '+s.success+' successful, '+s.failed+' failed, '+s.skipped+' skipped; '+s.dealers+' dealers synchronized.';sources.append(p);});issues.replaceChildren();run.issues.forEach(i=>{const li=document.createElement('li');li.textContent=i.source+' / '+i.agency+' (link #'+i.link_id+'): '+i.message;issues.append(li);});controls();}
 async function request(action){
  if(inFlight)return false;inFlight=true;lastError='';controls();const abort=new AbortController(),timer=setTimeout(()=>abort.abort(),45000);
  try{const body=new URLSearchParams({[csrf.name]:csrf.hash,token:run?.token||''});const response=await fetch(panel.dataset.url+'/'+action,{method:'POST',headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'},body,signal:abort.signal,credentials:'same-origin'});const data=await response.json();if(data.csrf){csrf=data.csrf;document.querySelectorAll('input[type="hidden"]').forEach(el=>{if(el.name===csrf.name)el.value=csrf.hash;});}if(!response.ok)throw new Error(data.message||'Request stopped. Reload to recover the server checkpoint.');run=data.run;render();return true;
  }catch(error){active=false;pause=true;lastError=error.name==='AbortError'?'Request timed out. Reload this page to recover the server checkpoint before resuming.':error.message;progress.textContent=lastError;return false;}finally{clearTimeout(timer);inFlight=false;controls();}
 }
 async function loop(){active=true;pause=false;controls();while(active&&!pause&&!run.done){if(!await request('step'))break;}active=false;render();if(lastError)progress.textContent=lastError;}
 button('start').onclick=async()=>{if(await request('start'))loop();};
 button('pause').onclick=()=>{pause=true;button('pause').disabled=true;};
 button('resume').onclick=()=>loop();
 button('retry').onclick=async()=>{if(await request('retry'))loop();};
 // Server session owns the checkpoint; no credentials or dealer data in browser storage.
 request('status').then(ok=>{if(ok){pause=true;render();}});
})();
</script>
