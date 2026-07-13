function initMenuUnitSelects(root = document) {
    root.querySelectorAll('[data-menu-unit-select]').forEach((wrap) => {
        if (wrap.dataset.menuUnitBound === '1') {
            return;
        }

        wrap.dataset.menuUnitBound = '1';

        const select = wrap.querySelector('[data-menu-unit-preset]');
        const customBox = wrap.querySelector('[data-menu-unit-custom]');
        const customInput = customBox?.querySelector('input');

        const sync = () => {
            const isOther = (select?.value || '') === 'other';

            if (customBox) {
                customBox.classList.toggle('hidden', !isOther);
            }

            if (customInput) {
                customInput.disabled = !isOther;
                customInput.required = isOther;

                if (isOther) {
                    customInput.focus();
                } else {
                    customInput.value = '';
                }
            }
        };

        select?.addEventListener('change', sync);
        sync();
    });
}

document.addEventListener('DOMContentLoaded', () => initMenuUnitSelects());

export { initMenuUnitSelects };
