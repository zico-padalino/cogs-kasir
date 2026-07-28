function initTime24Pickers(root = document) {
    root.querySelectorAll('[data-time-24-picker]').forEach((picker) => {
        if (picker.dataset.time24Bound === '1') {
            return;
        }

        picker.dataset.time24Bound = '1';

        const optional = picker.hasAttribute('data-time-24-optional');
        const hidden = picker.querySelector('[data-time-24-value]');
        const hourSelect = picker.querySelector('[data-time-24-hour]');
        const minuteSelect = picker.querySelector('[data-time-24-minute]');

        if (!hidden || !hourSelect || !minuteSelect) {
            return;
        }

        const sync = () => {
            const hour = hourSelect.value;
            const minute = minuteSelect.value;

            if (optional && (hour === '' || minute === '')) {
                hidden.value = '';
                return;
            }

            if (hour === '' || minute === '') {
                return;
            }

            hidden.value = `${hour}:${minute}`;
        };

        hourSelect.addEventListener('change', () => {
            if (optional && hourSelect.value !== '' && minuteSelect.value === '') {
                minuteSelect.value = '00';
            }
            sync();
        });

        minuteSelect.addEventListener('change', () => {
            if (optional && minuteSelect.value !== '' && hourSelect.value === '') {
                hourSelect.value = '00';
            }
            sync();
        });

        sync();
    });
}

document.addEventListener('DOMContentLoaded', () => initTime24Pickers());

export { initTime24Pickers };
