import AsyncStorage from '@react-native-async-storage/async-storage';
import Constants from 'expo-constants';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import * as TaskManager from 'expo-task-manager';
import { Platform } from 'react-native';
import { apiRequest, getToken } from '@/api/client';
import { announceSpeakText } from '@/kasir/orderAlert';

export const KASIR_PUSH_CHANNEL = 'kasir-orders';
const BACKGROUND_NOTIFICATION_TASK = 'KASIR_BACKGROUND_NOTIFICATION';
const STORED_PUSH_TOKEN_KEY = 'kasir_expo_push_token_v1';
const PERMISSION_DENIED_KEY = 'kasir_push_permission_denied_v1';

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

/** Harus di top-level agar jalan saat HP terkunci / app di-kill. */
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
      // Notifikasi sistem tetap tampil meski TTS background gagal
    }
  });
}

let listenersReady = false;
let registerInFlight: Promise<string | null> | null = null;
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
    description: 'Notifikasi pesanan online meski HP terkunci / app tertutup',
    importance: Notifications.AndroidImportance.MAX,
    vibrationPattern: [0, 250, 180, 250, 180, 250],
    sound: 'default',
    enableVibrate: true,
    enableLights: true,
    bypassDnd: true,
    showBadge: true,
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

async function persistLocalToken(token: string | null): Promise<void> {
  cachedToken = token;
  if (!token) {
    await AsyncStorage.removeItem(STORED_PUSH_TOKEN_KEY);
    return;
  }
  await AsyncStorage.setItem(STORED_PUSH_TOKEN_KEY, token);
}

async function readLocalToken(): Promise<string | null> {
  if (cachedToken) {
    return cachedToken;
  }
  cachedToken = await AsyncStorage.getItem(STORED_PUSH_TOKEN_KEY);
  return cachedToken;
}

/** Panggil sekali saat app start — channel, background task, listener. */
export async function setupKasirPushRuntime(): Promise<void> {
  await ensureAndroidChannel();

  try {
    await Notifications.registerTaskAsync(BACKGROUND_NOTIFICATION_TASK);
  } catch {
    // Expo Go / perangkat tertentu mungkin tidak support
  }

  if (listenersReady) {
    return;
  }
  listenersReady = true;

  Notifications.addNotificationReceivedListener((notification) => {
    void speakFromNotification(notification);
  });
}

/**
 * Daftarkan / perbarui Expo push token ke server.
 * Dipanggil setelah login dan setiap kali app kembali aktif.
 */
export async function registerKasirPushToken(): Promise<string | null> {
  if (!Device.isDevice) {
    return null;
  }

  if (registerInFlight) {
    return registerInFlight;
  }

  registerInFlight = (async () => {
    await setupKasirPushRuntime();

    const apiToken = await getToken();
    if (!apiToken) {
      return null;
    }

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
        android: {},
      });
      status = asked.status;
    }

    if (status !== 'granted') {
      await AsyncStorage.setItem(PERMISSION_DENIED_KEY, '1');
      return null;
    }

    await AsyncStorage.removeItem(PERMISSION_DENIED_KEY);
    await ensureAndroidChannel();

    const id = projectId();
    const tokenResponse = id
      ? await Notifications.getExpoPushTokenAsync({ projectId: id })
      : await Notifications.getExpoPushTokenAsync();

    const token = tokenResponse.data;
    await persistLocalToken(token);

    // Retry singkat: jaringan / server kadang gagal sekali.
    let lastError: unknown = null;
    for (let attempt = 0; attempt < 3; attempt += 1) {
      try {
        await apiRequest('/kasir/push-token', {
          method: 'POST',
          body: {
            token,
            platform: 'expo',
            device_name: `${Device.brand ?? 'device'} ${Device.modelName ?? ''}`.trim(),
          },
        });
        lastError = null;
        break;
      } catch (err) {
        lastError = err;
        await new Promise((resolve) => setTimeout(resolve, 800 * (attempt + 1)));
      }
    }

    if (lastError) {
      if (__DEV__) {
        console.warn('[kasir-push] gagal daftar token ke server', lastError);
      }
      throw lastError;
    }

    return token;
  })()
    .catch((err) => {
      if (__DEV__) {
        console.warn('[kasir-push] registerKasirPushToken failed', err);
      }
      return null;
    })
    .finally(() => {
      registerInFlight = null;
    });

  return registerInFlight;
}

/** True jika izin notifikasi belum diberikan. */
export async function isKasirPushPermissionDenied(): Promise<boolean> {
  const current = await Notifications.getPermissionsAsync();
  return current.status !== 'granted';
}

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
  const token = (await readLocalToken()) || cachedToken;

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
    await persistLocalToken(null);
  }
}
