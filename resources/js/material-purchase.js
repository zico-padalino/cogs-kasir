import { parseRupiahInput } from './rupiah';

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

function syncPurchaseBox(box) {
    const mode = box.querySelector('input[data-purchase-mode]:checked')?.value || 'direct';
    const directBox = box.querySelector('[data-purchase-direct]');
    const packBox = box.querySelector('[data-purchase-pack]');
    const preview = box.querySelector('[data-purchase-preview-text]');
    const packagePreset = box.querySelector('[data-pack-preset]');
    const customWrap = box.querySelector('[data-pack-custom-wrap]');
    const customInput = box.querySelector('[data-pack-custom]');

    if (directBox) directBox.classList.toggle('hidden', mode !== 'direct');
    if (packBox) packBox.classList.toggle('hidden', mode !== 'pack');

    const directQty = box.querySelector('[data-direct-qty]');
    const packQty = box.querySelector('[data-pack-qty]');
    const packUnits = box.querySelector('[data-pack-units]');
    const directCostHidden = directBox?.querySelector('input[type="hidden"][name="unit_cost"]');
    const packCostHidden = packBox?.querySelector('input[type="hidden"][name="package_cost"]');
    const directCostVisible = directBox?.querySelector('.rupiah-input');
    const packCostVisible = packBox?.querySelector('.rupiah-input');

    setFieldEnabled(directQty, mode === 'direct', true);
    setFieldEnabled(directCostHidden, mode === 'direct', true);
    setFieldEnabled(directCostVisible, mode === 'direct', true);

    setFieldEnabled(packQty, mode === 'pack', true);
    setFieldEnabled(packUnits, mode === 'pack', true);
    setFieldEnabled(packagePreset, mode === 'pack', false);
    setFieldEnabled(packCostHidden, mode === 'pack', true);
    setFieldEnabled(packCostVisible, mode === 'pack', true);

    const isOther = (packagePreset?.value || '') === 'other';
    if (customWrap) customWrap.classList.toggle('hidden', ! isOther || mode !== 'pack');
    setFieldEnabled(customInput, mode === 'pack' && isOther, true);

    if (! preview) return;

    if (mode === 'direct') {
        const qty = parseFloat(directQty?.value || '0') || 0;
        const cost = readRupiahField(directBox, 'unit_cost');
        if (qty > 0) {
            preview.textContent = `Stok masuk ${formatNumber(qty)} · harga ${formatRp(cost)} / satuan stok.`;
        } else {
            preview.textContent = 'Isi jumlah & harga per satuan stok.';
        }
        return;
    }

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
