import { Platform } from 'react-native';
import BackgroundService from 'react-native-background-actions';
import { getListenModeKind, setListenModeKind } from '@/kasir/listenModeState';

/**
 * Foreground service keepalive saja — tanpa HTTP poll.
 * Sync order lewat FCM/Expo push → pull sekali di foreground listeners.
 *
 * Jangan stop+start berulang: di beberapa HP Android itu membuat app
 * terasa “keluar sendiri” / proses di-kill.
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
  taskName: 'DapurListen',
  taskTitle: 'Dapur siap terima pesanan',
  taskDesc: 'Menunggu notifikasi push pesanan dapur',
  taskIcon: {
    name: 'ic_launcher',
    type: 'mipmap' as const,
  },
  color: '#5c4033',
  linkingURI: 'cogssederhana://dapur',
  parameters: {
    delay: KEEPALIVE_MS,
  },
};

export async function isDapurListenModeRunning(): Promise<boolean> {
  if (Platform.OS !== 'android') {
    return false;
  }
  try {
    return BackgroundService.isRunning() && getListenModeKind() === 'dapur';
  } catch {
    return false;
  }
}

export async function startDapurListenMode(): Promise<boolean> {
  if (Platform.OS !== 'android') {
    return false;
  }

  try {
    if (BackgroundService.isRunning() && getListenModeKind() === 'dapur') {
      return true;
    }
    if (BackgroundService.isRunning()) {
      await BackgroundService.stop();
      setListenModeKind(null);
    }
    await BackgroundService.start(listenTask, serviceOptions);
    setListenModeKind('dapur');
    return true;
  } catch (err) {
    setListenModeKind(null);
    if (__DEV__) {
      console.warn('[dapur-listen] start failed', err);
    }
    return false;
  }
}

export async function stopDapurListenMode(): Promise<void> {
  if (Platform.OS !== 'android') {
    return;
  }
  try {
    if (BackgroundService.isRunning() && getListenModeKind() === 'dapur') {
      await BackgroundService.stop();
      setListenModeKind(null);
    }
  } catch {
    // ignore
  }
}

/** Paksa matikan service (logout / ganti akun). */
export async function forceStopListenMode(): Promise<void> {
  if (Platform.OS !== 'android') {
    return;
  }
  try {
    if (BackgroundService.isRunning()) {
      await BackgroundService.stop();
    }
  } catch {
    // ignore
  }
  setListenModeKind(null);
}
