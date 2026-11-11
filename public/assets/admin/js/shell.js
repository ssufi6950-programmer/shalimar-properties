/* Shared admin presentation only. ERP records are never stored in browser storage. */
(() => {
    'use strict';
    const $=selector=>document.querySelector(selector);
    const $$=selector=>[...document.querySelectorAll(selector)];
    const esc=value=>String(value).replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const toggle=$('#sidebarToggle');
    function sidebarState(){toggle.setAttribute('aria-expanded',String(innerWidth<992?document.body.classList.contains('sidebar-open'):!document.documentElement.classList.contains('sidebar-collapsed')));}
    toggle.onclick=()=>{
        if(innerWidth<992)document.body.classList.toggle('sidebar-open');
        else{document.documentElement.classList.toggle('sidebar-collapsed');try{localStorage.setItem('shalimar-admin-sidebar',document.documentElement.classList.contains('sidebar-collapsed')?'collapsed':'expanded');}catch(error){}}
        sidebarState();
    };
    $('#sidebarBackdrop').onclick=()=>{document.body.classList.remove('sidebar-open');sidebarState();};
    window.addEventListener('resize',sidebarState);sidebarState();
    $$('.nav-group-toggle').forEach(button=>button.onclick=()=>button.setAttribute('aria-expanded',String(button.getAttribute('aria-expanded')!=='true')));
    function theme(value){document.documentElement.dataset.theme=value;$('#themeToggle').innerHTML=`<i class="bi bi-${value==='dark'?'sun':'moon'}"></i>`;$('#themeToggle').setAttribute('aria-label',`Switch to ${value==='dark'?'light':'dark'} theme`);try{localStorage.setItem('shalimar-admin-theme',value);}catch(error){}}
    theme(document.documentElement.dataset.theme||'light');
    $('#themeToggle').onclick=()=>theme(document.documentElement.dataset.theme==='dark'?'light':'dark');
    const search=$('#globalSearch'),results=$('#globalResults');
    search.addEventListener('input',()=>{const value=search.value.trim().toLowerCase();results.hidden=!value;if(!value)return;const matches=$$('.nav-item').filter(link=>link.title.toLowerCase().includes(value));results.innerHTML=matches.length?matches.map(link=>`<a href="${esc(link.href)}"><strong>${esc(link.title)}</strong><small>Workspace navigation</small></a>`).join(''):'<p>No matching navigation pages.</p>';});
    document.addEventListener('click',event=>{if(!event.target.closest('.global-search'))results.hidden=true;});
    document.addEventListener('keydown',event=>{if(event.key==='/'&&!/input|textarea|select/i.test(document.activeElement.tagName)){event.preventDefault();search.focus();}if(event.key==='Escape'){results.hidden=true;document.body.classList.remove('sidebar-open');sidebarState();}});
})();
