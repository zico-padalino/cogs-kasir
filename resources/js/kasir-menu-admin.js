function initKasirMenuAdmin() {
    const list = document.querySelector('[data-kasir-menu-admin-list]');
    if (! list) {
        return;
    }

    const searchInput = document.querySelector('[data-kasir-menu-admin-search]');
    const tabs = document.querySelectorAll('[data-kasir-menu-admin-category]');
    const items = list.querySelectorAll('[data-kasir-menu-admin-item]');
    const emptyState = document.querySelector('[data-kasir-menu-admin-empty]');

    let activeCategory = 'all';

    const applyFilters = () => {
        const query = (searchInput?.value ?? '').trim().toLowerCase();
        let visibleCount = 0;

        items.forEach((item) => {
            const matchCategory = activeCategory === 'all' || item.dataset.menuCategory === activeCategory;
            const matchSearch = query === '' || (item.dataset.search ?? '').includes(query);
            const visible = matchCategory && matchSearch;

            item.classList.toggle('hidden', ! visible);

            if (visible) {
                visibleCount += 1;
            }
        });

        emptyState?.classList.toggle('hidden', visibleCount > 0);
    };

    searchInput?.addEventListener('input', applyFilters);

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => {
            tabs.forEach((item) => item.classList.toggle('is-active', item === tab));
            activeCategory = tab.dataset.kasirMenuAdminCategory ?? 'all';
            applyFilters();
        });
    });
}

document.addEventListener('DOMContentLoaded', initKasirMenuAdmin);
