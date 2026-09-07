function initMaterialsListSearch() {
    const list = document.querySelector('[data-materials-list]');
    const searchInput = document.querySelector('[data-materials-search]');

    if (! list || ! searchInput) {
        return;
    }

    const items = list.querySelectorAll('[data-material-card]');
    const empty = list.querySelector('[data-materials-search-empty]');
    const countLabel = list.closest('.table-card')?.querySelector('.module-list-card__subtitle');
    const total = items.length;

    const apply = () => {
        const query = (searchInput.value || '').trim().toLowerCase();
        let visible = 0;

        items.forEach((item) => {
            const haystack = (item.dataset.search || '').toLowerCase();
            const match = query === '' || haystack.includes(query);
            item.classList.toggle('hidden', ! match);
            if (match) {
                visible += 1;
            }
        });

        empty?.classList.toggle('hidden', visible > 0);

        if (countLabel) {
            countLabel.textContent = query
                ? `${visible} dari ${total} bahan`
                : `${total} bahan terdaftar`;
        }
    };

    searchInput.addEventListener('input', apply);
}

function initDetailsCancel() {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-details-cancel]');
        if (! button) {
            return;
        }

        const details = button.closest('details');
        if (details) {
            details.open = false;
        }
    });
}

function focusMaterialRestock(anchorId) {
    if (! anchorId) {
        return false;
    }

    const card = document.getElementById(anchorId);
    if (! card) {
        return false;
    }

    const searchInput = document.querySelector('[data-materials-search]');
    if (searchInput && searchInput.value) {
        searchInput.value = '';
        searchInput.dispatchEvent(new Event('input', { bubbles: true }));
    }

    document.querySelectorAll('.material-card.is-restock-focus').forEach((el) => {
        el.classList.remove('is-restock-focus');
    });

    card.classList.remove('hidden');
    card.classList.add('is-restock-focus');

    const restock = card.querySelector('[data-material-restock]');
    if (restock instanceof HTMLDetailsElement) {
        restock.open = true;
    }

    card.scrollIntoView({ behavior: 'smooth', block: 'center' });

    window.setTimeout(() => {
        const focusTarget = restock?.querySelector(
            'input:not([type="hidden"]), select, textarea, button[type="submit"]'
        );
        if (focusTarget instanceof HTMLElement) {
            focusTarget.focus({ preventScroll: true });
        }
    }, 280);

    window.setTimeout(() => {
        card.classList.remove('is-restock-focus');
    }, 1800);

    return true;
}

function initStockMinusRestock() {
    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-stock-minus-restock]');
        if (! button) {
            return;
        }

        event.preventDefault();
        const anchor = button.getAttribute('data-stock-minus-anchor')
            || `material-${button.getAttribute('data-stock-minus-restock')}`;
        focusMaterialRestock(anchor);
    });

    const hash = window.location.hash.replace(/^#/, '');
    if (hash.startsWith('material-') || hash.startsWith('bahan-jadi-')) {
        window.setTimeout(() => focusMaterialRestock(hash), 80);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    initMaterialsListSearch();
    initDetailsCancel();
    initStockMinusRestock();
});
