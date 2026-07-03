export function formatRupiah(value: number): string {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value);
}

export function parseRupiahInput(value: string): number {
  const digits = value.replace(/[^\d]/g, '');

  return digits ? Number.parseInt(digits, 10) : 0;
}
