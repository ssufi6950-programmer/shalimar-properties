(() => {
    'use strict';

    const api = window.JsonProjectPreview?.api;
    if (!api) return;
    const livePage = window.JsonProjectPreview.livePage === true;

    const container = document.getElementById('jsonProjects');
    const tabs = document.getElementById('jsonProjectTabs');
    const status = document.getElementById('jsonProjectsStatus');
    const empty = document.getElementById('jsonProjectsEmpty');
    const refresh = document.getElementById('jsonProjectsRefresh');
    const footerProjects = document.getElementById('jsonFooterProjects');
    const footerCategories = document.getElementById('jsonFooterCategories');
    const selected = livePage ? null : new URLSearchParams(location.search).get('project');

    function node(tag, className = '', text) {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== undefined && text !== null) element.textContent = String(text);
        return element;
    }

    function safeUrl(reference) {
        if (typeof reference !== 'string' || !reference.trim()) return '';
        try {
            const url = new URL(reference, location.href);
            if (url.username || url.password) return '';
            return url.protocol === 'https:' || (url.protocol === 'http:' && url.origin === location.origin) ? url.href : '';
        } catch {
            return '';
        }
    }

    function imageUrl(reference) {
        if (typeof reference !== 'string') return '';
        if (/^project-image:[a-f0-9]{64}$/.test(reference)) return api + '/images/' + reference.slice(14);
        return safeUrl(reference);
    }

    function image(reference, alt, className = '') {
        const src = imageUrl(reference);
        if (!src) return null;
        const element = node('img', className);
        element.src = src;
        element.alt = alt || '';
        element.loading = 'lazy';
        return element;
    }

    function link(reference, label, className = '') {
        const href = safeUrl(reference);
        const element = node(href ? 'a' : 'span', className, label);
        if (href) element.href = href;
        return element;
    }

    function browseLink(slug, category, block) {
        const query = new URLSearchParams({project: slug});
        if (category) query.set('sector', category);
        if (block) query.set('block', block);
        return 'buy.php?' + query.toString();
    }

    function fact(label, value) {
        const element = node('div');
        element.append(node('small', '', label), node('b', '', value));
        return element;
    }

    function renderProject(project) {
        // Render only structured JSON. Never read payload.html/css/js/page_html.
        const document = project.payload?.document;
        if (!document || typeof document.name !== 'string') return;

        const article = node('article', 'project rv in');
        article.id = project.slug;
        const media = node('div', 'media');
        const cover = image(document.cover, document.name);
        if (cover) media.append(cover);
        const logo = image(document.logo, document.name + ' logo', 'plogo');
        if (logo) {
            const wrap = node('span', 'plogo-wrap');
            wrap.append(logo);
            media.append(wrap);
        }

        const info = node('div', 'info');
        const tags = node('div', 'tag-row');
        if (document.status) tags.append(node('span', 'pill', document.status));
        if (document.kind) tags.append(node('span', 'pill gold', document.kind));
        info.append(tags, node('h3', '', document.name));
        if (!livePage) info.append(node('div', 'json-project-version', `Saved version ${project.version} · ${project.updated_at}`));
        if (document.tagline) info.append(node('p', 'p-tag', document.tagline));
        if (document.location) {
            const address = node('p', 'addr');
            if (livePage) address.style.margin = '4px 0 8px';
            address.append(node('i', 'fa-solid fa-location-dot'), window.document.createTextNode(' ' + document.location));
            info.append(address);
        }
        if (document.summary) info.append(node('p', '', document.summary));

        const features = node('div', 'p-feats');
        for (const text of document.features || []) {
            const feature = node('span');
            feature.append(node('i', 'fa-solid fa-diamond'), window.document.createTextNode(String(text)));
            features.append(feature);
        }
        if (features.childElementCount) info.append(features);

        const facts = node('div', 'proj-facts');
        if (document.developer) facts.append(fact(document.labels?.developer || 'Developer', document.developer));
        for (const row of document.facts || []) facts.append(fact(row.label, row.value));
        if (livePage && window.JsonProjectPreview.metrics?.[project.slug]) {
            const metrics = window.JsonProjectPreview.metrics[project.slug];
            facts.append(fact('Completed ERP resales this month', metrics.monthly), fact('Live listings', metrics.listings));
        }
        if (facts.childElementCount) info.append(facts);

        const progress = Math.max(0, Math.min(100, Number(document.progress) || 0));
        const progressLabel = node('div', 'json-project-progress-label');
        if (livePage) progressLabel.style.cssText = 'display:flex;justify-content:space-between;font-size:12.5px;color:var(--muted)';
        const progressValue = node('strong', '', progress + '%');
        if (livePage) progressValue.style.color = 'var(--ink)';
        progressLabel.append(node('span', '', document.labels?.progress || 'Development progress'), progressValue);
        const progressBar = node('div', 'progress in');
        const fill = node('i');
        fill.style.setProperty('--w', progress + '%');
        progressBar.append(fill);
        info.append(progressLabel, progressBar);

        const gallery = node('div', 'p-gallery');
        for (const photo of document.gallery || []) {
            const thumbnail = image(photo.src, photo.alt || document.name);
            if (!thumbnail) continue;
            const anchor = link(photo.src.startsWith('project-image:') ? imageUrl(photo.src) : photo.src, '');
            if (anchor.tagName !== 'A') continue;
            anchor.setAttribute('data-json-gallery', '');
            anchor.setAttribute('aria-label', 'Open photo');
            anchor.append(thumbnail);
            gallery.append(anchor);
        }
        if (gallery.childElementCount) info.append(gallery);

        const categories = node('div', 'subp subp-full');
        for (const category of document.categories || []) {
            const row = node('div', 'subp-row');
            const categoryLink = link(browseLink(project.slug, category.key), '', 'subp-cat');
            categoryLink.append(node('i', 'fa-solid fa-layer-group'), window.document.createTextNode(category.label || category.key));
            const blocks = node('span', 'subp-subs');
            for (const block of category.blocks || []) {
                blocks.append(link(block.url || browseLink(project.slug, category.key, block.key), block.label || block.key));
            }
            row.append(categoryLink, blocks);
            if (safeUrl(category.url)) {
                const official = link(category.url, '', 'subp-off');
                official.target = '_blank';
                official.rel = 'noopener';
                official.title = 'Official page';
                official.append(node('i', 'fa-solid fa-arrow-up-right-from-square'));
                row.append(official);
            }
            categories.append(row);
        }
        if (categories.childElementCount) info.append(categories);

        const actions = node('div', 'json-project-actions');
        if (livePage) actions.style.cssText = 'display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-top:14px';
        if (document.price_from) {
            const price = node('span', 'price', document.price_from);
            if (livePage) price.style.cssText = 'font-size:17px;margin-right:auto';
            actions.append(price);
        }
        for (const [index, action] of (document.actions || []).entries()) {
            actions.append(link(action.url, action.label, index === 0 ? 'btn btn-solid btn-sm' : 'btn btn-ghost btn-sm'));
        }
        if (livePage && window.JsonProjectPreview.inventoryEnabled === true && typeof window.JsonProjectPreview.inventoryBase === 'string') {
            const inventory = link(window.JsonProjectPreview.inventoryBase.replace(/\/$/, '') + '/' + encodeURIComponent(project.slug), 'Available Units', 'btn btn-ghost btn-sm');
            inventory.prepend(node('i', 'fa-solid fa-table-cells-large'));
            inventory.setAttribute('aria-label', 'Available units for ' + document.name);
            actions.append(inventory);
        }
        if (actions.childElementCount) info.append(actions);
        article.append(media, info);
        container.append(article);

        const tab = link('#' + project.slug, '', 'proj-tab');
        const tabLogo = image(document.logo, '');
        if (tabLogo) tab.append(tabLogo);
        tab.append(window.document.createTextNode(document.name));
        tabs.append(tab);

        const footerLogo = image(document.logo, document.name);
        if (footerLogo && footerProjects) {
            const footerLink = link('#' + project.slug, '');
            footerLink.title = document.name;
            footerLink.append(footerLogo);
            footerProjects.append(footerLink);
        }
        if (document.categories?.length && footerCategories) {
            const footerRow = node('div');
            footerRow.append(node('b', '', document.name + ': '));
            document.categories.forEach((category, index) => {
                if (index) footerRow.append(window.document.createTextNode(' · '));
                footerRow.append(link(browseLink(project.slug, category.key), category.label || category.key));
            });
            footerCategories.append(footerRow);
        }
    }

    async function load() {
        if (refresh) refresh.disabled = true;
        status.hidden = false;
        status.dataset.error = 'false';
        status.textContent = 'Loading saved projects…';
        try {
            const endpoint = new URL(api, location.href);
            endpoint.searchParams.set('format', 'document');
            const response = await fetch(endpoint, {cache: 'no-store', credentials: 'omit', headers: {Accept: 'application/json'}});
            if (!response.ok) throw new Error('Saved projects are temporarily unavailable. Try refreshing.');
            const body = await response.json();
            if (!Array.isArray(body.data)) throw new Error('The project API returned an unexpected response.');

            container.replaceChildren();
            tabs.replaceChildren();
            footerProjects?.replaceChildren();
            footerCategories?.replaceChildren();
            const projects = selected ? body.data.filter(project => project.slug === selected) : body.data;
            projects.forEach(renderProject);
            const count = container.childElementCount;
            empty.hidden = count > 0;
            status.textContent = `${count} saved project${count === 1 ? '' : 's'} loaded from JSON · Checked ${new Date().toLocaleTimeString()}`;
            if (livePage && count > 0) status.hidden = true;
            if (selected) tabs.prepend(link('projects-preview.php', 'All projects', 'proj-tab'));
        } catch (error) {
            status.dataset.error = 'true';
            status.textContent = error.message || 'The project preview could not load.';
        } finally {
            if (refresh) refresh.disabled = false;
        }
    }

    const lightbox = node(livePage ? 'div' : 'dialog', livePage ? 'lightbox' : 'json-project-lightbox');
    lightbox.setAttribute('role', 'dialog');
    lightbox.setAttribute('aria-label', 'Project photo');
    if (livePage) lightbox.setAttribute('aria-hidden', 'true');
    const close = node('button', 'btn btn-ghost btn-sm', 'Close');
    close.type = 'button';
    close.setAttribute('aria-label', 'Close project photo');
    const largeImage = node('img');
    lightbox.append(largeImage, close);
    window.document.body.append(lightbox);
    let trigger = null;
    const closeLightbox = () => {
        if (livePage) {
            lightbox.classList.remove('on');
            lightbox.setAttribute('aria-hidden', 'true');
            if (trigger?.isConnected) trigger.focus();
        } else lightbox.close();
    };
    close.addEventListener('click', closeLightbox);
    lightbox.addEventListener('click', (event) => { if (event.target === lightbox) closeLightbox(); });
    window.addEventListener('keydown', (event) => { if (event.key === 'Escape' && livePage && lightbox.classList.contains('on')) closeLightbox(); });
    lightbox.addEventListener('close', () => trigger?.isConnected && trigger.focus());
    container.addEventListener('click', (event) => {
        const anchor = event.target.closest('a[data-json-gallery]');
        if (!anchor || !container.contains(anchor) || (!livePage && typeof lightbox.showModal !== 'function')) return;
        event.preventDefault();
        trigger = anchor;
        largeImage.src = anchor.href;
        largeImage.alt = anchor.querySelector('img')?.alt || 'Project photo';
        if (livePage) {
            lightbox.classList.add('on');
            lightbox.setAttribute('aria-hidden', 'false');
        } else lightbox.showModal();
        close.focus();
    });

    refresh?.addEventListener('click', load);
    load();
})();
