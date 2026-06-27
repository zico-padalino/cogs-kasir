/**
 * Kasir POS — tab Menu/Keranjang di mobile & cart bar.
 */
export function initKasirPos() {
    const root = document.getElementById('kasir-pos');
    if (!root) {
        return;
    }

    const tabs = root.querySelectorAll('[data-kasir-tab]');
    const panels = root.querySelectorAll('[data-kasir-panel]');
    const cartCount = root.querySelector('[data-kasir-cart-count]');

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

        if (name === 'cart') {
            root.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    tabs.forEach((tab) => {
        tab.addEventListener('click', () => setPanel(tab.dataset.kasirTab));
    });

    root.querySelectorAll('[data-kasir-go-cart]').forEach((btn) => {
        btn.addEventListener('click', () => setPanel('cart'));
    });

    const updateCartBadge = () => {
        const count = root.querySelectorAll('[data-kasir-item]').length;
        if (cartCount) {
            cartCount.textContent = String(count);
            cartCount.classList.toggle('hidden', count === 0);
        }
    };

    updateCartBadge();

    const syncDesktop = () => {
        if (window.innerWidth >= 1280) {
            panels.forEach((panel) => {
                panel.classList.remove('hidden', 'flex');
            });
            return;
        }

        const activeTab = root.querySelector('[data-kasir-tab].is-active');
        setPanel(activeTab?.dataset.kasirTab ?? 'menu');
    };

    window.addEventListener('resize', syncDesktop);
    syncDesktop();
}

document.addEventListener('DOMContentLoaded', initKasirPos);
