function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

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

    list.querySelectorAll('[data-sold-out-toggle]').forEach((input) => {
        input.addEventListener('change', async () => {
            const url = input.getAttribute('data-sold-out-url');
            const next = Boolean(input.checked);
            const row = input.closest('[data-kasir-menu-admin-item]');
            const badge = row?.querySelector('[data-sold-out-badge]');

            if (! url) {
                input.checked = ! next;
                return;
            }

            input.disabled = true;

            try {
                const res = await fetch(url, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrfToken(),
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ is_sold_out: next }),
                });
                const payload = await res.json().catch(() => ({}));

                if (! res.ok) {
                    input.checked = ! next;
                    window.alert(payload.message || 'Gagal menyimpan status habis.');
                    return;
                }

                row?.classList.toggle('is-sold-out', next);
                badge?.classList.toggle('hidden', ! next);
            } catch (_) {
                input.checked = ! next;
                window.alert('Gagal menyimpan status habis.');
            } finally {
                input.disabled = false;
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', initKasirMenuAdmin);
