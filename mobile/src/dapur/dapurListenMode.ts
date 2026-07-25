import { AppState, Platform } from 'react-native';
import BackgroundService from 'react-native-background-actions';
import { kasirApi } from '@/api/kasir';
import { getToken } from '@/api/client';
import { announceKitchenOrders } from '@/dapur/kitchenAlert';
import { takeNewKitchenIds } from '@/dapur/kitchenOrderTracker';

/** Shared hosting: 4s terlalu agresif → 503 entry process. Push menutupi jeda. */
const POLL_MS = 20_000;
const BACKOFF_MS = 45_000;

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function listenTask(): Promise<void> {
  await new Promise<void>(async (resolve) => {
    while (BackgroundService.isRunning()) {
      let nextSleep = POLL_MS;
      try {
        if (AppState.currentState === 'active') {
          await sleep(POLL_MS);
          continue;
        }

        const token = await getToken();
        if (token) {
          const res = await kasirApi.dapurPoll();
          const data = res.data;
          const orders = data.orders || [];
          const ids = (data.order_ids || []).map(Number);
          const notifyIds = (data.notify_order_ids || data.order_ids || []).map(Number);
          const newIds = takeNewKitchenIds(ids, notifyIds);

          if (newIds.length > 0) {
            const newOrders = orders.filter((o) => newIds.includes(o.id));
            if (newOrders.length > 0) {
              await announceKitchenOrders(newOrders);
            }
          }
        }
      } catch {
        nextSleep = BACKOFF_MS;
      }

      await sleep(nextSleep);
    }
    resolve();
  });
}

const serviceOptions = {
  taskName: 'DapurListen',
  taskTitle: 'Dapur siap terima pesanan',
  taskDesc: 'Notifikasi & suara AI menu aktif di luar app',
  taskIcon: {
    name: 'ic_launcher',
    type: 'mipmap' as const,
  },
  color: '#5c4033',
  linkingURI: 'cogssederhana://dapur',
  parameters: {
    delay: POLL_MS,
  },
};

export async function isDapurListenModeRunning(): Promise<boolean> {
  if (Platform.OS !== 'android') {
    return false;
  }
  try {
    return BackgroundService.isRunning();
  } catch {
    return false;
  }
}

export async function startDapurListenMode(): Promise<boolean> {
  if (Platform.OS !== 'android') {
    return false;
  }

  try {
    if (BackgroundService.isRunning()) {
      await BackgroundService.stop();
    }
    await BackgroundService.start(listenTask, serviceOptions);
    return true;
  } catch (err) {
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
    if (BackgroundService.isRunning()) {
      await BackgroundService.stop();
    }
  } catch {
    // ignore
  }
}
