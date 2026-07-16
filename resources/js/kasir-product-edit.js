function initKasirProductEdit() {
    const form = document.querySelector('[data-kasir-product-edit]');
    if (! form) {
        return;
    }

    const fileInput = form.querySelector('[data-kasir-product-image]');
    const preview = form.querySelector('[data-kasir-product-preview]');
    const presetRadios = form.querySelectorAll('input[name="preset_image"]');

    const showPreview = (src) => {
        if (! preview) {
            return;
        }
        preview.src = src;
        preview.classList.remove('hidden');
    };

    fileInput?.addEventListener('change', () => {
        const file = fileInput.files?.[0];
        if (! file) {
            return;
        }

        presetRadios.forEach((radio) => {
            radio.checked = false;
        });

        const reader = new FileReader();
        reader.onload = (event) => {
            if (typeof event.target?.result === 'string') {
                showPreview(event.target.result);
            }
        };
        reader.readAsDataURL(file);
    });

    presetRadios.forEach((radio) => {
        radio.addEventListener('change', () => {
            if (! radio.checked) {
                return;
            }
            if (fileInput) {
                fileInput.value = '';
            }
            const img = radio.closest('label')?.querySelector('img');
            if (img?.src) {
                showPreview(img.src);
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', initKasirProductEdit);
