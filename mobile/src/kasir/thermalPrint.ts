import { Linking, Platform } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';

export type ThermalPaper = '58mm' | '80mm';

export type ThermalPayload = {
  paper: string;
  width: number;
  base64: string;
  rawbt_url: string;
  intent_url: string;
  rawbt_play_store?: string;
};

const PAPER_KEY = 'pos-thermal-paper';
const RAWBT_PLAY = 'https://play.google.com/store/apps/details?id=ru.a402d.rawbtprinter';

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

/**
 * Cetak ESC/POS ke printer Ainuo lewat RawBT (Bluetooth).
 * Intent scheme membuka RawBT; jika belum terpasang, Android biasanya arahkan ke Play Store.
 */
export async function printThermalViaRawBt(thermal: ThermalPayload): Promise<'opened' | 'store' | 'failed'> {
  const intentUrl = thermal.intent_url;
  const rawbtUrl = thermal.rawbt_url;
  const playStore = thermal.rawbt_play_store || RAWBT_PLAY;

  const tryOpen = async (url: string) => {
    await Linking.openURL(url);
  };

  if (Platform.OS === 'android') {
    if (intentUrl) {
      try {
        await tryOpen(intentUrl);
        return 'opened';
      } catch {
        // try rawbt scheme
      }
    }
    if (rawbtUrl) {
      try {
        await tryOpen(rawbtUrl);
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

  try {
    if (rawbtUrl) {
      await tryOpen(rawbtUrl);
      return 'opened';
    }
  } catch {
    // ignore
  }

  return 'failed';
}
