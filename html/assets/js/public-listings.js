/* Existing GET search and native history remain the public navigation model. */
(() => {
  'use strict';
  addEventListener('pageshow', event => { if (event.persisted) location.reload(); });
  const budget = document.querySelector('#browse-budget');
  budget?.addEventListener('change', () => {
    ['browse-min', 'browse-max'].forEach(id => { document.getElementById(id).value = ''; });
  });
  ['browse-min', 'browse-max'].forEach(id => document.getElementById(id)?.addEventListener('input', () => { budget.value = ''; }));
  const block = document.getElementById('browse-block'), project = document.getElementById('browse-project');
  const sync = () => {
    if (!block || !project) return;
    const sector = document.getElementById('browse-sector').value;
    [...block.options].forEach(option => {
      const allowed = !option.value || (!!project.value && option.dataset.project === project.value && option.dataset.sector === sector);
      option.disabled = option.hidden = !allowed;
    });
    if (block.selectedOptions[0]?.disabled) block.value = '';
  };
  project?.addEventListener('change', sync);
  document.getElementById('browse-sector')?.addEventListener('change', sync);
  sync();
  const fallback = image => {
    if (!image.matches('.card-art img,#gMain,.gthumbs img') || image.dataset.fallbackApplied) return;
    image.dataset.fallbackApplied = '1';
    image.src = 'assets/images/property-placeholder.svg';
    image.alt = 'Property photo unavailable';
  };
  document.addEventListener('error', event => { if (event.target instanceof HTMLImageElement) fallback(event.target); }, true);
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.card-art img,#gMain,.gthumbs img').forEach(image => { if (image.complete && !image.naturalWidth) fallback(image); });
  });
})();
