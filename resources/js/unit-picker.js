function initUnitPickers(root = document) {
    root.querySelectorAll('[data-unit-picker]').forEach((picker) => {
        if (picker.dataset.unitPickerBound === '1') {
            return;
        }

        picker.dataset.unitPickerBound = '1';

        const customBox = picker.querySelector('[data-unit-custom]');
        const customInput = customBox?.querySelector('input');

        const sync = () => {
            const selected = picker.querySelector('input[type="radio"]:checked')?.value || 'kg';
            const isOther = selected === 'other';

            if (customBox) {
                customBox.classList.toggle('hidden', !isOther);
            }

            if (customInput) {
                customInput.disabled = !isOther;
                customInput.required = isOther;

                if (isOther) {
                    customInput.focus();
                }
            }
        };

        picker.querySelectorAll('input[type="radio"]').forEach((radio) => {
            radio.addEventListener('change', sync);
        });

        sync();
    });
}

document.addEventListener('DOMContentLoaded', () => initUnitPickers());

export { initUnitPickers };
