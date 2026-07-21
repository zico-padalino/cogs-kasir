import { Platform } from 'react-native';
import BackgroundService from 'react-native-background-actions';
import { kasirApi } from '@/api/kasir';
import { getToken } from '@/api/client';
import { announceNewOrders } from '@/kasir/orderAlert';
import { takeNewPendingIds } from '@/kasir/pendingOrderTracker';

const POLL_MS = 4000;

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

/**
 * Loop foreground: poll pesanan + TTS meskipun user keluar dari app.
 * Android menampilkan notifikasi tetap "Kasir siap terima pesanan".
 */
async function listenTask(): Promise<void> {
  await new Promise<void>(async (resolve) => {
    while (BackgroundService.isRunning()) {
      try {
        const token = await getToken();
        if (token) {
          const res = await kasirApi.poll();
          const data = res.data;
          const orders = data.orders || [];
          const ids = (data.order_ids || []).map(Number);
          const notifyIds = (data.notify_order_ids || data.order_ids || []).map(Number);
          const newIds = takeNewPendingIds(ids, notifyIds);

          if (newIds.length > 0) {
            const newOrders = orders.filter(
              (o) => newIds.includes(o.id) && o.source !== 'kasir' && o.status !== 'paid' && o.status !== 'served',
            );
            if (newOrders.length > 0) {
              await announceNewOrders(newOrders);
            }
          }
        }
      } catch {
        // jaringan / PIN — lanjut loop
      }

      await sleep(POLL_MS);
    }
    resolve();
  });
}

const serviceOptions = {
  taskName: 'KasirListen',
  taskTitle: 'Kasir siap terima pesanan',
  taskDesc: 'Notifikasi & suara AI aktif di luar app',
  taskIcon: {
    name: 'ic_launcher',
    type: 'mipmap' as const,
  },
  color: '#5c4033',
  linkingURI: 'cogssederhana://kasir/pin',
  parameters: {
    delay: POLL_MS,
  },
};

export async function isKasirListenModeRunning(): Promise<boolean> {
  if (Platform.OS !== 'android') {
    return false;
  }
  try {
    return BackgroundService.isRunning();
  } catch {
    return false;
  }
}

/** Mulai mode dengar (foreground). Wajib untuk suara AI di luar app. */
export async function startKasirListenMode(): Promise<boolean> {
  if (Platform.OS !== 'android') {
    return false;
  }

  try {
    if (BackgroundService.isRunning()) {
      return true;
    }
    await BackgroundService.start(listenTask, serviceOptions);
    return true;
  } catch (err) {
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
    if (BackgroundService.isRunning()) {
      await BackgroundService.stop();
    }
  } catch {
    // ignore
  }
}
