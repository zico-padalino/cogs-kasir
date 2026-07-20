import QRCode from 'qrcode';

function wrapText(ctx, text, maxWidth) {
    const words = String(text).split(/\s+/);
    const lines = [];
    let line = '';

    words.forEach((word) => {
        const next = line ? `${line} ${word}` : word;
        if (ctx.measureText(next).width > maxWidth && line) {
            lines.push(line);
            line = word;
        } else {
            line = next;
        }
    });

    if (line) {
        lines.push(line);
    }

    return lines;
}

function roundRect(ctx, x, y, width, height, radius) {
    const r = Math.min(radius, width / 2, height / 2);
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + width, y, x + width, y + height, r);
    ctx.arcTo(x + width, y + height, x, y + height, r);
    ctx.arcTo(x, y + height, x, y, r);
    ctx.arcTo(x, y, x + width, y, r);
    ctx.closePath();
}

/**
 * Stiker meja kompak (~8×10 cm saat dicetak 300dpi dari 960×1200).
 */
async function buildBarcodePoster({ shopName, shopTitle, orderUrl }) {
    const width = 960;
    const height = 1200;
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');

    ctx.fillStyle = '#fffdf9';
    ctx.fillRect(0, 0, width, height);

    ctx.strokeStyle = '#e7e0d6';
    ctx.lineWidth = 8;
    roundRect(ctx, 24, 24, width - 48, height - 48, 48);
    ctx.stroke();

    const ribbon = ctx.createLinearGradient(0, 0, width, 0);
    ribbon.addColorStop(0, '#312e81');
    ribbon.addColorStop(0.45, '#5c4033');
    ribbon.addColorStop(1, '#b8956c');
    ctx.fillStyle = ribbon;
    roundRect(ctx, 24, 24, width - 48, 120, 48);
    ctx.fill();
    ctx.fillRect(24, 84, width - 48, 60);

    ctx.fillStyle = '#ffffff';
    ctx.font = '700 26px "Source Sans 3", Arial, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('SCAN  ·  PESAN', width / 2, 98);

    ctx.fillStyle = '#0f172a';
    ctx.font = '800 54px "Source Sans 3", Arial, sans-serif';
    const shopLines = wrapText(ctx, shopName || 'Coffee & Kitchen', width - 140);
    let shopY = 220;
    shopLines.slice(0, 2).forEach((line) => {
        ctx.fillText(line, width / 2, shopY);
        shopY += 62;
    });

    if (shopTitle) {
        ctx.fillStyle = '#64748b';
        ctx.font = '500 26px "Source Sans 3", Arial, sans-serif';
        const titleLines = wrapText(ctx, shopTitle, width - 160);
        titleLines.slice(0, 2).forEach((line) => {
            ctx.fillText(line, width / 2, shopY + 8);
            shopY += 34;
        });
    }

    const qrSize = 520;
    const qrCanvas = document.createElement('canvas');
    await QRCode.toCanvas(qrCanvas, orderUrl, {
        width: qrSize,
        margin: 1,
        errorCorrectionLevel: 'H',
        color: {
            dark: '#0f172a',
            light: '#ffffff',
        },
    });

    const framePad = 28;
    const frameSize = qrSize + framePad * 2;
    const frameX = (width - frameSize) / 2;
    const frameY = shopY + 36;

    ctx.fillStyle = '#ffffff';
    roundRect(ctx, frameX, frameY, frameSize, frameSize, 32);
    ctx.fill();
    ctx.shadowColor = 'rgba(15, 23, 42, 0.08)';
    ctx.shadowBlur = 24;
    ctx.shadowOffsetY = 8;
    ctx.fill();
    ctx.shadowColor = 'transparent';
    ctx.shadowBlur = 0;
    ctx.shadowOffsetY = 0;

    ctx.strokeStyle = '#ddd6fe';
    ctx.lineWidth = 4;
    roundRect(ctx, frameX, frameY, frameSize, frameSize, 32);
    ctx.stroke();

    ctx.drawImage(qrCanvas, frameX + framePad, frameY + framePad);

    const ctaY = frameY + frameSize + 70;
    ctx.fillStyle = '#334155';
    ctx.font = '600 30px "Source Sans 3", Arial, sans-serif';
    ctx.fillText('Arahkan kamera ke kode ini', width / 2, ctaY);

    const chips = ['Scan', 'Pesan', 'Bayar'];
    const chipW = 150;
    const chipH = 56;
    const arrowGap = 36;
    const unit = chipW + arrowGap;
    const total = chips.length * chipW + (chips.length - 1) * arrowGap;
    let chipX = (width - total) / 2;
    const chipY = ctaY + 40;

    chips.forEach((label, index) => {
        roundRect(ctx, chipX, chipY, chipW, chipH, 28);
        ctx.fillStyle = index === 0 ? '#5c4033' : '#f7f1ea';
        ctx.fill();

        ctx.fillStyle = index === 0 ? '#ffffff' : '#5c4033';
        ctx.font = '700 24px "Source Sans 3", Arial, sans-serif';
        ctx.textAlign = 'center';
        ctx.fillText(label, chipX + chipW / 2, chipY + 36);

        if (index < chips.length - 1) {
            ctx.fillStyle = '#a5b4fc';
            ctx.font = '700 28px Arial, sans-serif';
            ctx.fillText('→', chipX + chipW + arrowGap / 2, chipY + 36);
        }

        chipX += unit;
    });

    ctx.fillStyle = '#94a3b8';
    ctx.font = '600 22px "Source Sans 3", Arial, sans-serif';
    ctx.fillText('Tempel di meja · Bayar di kasir', width / 2, height - 70);

    return canvas;
}

function slugify(value) {
    return String(value || 'barcode')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 40) || 'barcode';
}

async function downloadBarcodeImage(button) {
    const card = document.getElementById('table-barcode-print');
    if (! card) {
        return;
    }

    const shopName = card.dataset.shopName || document.querySelector('.barcode-print-shop')?.textContent?.trim() || 'Toko';
    const shopTitle = card.dataset.shopTitle || '';
    const orderUrl = card.dataset.orderUrl || document.querySelector('[data-table-qr-url]')?.dataset?.tableQrUrl;

    if (! orderUrl) {
        window.alert('URL pesanan tidak ditemukan.');
        return;
    }

    const originalLabel = button.textContent;
    button.disabled = true;
    button.textContent = 'Menyiapkan stiker…';

    try {
        const canvas = await buildBarcodePoster({ shopName, shopTitle, orderUrl });
        const link = document.createElement('a');
        link.download = `stiker-meja-${slugify(shopName)}.png`;
        link.href = canvas.toDataURL('image/png');
        link.click();
    } catch (error) {
        console.error(error);
        window.alert('Gagal membuat gambar barcode.');
    } finally {
        button.disabled = false;
        button.textContent = originalLabel;
    }
}

export function initBarcodeDownload() {
    document.querySelectorAll('[data-barcode-download]').forEach((button) => {
        button.addEventListener('click', () => {
            downloadBarcodeImage(button);
        });
    });
}

document.addEventListener('DOMContentLoaded', initBarcodeDownload);
