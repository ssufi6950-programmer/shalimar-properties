/* Four-step resale preview. All state is scoped to this browser tab. */
(() => {
    'use strict';
    const P=window.Portal;
    const { $, $$, esc, image, badge, money, priceWords, methodName, emptyState, config, state }=P;
    const form=$('#resaleWizard');
    if (!form || !state.signedIn) return;
    const available=P.eligibleProperties();
    const preselect=form.dataset.preselect;
    let draft={propertyId:'',price:0,remarks:'',method:'',assignee:'',step:0};
    if (state.draft && typeof state.draft==='object') draft={...draft,...state.draft};
    if (preselect && preselect!==draft.propertyId) draft={propertyId:preselect,price:0,remarks:'',method:'',assignee:'',step:0};
    if (!available.some(property=>property.id===draft.propertyId)) draft.propertyId='';
    draft.step=0; // Re-enter through the ownership check, retaining the customer's entered values.
    let step=0;
    let submitting=false;
    const selectedProperty=()=>P.getProperty(draft.propertyId);
    const currentSelection=()=>({method:draft.method,assignee:draft.assignee});
    const persist=()=>{state.draft={...draft,step};P.save();};
    const error=message=>{const panel=$('#wizardError');panel.textContent=message;panel.hidden=!message;if(message)panel.scrollIntoView({behavior:'smooth',block:'nearest'});};
    const peopleForMethod=()=>{
        const property=selectedProperty();
        const source=draft.method==='agent'?P.data.agents:P.data.agencies;
        return property?source.filter(person=>person.erp.includes(property.erp)):[];
    };

    function renderPropertyOptions() {
        $('#wizardPropertyOptions').innerHTML=available.length?available.map(property=>`<label class="wizard-property-option"><input type="radio" name="wizardProperty" value="${esc(property.id)}" ${property.id===draft.propertyId?'checked':''}><img src="${image(property.image)}" alt="${esc(property.project)}"><span class="selection-check"><i class="bi bi-check"></i></span><span class="wizard-property-option-body"><strong>${esc(property.project)}</strong><span>${esc(property.plot)} · Block ${esc(property.block)}</span><span>${esc(property.size)} · ${esc(property.erp)} ERP</span>${badge('Ownership Verified')}</span></label>`).join(''):emptyState('house-check','Every property has its own next step.','There are no eligible, unlisted properties right now. Review your portfolio to see ownership and listing status.',`<a class="btn btn-outline" href="${config.routes.properties}">View My Properties</a>`);
        $$('[name="wizardProperty"]').forEach(input=>input.addEventListener('change',()=>{
            draft.propertyId=input.value;draft.assignee='';$('#confirmListing').checked=false;error('');persist();renderSummary();renderRepresentatives();
        }));
        $('#wizardNext').disabled=!available.length;
    }

    function renderSummary() {
        const property=selectedProperty();
        if (!property) return;
        const person=P.representative(currentSelection());
        $('#wizardSidebarSummary').innerHTML=`<img class="summary-property-image" src="${image(property.image)}" alt="${esc(property.project)}"><h4 class="summary-project">${esc(property.project)}</h4><p class="summary-plot">${esc(property.plot)} · ${esc(property.size)}</p><dl class="information-list"><div><dt>Ownership</dt><dd>${badge('Verified')}</dd></div><div><dt>Your asking price</dt><dd>${draft.price?money(draft.price):'Not set yet'}</dd></div><div><dt>Selling method</dt><dd>${methodName(draft.method)}</dd></div>${draft.method&&draft.method!=='self'?`<div><dt>Representative</dt><dd>${esc(person?.name||'Not selected yet')}</dd></div>`:''}</dl>`;
    }

    function renderRepresentatives() {
        const panel=$('#representativePanel');
        panel.hidden=!['agent','agency'].includes(draft.method);
        if (!panel.hidden) {
            const isAgent=draft.method==='agent';
            const people=peopleForMethod();
            $('#representativeTitle').textContent=isAgent?'Choose your Shalimar agent':'Choose an authorized agency';
            $('#representativeOptions').innerHTML=people.length?people.map(person=>`<label class="representative-card"><input type="radio" name="representative" value="${esc(person.id)}" ${draft.assignee===person.id?'checked':''}><span class="representative-avatar ${isAgent?'':'agency'}">${esc(person.initials)}</span><span><strong>${esc(person.name)}</strong><small>${esc(isAgent?person.specialty:'Contact: '+person.person)}</small><span class="representative-phone"><i class="bi bi-telephone"></i> ${esc(person.phone)}</span>${isAgent?`<small>${esc(person.email)}</small>`:''}<span class="select-representative">${draft.assignee===person.id?'Selected ✓':isAgent?'Select Agent':'Select Agency'}</span></span></label>`).join(''):emptyState(isAgent?'person-check':'buildings',isAgent?'No agents available just yet.':'No agencies available just yet.','There are no sample representatives for this ERP source. Choose another selling method to continue.','',true);
            $$('[name="representative"]').forEach(input=>input.addEventListener('change',()=>{
                draft.assignee=input.value;$('#confirmListing').checked=false;persist();renderRepresentatives();renderSummary();error('');
            }));
        }
        const routing=$('#contactRouting');
        routing.hidden=!draft.method;
        if (draft.method) routing.innerHTML=`<i class="bi bi-signpost-split"></i><span>${esc(P.routing(currentSelection()))}</span>`;
    }

    function renderReview() {
        const property=selectedProperty();
        if (!property) return;
        const person=P.representative(currentSelection());
        $('#wizardReview').innerHTML=`<div class="review-property"><img src="${image(property.image)}" alt="${esc(property.project)}"><div><strong>${esc(property.project)}</strong><p>${esc(property.plot)} · Block ${esc(property.block)} · ${esc(property.size)}</p>${badge('Ownership Verified')}</div></div><div class="review-section"><h3>Your asking price</h3><strong class="review-price">${money(draft.price)}</strong><span class="price-readable">${priceWords(draft.price)}</span></div><dl class="information-grid review-section"><div><dt>Property reference</dt><dd>${esc(property.id)}</dd></div><div><dt>Booking ID</dt><dd>${esc(property.booking)}</dd></div><div><dt>Selling method</dt><dd>${methodName(draft.method)}</dd></div><div><dt>Agent / agency</dt><dd>${esc(draft.method==='self'?'Managed by you':person?.name||'Not selected')}</dd></div></dl><div class="review-section"><h3>How buyer inquiries will reach you</h3><div class="routing-note"><i class="bi bi-signpost-split"></i><span>${esc(P.routing(currentSelection()))}</span></div></div>${draft.remarks?`<div class="review-section"><h3>Your remarks</h3><p>${esc(draft.remarks)}</p></div>`:''}`;
    }

    function showStep(next, focus=true) {
        step=Math.max(0,Math.min(3,next));
        $$('[data-wizard-step]').forEach(panel=>panel.hidden=Number(panel.dataset.wizardStep)!==step);
        $$('[data-step-marker]').forEach(marker=>{
            const index=Number(marker.dataset.stepMarker);
            marker.classList.toggle('active',index===step);marker.classList.toggle('completed',index<step);
            if(index===step)marker.setAttribute('aria-current','step');else marker.removeAttribute('aria-current');
            $('.step-number',marker).innerHTML=index<step?'<i class="bi bi-check2"></i>':String(index+1);
        });
        $('#wizardStepText').textContent=`Step ${step+1} of 4`;
        $('#wizardNext').innerHTML=step===3?'Submit Resale Listing <i class="bi bi-check2"></i>':'Continue <i class="bi bi-arrow-right"></i>';
        $('#wizardBack').innerHTML=step===0?'<i class="bi bi-arrow-left"></i> Cancel':'<i class="bi bi-arrow-left"></i> Back';
        if(step===2)renderRepresentatives();
        if(step===3)renderReview();
        error('');renderSummary();persist();
        if(focus){const heading=$(`[data-wizard-step="${step}"] h2`);heading.tabIndex=-1;heading.focus({preventScroll:true});heading.scrollIntoView({block:'start',behavior:matchMedia('(prefers-reduced-motion: reduce)').matches?'instant':'smooth'});}
    }

    function validate(index) {
        const property=selectedProperty();
        if(!property||!property.eligible||property.ownership!=='Verified'||P.openListing(property.id))return 'Choose an eligible property without an open resale listing.';
        if(index>=1&&(!Number.isSafeInteger(draft.price)||draft.price<1||draft.price>999999999999))return 'Enter an asking price between PKR 1 and PKR 999,999,999,999.';
        if(index>=2&&!['self','agent','agency'].includes(draft.method))return 'Choose how you would like to sell your property.';
        if(index>=2&&draft.method!=='self'&&!peopleForMethod().some(person=>person.id===draft.assignee))return draft.method==='agent'?'Choose a Shalimar agent to continue.':'Choose an authorized agency to continue.';
        if(index===3&&!$('#confirmListing').checked)return 'Please confirm that the property details and asking price are correct.';
        return '';
    }

    async function submit() {
        if(submitting)return;
        const issue=validate(3);if(issue){error(issue);return;}
        const result=await P.dialog({title:'Ready for your next chapter?',text:'Submit this sample listing for review? It will appear only in this browser tab. No real property is published and no representative is notified.',showCancelButton:true,confirmButtonText:'Submit Demo Listing',cancelButtonText:'Keep Reviewing',icon:'question'});
        if(!result.isConfirmed)return;
        // Recheck ownership and duplicate-listing state immediately before creating the local preview.
        const finalIssue=validate(3);if(finalIssue){error(finalIssue);return;}
        submitting=true;$('#wizardNext').disabled=true;
        const property=selectedProperty();
        const id='RSL-DEMO-'+String(state.addedListings.length+1).padStart(4,'0');
        const listing={id,propertyId:property.id,project:property.project,plot:property.plot,size:property.size,image:property.image,price:draft.price,method:draft.method,assignee:draft.method==='self'?null:draft.assignee,status:'Pending Approval',date:'2026-11-04',remarks:draft.remarks,inquiries:0};
        state.addedListings.unshift(listing);state.lastReference=id;state.draft=null;state.events.unshift({id,project:property.project});state.read=false;
        if(P.save())location.href=config.routes.success;
        else {state.addedListings.shift();state.events.shift();state.lastReference=null;submitting=false;$('#wizardNext').disabled=false;}
    }

    renderPropertyOptions();
    $('#askingPrice').value=draft.price?Number(draft.price).toLocaleString('en-US'):'';
    $('#priceWords').textContent=priceWords(draft.price);
    $('#listingRemarks').value=draft.remarks;
    $('#remarksCount').textContent=draft.remarks.length;
    $$('[name="sellingMethod"]').forEach(input=>{
        input.checked=input.value===draft.method;
        input.addEventListener('change',()=>{draft.method=input.value;draft.assignee='';$('#confirmListing').checked=false;renderRepresentatives();renderSummary();persist();error('');});
    });
    $('#askingPrice').addEventListener('input',event=>{
        const digits=event.target.value.replace(/\D/g,'').slice(0,12);
        draft.price=digits?Number(digits):0;
        event.target.value=digits?draft.price.toLocaleString('en-US'):'';
        $('#priceWords').textContent=priceWords(draft.price);$('#confirmListing').checked=false;renderSummary();persist();error('');
    });
    $('#listingRemarks').addEventListener('input',event=>{draft.remarks=event.target.value.slice(0,500);$('#remarksCount').textContent=draft.remarks.length;$('#confirmListing').checked=false;persist();});
    $('#wizardNext').onclick=()=>{const issue=validate(step);if(issue){error(issue);return;}step===3?submit():showStep(step+1);};
    $('#wizardBack').onclick=()=>step?showStep(step-1):location.assign(config.routes.properties);
    form.addEventListener('submit',event=>{event.preventDefault();$('#wizardNext').click();});
    showStep(0,false);
    if(preselect&&!draft.propertyId)error('That property is not currently available for resale. Choose another eligible property below.');
})();
