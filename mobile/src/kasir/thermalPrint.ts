import { Linking, Platform } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';

export type ThermalPaper = '58mm' | '80mm';

export type ThermalPayload = {
  paper: string;
  width: number;
  base64?: string;
  thermer_url?: string;
  thermer_json?: string;
  intent_url: string;
  thermer_play_store?: string;
  /** @deprecated gunakan thermer_url */
  rawbt_url?: string;
  /** @deprecated gunakan thermer_play_store */
  rawbt_play_store?: string;
};

const PAPER_KEY = 'pos-thermal-paper';
const THERMER_PLAY = 'https://play.google.com/store/apps/details?id=mate.bluetoothprint';

export async function getThermalPaper(): Promise<ThermalPaper> {
  try {
    const v = await AsyncStorage.getItem(PAPER_KEY);
    return v === '80mm' ? '80mm' : '58mm';
  } catch {
    return '58mm';
  }
}

export async function setThermalPaper(paper: ThermalPaper): Promise<void> {
  await AsyncStorage.setItem(PAPER_KEY, paper);
}

function buildThermerUrls(thermal: ThermalPayload): {
  thermerUrl: string;
  intentUrl: string;
  playStore: string;
} {
  const playStore = thermal.thermer_play_store || thermal.rawbt_play_store || THERMER_PLAY;

  if (thermal.thermer_json) {
    const encoded = encodeURIComponent(thermal.thermer_json);
    return {
      thermerUrl: `thermer://?data=${encoded}`,
      intentUrl:
        `intent://?data=${encoded}#Intent;scheme=thermer;package=mate.bluetoothprint;` +
        `S.browser_fallback_url=${encodeURIComponent(playStore)};end;`,
      playStore,
    };
  }

  return {
    thermerUrl: thermal.thermer_url || thermal.rawbt_url || '',
    intentUrl: thermal.intent_url || '',
    playStore,
  };
}

/**
 * Cetak struk ke printer Bluetooth lewat aplikasi Thermer (mate.bluetoothprint).
 * Buka scheme thermer://; jika belum terpasang, arahkan ke Play Store.
 */
export async function printThermalViaThermer(
  thermal: ThermalPayload,
): Promise<'opened' | 'store' | 'failed'> {
  const { thermerUrl, intentUrl, playStore } = buildThermerUrls(thermal);

  const tryOpen = async (url: string) => {
    await Linking.openURL(url);
  };

  if (Platform.OS === 'android') {
    if (thermerUrl) {
      try {
        await tryOpen(thermerUrl);
        return 'opened';
      } catch {
        // coba intent URL
      }
    }
    if (intentUrl) {
      try {
        await tryOpen(intentUrl);
        return 'opened';
      } catch {
        // fall through to store
      }
    }
    try {
      await tryOpen(playStore);
      return 'store';
    } catch {
      return 'failed';
    }
  }

  // iOS: Thermer memakai scheme yang sama
  try {
    if (thermerUrl) {
      await tryOpen(thermerUrl);
      return 'opened';
    }
  } catch {
    // ignore
  }

  try {
    await tryOpen(playStore);
    return 'store';
  } catch {
    return 'failed';
  }
}

/** @deprecated alias — pakai printThermalViaThermer */
export const printThermalViaRawBt = printThermalViaThermer;
