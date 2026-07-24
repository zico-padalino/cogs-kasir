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

document.addEventListener('DOMContentLoaded', () => {
    initMaterialsListSearch();
    initDetailsCancel();
});
