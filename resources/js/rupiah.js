function parseRupiahInput(value) {
    if (value === null || value === undefined || value === '') {
        return 0;
    }

    const string = String(value).trim();

    if (/^-?\d+(\.\d+)?$/.test(string)) {
        return parseFloat(string);
    }

    const cleaned = string
        .replace(/[^\d,.-]/g, '')
        .replace(/\./g, '')
        .replace(',', '.');

    const parsed = parseFloat(cleaned);

    return Number.isFinite(parsed) ? parsed : 0;
}

function formatRupiahInput(value, decimals = 0) {
    const number = parseRupiahInput(value);

    if (number === 0 && (value === '' || value === null || value === undefined)) {
        return '';
    }

    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(number);
}

function syncRupiahInput(visibleInput) {
    const hiddenName = visibleInput.dataset.rupiahHidden;
    const decimals = parseInt(visibleInput.dataset.rupiahDecimals || '0', 10);
    const min = parseFloat(visibleInput.dataset.rupiahMin || '0');
    const hiddenInput = document.querySelector(`input[data-rupiah-target="${hiddenName}"]`);

    if (!hiddenInput) {
        return;
    }

    let numeric = parseRupiahInput(visibleInput.value);

    if (numeric < min) {
        numeric = min;
    }

    hiddenInput.value = numeric;
    visibleInput.value = formatRupiahInput(numeric, decimals);
}

function initRupiahInputs(root = document) {
    root.querySelectorAll('.rupiah-input').forEach((input) => {
        if (input.dataset.rupiahBound === '1') {
            return;
        }

        input.dataset.rupiahBound = '1';
        syncRupiahInput(input);

        input.addEventListener('input', () => {
            const hiddenName = input.dataset.rupiahHidden;
            const hiddenInput = document.querySelector(`input[data-rupiah-target="${hiddenName}"]`);

            if (!hiddenInput) {
                return;
            }

            hiddenInput.value = parseRupiahInput(input.value);
            input.value = formatRupiahInput(hiddenInput.value, parseInt(input.dataset.rupiahDecimals || '0', 10));
        });

        input.addEventListener('blur', () => syncRupiahInput(input));
    });

    root.querySelectorAll('form').forEach((form) => {
        if (form.dataset.rupiahFormBound === '1') {
            return;
        }

        form.dataset.rupiahFormBound = '1';
        form.addEventListener('submit', () => {
            form.querySelectorAll('.rupiah-input').forEach((input) => syncRupiahInput(input));
        });
    });
}

document.addEventListener('DOMContentLoaded', () => initRupiahInputs());

export { initRupiahInputs, parseRupiahInput, formatRupiahInput };
