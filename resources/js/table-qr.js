import QRCode from 'qrcode';

export async function renderTableQr(element, url, size = 160, options = {}) {
    const qrOptions = {
        width: size,
        margin: options.margin ?? 1,
        errorCorrectionLevel: options.errorCorrectionLevel ?? 'M',
        color: {
            dark: options.dark ?? '#0f172a',
            light: options.light ?? '#ffffff',
        },
    };

    if (element.tagName === 'CANVAS') {
        await QRCode.toCanvas(element, url, qrOptions);
        return;
    }

    if (element.tagName === 'IMG') {
        element.src = await QRCode.toDataURL(url, qrOptions);
    }
}

export function initTableQrCodes() {
    document.querySelectorAll('[data-table-qr-url]').forEach((element) => {
        const url = element.dataset.tableQrUrl;
        const size = parseInt(element.dataset.tableQrSize || '160', 10);
        const margin = parseInt(element.dataset.tableQrMargin || '1', 10);
        const errorCorrectionLevel = (element.dataset.tableQrEcc || 'M').toUpperCase();

        if (! url) {
            return;
        }

        renderTableQr(element, url, size, { margin, errorCorrectionLevel }).catch((error) => {
            console.error('Gagal membuat QR code meja:', error);
        });
    });
}

document.addEventListener('DOMContentLoaded', initTableQrCodes);
