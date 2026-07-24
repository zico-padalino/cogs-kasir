import { Linking, Platform } from 'react-native';
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
  /** @deprecated */
  rawbt_url?: string;
  /** @deprecated */
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
 * Sekali klik → langsung buka Thermer (tanpa Play Store / share sheet).
 */
export async function printThermalViaThermer(
  thermal: ThermalPayload,
): Promise<'opened' | 'store' | 'failed'> {
  const shareText = thermal.thermer_share_text || '';
  const thermerUrl = thermal.thermer_url || '';

  if (Platform.OS === 'android') {
    // 1) Native Intent langsung ke package Thermer (paling andal)
    if (shareText) {
      try {
        await IntentLauncher.startActivityAsync('android.intent.action.SEND', {
          type: 'text/plain',
          packageName: THERMER_PACKAGE,
          extra: {
            'android.intent.extra.TEXT': shareText,
          },
          flags: 0x10000000, // FLAG_ACTIVITY_NEW_TASK
        });
        return 'opened';
      } catch {
        // lanjut
      }
    }

    // 2) Intent URL ke package Thermer (tanpa browser_fallback_url)
    if (shareText) {
      try {
        const url =
          'intent:#Intent;action=android.intent.action.SEND;type=text/plain;' +
          `package=${THERMER_PACKAGE};` +
          `S.android.intent.extra.TEXT=${encodeURIComponent(shareText)};end`;
        await Linking.openURL(url);
        return 'opened';
      } catch {
        // lanjut
      }
    }

    // 3) Deep link thermer://
    if (thermerUrl) {
      try {
        await Linking.openURL(thermerUrl);
        return 'opened';
      } catch {
        // ignore
      }
    }

    return 'store';
  }

  // iOS — deep link
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
