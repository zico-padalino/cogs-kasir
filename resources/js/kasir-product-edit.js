function initKasirProductEdit() {
    const form = document.querySelector('[data-kasir-product-edit]');
    if (! form) {
        return;
    }

    const fileInput = form.querySelector('[data-kasir-product-image]');
    const filename = form.querySelector('[data-kasir-product-filename]');
    const preview = form.querySelector('[data-kasir-product-preview]');
    const presetRadios = form.querySelectorAll('[data-kasir-preset-radio]');
    const removeCheck = form.querySelector('[data-kasir-remove-image]');
    const uploadSection = form.querySelector('[data-kasir-image-upload-section]');

    const fallbackSrc = preview?.dataset?.fallbackSrc || '';

    const showPreview = (src) => {
        if (! preview) {
            return;
        }
        preview.src = src || fallbackSrc;
    };

    // When "Hapus" is checked: clear file selection, uncheck presets,
    // show fallback preview, and visually dim the upload/preset sections.
    if (removeCheck) {
        const syncRemoveState = () => {
            const removing = removeCheck.checked;

            if (removing) {
                if (fileInput) {
                    fileInput.value = '';
                }
                if (filename) {
                    filename.textContent = 'Belum ada file dipilih';
                }
                presetRadios.forEach((r) => { r.checked = false; });
                showPreview(fallbackSrc);
            }

            if (uploadSection) {
                uploadSection.style.opacity = removing ? '0.4' : '';
                uploadSection.style.pointerEvents = removing ? 'none' : '';
            }
        };

        removeCheck.addEventListener('change', syncRemoveState);
        syncRemoveState();
    }

    fileInput?.addEventListener('change', () => {
        const file = fileInput.files?.[0];
        if (! file) {
            return;
        }

        // Picking a new file cancels the delete intent.
        if (removeCheck) {
            removeCheck.checked = false;
            if (uploadSection) {
                uploadSection.style.opacity = '';
                uploadSection.style.pointerEvents = '';
            }
        }

        if (filename) {
            filename.textContent = file.name;
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

            // Picking a preset cancels delete intent.
            if (removeCheck) {
                removeCheck.checked = false;
                if (uploadSection) {
                    uploadSection.style.opacity = '';
                    uploadSection.style.pointerEvents = '';
                }
            }

            if (fileInput) {
                fileInput.value = '';
            }
            if (filename) {
                filename.textContent = 'Belum ada file dipilih';
            }
            const img = radio.closest('label')?.querySelector('img');
            if (img?.src) {
                showPreview(img.src);
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', initKasirProductEdit);
