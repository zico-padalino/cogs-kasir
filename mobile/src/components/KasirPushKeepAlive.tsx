import { useEffect, useRef } from 'react';
import { AppState, Platform, type AppStateStatus } from 'react-native';
import { useAuth } from '@/auth';
import {
  startKasirListenMode,
  stopKasirListenMode,
} from '@/kasir/kasirListenMode';
import { registerKasirPushToken, unregisterKasirPushToken } from '@/kasir/pushNotifications';

/**
 * Setelah login kasir:
 * - daftar FCM/Expo push token
 * - jalankan Mode Kasir (foreground) agar notifikasi + suara AI hidup di luar app
 */
export function KasirPushKeepAlive() {
  const { user, loading } = useAuth();
  const hasKasir = !!user?.has_kasir;
  const startedRef = useRef(false);

  useEffect(() => {
    if (loading) {
      return;
    }

    if (!hasKasir) {
      startedRef.current = false;
      void unregisterKasirPushToken();
      void stopKasirListenMode();
      return;
    }

    const boot = async () => {
      await registerKasirPushToken();
      if (Platform.OS === 'android') {
        const ok = await startKasirListenMode();
        startedRef.current = ok;
      }
    };

    void boot();

    const onAppState = (state: AppStateStatus) => {
      if (state === 'active') {
        void registerKasirPushToken();
        if (Platform.OS === 'android' && !startedRef.current) {
          void startKasirListenMode().then((ok) => {
            startedRef.current = ok;
          });
        }
      }
    };
    const sub = AppState.addEventListener('change', onAppState);

    const timer = setInterval(
      () => {
        void registerKasirPushToken();
        if (Platform.OS === 'android') {
          void startKasirListenMode().then((ok) => {
            startedRef.current = ok;
          });
        }
      },
      6 * 60 * 60 * 1000,
    );

    return () => {
      sub.remove();
      clearInterval(timer);
    };
  }, [hasKasir, loading, user?.id]);

  return null;
}
