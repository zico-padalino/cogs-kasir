import Constants from 'expo-constants';

export type AppModuleFlavor = 'kasir' | 'dapur';

/**
 * Build flavor: set EXPO_PUBLIC_APP_MODULE=dapur saat build APK dapur.
 * Default = kasir (aplikasi penuh).
 */
export function getAppModuleFlavor(): AppModuleFlavor {
  const fromExtra = Constants.expoConfig?.extra?.appModule;
  const fromEnv = process.env.EXPO_PUBLIC_APP_MODULE;
  const raw = String(fromExtra || fromEnv || 'kasir').toLowerCase().trim();
  return raw === 'dapur' ? 'dapur' : 'kasir';
}

export function isDapurOnlyApp(): boolean {
  return getAppModuleFlavor() === 'dapur';
}
