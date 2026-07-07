// Design tokens diselaraskan dengan web Laravel (Tailwind v4 + palet "brand" indigo).
// Sumber acuan: resources/css/app.css @theme dan komponen @layer.

export const colors = {
  // Brand (indigo) — sama dengan --color-brand-* di Laravel
  brand50: '#eef2ff',
  brand100: '#e0e7ff',
  brand200: '#c7d2fe',
  brand300: '#a5b4fc',
  brand400: '#818cf8',
  brand500: '#6366f1',
  brand600: '#4f46e5',
  brand700: '#4338ca',
  brand800: '#3730a3',
  brand900: '#312e81',

  // Slate
  slate50: '#f8fafc',
  slate100: '#f1f5f9',
  slate200: '#e2e8f0',
  slate300: '#cbd5e1',
  slate400: '#94a3b8',
  slate500: '#64748b',
  slate600: '#475569',
  slate700: '#334155',
  slate800: '#1e293b',
  slate900: '#0f172a',

  white: '#ffffff',

  // Semantik (badge/status/stat card)
  green50: '#f0fdf4',
  green200: '#bbf7d0',
  green600: '#16a34a',
  green700: '#15803d',
  emerald50: '#ecfdf5',
  emerald700: '#047857',
  blue50: '#eff6ff',
  blue200: '#bfdbfe',
  blue700: '#1d4ed8',
  amber50: '#fffbeb',
  amber100: '#fef3c7',
  amber200: '#fde68a',
  amber500: '#f59e0b',
  amber700: '#b45309',
  amber800: '#92400e',
  rose50: '#fff1f2',
  rose600: '#e11d48',
  rose700: '#be123c',
  red50: '#fef2f2',
  red200: '#fecaca',
  red600: '#dc2626',
  red700: '#b91c1c',
} as const;

export const spacing = {
  xs: 4,
  sm: 8,
  md: 12,
  lg: 16,
  xl: 20,
  xxl: 24,
} as const;

// Radius mengikuti skala Tailwind yang dipakai Laravel:
// rounded-md=6, rounded-lg=8, rounded-xl=12, rounded-2xl=16, rounded-3xl=24, rounded-full.
export const radius = {
  sm: 6,
  md: 8,
  lg: 12,
  xl: 16,
  '2xl': 16,
  '3xl': 24,
  full: 999,
} as const;

// Keluarga font Instrument Sans (dimuat lewat @expo-google-fonts/instrument-sans).
export const fontFamily = {
  regular: 'InstrumentSans_400Regular',
  medium: 'InstrumentSans_500Medium',
  semibold: 'InstrumentSans_600SemiBold',
  bold: 'InstrumentSans_700Bold',
} as const;

type Weight = '400' | '500' | '600' | '700' | '800' | '900';

// Pasangan {fontFamily, fontWeight} agar teks memakai file font yang benar
// di semua platform (native butuh nama family spesifik, web butuh weight).
export function font(weight: Weight = '400') {
  switch (weight) {
    case '500':
      return { fontFamily: fontFamily.medium, fontWeight: '500' as const };
    case '600':
      return { fontFamily: fontFamily.semibold, fontWeight: '600' as const };
    case '700':
    case '800':
    case '900':
      return { fontFamily: fontFamily.bold, fontWeight: '700' as const };
    default:
      return { fontFamily: fontFamily.regular, fontWeight: '400' as const };
  }
}

export const shadow = {
  sm: {
    shadowColor: '#0f172a',
    shadowOpacity: 0.05,
    shadowRadius: 6,
    shadowOffset: { width: 0, height: 1 },
    elevation: 1,
  },
  md: {
    shadowColor: '#0f172a',
    shadowOpacity: 0.08,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 4 },
    elevation: 3,
  },
} as const;
