import { parseRupiahInput } from './rupiah';

const UNIT_TO_BASE = {
    kg: { family: 'mass', toBase: 1 },
    gr: { family: 'mass', toBase: 0.001 },
    liter: { family: 'volume', toBase: 1 },
    ml: { family: 'volume', toBase: 0.001 },
};

const UNIT_LABEL = {
    kg: 'kg',
    gr: 'gram',
    liter: 'liter',
    ml: 'ml',
};

function formatNumber(value, decimals = 2) {
    const number = Number(value) || 0;
    return new Intl.NumberFormat('id-ID', {
        minimumFractionDigits: 0,
        maximumFractionDigits: decimals,
    }).format(number);
}

function formatRp(value) {
    const number = Math.round(Number(value) || 0);
    return 'Rp ' + number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function readRupiahField(section, name) {
    const hidden = section?.querySelector(`input[type="hidden"][name="${name}"]`);
    if (hidden) {
        return parseRupiahInput(hidden.value);
    }

    const visible = section?.querySelector(`input[name="${name}"]`);
    return parseRupiahInput(visible?.value || 0);
}

function setFieldEnabled(el, enabled, required = false) {
    if (! el) return;
    el.disabled = ! enabled;
    if (el.type !== 'hidden') {
        el.required = enabled && required;
    }
}

function convertUnits(quantity, fromUnit, toUnit) {
    const from = UNIT_TO_BASE[fromUnit];
    const to = UNIT_TO_BASE[toUnit];

    if (! from || ! to || from.family !== to.family) {
        return null;
    }

    return (quantity * from.toBase) / to.toBase;
}

function syncPurchaseBox(box) {
    const mode = box.querySelector('input[data-purchase-mode]:checked')?.value || 'direct';
    const directBox = box.querySelector('[data-purchase-direct]');
    const packBox = box.querySelector('[data-purchase-pack]');
    const portionBox = box.querySelector('[data-purchase-portion]');
    const preview = box.querySelector('[data-purchase-preview-text]');
    const packagePreset = box.querySelector('[data-pack-preset]');
    const customWrap = box.querySelector('[data-pack-custom-wrap]');
    const customInput = box.querySelector('[data-pack-custom]');

    if (directBox) directBox.classList.toggle('hidden', mode !== 'direct');
    if (packBox) packBox.classList.toggle('hidden', mode !== 'pack');
    if (portionBox) portionBox.classList.toggle('hidden', mode !== 'portion');

    const directQty = box.querySelector('[data-direct-qty]');
    const packQty = box.querySelector('[data-pack-qty]');
    const packUnits = box.querySelector('[data-pack-units]');
    const portionSize = box.querySelector('[data-portion-size]');
    const portionUnit = box.querySelector('[data-portion-unit]');
    const purchaseQty = box.querySelector('[data-purchase-qty]');
    const purchaseUnit = box.querySelector('[data-purchase-unit]');

    const directCostHidden = directBox?.querySelector('input[type="hidden"][name="unit_cost"]');
    const packCostHidden = packBox?.querySelector('input[type="hidden"][name="package_cost"]');
    const purchaseCostHidden = portionBox?.querySelector('input[type="hidden"][name="purchase_cost"]');
    const directCostVisible = directBox?.querySelector('.rupiah-input');
    const packCostVisible = packBox?.querySelector('.rupiah-input');
    const purchaseCostVisible = portionBox?.querySelector('.rupiah-input');

    setFieldEnabled(directQty, mode === 'direct', true);
    setFieldEnabled(directCostHidden, mode === 'direct', true);
    setFieldEnabled(directCostVisible, mode === 'direct', true);

    setFieldEnabled(packQty, mode === 'pack', true);
    setFieldEnabled(packUnits, mode === 'pack', true);
    setFieldEnabled(packagePreset, mode === 'pack', false);
    setFieldEnabled(packCostHidden, mode === 'pack', true);
    setFieldEnabled(packCostVisible, mode === 'pack', true);

    setFieldEnabled(portionSize, mode === 'portion', true);
    setFieldEnabled(portionUnit, mode === 'portion', false);
    setFieldEnabled(purchaseQty, mode === 'portion', true);
    setFieldEnabled(purchaseUnit, mode === 'portion', false);
    setFieldEnabled(purchaseCostHidden, mode === 'portion', true);
    setFieldEnabled(purchaseCostVisible, mode === 'portion', true);

    const isOther = (packagePreset?.value || '') === 'other';
    if (customWrap) customWrap.classList.toggle('hidden', ! isOther || mode !== 'pack');
    setFieldEnabled(customInput, mode === 'pack' && isOther, true);

    if (! preview) return;

    if (mode === 'direct') {
        const qty = parseFloat(directQty?.value || '0') || 0;
        const cost = readRupiahField(directBox, 'unit_cost');
        preview.textContent = qty > 0
            ? `Stok masuk ${formatNumber(qty)} · harga ${formatRp(cost)} / satuan stok.`
            : 'Isi jumlah & harga per satuan stok.';
        return;
    }

    if (mode === 'pack') {
        const packages = parseFloat(packQty?.value || '0') || 0;
        const units = parseFloat(packUnits?.value || '0') || 0;
        const packageCost = readRupiahField(packBox, 'package_cost');
        let packageLabel = packagePreset?.value || 'dus';
        if (packageLabel === 'other') {
            packageLabel = (customInput?.value || '').trim() || 'kemasan';
        }

        if (packages > 0 && units > 0) {
            const totalQty = packages * units;
            const unitCost = packageCost / units;
            preview.textContent = `${formatNumber(packages)} ${packageLabel} × ${formatNumber(units)} = stok ${formatNumber(totalQty)} · harga ${formatRp(unitCost)} / satuan stok (dari ${formatRp(packageCost)}/${packageLabel}).`;
        } else {
            preview.textContent = 'Isi jumlah kemasan, isi per kemasan, dan harga per kemasan.';
        }
        return;
    }

    const size = parseFloat(portionSize?.value || '0') || 0;
    const buyQty = parseFloat(purchaseQty?.value || '0') || 0;
    const pUnit = portionUnit?.value || 'gr';
    const bUnit = purchaseUnit?.value || 'kg';
    const buyCost = readRupiahField(portionBox, 'purchase_cost');
    const converted = convertUnits(buyQty, bUnit, pUnit);

    if (size > 0 && buyQty > 0 && converted !== null) {
        const totalQty = converted / size;
        const unitCost = totalQty > 0 ? buyCost / totalQty : 0;
        preview.textContent = `1 stok = ${formatNumber(size)} ${UNIT_LABEL[pUnit] || pUnit} · beli ${formatNumber(buyQty)} ${UNIT_LABEL[bUnit] || bUnit} = stok ${formatNumber(totalQty)} · harga ${formatRp(unitCost)} / stok.`;
    } else if (converted === null) {
        preview.textContent = 'Satuan tidak cocok. Gram/kg hanya dengan gram/kg; ml/liter hanya dengan ml/liter.';
    } else {
        preview.textContent = 'Isi 1 satuan stok berapa gram/ml, jumlah dibeli, dan harga total.';
    }
}

function initMaterialPurchase(root = document) {
    root.querySelectorAll('[data-material-purchase]').forEach((box) => {
        if (box.dataset.purchaseBound === '1') return;
        box.dataset.purchaseBound = '1';

        const sync = () => syncPurchaseBox(box);

        box.querySelectorAll('input, select').forEach((el) => {
            el.addEventListener('input', sync);
            el.addEventListener('change', sync);
        });

        sync();
    });
}

document.addEventListener('DOMContentLoaded', () => initMaterialPurchase());

export { initMaterialPurchase };
