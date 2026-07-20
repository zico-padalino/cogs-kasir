// Design tokens — Espresso Cafe (selaras dengan resources/css/app.css @theme).

export const colors = {
  // Brand (espresso / tembaga)
  brand50: '#f7f1ea',
  brand100: '#ede4d8',
  brand200: '#dccbb8',
  brand300: '#c4a484',
  brand400: '#b8956c',
  brand500: '#8b6914',
  brand600: '#5c4033',
  brand700: '#3f2a22',
  brand800: '#2c2118',
  brand900: '#1c1410',

  // Warm stone (mengganti slate dingin)
  slate50: '#f6f1ea',
  slate100: '#efe8de',
  slate200: '#e0d5c8',
  slate300: '#cbbba8',
  slate400: '#a8927c',
  slate500: '#8a7360',
  slate600: '#6b584a',
  slate700: '#4a3c32',
  slate800: '#2c2118',
  slate900: '#1c1410',

  white: '#ffffff',
  cream: '#f6f1ea',
  espresso: '#1c1410',
  copper: '#b8956c',

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

export const radius = {
  sm: 6,
  md: 8,
  lg: 12,
  xl: 16,
  '2xl': 16,
  '3xl': 24,
  full: 999,
} as const;

// Body: Source Sans 3 · Display: Fraunces
export const fontFamily = {
  regular: 'SourceSans3_400Regular',
  medium: 'SourceSans3_500Medium',
  semibold: 'SourceSans3_600SemiBold',
  bold: 'SourceSans3_700Bold',
  display: 'Fraunces_600SemiBold',
  displayBold: 'Fraunces_700Bold',
} as const;

type Weight = '400' | '500' | '600' | '700' | '800' | '900';

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

export function fontDisplay(weight: '600' | '700' = '600') {
  return {
    fontFamily: weight === '700' ? fontFamily.displayBold : fontFamily.display,
    fontWeight: weight,
  } as const;
}

export const shadow = {
  sm: {
    shadowColor: '#1c1410',
    shadowOpacity: 0.06,
    shadowRadius: 6,
    shadowOffset: { width: 0, height: 1 },
    elevation: 1,
  },
  md: {
    shadowColor: '#1c1410',
    shadowOpacity: 0.1,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: 4 },
    elevation: 3,
  },
} as const;
