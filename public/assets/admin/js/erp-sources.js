/* Actual authenticated CI4 AJAX requests. All ERP configuration is persisted in MySQL. */
(() => {
    'use strict';
    const config=window.ErpSourcesConfig;
    const $=window.jQuery;
    const can=action=>config.permissions?.['erp_sources.'+action]===true;
    const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const labels={not_configured:'Not Configured',not_checked:'Not Checked',connected:'Connected',failed:'Failed'};
    const authLabels={none:'None',bearer:'Bearer token',basic:'HTTP Basic',api_key:'API key'};
    let records=[];
    let busy=false;
    const form=document.getElementById('erpSourceForm');
    const formModal=bootstrap.Modal.getOrCreateInstance(document.getElementById('erpSourceModal'));
    const detailsModal=bootstrap.Modal.getOrCreateInstance(document.getElementById('erpDetailsModal'));
    const badge=(label,style)=>`<span class="status-badge status-${esc(style)}"><i></i>${esc(label)}</span>`;
    const date=value=>value?new Intl.DateTimeFormat('en-PK',{timeZone:'Asia/Karachi',dateStyle:'medium',timeStyle:'short'}).format(new Date(value)):'Never';
    function refreshCsrf(token){if(!token)return;config.csrf=token;$(`input[name="${token.name}"]`).val(token.hash);}
    function notify(message,icon='success'){Swal.fire({toast:true,position:'bottom-end',icon,titleText:message,showConfirmButton:false,timer:4500,timerProgressBar:true,customClass:{popup:'erp-dialog'}});}
    function confirm(title,text){return Swal.fire({titleText:title,text,icon:'question',showCancelButton:true,confirmButtonText:'Confirm',cancelButtonText:'Cancel',reverseButtons:true,buttonsStyling:false,customClass:{popup:'erp-dialog',confirmButton:'btn btn-primary',cancelButton:'btn btn-outline-secondary'}});}
    async function request(method,url,data){
        // Accept selects JSON without jQuery's automatically added legacy AJAX header.
        const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),20000);
        const options={method,credentials:'same-origin',signal:controller.signal,headers:{Accept:'application/json','X-CSRF-TOKEN':config.csrf.hash}};
        if(method!=='GET'){options.headers['Content-Type']='application/json';options.body=JSON.stringify(data??{});}
        try{
            const response=await fetch(url,options);let payload;
            try{payload=await response.json();}catch{const error=new Error(`The portal returned HTTP ${response.status} without a valid response. Reload and try again.`);error.status=response.status;error.responseJSON={message:error.message};throw error;}
            if(!payload||typeof payload!=='object'||Array.isArray(payload)){const error=new Error('The portal returned an invalid response. Reload and try again.');error.status=response.status;error.responseJSON={message:error.message};throw error;}
            refreshCsrf(payload.csrf);
            if(!response.ok){const error=new Error(payload.message||'The request could not be completed.');error.status=response.status;error.responseJSON=payload;throw error;}
            return payload;
        }catch(error){
            if(error.name==='AbortError'){error.responseJSON={message:'The portal request timed out. Reload its status before trying again.'};}
            throw error;
        }finally{clearTimeout(timer);}
    }
    function lock(value){busy=value;$('#addErpSource,#saveErpSource,[data-edit-source],[data-toggle-source],[data-test-source]').prop('disabled',value);if(!value)updateTestButtons();}
    function updateTestButtons(){records.forEach(row=>$(`[data-test-source="${row.id}"]`).prop('disabled',!row.test_available||busy||!can('test_connection')));}
    async function load(){
        try{const result=await request('GET',config.dataUrl);render(result);$('#erpApiError').prop('hidden',true);}
        catch(error){handleError(error);$('#erpApiError').prop('hidden',false).find('span').text('ERP sources could not be loaded. Please check your connection and retry.');}
    }
    function handleError(error,showForm=false){
        const payload=error.responseJSON||{};
        refreshCsrf(payload.csrf);
        if(error.status===401){location.href=config.loginUrl;return;}
        if(error.status===403&&payload.code==='access_denied'){notify(payload.message,'error');return;}
        if(error.status===403&&payload.code==='password_change_required'){location.href=payload.redirect_url;return;}
        if(error.status===403){
            request('GET',config.dataUrl).then(()=>notify('Your security token was refreshed. Please try the action again.','info')).catch(()=>notify('Please reload the page or sign in again.','error'));
            return;
        }
        const message=payload.message||'The request could not be completed. Please try again.';
        if(showForm){
            $('#erpFormError').text(message).prop('hidden',false);
            Object.entries(payload.errors||{}).forEach(([field,message])=>{$(form).find(`[name="${field}"]`).addClass('is-invalid');$(`[data-error-for="${field}"]`).text(message);});
        }else notify(message,'error');
    }
    function render(payload){
        records=payload.sources;
        $('#sourceTotal').text(payload.summary.total);$('#sourceActive').text(payload.summary.active);$('#sourceConnected').text(payload.summary.connected);
        $('#erpSourceCards').html(records.length?records.map(row=>`<section class="card erp-connection" data-source-id="${row.id}"><div class="erp-connection-heading"><span class="source-icon large"><i class="bi bi-database"></i></span>${badge(row.is_active?'Active':'Inactive',row.is_active?'active':'inactive')}</div><div class="erp-card-heading"><h2>${esc(row.name)}</h2><span class="erp-code">${esc(row.code)}</span></div><p class="erp-source-description">${esc(labels[row.connection_status]||'Not Configured')} · ${esc(authLabels[row.auth_type]||row.auth_type)} authentication</p><dl class="info-grid"><div class="erp-url"><dt>Base URL</dt><dd>${esc(row.base_url||'Not configured')}</dd></div><div><dt>Connection status</dt><dd>${badge(labels[row.connection_status]||'Not Configured',(row.connection_status||'not_configured').replaceAll('_','-'))}</dd></div><div><dt>Last connection check</dt><dd>${esc(date(row.last_checked_at))}</dd></div><div><dt>Last synchronization</dt><dd>${row.last_sync_at?esc(date(row.last_sync_at)):'Not synchronized'}</dd></div><div><dt>ERP source reference</dt><dd>#${row.id}</dd></div></dl><div class="erp-health-reason"><i class="bi bi-${row.test_available?'shield-check':'info-circle'}"></i><span>${esc(row.test_unavailable_reason||'Approved health endpoint is configured. A connection check does not synchronize any records.')}</span></div><div class="connection-actions"><button class="btn btn-primary" ${can('edit')?'':'hidden'} data-edit-source="${row.id}"><i class="bi bi-pencil"></i> Edit Source</button><button class="btn btn-outline-secondary" ${can('test_connection')?'':'hidden'} data-test-source="${row.id}" ${row.test_available?'':'disabled'} title="${esc(row.test_unavailable_reason||'Test approved health endpoint')}"><i class="bi bi-link-45deg"></i> Test Connection</button><button class="btn btn-outline-secondary" data-view-source="${row.id}"><i class="bi bi-eye"></i> Details</button></div><div class="erp-secondary-actions"><span class="erp-card-reference">${row.reference_count?`${Number(row.reference_count)} reference(s) · Code locked`:'No linked records'}</span><button class="btn btn-outline-secondary" ${can('deactivate')?'':'hidden'} data-toggle-source="${row.id}"><i class="bi bi-toggle-${row.is_active?'on':'off'}"></i> ${row.is_active?'Deactivate':'Activate'}</button></div></section>`).join(''):'<div class="card empty-state erp-source-empty"><i class="bi bi-database-add"></i><h2>No ERP sources yet</h2><p>Add a source to configure your ERP connection.</p><button class="btn btn-primary" data-add-source>Add ERP Source</button></div>');
        $('#addErpSource,[data-add-source]').prop('hidden',!can('create'));
        updateTestButtons();
    }
    function clearErrors(){$('#erpFormError').prop('hidden',true).text('');$(form).find('.is-invalid').removeClass('is-invalid');$('[data-error-for]').text('');}
    function credentials(){const type=$('#erpAuthType').val();$('#erpCredentialRef').prop('disabled',type==='none'||!can('manage_credentials'));$('#erpCredentialRef option[data-auth-type]').each(function(){this.hidden=this.dataset.authType!==type;});if(type==='none'||$('#erpCredentialRef option:selected').prop('hidden'))$('#erpCredentialRef').val('');}
    function openForm(row){
        form.reset();clearErrors();$('#erpSourceId').val(row?.id||'');$('#erpSourceModalTitle').text(row?'Edit ERP Source':'Add ERP Source');if(row&&!can('manage_credentials'))$('#erpCredentialRef').html('<option value="">Server-managed credential reference</option>');
        $('#erpName').val(row?.name||'');$('#erpCode').val(row?.code||'').prop('readonly',row?!row.can_change_code:false);$('#erpBaseUrl').val(row?.base_url||'');$('#erpAuthType').val(row?.auth_type||'none').prop('disabled',!can('manage_credentials'));$('#erpCredentialRef').val(row?.credential_ref||'');$('#erpIsActive').prop('checked',row?row.is_active:true).prop('disabled',!!row&&!can('deactivate'));
        $('#erpCodeHelp').text(row&&!row.can_change_code?'This source is referenced by other records. Its code cannot be changed.':'Unique letters, numbers, underscores or hyphens.');credentials();formModal.show();
    }
    async function details(id){
        try{
            const result=await request('GET',config.endpoint+'/'+id);const row=result.source;
            $('#erpDetailsTitle').text(row.name);
            const values={'Source ID':'#'+row.id,'Code':row.code,'Status':row.is_active?'Active':'Inactive','Base URL':row.base_url||'Not configured','Authentication':authLabels[row.auth_type],'Credential reference':row.credential_ref||'None','Connection status':labels[row.connection_status],'Last check':date(row.last_checked_at),'Last sync':row.last_sync_at?date(row.last_sync_at):'Not synchronized','Linked references':row.reference_count,'Created':date(row.created_at),'Updated':date(row.updated_at)};
            $('#erpDetailsBody').html(`<dl class="info-list">${Object.entries(values).map(([label,value])=>`<div><dt>${esc(label)}</dt><dd class="erp-details-url">${esc(value)}</dd></div>`).join('')}</dl>${row.test_unavailable_reason?`<div class="notice-inline mt-3"><i class="bi bi-info-circle"></i><span>${esc(row.test_unavailable_reason)}</span></div>`:''}`);detailsModal.show();
        }catch(error){handleError(error);}
    }
    $('#erpSourceModal').on('shown.bs.modal',()=>$('#erpName').trigger('focus'));
    $('#addErpSource').on('click',()=>openForm(null));$('#erpAuthType').on('change',credentials);$('#retryErpSources').on('click',load);
    $('#erpCode').on('input',function(){this.value=this.value.toUpperCase();});
    $(form).on('input change',event=>{const field=event.target.name;if(field){$(event.target).removeClass('is-invalid');$(`[data-error-for="${field}"]`).text('');}});
    $(form).on('submit',async event=>{
        event.preventDefault();if(busy)return;clearErrors();if(!form.reportValidity())return;
        const id=$('#erpSourceId').val();
        const payload={name:$('#erpName').val(),code:$('#erpCode').val(),base_url:$('#erpBaseUrl').val(),auth_type:$('#erpAuthType').val(),credential_ref:can('manage_credentials')?($('#erpCredentialRef').val()||null):(records.find(row=>String(row.id)===String(id))?.credential_ref||null),is_active:$('#erpIsActive').prop('checked')};
        lock(true);
        try{const result=await request('POST',config.endpoint+(id?'/'+id:''),payload);formModal.hide();await load();notify(result.message);}
        catch(error){handleError(error,true);}
        finally{lock(false);}
    });
    $('#erpSourceCards').on('click','[data-add-source]',()=>openForm(null));
    $('#erpSourceCards').on('click','[data-view-source]',function(){details(Number(this.dataset.viewSource));});
    $('#erpSourceCards').on('click','[data-edit-source]',async function(){if(busy)return;try{const result=await request('GET',config.endpoint+'/'+Number(this.dataset.editSource));openForm(result.source);}catch(error){handleError(error);}});
    $('#erpSourceCards').on('click','[data-toggle-source]',async function(){
        if(busy)return;const row=records.find(item=>item.id===Number(this.dataset.toggleSource));if(!row)return;
        const result=await confirm(`${row.is_active?'Deactivate':'Activate'} ${row.name}?`,row.is_active?'Existing references will be preserved. The source will no longer appear in active ERP dropdowns.':'The source will become available for selection. Activating it does not establish an ERP connection.');
        if(!result.isConfirmed)return;lock(true);
        try{const response=await request('POST',config.endpoint+'/'+row.id+'/status',{is_active:!row.is_active});await load();notify(response.message);}catch(error){handleError(error);}finally{lock(false);}
    });
    $('#erpSourceCards').on('click','[data-test-source]',async function(){
        if(busy)return;const row=records.find(item=>item.id===Number(this.dataset.testSource));if(!row?.test_available)return;
        const result=await confirm(`Test ${row.name}?`,'Send a server-side request to the approved ERP health endpoint. No records will be synchronized.');if(!result.isConfirmed)return;
        lock(true);const button=$(this);button.html('<i class="bi bi-arrow-repeat"></i> Checking...');
        try{const response=await request('POST',config.endpoint+'/'+row.id+'/test',{});await load();notify(response.message);}catch(error){handleError(error);await load();}finally{lock(false);}
    });
    render(config.initial);load();
})();
