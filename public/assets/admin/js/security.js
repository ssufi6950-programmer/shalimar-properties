/* Real CI4 user/role management. Credentials and authorization never live in browser storage. */
(() => {
    'use strict';
    const cfg=window.AdminManagement,$=window.jQuery,kind=cfg.kind;
    const can=key=>cfg.permissions[key]===true;
    const esc=value=>String(value??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const modal=bootstrap.Modal.getOrCreateInstance(document.getElementById('securityModal'));
    let page=1,rows=[],roles=[],permissions=[],mode='edit',editing=null,busy=false;
    function csrf(token){if(token){cfg.csrf=token;$(`input[name="${token.name}"]`).val(token.hash);}}
    async function request(method,url,data){
        try{const result=await $.ajax({url,method,data:method==='GET'?data:JSON.stringify(data??{}),contentType:'application/json',dataType:'json',headers:{'X-CSRF-TOKEN':cfg.csrf.hash,Accept:'application/json'},timeout:20000});csrf(result.csrf);return result;}
        catch(e){csrf(e.responseJSON?.csrf);if(e.status===401)location.href=cfg.endpoint.replace(/\/(users|roles)$/,'/login');if(e.responseJSON?.code==='password_change_required')location.href=e.responseJSON.redirect_url;throw e;}
    }
    const notify=(message,icon='success')=>Swal.fire({toast:true,position:'bottom-end',titleText:message,icon,showConfirmButton:false,timer:5000});
    function error(e,form=false){const message=e.responseJSON?.message||'The request could not be completed. Reload and try again.';if(form)$('#securityFormError').text(message).prop('hidden',false);else $('#managementError').text(message).prop('hidden',false);}
    const input=(name,label,value='',type='text',required=true)=>`<div class="mb-3"><label class="form-label" for="field-${name}">${esc(label)}</label><input id="field-${name}" class="form-control" name="${name}" type="${type}" value="${type==='password'?'':esc(value)}" ${required?'required':''} ${type==='password'?'autocomplete="new-password" minlength="12" maxlength="72"':'maxlength="190"'}></div>`;
    function matrix(selected){
        const groups=Object.groupBy?Object.groupBy(permissions,p=>p.module):permissions.reduce((all,p)=>((all[p.module]??=[]).push(p),all),{});
        return `<h3 class="mt-4">Permissions</h3><p>Grant only the actions this role needs.</p>`+Object.entries(groups).map(([module,items])=>`<fieldset class="border rounded p-3 mb-3"><legend class="float-none w-auto fs-6 px-2">${esc(module.replaceAll('_',' '))}</legend>${can('roles.manage_permissions')?`<label class="form-check mb-3"><input class="form-check-input" type="checkbox" data-module-all="${esc(module)}"> Select all in this module</label>`:''}<div class="d-flex flex-wrap gap-3">${items.map(p=>`<label class="form-check"><input class="form-check-input" type="checkbox" name="permission_keys" data-module="${esc(module)}" value="${esc(p.permission_key)}" ${selected.includes(p.permission_key)?'checked':''} ${can('roles.manage_permissions')?'':'disabled'}>${esc(p.action.replaceAll('_',' '))}</label>`).join('')}</div></fieldset>`).join('');
    }
    async function load(){
        try{
            const q=$('#recordSearch').val();const result=await request('GET',cfg.endpoint+'/data',{q,page,status:$('#statusFilter').val(),role:$('#roleFilter').val()});
            rows=result.rows;roles=result.roles||roles;permissions=result.permissions||permissions;$('#managementError').prop('hidden',true);
            if(kind==='users'){const selected=$('#roleFilter').val();$('#roleFilter').html('<option value="">All roles</option>'+roles.map(r=>`<option value="${Number(r.id)}">${esc(r.name)}</option>`).join('')).val(selected||'');}
            if($.fn.dataTable?.isDataTable('#recordsTable'))$('#recordsTable').DataTable().destroy();
            $('#recordsTable tbody').html(rows.map(r=>{
                const actions=`<button class="btn btn-sm btn-outline-secondary" data-detail="${r.id}">Details</button> ${can(kind+'.edit')&&!(kind==='roles'&&r.slug==='super_admin')?`<button class="btn btn-sm btn-primary" data-edit="${r.id}">Edit</button>`:''} ${kind==='users'&&can('users.reset_password')?`<button class="btn btn-sm btn-outline-secondary" data-password="${r.id}">Reset password</button>`:''} ${kind==='users'&&can('users.deactivate')?`<button class="btn btn-sm btn-outline-secondary" data-status="${r.id}">${r.status==='active'?'Deactivate':'Activate'}</button>`:''} ${kind==='roles'&&!Number(r.is_system)&&!Number(r.user_count)&&can('roles.delete')?`<button class="btn btn-sm btn-outline-danger" data-delete="${r.id}">Delete</button>`:''}`;
                const cells=kind==='users'?[`<strong>${esc(r.name)}</strong><small class="d-block">${esc(r.username)}</small>`,esc(r.email||'—'),r.roles.map(role=>esc(role.name)).join(', ')||'No roles',esc(r.status),esc(r.last_login_at||'Never'),esc(r.created_at),actions]:[esc(r.name),esc(r.slug),Number(r.is_system)?'System':'Custom',Number(r.is_active)?'Active':'Inactive',Number(r.user_count),r.permission_keys.length,actions];
                return '<tr>'+cells.map(c=>`<td>${c}</td>`).join('')+'</tr>';
            }).join(''));
            if($.fn.DataTable)$('#recordsTable').DataTable({paging:false,searching:false,info:false,order:[]});
            $('#recordCount').text(`${result.total} ${kind} · Page ${page}`);$('#previousPage').prop('disabled',page<=1||kind==='roles');$('#nextPage').prop('disabled',kind==='roles'||page*result.perPage>=result.total);
        }catch(e){error(e);}
    }
    async function open(id=null,password=false){
        try{
            const record=id?(await request('GET',cfg.endpoint+'/'+id)).record:null;
            editing=record;mode=password?'password':'edit';$('#securityFormError').prop('hidden',true);$('#securityForm')[0].reset();
            $('#securityModalTitle').text(password?'Set temporary password':`${id?'Edit':'Add'} ${kind==='users'?'user':'role'}`);
            let fields='';
            if(password)fields=input('password','Temporary password','','password')+input('password_confirmation','Confirm password','','password')+'<p>The user must change this password at next login. Share it through a private channel.</p>';
            else if(kind==='users'){
                fields=input('name','Full name',record?.name)+input('username','Username',record?.username)+input('email','Email',record?.email,'email',false)+(!id?input('password','Temporary password','','password')+input('password_confirmation','Confirm password','','password'):'');
                fields+=`<label class="form-label" for="accountStatus">Account status</label><select id="accountStatus" class="form-select mb-3" name="status" ${id&&!can('users.deactivate')?'disabled':''}>${['active','inactive','suspended'].map(s=>`<option ${s===(record?.status||'active')?'selected':''}>${s}</option>`).join('')}</select>`;
                if(can('users.assign_roles'))fields+='<fieldset><legend class="fs-6">Assigned roles</legend>'+roles.map(r=>`<label class="form-check mb-2"><input class="form-check-input" type="checkbox" name="role_ids" value="${r.id}" ${record?.roles.some(role=>Number(role.id)===Number(r.id))?'checked':''}>${esc(r.name)}${Number(r.is_active)?'':' (inactive)'}</label>`).join('')+'</fieldset>';
            }else{
                fields=input('name','Role name',record?.name)+input('slug','Slug',record?.slug)+input('description','Description',record?.description,'text',false)+`<label class="form-check"><input class="form-check-input" type="checkbox" name="is_active" ${record&&!Number(record.is_active)?'':'checked'}> Active role</label>`+matrix(record?.permission_keys||[]);
            }
            $('#securityFields').html(fields);$('#saveRecord').prop('disabled',false);modal.show();
        }catch(e){error(e);}
    }
    $('#createRecord').on('click',()=>open());$('#recordsTable').on('click','[data-edit]',function(){open(Number(this.dataset.edit));}).on('click','[data-password]',function(){open(Number(this.dataset.password),true);});
    $('#recordsTable').on('click','[data-detail]',async function(){try{const {record}=await request('GET',cfg.endpoint+'/'+this.dataset.detail);const text=Object.entries(record).filter(([key])=>!['auth_version'].includes(key)).map(([key,value])=>`${key}: ${typeof value==='object'?JSON.stringify(value):value??'—'}`).join('\n');Swal.fire({titleText:kind==='users'?record.name:record.name,text,confirmButtonText:'Close',width:700});}catch(e){error(e);}});
    $('#securityFields').on('change','[data-module-all]',function(){const module=this.dataset.moduleAll;$('#securityFields input[name="permission_keys"]').filter((_,el)=>el.dataset.module===module).prop('checked',this.checked);});
    $('#recordsTable').on('click','[data-delete],[data-status]',async function(){
        if(busy)return;const deleting=!!this.dataset.delete,id=Number(this.dataset.delete||this.dataset.status),row=rows.find(r=>Number(r.id)===id);
        const result=await Swal.fire({titleText:deleting?'Delete unused custom role?':`${row.status==='active'?'Deactivate':'Activate'} ${row.name}?`,text:'Account access changes revoke active sessions.',icon:'warning',showCancelButton:true});if(!result.isConfirmed)return;
        busy=true;try{const response=await request('POST',cfg.endpoint+'/'+id+(deleting?'/delete':'/status'),deleting?{}:{status:row.status==='active'?'inactive':'active'});notify(response.message);await load();}catch(e){error(e);}finally{busy=false;}
    });
    $('#securityForm').on('submit',async function(event){
        event.preventDefault();if(busy)return;const form=this;if(!form.reportValidity())return;
        const data=Object.fromEntries(new FormData(form));
        if(mode!=='password'&&kind==='users'){data.status=$('#accountStatus').val();if(can('users.assign_roles'))data.role_ids=[...form.querySelectorAll('[name="role_ids"]:checked')].map(el=>Number(el.value));}
        if(mode!=='password'&&kind==='roles'){data.is_active=form.querySelector('[name="is_active"]').checked;if(can('roles.manage_permissions'))data.permission_keys=[...form.querySelectorAll('[name="permission_keys"]:checked')].map(el=>el.value);}
        busy=true;$('#saveRecord').prop('disabled',true);$('#securityFormError').prop('hidden',true);
        try{const result=await request('POST',cfg.endpoint+(editing?'/'+editing.id:'')+(mode==='password'?'/password':''),data);modal.hide();notify(result.message);await load();}
        catch(e){error(e,true);if(e.status===403&&e.responseJSON?.code!=='access_denied'){await load();$('#securityFormError').text('Your security token was refreshed. Please try again.').prop('hidden',false);}}
        finally{busy=false;$('#saveRecord').prop('disabled',false);}
    });
    // Reset as hiding starts; a delayed hidden event must not clear a newly opened record.
    $('#securityModal').on('hide.bs.modal',()=>{$('#securityForm')[0].reset();$('#securityFields').empty();editing=null;});
    let timer;$('#recordSearch').on('input',()=>{clearTimeout(timer);timer=setTimeout(()=>{page=1;load();},300);});$('#statusFilter,#roleFilter').on('change',()=>{page=1;load();});$('#previousPage').on('click',()=>{page=Math.max(1,page-1);load();});$('#nextPage').on('click',()=>{page++;load();});load();
})();
