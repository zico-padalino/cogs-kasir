import { parseRupiahInput } from './rupiah';

function formatRupiahId(amount, decimals = 0) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    }).format(amount).replace(/\s/g, ' ');
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
    const persen = selling > 0 ? (untung / selling) * 100 : 0;

    const amountEl = profitBox.querySelector('[data-pricing-amount]');
    const percentEl = profitBox.querySelector('[data-pricing-percent]');

    if (! amountEl || ! percentEl) {
        return;
    }

    amountEl.textContent = formatRupiahId(untung, 0);
    amountEl.classList.remove('text-green-700', 'text-red-600');
    amountEl.classList.add(untung >= 0 ? 'text-green-700' : 'text-red-600');

    const persenText = persen.toLocaleString('id-ID', {
        minimumFractionDigits: 1,
        maximumFractionDigits: 1,
    });
    percentEl.textContent = `(${persenText}%)`;

    profitBox.classList.toggle('is-empty', selling <= 0);
}

function initMenuPricing(root = document) {
    root.querySelectorAll('[data-pricing-form]').forEach((form) => {
        if (form.dataset.pricingBound === '1') {
            return;
        }

        form.dataset.pricingBound = '1';

        const input = form.querySelector('.rupiah-input');

        if (input) {
            input.addEventListener('input', () => updatePricingProfit(form));
            input.addEventListener('blur', () => updatePricingProfit(form));
        }

        updatePricingProfit(form);
    });
}

document.addEventListener('DOMContentLoaded', () => initMenuPricing());

export { initMenuPricing, updatePricingProfit };
