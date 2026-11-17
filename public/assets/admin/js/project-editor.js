(() => {
    'use strict';

    const config = window.ProjectPageEditor;
    const root = document.getElementById('projectPageEditor');
    if (!config || !root) return;

    const select = (selector) => root.querySelector(selector);
    const canvas = select('#projectEditorCanvas');
    const picker = select('#projectTemplatePicker');
    const slugInput = select('#projectEditorSlug');
    const saveButton = select('#projectSave');
    const stateLabel = select('#projectSaveState');
    const messageBox = select('#projectEditorMessage');
    const dialog = select('#projectItemDialog');
    const dialogForm = select('#projectItemForm');
    const dialogFields = select('#projectDialogFields');
    const dialogError = select('#projectDialogError');
    const fileInput = select('#projectImageFile');
    const clone = (value) => JSON.parse(JSON.stringify(value));
    const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'
    })[character]);

    let recordId = config.record?.slug ?? null;
    let version = config.record?.version ?? 0;
    let template = config.record?.template ?? null;
    let content = clone(config.record?.document ?? Object.values(config.templates)[0].document);
    let dirty = false;
    let busy = false;
    let dialogAction = null;
    let previousFocus = null;
    let slugEdited = false;

    function get(path) {
        return path.split('.').reduce((value, key) => value?.[key], content);
    }

    function set(path, value) {
        const keys = path.split('.');
        const key = keys.pop();
        const parent = keys.reduce((row, part) => row[part], content);
        parent[key] = value;
    }

    function changed() {
        dirty = true;
        stateLabel.textContent = 'Unsaved changes';
    }

    function message(text, error = false) {
        messageBox.hidden = !text;
        messageBox.className = error ? 'alert alert-danger' : 'alert alert-success';
        messageBox.textContent = text;
    }

    function imageUrl(reference) {
        if (reference.startsWith('project-image:')) {
            return config.routes.image + '/' + reference.slice(14);
        }
        if (reference.startsWith('https://')) return reference;
        return reference ? config.routes.public.replace(/\/$/, '') + '/' + reference : '';
    }

    const field = (path, tag = 'span', className = '') => `<${tag} class="${className}" data-field="${escape(path)}" tabindex="0" title="Double-click or press Enter to edit">${escape(get(path))}</${tag}>`;
    const add = (path, label) => `<button type="button" class="ppe-add" data-add="${escape(path)}" aria-label="${escape(label)}">+</button>`;
    const remove = (path) => `<button type="button" class="ppe-remove" data-remove="${escape(path)}" aria-label="Remove item">&times;</button>`;
    const settings = (path, label) => `<button type="button" class="ppe-setting" data-settings="${escape(path)}" aria-label="${escape(label)}">⚙</button>`;

    function image(path, alt) {
        const reference = get(path) ?? '';
        return `<div class="ppe-image" data-image="${escape(path)}">
            ${reference ? `<img src="${escape(imageUrl(reference))}" alt="${escape(alt)}">` : '<span class="ppe-image-empty">Image</span>'}
            <div class="ppe-image-tools">
                <button type="button" data-edit-image="${escape(path)}">${reference ? 'Edit' : 'Add'} image</button>
                <button type="button" data-clear-image="${escape(path)}" aria-label="Remove image">&times;</button>
            </div>
        </div>`;
    }

    function render() {
        canvas.innerHTML = `<article class="ppe-preview ppe-template-${escape(template ?? 'unselected')}">
            <div class="ppe-media">
                ${image('cover', content.name)}
                <div class="ppe-logo">${image('logo', content.name + ' logo')}</div>
            </div>
            <div class="ppe-info">
                <div class="ppe-tags">${field('status', 'span', 'ppe-pill')}${field('kind', 'span', 'ppe-pill ppe-gold')}</div>
                ${field('name', 'h2')}
                ${field('tagline', 'p', 'ppe-tagline')}
                <p class="ppe-location"><span aria-hidden="true">⌖</span> ${field('location')}</p>
                ${field('summary', 'p', 'ppe-summary')}
                <div class="ppe-section-heading">${field('labels.features', 'small')}${add('features', 'Add feature')}</div>
                <div class="ppe-features">${content.features.map((_, index) => `<span class="ppe-item">${field('features.' + index)}${remove('features.' + index)}</span>`).join('')}</div>
                <div class="ppe-facts">
                    <div>${field('labels.developer', 'small')}${field('developer', 'strong')}</div>
                    ${content.facts.map((_, index) => `<div>${field(`facts.${index}.label`, 'small')}${field(`facts.${index}.value`, 'strong')}${remove('facts.' + index)}</div>`).join('')}
                    ${add('facts', 'Add fact card')}
                </div>
                <div class="ppe-progress-label">${field('labels.progress')}<strong>${field('progress')}%</strong></div>
                <div class="ppe-progress"><i style="width:${Number(content.progress) || 0}%"></i></div>
                <div class="ppe-section-heading">${field('labels.gallery', 'small')}${add('gallery', 'Add gallery image')}</div>
                <div class="ppe-gallery">${content.gallery.map((photo, index) => `<div class="ppe-gallery-item">${image(`gallery.${index}.src`, photo.alt)}${remove('gallery.' + index)}${field(`gallery.${index}.alt`, 'span', 'ppe-alt')}</div>`).join('')}</div>
                <div class="ppe-section-heading">${field('labels.categories', 'small')}${add('categories', 'Add category')}</div>
                <div class="ppe-categories">${content.categories.map((category, index) => `<div class="ppe-category">
                    <div class="ppe-category-title">${field(`categories.${index}.label`, 'strong')}${settings('categories.' + index, 'Category settings')}${remove('categories.' + index)}${add(`categories.${index}.blocks`, 'Add block')}</div>
                    <div class="ppe-blocks">${category.blocks.map((_, block) => `<span class="ppe-item">${field(`categories.${index}.blocks.${block}.label`)}${settings(`categories.${index}.blocks.${block}`, 'Block settings')}${remove(`categories.${index}.blocks.${block}`)}</span>`).join('')}</div>
                </div>`).join('')}</div>
                <div class="ppe-price-actions">${field('price_from', 'strong', 'ppe-price')}
                    <div class="ppe-actions">${content.actions.map((_, index) => `<span class="ppe-item ppe-action">${field(`actions.${index}.label`)}${settings('actions.' + index, 'Button settings')}${remove('actions.' + index)}</span>`).join('')}${add('actions', 'Add button')}</div>
                </div>
            </div>
        </article>`;
    }

    function editText(element) {
        if (busy || element.isContentEditable) return;
        const path = element.dataset.field;
        const original = get(path);
        let cancelled = false;
        element.contentEditable = 'true';
        element.focus();
        const range = document.createRange();
        range.selectNodeContents(element);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);

        const onKey = (event) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                cancelled = true;
                element.blur();
            }
            if (event.key === 'Enter' && !(path === 'summary' && event.shiftKey)) {
                event.preventDefault();
                element.blur();
            }
        };
        const onPaste = (event) => {
            event.preventDefault();
            const text = event.clipboardData.getData('text/plain');
            const selection = window.getSelection();
            if (!selection.rangeCount) return;
            const range = selection.getRangeAt(0);
            range.deleteContents();
            const node = document.createTextNode(text);
            range.insertNode(node);
            range.setStartAfter(node);
            range.collapse(true);
            selection.removeAllRanges();
            selection.addRange(range);
        };
        element.addEventListener('keydown', onKey);
        element.addEventListener('paste', onPaste);
        element.addEventListener('blur', () => {
            element.removeEventListener('keydown', onKey);
            element.removeEventListener('paste', onPaste);
            if (!cancelled) {
                let value = element.innerText.trim();
                if (path === 'progress') {
                    if (!/^\d{1,3}$/.test(value) || Number(value) > 100) {
                        message('Development progress must be between 0 and 100.', true);
                        value = original;
                    } else value = Number(value);
                }
                if (value !== original) {
                    set(path, value);
                    if (path === 'name' && !recordId && !slugEdited) {
                        slugInput.value = String(value).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
                    }
                    changed();
                }
            }
            element.contentEditable = 'false';
            render();
        }, { once: true });
    }

    function openDialog(title, fields, action) {
        previousFocus = document.activeElement;
        select('#projectDialogTitle').textContent = title;
        dialogError.hidden = true;
        dialogFields.innerHTML = fields.map(({ key, label, value = '', type = 'text', hint = '' }) => `<label class="ppe-dialog-field">${escape(label)}<input class="form-control" name="${escape(key)}" type="${escape(type)}" value="${escape(value)}" maxlength="1000">${hint ? `<small>${escape(hint)}</small>` : ''}</label>`).join('');
        dialogAction = action;
        dialog.showModal();
        dialogFields.querySelector('input')?.focus();
    }

    function safeUrl(value, image = false) {
        if (!value) return true;
        if (value.startsWith('https://')) {
            try {
                const url = new URL(value);
                return !url.username && !url.password;
            } catch { return false; }
        }
        if (value.includes('..') || /[\\<>"'\s]/.test(value) || value.startsWith('//')) return false;
        return image
            ? /^(?:assets\/images\/[a-zA-Z0-9_./-]+\.(?:webp|png|jpe?g|svg)|project-image:[a-f0-9]{64})$/.test(value)
            : /^(?:[a-zA-Z0-9_/-]+\.php(?:[?#][a-zA-Z0-9_/?=&%+.#:-]*)?|#[a-zA-Z0-9_-]+)$/.test(value);
    }

    function editImage(path) {
        openDialog('Project image', [
            { key: 'src', label: 'Image URL or local asset', value: get(path), hint: 'HTTPS URL or assets/images/... path' }
        ], (values) => {
            if (!safeUrl(values.src, true)) throw new Error('Enter a valid HTTPS image URL or local image asset.');
            set(path, values.src);
        });
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-outline-primary';
        button.textContent = 'Upload image';
        button.addEventListener('click', () => fileInput.click());
        dialogFields.append(button);
    }

    function addItem(path) {
        const items = get(path);
        if (path === 'gallery') {
            openDialog('Add gallery image', [
                { key: 'src', label: 'Image URL (or upload below)' },
                { key: 'alt', label: 'Image description', value: content.name }
            ], (values) => {
                if (!values.src || !safeUrl(values.src, true)) throw new Error('Choose an image or enter a valid image URL.');
                items.push({ src: values.src, alt: values.alt });
            });
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'btn btn-outline-primary';
            button.textContent = 'Upload image';
            button.addEventListener('click', () => fileInput.click());
            dialogFields.append(button);
            return;
        }
        const isCategory = path === 'categories';
        const isBlock = path.endsWith('.blocks');
        if (isCategory || isBlock) {
            openDialog(isCategory ? 'Add category' : 'Add block', [
                { key: 'label', label: 'Display name' },
                { key: 'key', label: 'Internal key', hint: 'A unique name within this section.' },
                { key: 'url', label: 'Link (optional)' }
            ], (values) => {
                const key = values.key || values.label;
                if (!values.label || !key || items.some((item) => item.key === key)) throw new Error('Enter a display name and a unique key.');
                if (!safeUrl(values.url)) throw new Error('Enter a valid link.');
                items.push({ key, label: values.label, url: values.url, ...(isCategory ? { blocks: [] } : {}) });
            });
            return;
        }
        const isFeature = path === 'features';
        openDialog(isFeature ? 'Add feature' : path === 'facts' ? 'Add fact card' : 'Add button', [
            { key: 'label', label: isFeature ? 'Feature' : 'Label' },
            ...(isFeature ? [] : [{ key: path === 'facts' ? 'value' : 'url', label: path === 'facts' ? 'Value' : 'Link' }])
        ], (values) => {
            if (!values.label) throw new Error('Enter a label.');
            if (path === 'actions' && !safeUrl(values.url)) throw new Error('Enter a valid link.');
            items.push(isFeature ? values.label : values);
        });
    }

    dialogForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (busy) return;
        try {
            const values = Object.fromEntries([...new FormData(dialogForm)].map(([key, value]) => [key, String(value).trim()]));
            dialogAction(values);
            changed();
            dialog.close();
            render();
        } catch (error) {
            dialogError.textContent = error.message;
            dialogError.hidden = false;
        }
    });
    root.querySelectorAll('[data-close-dialog]').forEach((button) => button.addEventListener('click', () => { if (!busy) dialog.close(); }));
    dialog.addEventListener('cancel', (event) => { if (busy) event.preventDefault(); });
    dialog.addEventListener('close', () => previousFocus?.isConnected && previousFocus.focus());

    async function request(url, body) {
        const form = body instanceof FormData;
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': config.csrf.hash, ...(form ? {} : { 'Content-Type': 'application/json' }) },
            body: form ? body : JSON.stringify(body)
        });
        let data;
        try { data = await response.json(); } catch { throw new Error('The server returned an unreadable response. Refresh the page if your session expired.'); }
        if (data.csrf) {
            config.csrf = data.csrf;
            document.querySelectorAll(`input[name="${data.csrf.name}"]`).forEach((input) => { input.value = data.csrf.hash; });
        }
        if (!response.ok) throw new Error(data.message || 'The request could not be completed.');
        return data;
    }

    fileInput.addEventListener('change', async () => {
        const file = fileInput.files[0];
        if (!file) return;
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
            dialogError.textContent = 'Choose JPEG, PNG or WebP up to 5 MB.';
            dialogError.hidden = false;
            fileInput.value = '';
            return;
        }
        busy = true;
        saveButton.disabled = true;
        dialogError.textContent = 'Uploading image…';
        dialogError.hidden = false;
        try {
            const body = new FormData();
            body.append('image', file);
            const data = await request(config.routes.upload, body);
            const input = dialogFields.querySelector('[name="src"]');
            if (input) input.value = data.reference;
            dialogError.textContent = 'Image uploaded. Press Apply to use it.';
        } catch (error) {
            dialogError.textContent = error.message;
        } finally {
            busy = false;
            saveButton.disabled = false;
            fileInput.value = '';
        }
    });

    root.addEventListener('dblclick', (event) => {
        const fieldElement = event.target.closest('[data-field]');
        if (fieldElement) editText(fieldElement);
        else {
            const imageElement = event.target.closest('[data-image]');
            if (imageElement && !busy) editImage(imageElement.dataset.image);
        }
    });
    root.addEventListener('keydown', (event) => {
        const element = event.target.closest('[data-field]');
        if (element && !element.isContentEditable && event.key === 'Enter') {
            event.preventDefault();
            editText(element);
        }
    });
    root.addEventListener('click', (event) => {
        if (busy) return;
        const button = event.target.closest('button');
        if (!button) return;
        if (button.dataset.template) {
            const selected = button.dataset.template;
            if (template && (dirty || template !== selected) && !window.confirm('Replace the current editor content with this starting template?')) return;
            if (!config.record || selected !== (config.record.template ?? config.record.slug)) {
                const name = recordId ? content.name : null;
                content = clone(config.templates[selected].document);
                if (name) content.name = name;
            }
            template = selected;
            picker.hidden = true;
            changed();
            render();
        } else if (button.dataset.add) {
            addItem(button.dataset.add);
        } else if (button.dataset.remove) {
            if (!window.confirm('Remove this item from the project draft?')) return;
            const parts = button.dataset.remove.split('.');
            const index = Number(parts.pop());
            get(parts.join('.')).splice(index, 1);
            changed();
            render();
        } else if (button.dataset.editImage) {
            editImage(button.dataset.editImage);
        } else if (button.dataset.clearImage) {
            set(button.dataset.clearImage, '');
            changed();
            render();
        } else if (button.dataset.settings) {
            const path = button.dataset.settings;
            const item = get(path);
            openDialog('Item settings', [
                { key: 'label', label: 'Display name', value: item.label },
                ...(item.key !== undefined ? [{ key: 'key', label: 'Internal key', value: item.key }] : []),
                { key: 'url', label: 'Link (optional)', value: item.url }
            ], (values) => {
                if (!safeUrl(values.url)) throw new Error('Enter a valid link.');
                Object.assign(item, values);
            });
        }
    });
    select('#projectTemplates').addEventListener('click', () => { picker.hidden = !picker.hidden; });
    slugInput.addEventListener('input', () => {
        slugEdited = true;
        changed();
    });
    saveButton.addEventListener('click', async () => {
        if (busy || dialog.open) return;
        if (!template) {
            picker.hidden = false;
            message('Select a starting template before saving.', true);
            return;
        }
        if (!recordId && !slugInput.value) {
            slugInput.value = content.name.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        }
        if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slugInput.value)) {
            message('Use lowercase letters, numbers and hyphens for the project slug.', true);
            slugInput.focus();
            return;
        }
        busy = true;
        saveButton.disabled = true;
        stateLabel.textContent = 'Saving…';
        message('');
        try {
            const data = await request(config.routes.save, { id: recordId, slug: slugInput.value, template, version, document: content });
            recordId = data.slug;
            version = data.version;
            slugInput.readOnly = true;
            dirty = false;
            stateLabel.textContent = 'Saved ' + data.updated_at;
            message('Project saved with HTML, CSS and JS. Public JSON is available when the project is publicly eligible.');
            const publicLink = select('#projectPublicJson');
            publicLink.href = config.routes.api + '/' + encodeURIComponent(data.slug);
            publicLink.hidden = false;
            history.replaceState(null, '', data.editUrl);
        } catch (error) {
            stateLabel.textContent = 'Not saved';
            message(error.message, true);
        } finally {
            busy = false;
            saveButton.disabled = false;
        }
    });
    window.addEventListener('beforeunload', (event) => {
        if (dirty || busy) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
    render();
})();
