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
  /** @deprecated gunakan thermer_url / intent_url */
  rawbt_url?: string;
  /** @deprecated gunakan thermer_play_store */
  rawbt_play_store?: string;
};

const PAPER_KEY = 'pos-thermal-paper';
const THERMER_PLAY = 'https://play.google.com/store/apps/details?id=mate.bluetoothprint';
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

function buildSendIntentUrl(shareText: string): string {
  return (
    'intent:#Intent;action=android.intent.action.SEND;type=text/plain;' +
    `package=${THERMER_PACKAGE};` +
    `S.android.intent.extra.TEXT=${encodeURIComponent(shareText)};end`
  );
}

/**
 * Cetak struk ke printer Bluetooth lewat Thermer (mate.bluetoothprint).
 * Android: Intent ACTION_SEND native (bukan URL dengan Play Store fallback).
 * iOS: scheme thermer://
 */
export async function printThermalViaThermer(
  thermal: ThermalPayload,
): Promise<'opened' | 'store' | 'failed'> {
  const shareText = thermal.thermer_share_text || '';
  const thermerUrl = thermal.thermer_url || '';

  if (Platform.OS === 'android') {
    if (shareText) {
      try {
        await IntentLauncher.startActivityAsync('android.intent.action.SEND', {
          type: 'text/plain',
          packageName: THERMER_PACKAGE,
          extra: {
            'android.intent.extra.TEXT': shareText,
          },
        });
        return 'opened';
      } catch {
        // Thermer mungkin tidak resolve package — coba intent URL / share sheet
      }

      try {
        await Linking.openURL(buildSendIntentUrl(shareText));
        return 'opened';
      } catch {
        // lanjut share sheet
      }

      try {
        await Share.share({ message: shareText, title: 'Cetak Thermal' });
        return 'opened';
      } catch {
        // ignore
      }
    } else if (thermal.intent_url) {
      try {
        await Linking.openURL(thermal.intent_url);
        return 'opened';
      } catch {
        // ignore
      }
    }

    // Jangan auto-buka Play Store.
    return 'store';
  }

  // iOS
  try {
    if (thermerUrl) {
      await Linking.openURL(thermerUrl);
      return 'opened';
    }
  } catch {
    // ignore
  }

  if (shareText) {
    try {
      await Share.share({ message: shareText });
      return 'opened';
    } catch {
      // ignore
    }
  }

  try {
    await Linking.openURL(THERMER_PLAY);
    return 'store';
  } catch {
    return 'failed';
  }
}

/** @deprecated alias — pakai printThermalViaThermer */
export const printThermalViaRawBt = printThermalViaThermer;
