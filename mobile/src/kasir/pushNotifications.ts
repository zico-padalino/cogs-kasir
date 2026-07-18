import Constants from 'expo-constants';
import * as Device from 'expo-device';
import * as Notifications from 'expo-notifications';
import { Platform } from 'react-native';
import { apiRequest } from '@/api/client';

Notifications.setNotificationHandler({
  handleNotification: async () => ({
    shouldShowAlert: true,
    shouldPlaySound: true,
    shouldSetBadge: true,
    shouldShowBanner: true,
    shouldShowList: true,
  }),
});

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

  await Notifications.setNotificationChannelAsync('kasir-orders', {
    name: 'Pesanan kasir',
    importance: Notifications.AndroidImportance.MAX,
    vibrationPattern: [0, 250, 180, 250],
    sound: 'default',
    enableVibrate: true,
    lockscreenVisibility: Notifications.AndroidNotificationVisibility.PUBLIC,
  });
}

export async function registerKasirPushToken(): Promise<string | null> {
  if (!Device.isDevice) {
    return null;
  }

  await ensureAndroidChannel();

  const current = await Notifications.getPermissionsAsync();
  let status = current.status;

  if (status !== 'granted') {
    const asked = await Notifications.requestPermissionsAsync();
    status = asked.status;
  }

  if (status !== 'granted') {
    return null;
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

/** Listener: tap notifikasi → buka kasir. */
export function addKasirNotificationResponseListener(
  onNewOrder: () => void,
): { remove: () => void } {
  return Notifications.addNotificationResponseReceivedListener((response) => {
    const data = response.notification.request.content.data as { type?: string } | undefined;
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
