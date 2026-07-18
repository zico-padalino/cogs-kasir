import Constants from 'expo-constants';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import * as TaskManager from 'expo-task-manager';
import { Platform } from 'react-native';
import { apiRequest } from '@/api/client';
import { announceSpeakText } from '@/kasir/orderAlert';

export const KASIR_PUSH_CHANNEL = 'kasir-orders';
const BACKGROUND_NOTIFICATION_TASK = 'KASIR_BACKGROUND_NOTIFICATION';

type PushData = {
  type?: string;
  order_id?: number | string;
  customer_name?: string;
  speak_text?: string;
};

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldPlaySound: true,
    shouldSetBadge: true,
    shouldShowBanner: true,
    shouldShowList: true,
    priority: Notifications.AndroidNotificationPriority.MAX,
  }),
});

function pushPayloadFromNotification(
  notification: Notifications.Notification,
): { speakText: string; dedupeKey: string } | null {
  const content = notification.request.content;
  const data = (content.data || {}) as PushData;

  if (data.type && data.type !== 'new_order') {
    return null;
  }

  const speakText =
    (typeof data.speak_text === 'string' && data.speak_text.trim()) ||
    (typeof data.customer_name === 'string' && data.customer_name.trim()
      ? `Pesanan baru masuk, atas nama ${data.customer_name.trim()}.`
      : '') ||
    (content.body ? `Pesanan baru masuk. ${content.body}` : 'Pesanan baru masuk.');

  const dedupeKey = String(data.order_id ?? speakText);

  return { speakText, dedupeKey };
}

async function speakFromNotification(notification: Notifications.Notification): Promise<void> {
  const payload = pushPayloadFromNotification(notification);
  if (!payload) {
    return;
  }
  await announceSpeakText(payload.speakText, payload.dedupeKey);
}

/** Harus di top-level (sebelum React mount) agar jalan saat HP terkunci. */
if (!TaskManager.isTaskDefined(BACKGROUND_NOTIFICATION_TASK)) {
  TaskManager.defineTask(BACKGROUND_NOTIFICATION_TASK, async ({ data, error }) => {
    if (error) {
      return;
    }

    try {
      const notification = (data as { notification?: Notifications.Notification } | undefined)
        ?.notification;
      if (notification) {
        await speakFromNotification(notification);
        return;
      }

      // Bentuk alternatif payload background task
      const raw = (data as { data?: PushData } | undefined)?.data;
      if (raw?.speak_text || raw?.customer_name || raw?.type === 'new_order') {
        const speakText =
          raw.speak_text ||
          (raw.customer_name
            ? `Pesanan baru masuk, atas nama ${raw.customer_name}.`
            : 'Pesanan baru masuk.');
        await announceSpeakText(speakText, String(raw.order_id ?? speakText));
      }
    } catch {
      // Background TTS opsional — notifikasi sistem tetap tampil + bunyi
    }
  });
}

let listenersReady = false;
let cachedToken: string | null = null;

function projectId(): string | undefined {
  return (
    Constants.easConfig?.projectId ??
    (Constants.expoConfig?.extra?.eas as { projectId?: string } | undefined)?.projectId
  );
}

async function ensureAndroidChannel(): Promise<void> {
  if (Platform.OS !== 'android') {
    return;
  }

  await Notifications.setNotificationChannelAsync(KASIR_PUSH_CHANNEL, {
    name: 'Pesanan kasir',
    description: 'Notifikasi pesanan online meski HP terkunci',
    importance: Notifications.AndroidImportance.MAX,
    vibrationPattern: [0, 250, 180, 250, 180, 250],
    sound: 'default',
    enableVibrate: true,
    enableLights: true,
    bypassDnd: true,
    lockscreenVisibility: Notifications.AndroidNotificationVisibility.PUBLIC,
    audioAttributes: {
      usage: Notifications.AndroidAudioUsage.ALARM,
      contentType: Notifications.AndroidAudioContentType.SPEECH,
      flags: {
        enforceAudibility: true,
        requestHardwareAudioVideoSynchronization: false,
      },
    },
  });
}

/** Panggil sekali saat app start — listener + background task. */
export async function setupKasirPushRuntime(): Promise<void> {
  await ensureAndroidChannel();

  try {
    await Notifications.registerTaskAsync(BACKGROUND_NOTIFICATION_TASK);
  } catch {
    // Expo Go / platform tertentu mungkin tidak support background task
  }

  if (listenersReady) {
    return;
  }
  listenersReady = true;

  // App masih hidup (termasuk layar kunci / background) → TTS
  Notifications.addNotificationReceivedListener((notification) => {
    void speakFromNotification(notification);
  });
}

export async function registerKasirPushToken(): Promise<string | null> {
  if (!Device.isDevice) {
    return null;
  }

  await setupKasirPushRuntime();

  const current = await Notifications.getPermissionsAsync();
  let status = current.status;

  if (status !== 'granted') {
    const asked = await Notifications.requestPermissionsAsync({
      ios: {
        allowAlert: true,
        allowBadge: true,
        allowSound: true,
        allowCriticalAlerts: false,
        provideAppNotificationSettings: true,
      },
    });
    status = asked.status;
  }

  if (status !== 'granted') {
    return null;
  }

  if (Platform.OS === 'android') {
    // Beberapa OEM butuh channel siap sebelum token dipakai
    await ensureAndroidChannel();
  }

  const id = projectId();
  const tokenResponse = id
    ? await Notifications.getExpoPushTokenAsync({ projectId: id })
    : await Notifications.getExpoPushTokenAsync();

  const token = tokenResponse.data;
  cachedToken = token;

  await apiRequest('/kasir/push-token', {
    method: 'POST',
    body: {
      token,
      platform: 'expo',
      device_name: `${Device.brand ?? 'device'} ${Device.modelName ?? ''}`.trim(),
    },
  });

  return token;
}

/** Listener: tap notifikasi di layar kunci → buka kasir. */
export function addKasirNotificationResponseListener(
  onNewOrder: () => void,
): { remove: () => void } {
  return Notifications.addNotificationResponseReceivedListener((response) => {
    const data = response.notification.request.content.data as PushData | undefined;
    if (data?.type === 'new_order') {
      onNewOrder();
    }
  });
}

export async function unregisterKasirPushToken(): Promise<void> {
  const token = cachedToken;

  if (!token) {
    return;
  }

  try {
    await apiRequest('/kasir/push-token', {
      method: 'DELETE',
      body: { token },
    });
  } catch {
    // ignore
  } finally {
    cachedToken = null;
  }
}
