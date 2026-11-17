(() => {
    'use strict';

    document.querySelectorAll('.smp-project-page').forEach((root) => {
        if (root.dataset.projectReady) return;
        root.dataset.projectReady = 'true';

        const dialog = document.createElement('dialog');
        dialog.className = 'ppe-lightbox';

        const close = document.createElement('button');
        close.type = 'button';
        close.textContent = 'Close';
        close.setAttribute('aria-label', 'Close project image');

        const image = document.createElement('img');
        dialog.append(close, image);
        root.append(dialog);
        let trigger = null;

        close.addEventListener('click', () => dialog.close());
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) dialog.close();
        });
        dialog.addEventListener('close', () => trigger?.focus());

        root.addEventListener('click', (event) => {
            const link = event.target.closest('a[data-project-gallery]');
            if (!link || !root.contains(link) || typeof dialog.showModal !== 'function') return;
            event.preventDefault();
            trigger = link;
            image.src = link.href;
            image.alt = link.querySelector('img')?.alt ?? 'Project image';
            dialog.showModal();
            close.focus();
        });
    });
})();
