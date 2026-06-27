/**
 * Kasir POS — tab Menu/Pesanan, pencarian menu, metode bayar.
 */
const POS_DESKTOP_BP = 1024;

export function initKasirPos() {
    const root = document.getElementById('kasir-pos');
    if (!root) {
        return;
    }

    const tabs = root.querySelectorAll('[data-kasir-tab]');
    const panels = root.querySelectorAll('[data-kasir-panel]');
    const cartCount = root.querySelector('[data-kasir-cart-count]');
    const searchInput = root.querySelector('[data-kasir-search]');

    const setPanel = (name) => {
        tabs.forEach((tab) => {
            const active = tab.dataset.kasirTab === name;
            tab.classList.toggle('is-active', active);
            tab.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        panels.forEach((panel) => {
            const show = panel.dataset.kasirPanel === name;
            panel.classList.toggle('hidden', !show);
            panel.classList.toggle('flex', show);
        });
    };

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => setPanel(tab.dataset.kasirTab));
    });

    root.querySelectorAll('[data-kasir-go-cart]').forEach((btn) => {
        btn.addEventListener('click', () => setPanel('cart'));
    });

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            const query = searchInput.value.trim().toLowerCase();

            root.querySelectorAll('[data-kasir-product]').forEach((tile) => {
                const key = tile.dataset.kasirProduct ?? '';
                const match = query === '' || key.includes(query);
                tile.classList.toggle('hidden', !match);
            });
        });
    }

    root.querySelectorAll('.pos-pay-option input[type="radio"]').forEach((radio) => {
        radio.addEventListener('change', () => {
            root.querySelectorAll('.pos-pay-option').forEach((option) => {
                const input = option.querySelector('input[type="radio"]');
                option.classList.toggle('is-selected', Boolean(input?.checked));
            });
        });
    });

    const updateCartBadge = () => {
        const count = root.querySelectorAll('[data-kasir-item]').length;
        if (cartCount) {
            cartCount.textContent = String(count);
            cartCount.classList.toggle('hidden', count === 0);
        }
    };

    updateCartBadge();

    const syncLayout = () => {
        if (window.innerWidth >= POS_DESKTOP_BP) {
            panels.forEach((panel) => {
                panel.classList.remove('hidden');
                panel.classList.add('flex');
            });
            return;
        }

        const activeTab = root.querySelector('[data-kasir-tab].is-active');
        setPanel(activeTab?.dataset.kasirTab ?? 'menu');
    };

    window.addEventListener('resize', syncLayout);
    syncLayout();
}

document.addEventListener('DOMContentLoaded', initKasirPos);
