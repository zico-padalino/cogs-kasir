function initMaterialHistoryModal() {
    const modal = document.getElementById('material-history-modal');
    const openBtn = document.querySelector('[data-material-history-open]');

    if (! modal || ! openBtn || openBtn.dataset.historyBound === '1') {
        return;
    }

    openBtn.dataset.historyBound = '1';

    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }

    const open = (event) => {
        event?.preventDefault();
        event?.stopPropagation();
        modal.hidden = false;
        modal.removeAttribute('hidden');
        modal.style.display = 'flex';
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    };

    const close = () => {
        modal.hidden = true;
        modal.setAttribute('hidden', 'hidden');
        modal.style.display = 'none';
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };

    openBtn.addEventListener('click', open);

    modal.querySelectorAll('[data-material-history-close]').forEach((el) => {
        el.addEventListener('click', close);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && modal.classList.contains('is-open')) {
            close();
        }
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMaterialHistoryModal);
} else {
    initMaterialHistoryModal();
}

export { initMaterialHistoryModal };
