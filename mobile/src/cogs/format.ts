export function formatRupiah(value: number): string {
  const rounded = Math.round(Number.isFinite(value) ? value : 0);

  return `Rp ${new Intl.NumberFormat('id-ID').format(rounded)}`;
}

export function parseRupiah(value: string): number {
  const digits = value.replace(/[^\d]/g, '');

  return digits ? Number.parseInt(digits, 10) : 0;
}

export function formatQty(value: number): string {
  if (!Number.isFinite(value)) {
    return '0';
  }

  const rounded = Math.round(value * 1000) / 1000;

  return new Intl.NumberFormat('id-ID', {
    maximumFractionDigits: 3,
  }).format(rounded);
}

export function parseNumber(value: string): number {
  const normalized = value.replace(/\./g, '').replace(',', '.').replace(/[^\d.]/g, '');
  const parsed = Number.parseFloat(normalized);

  return Number.isFinite(parsed) ? parsed : 0;
}

export function round4(value: number): number {
  return Math.round(value * 10000) / 10000;
}
