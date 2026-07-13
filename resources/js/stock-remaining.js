const UNIT_TO_BASE = {
    kg: { family: 'mass', toBase: 1 },
    gr: { family: 'mass', toBase: 0.001 },
    liter: { family: 'volume', toBase: 1 },
    ml: { family: 'volume', toBase: 0.001 },
    pcs: { family: 'count', toBase: 1 },
    buah: { family: 'count', toBase: 1 },
    bungkus: { family: 'count', toBase: 1 },
    kaleng: { family: 'count', toBase: 1 },
    ikat: { family: 'count', toBase: 1 },
};

const UNIT_LABEL = {
    kg: 'kg',
    gr: 'gram',
    liter: 'liter',
    ml: 'ml',
    pcs: 'pcs',
    buah: 'buah',
    bungkus: 'bungkus',
    kaleng: 'kaleng',
    ikat: 'ikat',
};

function formatNumber(value, decimals = null) {
    const number = Number(value) || 0;
    const options = {
        minimumFractionDigits: 0,
        maximumFractionDigits: decimals === null || decimals === undefined ? 2 : decimals,
    };
    if (decimals === 0) {
        options.maximumFractionDigits = 0;
    }
    return new Intl.NumberFormat('id-ID', options).format(number);
}

function convertUnits(quantity, fromUnit, toUnit) {
    const from = UNIT_TO_BASE[fromUnit];
    const to = UNIT_TO_BASE[toUnit];

    if (! from || ! to || from.family !== to.family) {
        return null;
    }

    if (from.family === 'count' && fromUnit !== toUnit) {
        return fromUnit === toUnit ? quantity : null;
    }

    return (quantity * from.toBase) / to.toBase;
}

function setEnabled(el, enabled, required = false) {
    if (! el) return;
    el.disabled = ! enabled;
    if (el.type !== 'hidden' && el.tagName !== 'SELECT') {
        el.required = enabled && required;
    } else if (el.tagName !== 'SELECT') {
        el.required = enabled && required;
    }
}

function syncStockRemaining(box) {
    const stockUnit = (box.dataset.stockUnit || 'kg').toLowerCase();
    const maxQty = box.dataset.maxQty !== undefined ? parseFloat(box.dataset.maxQty) : null;
    const modeInput = box.querySelector('input[data-adjust-mode]:checked')
        || box.querySelector('input[data-adjust-mode][type="hidden"]')
        || box.querySelector('input[name="adjust_mode"]:checked');
    const mode = modeInput?.value || 'direct';

    const directBox = box.querySelector('[data-adjust-direct]');
    const portionBox = box.querySelector('[data-adjust-portion]');
    const preview = box.querySelector('[data-adjust-preview]');

    if (directBox) directBox.classList.toggle('hidden', mode === 'portion');
    if (portionBox) portionBox.classList.toggle('hidden', mode !== 'portion');

    const qty = box.querySelector('[data-adjust-qty]');
    const unit = box.querySelector('[data-adjust-unit]');
    const portionSize = box.querySelector('[data-adjust-portion-size]');
    const portionUnit = box.querySelector('[data-adjust-portion-unit]');
    const physicalQty = box.querySelector('[data-adjust-physical-qty]');
    const physicalUnit = box.querySelector('[data-adjust-physical-unit]');

    setEnabled(qty, mode === 'direct', true);
    setEnabled(unit, mode === 'direct', false);
    setEnabled(portionSize, mode === 'portion', true);
    setEnabled(portionUnit, mode === 'portion', false);
    setEnabled(physicalQty, mode === 'portion', true);
    setEnabled(physicalUnit, mode === 'portion', false);

    if (! preview) return;

    let stockQty = null;
    let detail = '';

    if (mode === 'portion') {
        const size = parseFloat(portionSize?.value || '0') || 0;
        const buy = parseFloat(physicalQty?.value || '0');
        const pUnit = (portionUnit?.value || 'gr').toLowerCase();
        const bUnit = (physicalUnit?.value || 'gr').toLowerCase();

        if (!(size > 0) || Number.isNaN(buy) || buy < 0) {
            preview.textContent = 'Isi 1 satuan stok dan sisa fisik (gram/ml).';
            return;
        }

        if (buy === 0) {
            stockQty = 0;
            detail = `sisa fisik 0 → stok 0 ${UNIT_LABEL[stockUnit] || stockUnit}`;
        } else {
            const converted = convertUnits(buy, bUnit, pUnit);
            if (converted === null) {
                preview.textContent = 'Satuan berat/volume harus sejenis.';
                return;
            }
            stockQty = converted / size;
            detail = `sisa ${formatNumber(buy)} ${UNIT_LABEL[bUnit] || bUnit} · 1 stok = ${formatNumber(size)} ${UNIT_LABEL[pUnit] || pUnit}`;
        }
    } else {
        const amount = parseFloat(qty?.value || '0');
        const fromUnit = (unit?.value || stockUnit).toLowerCase();

        if (Number.isNaN(amount) || amount < 0) {
            preview.textContent = 'Isi stok sisa aktual.';
            return;
        }

        if (amount === 0) {
            stockQty = 0;
            detail = 'stok sisa 0';
        } else {
            const converted = convertUnits(amount, fromUnit, stockUnit);
            if (converted === null) {
                preview.textContent = `Isi dalam satuan yang cocok dengan stok (${UNIT_LABEL[stockUnit] || stockUnit}).`;
                return;
            }
            stockQty = converted;
            detail = fromUnit === stockUnit
                ? ''
                : `${formatNumber(amount)} ${UNIT_LABEL[fromUnit] || fromUnit} =`;
        }
    }

    if (stockQty === null || Number.isNaN(stockQty)) {
        preview.textContent = 'Isi stok sisa aktual.';
        return;
    }

    if (maxQty !== null && ! Number.isNaN(maxQty) && stockQty > maxQty + 1e-9) {
        preview.textContent = `Hasil ${formatNumber(stockQty)} ${UNIT_LABEL[stockUnit] || stockUnit} melebihi maks. ${formatNumber(maxQty)} ${UNIT_LABEL[stockUnit] || stockUnit}.`;
        return;
    }

    preview.textContent = detail
        ? `${detail} → disimpan ${formatNumber(stockQty)} ${UNIT_LABEL[stockUnit] || stockUnit}.`
        : `Akan disimpan ${formatNumber(stockQty)} ${UNIT_LABEL[stockUnit] || stockUnit}.`;
}

function initStockRemaining(root = document) {
    root.querySelectorAll('[data-stock-remaining]').forEach((box) => {
        if (box.dataset.stockBound === '1') return;
        box.dataset.stockBound = '1';

        const sync = () => syncStockRemaining(box);
        box.querySelectorAll('input, select').forEach((el) => {
            el.addEventListener('input', sync);
            el.addEventListener('change', sync);
        });
        sync();
    });
}

document.addEventListener('DOMContentLoaded', () => initStockRemaining());

export { initStockRemaining };
