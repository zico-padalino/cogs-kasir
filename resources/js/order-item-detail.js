/**
 * Popup gambar produk dari item pesanan.
 */
export function initOrderItemImageLightbox(root = document) {
    const lightbox = root.querySelector('[data-order-image-lightbox]');

    if (! lightbox || lightbox.dataset.orderImageLightboxBound === '1') {
        return;
    }

    lightbox.dataset.orderImageLightboxBound = '1';

    const image = lightbox.querySelector('[data-order-image-lightbox-image]');
    const title = lightbox.querySelector('[data-order-image-lightbox-title]');

    const close = () => {
        lightbox.classList.add('hidden');
        lightbox.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('order-image-lightbox-open');
    };

    const open = (url, productName) => {
        if (! url || ! image) {
            return;
        }

        image.src = url;
        image.alt = productName;
        if (title) {
            title.textContent = productName;
        }

        lightbox.classList.remove('hidden');
        lightbox.setAttribute('aria-hidden', 'false');
        document.body.classList.add('order-image-lightbox-open');
    };

    root.querySelectorAll('[data-order-item-image-open]').forEach((button) => {
        button.addEventListener('click', () => {
            open(button.dataset.imageUrl, button.dataset.imageTitle || '');
        });
    });

    lightbox.querySelectorAll('[data-order-image-lightbox-close]').forEach((element) => {
        element.addEventListener('click', close);
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && ! lightbox.classList.contains('hidden')) {
            close();
        }
    });
}

document.addEventListener('DOMContentLoaded', () => {
    initOrderItemImageLightbox();
});
