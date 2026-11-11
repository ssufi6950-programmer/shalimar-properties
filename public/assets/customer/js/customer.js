/* Shalimar Customer Portal — sample-data interactions only. No authentication or ERP API. */
(() => {
    'use strict';
    const config = window.CustomerPortal;
    const data = config.data;
    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
    const esc = value => String(value ?? '').replace(/[&<>"']/g, character => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[character]));
    const storageKey = 'shalimar-customer-preview-v1';
    const initialState = () => ({signedIn:false, addedListings:[], overrides:{}, profile:{}, read:false, lastReference:null, draft:null, events:[]});
    let state = initialState();
    try {
        const saved = JSON.parse(sessionStorage.getItem(storageKey) || 'null');
        if (saved && typeof saved === 'object') state = {...state, ...saved};
    } catch (error) { /* Invalid preview state is safely replaced with the sample account. */ }
    for (const key of ['addedListings', 'events']) if (!Array.isArray(state[key])) state[key] = [];
    for (const key of ['overrides', 'profile']) if (!state[key] || typeof state[key] !== 'object') state[key] = {};

    function save() {
        try { sessionStorage.setItem(storageKey, JSON.stringify(state)); return true; }
        catch (error) { toast('Allow browser tab storage to keep changes while exploring the demo.'); return false; }
    }

    function toast(message) {
        const item = document.createElement('div');
        item.className = 'portal-toast';
        item.innerHTML = `<i class="bi bi-check2-circle"></i><span>${esc(message)}</span><button class="icon-button" aria-label="Dismiss notification"><i class="bi bi-x-lg"></i></button>`;
        $('#portalToast').append(item);
        $('button', item).onclick = () => item.remove();
        setTimeout(() => item.remove(), 6000);
    }

    function dialog(options) {
        const settings = {
            buttonsStyling:false, reverseButtons:true, confirmButtonText:'Got it',
            customClass:{popup:'portal-dialog',confirmButton:'btn btn-primary',cancelButton:'btn btn-outline'},
            ...options,
        };
        const mobileMenu = $('.offcanvas.show');
        if (mobileMenu && window.bootstrap) {
            // Close the navigation focus trap before opening the confirmation dialog.
            return new Promise(resolve => {
                mobileMenu.addEventListener('hidden.bs.offcanvas', () => resolve(Swal.fire(settings)), {once:true});
                bootstrap.Offcanvas.getOrCreateInstance(mobileMenu).hide();
            });
        }
        return Swal.fire(settings);
    }

    const image = name => config.assets + 'images/' + encodeURIComponent(name);
    const propertyUrl = id => config.routes.properties + '/' + encodeURIComponent(id);
    const listingUrl = id => config.routes.resale + '/' + encodeURIComponent(id);
    const money = value => 'PKR ' + Number(value).toLocaleString('en-US');
    const badge = status => `<span class="portal-badge badge-${esc(String(status).toLowerCase().replace(/[^a-z0-9]+/g, '-'))}"><i></i>${esc(status)}</span>`;
    const methodName = method => ({self:'Sell Myself',agent:'Shalimar Agent',agency:'Authorized Agency'}[method] || 'Not selected');
    function priceWords(value) {
        let remaining = Math.floor(Number(value));
        if (!remaining) return 'Your price, in your own terms.';
        const parts = [];
        for (const [unit, label] of [[10000000,'Crore'],[100000,'Lakh'],[1000,'Thousand']]) {
            const count = Math.floor(remaining / unit);
            if (count) parts.push(count.toLocaleString('en-US') + ' ' + label);
            remaining %= unit;
        }
        if (remaining) parts.push(String(remaining));
        return parts.join(' ');
    }

    function allListings() {
        return [...state.addedListings, ...data.listings].map(listing => ({...listing, ...(state.overrides[listing.id] || {})}));
    }
    const getProperty = id => data.properties.find(property => property.id === id);
    const getListing = id => allListings().find(listing => listing.id === id);
    const openListing = id => allListings().find(listing => listing.propertyId === id && ['Active','Pending Approval'].includes(listing.status));
    const eligibleProperties = () => data.properties.filter(property => property.eligible && property.ownership === 'Verified' && !openListing(property.id));
    const representative = listing => (listing.method === 'agent' ? data.agents : data.agencies).find(person => person.id === listing.assignee);
    function routing(listing) {
        if (listing.method === 'self') return 'Buyer inquiries come directly to you through your preferred contact details.';
        const person = representative(listing);
        return `Buyer inquiries are routed to ${person?.name || 'your selected representative'}, your ${listing.method === 'agent' ? 'Shalimar representative' : 'authorized agency'}.`;
    }
    function emptyState(icon, title, description, action = '', compact = false) {
        return `<div class="portal-empty ${compact?'compact':''}"><span class="empty-symbol"><i class="bi bi-${esc(icon)}"></i></span><h3>${esc(title)}</h3><p>${esc(description)}</p>${action}</div>`;
    }

    window.Portal = {config, data, state, $, $$, esc, save, toast, dialog, image, propertyUrl, listingUrl, money, priceWords, badge, methodName, allListings, getProperty, getListing, openListing, eligibleProperties, representative, routing, emptyState};

    // A browser-only sample-account state. These routes never authenticate a real customer.
    if (document.body.dataset.authPage !== 'true' && !state.signedIn) {
        location.replace(config.routes.login);
        return;
    }
    document.documentElement.classList.add('portal-ready');

    const maskCnic = value => {
        const digits = value.replace(/\D/g, '').slice(0,13);
        return digits.slice(0,5) + (digits.length>5?'-'+digits.slice(5,12):'') + (digits.length>12?'-'+digits.slice(12):'');
    };
    const maskMobile = value => {
        const digits = value.replace(/\D/g, '').slice(0,11);
        return digits.slice(0,4) + (digits.length>4?'-'+digits.slice(4):'');
    };
    $('#cnic')?.addEventListener('input', event => { event.target.value = maskCnic(event.target.value); });
    ['#mobile','#profilePhone'].forEach(selector => $(selector)?.addEventListener('input', event => { event.target.value = maskMobile(event.target.value); }));
    $('#identityForm')?.addEventListener('submit', event => {
        event.preventDefault();
        const cnic = $('#cnic').value.replace(/\D/g, '');
        const mobile = $('#mobile').value.replace(/\D/g, '');
        const error = $('#identityError');
        if (cnic.length !== 13 || !/^03\d{9}$/.test(mobile)) {
            error.textContent = cnic.length !== 13 ? 'Enter a 13-digit sample CNIC to continue.' : 'Enter an 11-digit mobile number starting with 03.';
            error.hidden = false;
            (cnic.length !== 13 ? $('#cnic') : $('#mobile')).focus();
            return;
        }
        // Identity inputs are deliberately not persisted or sent to a server.
        location.href = config.routes.verify;
    });

    const otpForm = $('#otpForm');
    if (otpForm) {
        const inputs = $$('.otp-inputs input');
        let countdown = 30;
        let timer;
        function startCountdown() {
            clearInterval(timer); countdown = 30; $('#resendOtp').disabled = true;
            $('#otpCountdown').textContent = 'Resend in 30s';
            timer = setInterval(() => {
                countdown--;
                $('#otpCountdown').textContent = countdown ? `Resend in ${countdown}s` : 'You can try the demo code again.';
                if (countdown <= 0) { clearInterval(timer); $('#resendOtp').disabled = false; }
            }, 1000);
        }
        inputs.forEach((input,index) => {
            input.addEventListener('input', () => {
                input.value = input.value.replace(/\D/g,'').slice(-1);
                if (input.value && inputs[index+1]) inputs[index+1].focus();
            });
            input.addEventListener('keydown', event => {
                if (event.key === 'Backspace' && !input.value && index) inputs[index-1].focus();
                if (event.key === 'ArrowLeft' && index) { event.preventDefault(); inputs[index-1].focus(); }
                if (event.key === 'ArrowRight' && index<5) { event.preventDefault(); inputs[index+1].focus(); }
            });
            input.addEventListener('paste', event => {
                event.preventDefault();
                const code = event.clipboardData.getData('text').replace(/\D/g,'').slice(0,6);
                [...code].forEach((digit,i) => { inputs[i].value = digit; });
                inputs[Math.min(code.length,5)].focus();
            });
        });
        otpForm.addEventListener('submit', event => {
            event.preventDefault();
            const code = inputs.map(input => input.value).join('');
            if (code !== '123456') {
                $('#otpError').hidden = false;
                $('#otpError').textContent = 'For this demo, enter the sample passcode 123456.';
                inputs[0].focus(); return;
            }
            state.signedIn = true;
            if (save()) location.href = config.routes.dashboard;
        });
        $('#resendOtp').onclick = () => {
            inputs.forEach(input => input.value = '');
            $('#otpError').hidden = true; inputs[0].focus(); startCountdown();
            toast('Demo passcode reset to 123456. No SMS was sent.');
        };
        startCountdown();
        window.addEventListener('pagehide', () => clearInterval(timer));
    }

    if (window.jQuery?.fn.select2) jQuery('.enhanced-select').select2({width:'100%',minimumResultsForSearch:0});

    function renderPropertyCard(property) {
        const listing = openListing(property.id);
        const canList = property.eligible && property.ownership === 'Verified';
        const action = listing
            ? `<a class="btn btn-soft" href="${listingUrl(listing.id)}">View Listing <i class="bi bi-arrow-up-right"></i></a>`
            : canList ? `<a class="btn btn-primary" href="${config.routes.create}?property=${encodeURIComponent(property.id)}">List for Resale <i class="bi bi-arrow-right"></i></a>`
            : `<button class="btn btn-disabled" disabled title="${esc(property.reason)}">Not Yet Eligible</button>`;
        return `<article class="property-card" data-property-card="${esc(property.id)}">
            <a class="property-photo" href="${propertyUrl(property.id)}"><img src="${image(property.image)}" alt="${esc(property.project)}" loading="lazy"><span class="erp-label"><i class="bi bi-buildings"></i>${esc(property.erp)}</span><span class="photo-project">${esc(property.project)}</span></a>
            <div class="property-card-body"><div class="property-title-row"><span class="property-type">${esc(property.type)}</span><span class="property-size">${esc(property.size)}</span></div>
            <h3>${esc(property.plot)} <span>· Block ${esc(property.block)}</span></h3><p class="property-location"><i class="bi bi-geo-alt"></i>${esc(property.location)}</p>
            <dl class="property-mini-info"><div><dt>Sector</dt><dd>${esc(property.sector)}</dd></div><div><dt>Booking ID</dt><dd>${esc(property.booking)}</dd></div></dl>
            <div class="property-badges">${badge(property.ownership)}${badge(listing?'Already Listed':canList?'Eligible for Resale':'Not Yet Eligible')}</div>
            ${!canList?`<p class="eligibility-reason">${esc(property.reason)}</p>`:''}
            <div class="property-card-actions"><a class="btn btn-outline" href="${propertyUrl(property.id)}">View Details</a>${action}</div></div></article>`;
    }

    function renderProperties() {
        const query = ($('#propertySearch')?.value || '').trim().toLowerCase();
        const project = $('#projectFilter')?.value;
        const erp = $('#erpFilter')?.value;
        const type = $('#typeFilter')?.value;
        const eligibility = $('#eligibilityFilter')?.value;
        let records = data.properties.filter(property => {
            const listed = !!openListing(property.id);
            return (!query || [property.id,property.plot,property.project,property.booking,property.block].join(' ').toLowerCase().includes(query))
                && (!project || property.project === project) && (!erp || property.erp === erp) && (!type || property.type === type)
                && (!eligibility || (eligibility==='listed'?listed:eligibility==='eligible'?property.eligible&&!listed:!property.eligible));
        });
        $$('[data-property-grid]').forEach(grid => {
            const visible = grid.dataset.gridLimit ? records.slice(0,Number(grid.dataset.gridLimit)) : records;
            grid.innerHTML = visible.length ? visible.map(renderPropertyCard).join('') : emptyState('house-heart', data.properties.length?'No properties match just yet.':'Your portfolio starts here.', data.properties.length?'Try a different search or clear a filter to find your property.':'Owned properties will appear here once they are connected to your verified ownership records.', data.properties.length?'<button class="btn btn-outline" data-clear-property-filters>Clear filters</button>':'<button class="btn btn-outline" data-help>View guidance</button>');
        });
        if ($('#propertyResultCount')) $('#propertyResultCount').textContent = `${records.length} ${records.length===1?'property':'properties'} in your portfolio`;
    }
    function clearFilters() {
        ['#propertySearch','#projectFilter','#erpFilter','#typeFilter','#eligibilityFilter'].forEach(selector => {
            if ($(selector)) { $(selector).value=''; if (window.jQuery) jQuery(selector).trigger('change.select2'); }
        });
        renderProperties();
    }
    if ($('#eligibilityFilter')) {
        const params = new URLSearchParams(location.search);
        if (['eligible','listed','ineligible'].includes(params.get('eligibility'))) $('#eligibilityFilter').value = params.get('eligibility');
    }
    if ($('[data-property-grid]')) renderProperties();
    $('#propertySearch')?.addEventListener('input', renderProperties);
    ['#projectFilter','#erpFilter','#typeFilter','#eligibilityFilter'].forEach(selector => $(selector)?.addEventListener('change', renderProperties));
    if (window.jQuery) jQuery('#projectFilter').on('select2:select select2:clear', renderProperties);
    $('#clearPropertyFilters')?.addEventListener('click', clearFilters);
    $('#moreFilters')?.addEventListener('click', event => {
        const panel = $('#propertyAdvancedFilters'); panel.hidden = !panel.hidden;
        event.currentTarget.setAttribute('aria-expanded', String(!panel.hidden));
    });

    function renderPropertyDetails() {
        const root = $('[data-detail-property]'); if (!root) return;
        const property = getProperty(root.dataset.detailProperty);
        if (!property) return;
        const listing = openListing(property.id);
        $('#propertyResaleInfo').innerHTML = `<dl class="information-list"><div><dt>Resale eligibility</dt><dd>${badge(listing?'Already Listed':property.eligible?'Eligible for Resale':'Not Yet Eligible')}</dd></div><div><dt>Current listing</dt><dd>${listing?badge(listing.status):'No open listing'}</dd></div>${listing?`<div><dt>Asking price</dt><dd>${money(listing.price)}</dd></div><div><dt>Selling method</dt><dd>${methodName(listing.method)}</dd></div>`:''}</dl>${!property.eligible?`<p class="eligibility-reason">${esc(property.reason)}</p>`:''}`;
        $('#propertyResaleAction').innerHTML = listing ? `<a class="btn btn-soft w-100" href="${listingUrl(listing.id)}">View My Listing <i class="bi bi-arrow-up-right"></i></a>` : property.eligible ? `<a class="btn btn-primary w-100" href="${config.routes.create}?property=${encodeURIComponent(property.id)}">List This Property for Resale <i class="bi bi-arrow-right"></i></a>` : `<button class="btn btn-disabled w-100" disabled>Resale available after verification</button>`;
    }
    renderPropertyDetails();

    const stats = {owned:data.properties.length,eligible:eligibleProperties().length,active:allListings().filter(l=>l.status==='Active').length,sold:allListings().filter(l=>l.status==='Sold').length};
    $$('[data-stat]').forEach(item => item.textContent = stats[item.dataset.stat]);
    $$('[data-owned-count]').forEach(item => item.textContent = stats.owned);

    let listingStatus = new URLSearchParams(location.search).get('status') || '';
    function renderListings() {
        const root = $('#customerListings'); if (!root) return;
        const records = allListings().filter(listing=>!listingStatus || listing.status===listingStatus);
        root.innerHTML = records.length ? records.map(listing => {
            const person = listing.method === 'self' ? 'Managed by you' : representative(listing)?.name || 'Representative unavailable';
            return `<article class="customer-listing-card"><a href="${listingUrl(listing.id)}"><img class="listing-card-image" src="${image(listing.image)}" alt="${esc(listing.project)}"></a><div class="listing-card-content"><div class="listing-card-heading"><div><span class="listing-reference">${esc(listing.id)} · ${esc(listing.date)}</span><h2>${esc(listing.project)}</h2><p class="listing-card-subtitle">${esc(listing.plot)} · ${esc(listing.size)}</p></div>${badge(listing.status)}</div><div class="listing-card-metrics"><span><small>Asking price</small><strong class="listing-card-price">${money(listing.price)}</strong></span><span><small>Selling method</small><strong>${methodName(listing.method)}</strong></span><span><small>Inquiries handled by</small><strong>${esc(person)}</strong></span></div><div class="listing-card-actions"><a class="btn btn-outline" href="${listingUrl(listing.id)}">View Details <i class="bi bi-arrow-up-right"></i></a><button class="btn btn-soft" data-inquiries="${esc(listing.id)}"><i class="bi bi-chat-dots"></i> View Inquiries (${Number(listing.inquiries)})</button>${['Active','Pending Approval'].includes(listing.status)?`<button class="text-button" data-cancel-listing="${esc(listing.id)}" ${listing.cancelRequested?'disabled':''}>${listing.cancelRequested?'Cancellation Requested (Demo)':'Request Cancellation'}</button>`:''}</div></div></article>`;
        }).join('') : emptyState('house-up', listingStatus?'No listings in this chapter.':'Ready for your next opportunity?',listingStatus?'Your listings with this status will appear here. You can explore your other listings in the meantime.':'Choose an eligible property and set up your first resale listing in a few simple steps.',listingStatus?'<button class="btn btn-outline" data-show-all-listings>View all listings</button>':`<a class="btn btn-primary" href="${config.routes.create}">List a Property <i class="bi bi-arrow-right"></i></a>`);
        $$('[data-listing-status]').forEach(button => { button.classList.toggle('active',button.dataset.listingStatus===listingStatus); button.setAttribute('aria-pressed',String(button.dataset.listingStatus===listingStatus)); });
        $('[data-listing-count]').textContent = allListings().length;
    }
    $$('[data-listing-status]').forEach(button => button.onclick=()=>{listingStatus=button.dataset.listingStatus;renderListings();});
    renderListings();

    function renderListingDetail() {
        const root = $('#customerListingDetail'); if (!root) return;
        const listing = getListing(root.dataset.listingId);
        if (!listing) { root.innerHTML=emptyState('search','We couldn’t find that demo listing.','A browser-local submission may have been reset. Your other sample listings are ready to explore.',`<a href="${config.routes.resale}" class="btn btn-primary">View My Listings</a>`); return; }
        const person = listing.method==='self' ? 'You, the property owner' : representative(listing)?.name || 'Representative unavailable';
        const terminal = ['Rejected','Cancelled'].includes(listing.status);
        const events = [
            ['Listing submitted',listing.date,false],
            ['Review '+(listing.status==='Pending Approval'?'in progress':'completed'),listing.status==='Pending Approval'?'Your sample listing is awaiting review.':'Sample review completed',false],
            [terminal?`Listing ${listing.status.toLowerCase()}`:'Open for buyer inquiries',terminal?'Historical sample outcome':listing.status==='Pending Approval'?'After approval':listing.date,listing.status==='Pending Approval'],
        ];
        if (listing.status==='Sold') events.push(['Resale completed','Ownership transferred · Historical demo record',false]);
        if (listing.cancelRequested) events.push(['Cancellation requested','Demo request recorded in this browser tab',false]);
        root.innerHTML=`<div class="page-header listing-detail-header"><div><span class="eyebrow">YOUR LISTING, AT EVERY STEP</span><h1>${esc(listing.project)}</h1><p>${esc(listing.id)} · ${esc(listing.plot)} · ${esc(listing.size)}</p></div>${badge(listing.status)}</div><div class="listing-detail-grid"><div><section class="surface-card"><img class="listing-detail-cover" src="${image(listing.image)}" alt="${esc(listing.project)}"><div class="listing-detail-body"><span class="eyebrow">YOUR PROPERTY</span><h2>${esc(listing.plot)} · ${esc(listing.size)}</h2><p>${esc(listing.project)}, Sargodha</p><dl class="information-grid"><div><dt>Property reference</dt><dd>${esc(listing.propertyId)}</dd></div><div><dt>Listing date</dt><dd>${esc(listing.date)}</dd></div><div><dt>Selling method</dt><dd>${methodName(listing.method)}</dd></div><div><dt>Selected agent / agency</dt><dd>${esc(person)}</dd></div></dl>${listing.remarks?`<div class="review-section"><h3>Your remarks</h3><p>${esc(listing.remarks)}</p></div>`:''}</div></section><section class="surface-card detail-information mt-4"><h2>Contact Routing</h2><div class="routing-note"><i class="bi bi-signpost-split"></i><span>${esc(routing(listing))}</span></div><div class="info-note mt-3"><i class="bi bi-info-circle"></i><span>Contact routing is simulated. No buyer, agent or agency is notified by this demo.</span></div></section></div><aside class="listing-detail-side"><section class="surface-card detail-information"><span class="eyebrow">YOUR ASKING PRICE</span><strong class="review-price">${money(listing.price)}</strong><span class="price-readable">${priceWords(listing.price)}</span><dl class="information-list"><div><dt>Status</dt><dd>${badge(listing.status)}</dd></div><div><dt>Buyer inquiries</dt><dd>${Number(listing.inquiries)}</dd></div><div><dt>Handled by</dt><dd>${esc(person)}</dd></div></dl><button class="btn btn-primary w-100" data-inquiries="${esc(listing.id)}"><i class="bi bi-chat-dots"></i> View Demo Inquiries</button>${['Active','Pending Approval'].includes(listing.status)?`<button class="btn btn-outline w-100 mt-3" data-cancel-listing="${esc(listing.id)}" ${listing.cancelRequested?'disabled':''}>${listing.cancelRequested?'Cancellation Requested (Demo)':'Request Cancellation'}</button>`:''}</section><section class="surface-card detail-information"><h2>Your Listing Journey</h2><div class="status-timeline">${events.map(([title,text,upcoming])=>`<div class="${upcoming?'upcoming':''}"><span class="timeline-point"></span><strong>${esc(title)}</strong><small>${esc(text)}</small></div>`).join('')}</div></section></aside></div>`;
    }
    renderListingDetail();

    if ($('#successReference')) {
        const listing = getListing(state.lastReference);
        if (listing) $('#successReference').textContent = listing.id;
        else $('#listingSuccess').innerHTML = emptyState('house-heart','Your next chapter is waiting.','Create a demo resale listing to see its confirmation and sample reference here.',`<a class="btn btn-primary" href="${config.routes.create}">List a Property <i class="bi bi-arrow-right"></i></a>`);
    }

    const profileForm = $('#customerProfileForm');
    if (profileForm) {
        for (const [key,value] of Object.entries(state.profile)) {
            const input = profileForm.elements.namedItem(key);
            if (input) input.type === 'checkbox' ? input.checked = !!value : input.value = value;
        }
        profileForm.addEventListener('submit', event => {
            event.preventDefault();
            if (!profileForm.reportValidity()) return;
            if (!/^03\d{9}$/.test($('#profilePhone').value.replace(/\D/g,''))) { toast('Use an 11-digit preferred mobile number starting with 03.'); $('#profilePhone').focus(); return; }
            state.profile = Object.fromEntries(new FormData(profileForm));
            state.profile.updates = $('#profileUpdates').checked;
            if (save()) toast('Your demo preferences are saved in this browser tab.');
        });
        profileForm.addEventListener('reset', () => toast('The contact form has returned to its original sample values. Save to apply them to this demo.'));
    }

    if (state.read) { $$('.notification-row').forEach(row=>row.classList.remove('unread')); $$('.notification-bell>span').forEach(dot=>dot.hidden=true); }
    if (state.events.length && $('[data-activity-list]')) {
        const container = $('[data-activity-list]');
        state.events.slice(0,2).reverse().forEach(event => {
            const row = document.createElement('a'); row.className='activity-row'; row.href=listingUrl(event.id);
            row.innerHTML=`<span class="activity-icon amber"><i class="bi bi-send"></i></span><span><strong>Demo resale listing submitted</strong><small>${esc(event.project)} · ${esc(event.id)}</small></span><time>In this browser tab</time>`;
            container.prepend(row);
        });
    }

    document.addEventListener('click', async event => {
        const trigger = event.target.closest('button, a'); if (!trigger) return;
        if (trigger.hasAttribute('data-clear-property-filters')) clearFilters();
        if (trigger.hasAttribute('data-show-all-listings')) { listingStatus=''; renderListings(); }
        if (trigger.hasAttribute('data-mark-read')) {
            state.read=true; save(); $$('.notification-row').forEach(row=>row.classList.remove('unread'));
            $$('.notification-bell>span').forEach(dot=>dot.hidden=true); toast('Sample notifications marked as read.');
        }
        if (trigger.hasAttribute('data-logout')) {
            const result = await dialog({title:'Leave the preview?',text:'You can sign in again with the demo passcode. Your sample changes stay in this browser tab until it is closed or reset.',showCancelButton:true,confirmButtonText:'Sign out of preview',cancelButtonText:'Stay here',icon:'question'});
            if (result.isConfirmed) { state.signedIn=false; save(); location.href=config.routes.login; }
        }
        if (trigger.hasAttribute('data-reset-demo')) {
            const result = await dialog({title:'A fresh start?',text:'This clears your demo listings, preferences and draft in this browser tab. The original sample account will be restored.',showCancelButton:true,confirmButtonText:'Reset Demo',cancelButtonText:'Keep exploring',icon:'question'});
            if (result.isConfirmed) { state=initialState(); state.signedIn=true; window.Portal.state=state; save(); location.href=config.routes.dashboard; }
        }
        if (trigger.hasAttribute('data-help')) dialog({title:'Your next step, made simpler.',html:'<p><strong>Explore your properties</strong><br>Eligible properties are verified and have no active or pending listing.</p><p><strong>Choose how you sell</strong><br>Handle inquiries yourself, select a Shalimar agent, or choose an authorized agency.</p><p><strong>Keep track</strong><br>Find your sample submissions, status and inquiries in My Resale Listings.</p><p>This is a sample-data preview. For real ownership updates, use the contact information on the public Shalimar website.</p>',icon:'info'});
        if (trigger.dataset.inquiries) {
            const listing=getListing(trigger.dataset.inquiries); if (!listing) return;
            const names=['Bilal Ahmed','Ayesha Khan','Omar Farooq'];
            const html=listing.inquiries ? `<p>${esc(routing(listing))}</p>${names.slice(0,listing.inquiries).map((name,index)=>`<div class="inquiry-preview"><strong>${name}</strong><p>${['Is this property still available for resale?','I would like to learn more about this property.','Could your representative share the property details?'][index]}</p><small>03XX-***-${4021+index} · Sample buyer inquiry</small></div>`).join('')}` : emptyState('chat-square-heart','A conversation is yet to begin.','Buyer inquiries will appear here once someone expresses interest in your listing.','',true);
            dialog({title:'Buyer inquiries · demo',html,confirmButtonText:'Back to my listing'});
        }
        if (trigger.dataset.cancelListing) {
            const listing=getListing(trigger.dataset.cancelListing); if (!listing || listing.cancelRequested) return;
            const result=await dialog({title:'Request a cancellation?',text:'This records a sample cancellation request in this browser tab. Your demo listing stays in its current status while the request is shown as pending review. No real request is sent.',showCancelButton:true,confirmButtonText:'Record Demo Request',cancelButtonText:'Keep Listing',icon:'question'});
            if (result.isConfirmed) { state.overrides[listing.id]={...(state.overrides[listing.id]||{}),cancelRequested:true}; save(); renderListings(); renderListingDetail(); toast('Demo cancellation requested. No message was sent.'); }
        }
    });
})();
