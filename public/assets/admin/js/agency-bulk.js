(() => {
    'use strict';
    const form = document.querySelector('#agencyBulkForm');
    if (!form) return;
    const rows = [...form.querySelectorAll('[data-agency-select]')];
    const all = form.querySelector('[data-bulk-select-all]');
    const submit = form.querySelector('[data-review-selected]');
    const counter = form.querySelector('[data-bulk-selection-count]');
    const target = row => form.querySelector('[data-agency-target="' + row.value + '"]');
    function readyRows() {
        const available = rows.filter(row => !row.disabled && target(row)?.value);
        const ready = available.filter(row => target(row).value !== 'new');
        const names = new Map();
        available.filter(row => target(row).value === 'new').forEach(row => {
            const group = names.get(row.dataset.normalizedName) || [];
            group.push(row);names.set(row.dataset.normalizedName, group);
        });
        names.forEach(group => ready.push(group.find(row => row.checked) || group[0]));
        return ready;
    }
    function selectReady(checked) {
        const ready = new Set(readyRows());
        rows.forEach(row => { if (!row.disabled) row.checked = checked && ready.has(row); });
        render();
    }
    function render() {
        rows.forEach(row => target(row)?.setCustomValidity(''));
        const selected = rows.filter(row => row.checked && !row.disabled);
        const ready = readyRows();
        counter.textContent = selected.length + ' selected on this page';
        submit.disabled = selected.length === 0;
        if (all) {
            all.checked = ready.length > 0 && ready.every(row => row.checked);
            all.indeterminate = !all.checked && ready.some(row => row.checked);
            all.disabled = ready.length === 0;
        }
    }
    rows.forEach(row => row.addEventListener('change', render));
    form.querySelectorAll('[data-agency-target]').forEach(select => select.addEventListener('change', () => {
        const row = rows.find(item => item.value === select.dataset.agencyTarget);
        if (row && !select.value) row.checked = false;
        render();
    }));
    all?.addEventListener('change', () => {
        selectReady(all.checked);
    });
    form.querySelector('[data-select-ready]')?.addEventListener('click', () => selectReady(true));
    form.querySelector('[data-select-suggested]')?.addEventListener('click', () => {
        // Multiple candidates always require an explicit dropdown choice and row selection.
        rows.forEach(row => { if (!row.disabled && row.dataset.matchCount === '1' && target(row)?.value) row.checked = true; });
        render();
    });
    form.querySelector('[data-clear-agency-selection]')?.addEventListener('click', () => {
        rows.forEach(row => row.checked = false);
        render();
    });
    form.addEventListener('submit', event => {
        const missing = rows.find(row => row.checked && !target(row)?.value);
        if (missing) {
            event.preventDefault();
            const select = target(missing);
            select?.setCustomValidity('Choose the portal agency for this selected row.');
            select?.reportValidity();
            select?.addEventListener('change', () => select.setCustomValidity(''), { once: true });
        }
    });
    window.addEventListener('pageshow', render);
    render();
})();
