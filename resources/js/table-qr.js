import QRCode from 'qrcode';

export async function renderTableQr(element, url, size = 160) {
    const options = {
        width: size,
        margin: 1,
        color: {
            dark: '#1e293b',
            light: '#ffffff',
        },
    };

    if (element.tagName === 'CANVAS') {
        await QRCode.toCanvas(element, url, options);
        return;
    }

    if (element.tagName === 'IMG') {
        element.src = await QRCode.toDataURL(url, options);
    }
}

export function initTableQrCodes() {
    document.querySelectorAll('[data-table-qr-url]').forEach((element) => {
        const url = element.dataset.tableQrUrl;
        const size = parseInt(element.dataset.tableQrSize || '160', 10);

        if (! url) {
            return;
        }

        renderTableQr(element, url, size).catch((error) => {
            console.error('Gagal membuat QR code meja:', error);
        });
    });
}

document.addEventListener('DOMContentLoaded', initTableQrCodes);
