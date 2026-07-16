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

async function buildAttendancePoster({ shopName, shopTitle, scanUrl }) {
    const width = 960;
    const height = 1200;
    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');

    ctx.fillStyle = '#f8fafc';
    ctx.fillRect(0, 0, width, height);

    ctx.strokeStyle = '#e2e8f0';
    ctx.lineWidth = 8;
    roundRect(ctx, 24, 24, width - 48, height - 48, 48);
    ctx.stroke();

    const ribbon = ctx.createLinearGradient(0, 0, width, 0);
    ribbon.addColorStop(0, '#0f766e');
    ribbon.addColorStop(0.5, '#0d9488');
    ribbon.addColorStop(1, '#14b8a6');
    ctx.fillStyle = ribbon;
    roundRect(ctx, 24, 24, width - 48, 120, 48);
    ctx.fill();
    ctx.fillRect(24, 84, width - 48, 60);

    ctx.fillStyle = '#ffffff';
    ctx.font = '700 26px "Instrument Sans", Arial, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText('SCAN  ·  ABSENSI', width / 2, 98);

    ctx.fillStyle = '#0f172a';
    ctx.font = '800 54px "Instrument Sans", Arial, sans-serif';
    const shopLines = wrapText(ctx, shopName || 'Coffee & Kitchen', width - 140);
    let shopY = 220;
    shopLines.slice(0, 2).forEach((line) => {
        ctx.fillText(line, width / 2, shopY);
        shopY += 62;
    });

    if (shopTitle) {
        ctx.fillStyle = '#64748b';
        ctx.font = '500 26px "Instrument Sans", Arial, sans-serif';
        const titleLines = wrapText(ctx, shopTitle, width - 160);
        titleLines.slice(0, 2).forEach((line) => {
            ctx.fillText(line, width / 2, shopY + 8);
            shopY += 34;
        });
    }

    const qrSize = 520;
    const qrCanvas = document.createElement('canvas');
    await QRCode.toCanvas(qrCanvas, scanUrl, {
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

    ctx.strokeStyle = '#99f6e4';
    ctx.lineWidth = 4;
    roundRect(ctx, frameX, frameY, frameSize, frameSize, 32);
    ctx.stroke();

    ctx.drawImage(qrCanvas, frameX + framePad, frameY + framePad);

    const ctaY = frameY + frameSize + 70;
    ctx.fillStyle = '#334155';
    ctx.font = '600 30px "Instrument Sans", Arial, sans-serif';
    ctx.fillText('Arahkan kamera HP ke kode ini', width / 2, ctaY);

    const chips = ['Scan', 'Nama', 'Selfie'];
    const chipW = 150;
    const gap = 18;
    const totalW = chips.length * chipW + (chips.length - 1) * gap;
    let chipX = (width - totalW) / 2;
    const chipY = ctaY + 40;

    chips.forEach((label) => {
        ctx.fillStyle = '#ecfdf5';
        roundRect(ctx, chipX, chipY, chipW, 52, 26);
        ctx.fill();
        ctx.fillStyle = '#0f766e';
        ctx.font = '700 22px "Instrument Sans", Arial, sans-serif';
        ctx.fillText(label, chipX + chipW / 2, chipY + 34);
        chipX += chipW + gap;
    });

    return canvas;
}

function downloadCanvas(canvas, filename) {
    const link = document.createElement('a');
    link.download = filename;
    link.href = canvas.toDataURL('image/png');
    link.click();
}

document.addEventListener('DOMContentLoaded', () => {
    const card = document.getElementById('attendance-qr-print');
    if (! card) {
        return;
    }

    document.querySelectorAll('[data-attendance-qr-download]').forEach((button) => {
        button.addEventListener('click', async () => {
            const shopName = card.dataset.shopName || '';
            const shopTitle = card.dataset.shopTitle || '';
            const scanUrl = card.dataset.scanUrl
                || document.querySelector('[data-table-qr-url]')?.dataset?.tableQrUrl
                || '';

            if (! scanUrl) {
                return;
            }

            button.disabled = true;
            const original = button.textContent;
            button.textContent = 'Menyiapkan…';

            try {
                const canvas = await buildAttendancePoster({ shopName, shopTitle, scanUrl });
                downloadCanvas(canvas, `qr-absensi-${Date.now()}.png`);
            } catch (error) {
                console.error(error);
                window.alert('Gagal membuat gambar QR.');
            } finally {
                button.disabled = false;
                button.textContent = original;
            }
        });
    });

    document.querySelectorAll('[data-attendance-qr-print]').forEach((button) => {
        button.addEventListener('click', () => {
            window.print();
        });
    });
});
