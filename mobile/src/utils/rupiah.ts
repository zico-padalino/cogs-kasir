export function formatRupiah(value: number): string {
  return new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(value);
}

/** Parse input rupiah (boleh bertitik ribuan) jadi angka. */
export function parseRupiahInput(value: string | number | null | undefined): number {
  if (value === null || value === undefined || value === '') {
    return 0;
  }

  const string = String(value).trim();

  if (/^\d+$/.test(string)) {
    return Number.parseInt(string, 10);
  }

  const cleaned = string.replace(/[^\d]/g, '');

  return cleaned ? Number.parseInt(cleaned, 10) : 0;
}

/** Format angka untuk field input: 50000 → "50.000" (tanpa Rp). */
export function formatRupiahInput(value: string | number | null | undefined): string {
  if (value === '' || value === null || value === undefined) {
    return '';
  }

  const number = parseRupiahInput(value);
  if (number === 0 && String(value).replace(/[^\d]/g, '') === '') {
    return '';
  }

  return new Intl.NumberFormat('id-ID', {
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(number);
}
