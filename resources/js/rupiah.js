function parseRupiahInput(value) {
    if (value === null || value === undefined || value === '') {
        return 0;
    }

    const string = String(value).trim();

    if (/^\d+$/.test(string)) {
        return parseInt(string, 10);
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

function formatRupiahInputLive(input, decimals = 0) {
    if (! input) {
        return 0;
    }

    const raw = input.value;

    if (raw === '') {
        return 0;
    }

    const selectionStart = input.selectionStart ?? raw.length;
    const digitsBeforeCursor = raw.slice(0, selectionStart).replace(/\D/g, '').length;
    const numeric = parseRupiahInput(raw);

    input.value = formatRupiahInput(numeric, decimals);

    let digitsSeen = 0;
    let cursor = input.value.length;

    for (let i = 0; i < input.value.length; i++) {
        if (/\d/.test(input.value[i])) {
            digitsSeen++;

            if (digitsSeen >= digitsBeforeCursor) {
                cursor = i + 1;
                break;
            }
        }
    }

    input.setSelectionRange(cursor, cursor);

    return numeric;
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

export { initRupiahInputs, parseRupiahInput, formatRupiahInput, formatRupiahInputLive };
