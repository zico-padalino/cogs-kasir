import AsyncStorage from '@react-native-async-storage/async-storage';
import Constants from 'expo-constants';

const STORAGE_KEY = 'cogs_app_base_url';

function normalizeUrl(url: string): string {
  return url.trim().replace(/\/+$/, '');
}

export function getDefaultAppUrl(): string {
  const fromEnv = process.env.EXPO_PUBLIC_APP_URL;
  if (fromEnv) {
    return normalizeUrl(fromEnv);
  }

  const fromExtra = Constants.expoConfig?.extra?.appUrl;
  if (typeof fromExtra === 'string' && fromExtra.length > 0) {
    return normalizeUrl(fromExtra);
  }

  return 'http://localhost:8000';
}

export async function getAppBaseUrl(): Promise<string> {
  const saved = await AsyncStorage.getItem(STORAGE_KEY);

  if (saved) {
    return normalizeUrl(saved);
  }

  return getDefaultAppUrl();
}

export async function setAppBaseUrl(url: string): Promise<void> {
  await AsyncStorage.setItem(STORAGE_KEY, normalizeUrl(url));
}

export async function clearAppBaseUrl(): Promise<void> {
  await AsyncStorage.removeItem(STORAGE_KEY);
}

export function buildAppPath(baseUrl: string, path: string): string {
  return `${normalizeUrl(baseUrl)}${path.startsWith('/') ? path : `/${path}`}`;
}
