function initProductsListSearch() {
    const searchInput = document.querySelector('[data-products-search]');
    const rows = document.querySelectorAll('[data-product-row]');
    const table = document.querySelector('[data-products-table]');
    const empty = document.querySelector('[data-products-search-empty]');
    const countLabel = document.querySelector('.module-step-3 .module-list-card__subtitle');

    if (! searchInput || rows.length === 0) {
        return;
    }

    const total = rows.length;

    const apply = () => {
        const query = (searchInput.value || '').trim().toLowerCase();
        let visible = 0;

        rows.forEach((row) => {
            const haystack = (row.dataset.search || '').toLowerCase();
            const match = query === '' || haystack.includes(query);
            row.classList.toggle('hidden', ! match);
            if (match) {
                visible += 1;
            }
        });

        table?.classList.toggle('hidden', visible === 0);
        empty?.classList.toggle('hidden', visible > 0);

        if (countLabel) {
            countLabel.textContent = query
                ? `${visible} dari ${total} menu`
                : `${total} menu`;
        }
    };

    searchInput.addEventListener('input', apply);
}

document.addEventListener('DOMContentLoaded', initProductsListSearch);
