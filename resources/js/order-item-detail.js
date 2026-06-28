/**
 * Toggle detail panel per item pesanan (kasir + menu meja).
 */
export function initOrderItemDetails(root = document) {
    root.querySelectorAll('[data-order-item-toggle]').forEach((button) => {
        if (button.dataset.orderItemBound === '1') {
            return;
        }

        button.dataset.orderItemBound = '1';

        const item = button.closest('[data-order-item]');
        const panel = item?.querySelector('[data-order-item-detail]');
        const label = button.querySelector('[data-order-item-toggle-label]');

        if (! panel) {
            return;
        }

        button.addEventListener('click', () => {
            const open = panel.classList.toggle('hidden');
            const isOpen = ! open;

            button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            panel.hidden = ! isOpen;

            if (label) {
                label.textContent = isOpen ? 'Tutup' : 'Detail';
            }

            button.classList.toggle('is-open', isOpen);

            if (isOpen) {
                panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initOrderItemDetails();
});
