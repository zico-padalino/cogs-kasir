import { Platform } from 'react-native';
import BackgroundService from 'react-native-background-actions';
import { getListenModeKind, setListenModeKind } from '@/kasir/listenModeState';

/**
 * Foreground service keepalive saja — tanpa HTTP poll.
 * Sync order lewat push → pull sekali (hindari 503 EP/NPROC).
 *
 * Jangan stop+start berulang — bisa bikin app “keluar sendiri” di Android.
 */
const KEEPALIVE_MS = 60_000;

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function listenTask(): Promise<void> {
  await new Promise<void>(async (resolve) => {
    while (BackgroundService.isRunning()) {
      await sleep(KEEPALIVE_MS);
    }
    resolve();
  });
}

const serviceOptions = {
  taskName: 'KasirListen',
  taskTitle: 'Kasir siap terima pesanan',
  taskDesc: 'Menunggu notifikasi push pesanan baru',
  taskIcon: {
    name: 'ic_launcher',
    type: 'mipmap' as const,
  },
  color: '#5c4033',
  linkingURI: 'cogssederhana://kasir/pin',
  parameters: {
    delay: KEEPALIVE_MS,
  },
};

export async function isKasirListenModeRunning(): Promise<boolean> {
  if (Platform.OS !== 'android') {
    return false;
  }
  try {
    return BackgroundService.isRunning() && getListenModeKind() === 'kasir';
  } catch {
    return false;
  }
}

/** Mulai mode dengar (foreground service). Push + TTS di luar app. */
export async function startKasirListenMode(): Promise<boolean> {
  if (Platform.OS !== 'android') {
    return false;
  }

  try {
    if (BackgroundService.isRunning() && getListenModeKind() === 'kasir') {
      return true;
    }
    if (BackgroundService.isRunning()) {
      await BackgroundService.stop();
      setListenModeKind(null);
    }
    await BackgroundService.start(listenTask, serviceOptions);
    setListenModeKind('kasir');
    return true;
  } catch (err) {
    setListenModeKind(null);
    if (__DEV__) {
      console.warn('[kasir-listen] start failed', err);
    }
    return false;
  }
}

export async function stopKasirListenMode(): Promise<void> {
  if (Platform.OS !== 'android') {
    return;
  }
  try {
    if (BackgroundService.isRunning() && getListenModeKind() === 'kasir') {
      await BackgroundService.stop();
      setListenModeKind(null);
    }
  } catch {
    // ignore
  }
}
