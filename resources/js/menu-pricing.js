import { parseRupiahInput, formatRupiahInput } from './rupiah';

function formatRupiahId(amount, decimals = 0) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(amount).replace(/\s/g, ' ');
}

function clampMarginInput(percent) {
    if (! Number.isFinite(percent)) {
        return NaN;
    }

    return Math.min(99.9, Math.max(0, percent));
}

function priceFromMargin(modal, percent) {
    const p = clampMarginInput(percent) / 100;

    if (modal <= 0 || ! Number.isFinite(p) || p >= 1) {
        return 0;
    }

    return Math.round(modal / (1 - p));
}

function marginFromPrice(modal, selling) {
    if (selling <= 0) {
        return NaN;
    }

    return ((selling - modal) / selling) * 100;
}

function formatPercentValue(percent) {
    if (! Number.isFinite(percent)) {
        return '';
    }

    const rounded = Math.round(percent * 10) / 10;

    return String(rounded);
}

function setSellingPrice(form, amount) {
    const visible = form.querySelector('.rupiah-input');
    const hidden = form.querySelector('input[data-rupiah-target="selling_price"]');
    const value = Math.max(0, Math.round(amount || 0));

    if (visible) {
        visible.value = value > 0 ? formatRupiahInput(value, 0) : '';
    }

    if (hidden) {
        hidden.value = value > 0 ? String(value) : '';
    }
}

function setMarginInput(form, percent) {
    const marginInput = form.querySelector('[data-pricing-margin]');

    if (! marginInput || marginInput.disabled) {
        return;
    }

    marginInput.value = formatPercentValue(percent);
}

function setMode(form, mode) {
    const modeInput = form.querySelector('[data-pricing-mode]');

    if (modeInput) {
        modeInput.value = mode;
    }
}

function updatePricingProfit(form) {
    const modal = parseFloat(form.dataset.modal || '0');
    const profitBox = form.querySelector('[data-pricing-profit]');

    if (! profitBox || modal <= 0) {
        return;
    }

    const visibleInput = form.querySelector('.rupiah-input');
    const selling = parseRupiahInput(visibleInput?.value ?? '');
    const untung = selling - modal;
    const persen = marginFromPrice(modal, selling);

    const amountEl = profitBox.querySelector('[data-pricing-amount]');
    const percentEl = profitBox.querySelector('[data-pricing-percent]');

    if (! amountEl || ! percentEl) {
        return;
    }

    amountEl.textContent = formatRupiahId(untung, 0);
    amountEl.classList.remove('text-green-700', 'text-red-600');
    amountEl.classList.add(untung >= 0 ? 'text-green-700' : 'text-red-600');

    if (Number.isFinite(persen)) {
        const persenText = persen.toLocaleString('id-ID', {
            minimumFractionDigits: 1,
            maximumFractionDigits: 1,
        });
        percentEl.textContent = `(${persenText}%)`;
    } else {
        percentEl.textContent = '(—%)';
    }

    profitBox.classList.toggle('is-empty', selling <= 0);
}

function syncFromPrice(form) {
    if (form.dataset.pricingSyncing === '1') {
        return;
    }

    form.dataset.pricingSyncing = '1';

    try {
        const modal = parseFloat(form.dataset.modal || '0');
        const visibleInput = form.querySelector('.rupiah-input');
        const selling = parseRupiahInput(visibleInput?.value ?? '');

        setMode(form, 'price');

        if (modal > 0) {
            setMarginInput(form, marginFromPrice(modal, selling));
        }

        updatePricingProfit(form);
    } finally {
        form.dataset.pricingSyncing = '0';
    }
}

function syncFromMargin(form) {
    if (form.dataset.pricingSyncing === '1') {
        return;
    }

    form.dataset.pricingSyncing = '1';

    try {
        const modal = parseFloat(form.dataset.modal || '0');
        const marginInput = form.querySelector('[data-pricing-margin]');

        if (! marginInput || marginInput.disabled || modal <= 0) {
            return;
        }

        const raw = String(marginInput.value || '').trim().replace(',', '.');
        setMode(form, 'percent');

        if (raw === '') {
            setSellingPrice(form, 0);
            updatePricingProfit(form);

            return;
        }

        const percent = clampMarginInput(parseFloat(raw));

        if (! Number.isFinite(percent)) {
            return;
        }

        setSellingPrice(form, priceFromMargin(modal, percent));
        updatePricingProfit(form);
    } finally {
        form.dataset.pricingSyncing = '0';
    }
}

function initMenuPricing(root = document) {
    root.querySelectorAll('[data-pricing-form]').forEach((form) => {
        if (form.dataset.pricingBound === '1') {
            return;
        }

        form.dataset.pricingBound = '1';

        const priceInput = form.querySelector('.rupiah-input');
        const marginInput = form.querySelector('[data-pricing-margin]');

        if (priceInput) {
            priceInput.addEventListener('input', () => syncFromPrice(form));
            priceInput.addEventListener('change', () => syncFromPrice(form));
            priceInput.addEventListener('blur', () => syncFromPrice(form));
            priceInput.addEventListener('keyup', () => syncFromPrice(form));
        }

        if (marginInput) {
            marginInput.addEventListener('input', () => syncFromMargin(form));
            marginInput.addEventListener('change', () => syncFromMargin(form));
            marginInput.addEventListener('keyup', () => syncFromMargin(form));
            marginInput.addEventListener('blur', () => syncFromMargin(form));
        }

        // Isi persen awal dari harga yang sudah tersimpan
        if (parseFloat(form.dataset.modal || '0') > 0) {
            syncFromPrice(form);
        } else {
            updatePricingProfit(form);
        }
    });
}

document.addEventListener('DOMContentLoaded', () => initMenuPricing());

export { initMenuPricing, updatePricingProfit };
