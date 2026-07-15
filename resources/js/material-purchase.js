import { parseRupiahInput } from './rupiah';

const UNIT_TO_BASE = {
    kg: { family: 'mass', toBase: 1 },
    gr: { family: 'mass', toBase: 0.001 },
    liter: { family: 'volume', toBase: 1 },
    ml: { family: 'volume', toBase: 0.001 },
    pcs: { family: 'count', toBase: 1 },
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

function formatRp(value) {
    const number = Math.round(Number(value) || 0);
    return 'Rp ' + number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
}

function resolveStockUnitLabel(box) {
    if (box.dataset.stockUnitLabel) {
        return box.dataset.stockUnitLabel;
    }

    const form = box.closest('form');
    if (! form) {
        return 'satuan stok';
    }

    const selected = form.querySelector('[data-unit-picker] input[type="radio"]:checked')?.value;
    if (! selected) {
        return 'satuan stok';
    }

    if (selected === 'other') {
        const custom = (form.querySelector('[data-unit-custom] input')?.value || '').trim();
        return custom || 'satuan stok';
    }

    return UNIT_LABEL[selected] || selected;
}

function updatePackStockUnitTexts(box, stockUnit) {
    box.querySelectorAll('[data-pack-stock-unit-text]').forEach((el) => {
        el.textContent = stockUnit;
    });
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
    const optional = box.dataset.optional === '1';
    const requireFields = ! optional;
    const stockUnit = resolveStockUnitLabel(box);
    const directBox = box.querySelector('[data-purchase-direct]');
    const packBox = box.querySelector('[data-purchase-pack]');
    const portionBox = box.querySelector('[data-purchase-portion]');
    const preview = box.querySelector('[data-purchase-preview-text]');
    const packagePreset = box.querySelector('[data-pack-preset]');
    const customWrap = box.querySelector('[data-pack-custom-wrap]');
    const customInput = box.querySelector('[data-pack-custom]');

    updatePackStockUnitTexts(box, stockUnit);

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

    const directCostHidden = directBox?.querySelector('input[type="hidden"][name="direct_total"]')
        || directBox?.querySelector('input[type="hidden"][name="unit_cost"]');
    const packCostHidden = packBox?.querySelector('input[type="hidden"][name="package_cost"]');
    const purchaseCostHidden = portionBox?.querySelector('input[type="hidden"][name="purchase_cost"]');
    const directCostVisible = directBox?.querySelector('.rupiah-input');
    const packCostVisible = packBox?.querySelector('.rupiah-input');
    const purchaseCostVisible = portionBox?.querySelector('.rupiah-input');

    setFieldEnabled(directQty, mode === 'direct', requireFields);
    setFieldEnabled(directCostHidden, mode === 'direct', requireFields);
    setFieldEnabled(directCostVisible, mode === 'direct', requireFields);

    setFieldEnabled(packQty, mode === 'pack', requireFields);
    setFieldEnabled(packUnits, mode === 'pack', requireFields);
    setFieldEnabled(packagePreset, mode === 'pack', false);
    setFieldEnabled(packCostHidden, mode === 'pack', requireFields);
    setFieldEnabled(packCostVisible, mode === 'pack', requireFields);

    setFieldEnabled(portionSize, mode === 'portion', requireFields);
    setFieldEnabled(portionUnit, mode === 'portion', false);
    setFieldEnabled(purchaseQty, mode === 'portion', requireFields);
    setFieldEnabled(purchaseUnit, mode === 'portion', false);
    setFieldEnabled(purchaseCostHidden, mode === 'portion', requireFields);
    setFieldEnabled(purchaseCostVisible, mode === 'portion', requireFields);

    const isOther = (packagePreset?.value || '') === 'other';
    if (customWrap) customWrap.classList.toggle('hidden', ! isOther || mode !== 'pack');
    setFieldEnabled(customInput, mode === 'pack' && isOther, requireFields);

    if (! preview) return;

    if (mode === 'direct') {
        const qty = parseFloat(directQty?.value || '0') || 0;
        const total = readRupiahField(directBox, 'direct_total');
        if (qty > 0) {
            const unitCost = total / qty;
            preview.textContent = `Stok masuk ${formatNumber(qty)} ${stockUnit} · harga ${formatRp(unitCost)} / ${stockUnit} (dari ${formatRp(total)} total).`;
        } else {
            preview.textContent = `Isi jumlah & harga total — harga per ${stockUnit} dihitung otomatis.`;
        }
        return;
    }

    if (mode === 'pack') {
        const packages = parseFloat(packQty?.value || '0') || 0;
        const units = parseFloat(packUnits?.value || '0') || 0;
        const packageCost = readRupiahField(packBox, 'package_cost');
        let packageLabel = packagePreset?.value || 'botol';
        if (packageLabel === 'other') {
            packageLabel = (customInput?.value || '').trim() || 'wadah';
        }

        if (packages > 0 && units > 0) {
            const totalQty = packages * units;
            const unitCost = packageCost / units;
            preview.textContent = `${formatNumber(packages)} ${packageLabel} × ${formatNumber(units)} ${stockUnit} = stok ${formatNumber(totalQty)} ${stockUnit} · harga ${formatRp(unitCost)} / ${stockUnit} (dari ${formatRp(packageCost)}/${packageLabel}).`;
        } else {
            preview.textContent = `Isi jumlah wadah, isi per wadah (dalam ${stockUnit}), dan harga per wadah.`;
        }
        return;
    }

    const size = parseFloat(portionSize?.value || '0') || 0;
    const buyQty = parseFloat(purchaseQty?.value || '0') || 0;
    const pUnit = portionUnit?.value || 'gr';
    const bUnit = purchaseUnit?.value || 'kg';
    const buyCost = readRupiahField(portionBox, 'purchase_cost');

    if (bUnit === 'pcs') {
        if (buyQty > 0) {
            const unitCost = buyCost / buyQty;
            preview.textContent = `Beli ${formatNumber(buyQty)} pcs = stok ${formatNumber(buyQty)} · 1 stok ≈ ${formatNumber(size)} ${UNIT_LABEL[pUnit] || pUnit} · harga ${formatRp(unitCost)} / stok (dari ${formatRp(buyCost)}).`;
        } else {
            preview.textContent = 'Isi jumlah pcs dibeli dan harga total — harga per stok dihitung otomatis.';
        }
        return;
    }

    const converted = convertUnits(buyQty, bUnit, pUnit);

    if (size > 0 && buyQty > 0 && converted !== null) {
        const totalQty = converted / size;
        const unitCost = totalQty > 0 ? buyCost / totalQty : 0;
        preview.textContent = `1 stok = ${formatNumber(size)} ${UNIT_LABEL[pUnit] || pUnit} · beli ${formatNumber(buyQty)} ${UNIT_LABEL[bUnit] || bUnit} = stok ${formatNumber(totalQty)} · harga ${formatRp(unitCost)} / stok (dari ${formatRp(buyCost)}).`;
    } else if (converted === null) {
        preview.textContent = 'Satuan tidak cocok. Gram/kg hanya dengan gram/kg; ml/liter hanya dengan ml/liter. Atau pilih pcs.';
    } else {
        preview.textContent = 'Isi 1 satuan stok, jumlah dibeli, dan harga total — harga per stok dihitung otomatis.';
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

        const form = box.closest('form');
        form?.querySelectorAll('[data-unit-picker] input, [data-unit-custom] input').forEach((el) => {
            el.addEventListener('input', sync);
            el.addEventListener('change', sync);
        });

        sync();
    });
}

document.addEventListener('DOMContentLoaded', () => initMaterialPurchase());

export { initMaterialPurchase };
