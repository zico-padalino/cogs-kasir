import { useEffect, useRef } from 'react';
import { AppState, Platform, type AppStateStatus } from 'react-native';
import { useAuth } from '@/auth';
import {
  startDapurListenMode,
  stopDapurListenMode,
} from '@/dapur/dapurListenMode';
import {
  startKasirListenMode,
  stopKasirListenMode,
} from '@/kasir/kasirListenMode';
import { registerKasirPushToken, unregisterKasirPushToken } from '@/kasir/pushNotifications';

/**
 * Setelah login kasir/dapur:
 * - daftar FCM/Expo push token
 * - jalankan Mode Dengar (foreground) sesuai modul aktif
 */
export function KasirPushKeepAlive() {
  const { user, loading, activeModule } = useAuth();
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
      void stopDapurListenMode();
      return;
    }

    const boot = async () => {
      await registerKasirPushToken();
      if (Platform.OS !== 'android') {
        return;
      }

      if (activeModule === 'dapur') {
        await stopKasirListenMode();
        const ok = await startDapurListenMode();
        startedRef.current = ok;
        return;
      }

      await stopDapurListenMode();
      const ok = await startKasirListenMode();
      startedRef.current = ok;
    };

    void boot();

    const onAppState = (state: AppStateStatus) => {
      if (state === 'active') {
        void boot();
      }
    };
    const sub = AppState.addEventListener('change', onAppState);

    const timer = setInterval(() => {
      void boot();
    }, 6 * 60 * 60 * 1000);

    return () => {
      sub.remove();
      clearInterval(timer);
    };
  }, [hasKasir, loading, user?.id, activeModule]);

  return null;
}
