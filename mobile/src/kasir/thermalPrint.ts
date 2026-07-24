import { Linking, Platform, Share } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import * as IntentLauncher from 'expo-intent-launcher';

export type ThermalPaper = '58mm' | '80mm';

export type ThermalPayload = {
  paper: string;
  width: number;
  base64?: string;
  thermer_url?: string;
  thermer_json?: string;
  thermer_share_text?: string;
  intent_url: string;
  thermer_play_store?: string;
  rawbt_url?: string;
  rawbt_play_store?: string;
};

const PAPER_KEY = 'pos-thermal-paper';
const THERMER_PACKAGE = 'mate.bluetoothprint';

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
 * Buka Thermer langsung. Tidak pernah auto-buka Play Store.
 */
export async function printThermalViaThermer(
  thermal: ThermalPayload,
): Promise<'opened' | 'store' | 'failed'> {
  const shareText = thermal.thermer_share_text || '';
  const thermerUrl =
    thermal.thermer_url ||
    (thermal.thermer_json ? `thermer://?data=${encodeURIComponent(thermal.thermer_json)}` : '');

  if (Platform.OS === 'android') {
    // 1) Deep link thermer:// (HTML type 4 — struk besar)
    if (thermerUrl) {
      try {
        const can = await Linking.canOpenURL(thermerUrl).catch(() => true);
        if (can !== false) {
          await Linking.openURL(thermerUrl);
          return 'opened';
        }
      } catch {
        // lanjut
      }
    }

    // 2) Native SEND ke package Thermer (markup <BAF> besar)
    if (shareText) {
      try {
        await IntentLauncher.startActivityAsync('android.intent.action.SEND', {
          type: 'text/plain',
          packageName: THERMER_PACKAGE,
          extra: { 'android.intent.extra.TEXT': shareText },
          flags: 0x10000000,
        });
        return 'opened';
      } catch {
        // lanjut
      }
    }

    // 3) Share sheet — user pilih Thermer (tidak ke Play Store)
    if (shareText) {
      try {
        await Share.share({ message: shareText, title: 'Cetak Thermal' });
        return 'opened';
      } catch {
        // ignore
      }
    }

    return 'store';
  }

  if (thermerUrl) {
    try {
      await Linking.openURL(thermerUrl);
      return 'opened';
    } catch {
      return 'failed';
    }
  }

  return 'failed';
}

/** @deprecated */
export const printThermalViaRawBt = printThermalViaThermer;
