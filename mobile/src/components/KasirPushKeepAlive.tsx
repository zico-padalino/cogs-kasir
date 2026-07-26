import { useEffect, useRef } from 'react';
import { AppState, Platform, type AppStateStatus } from 'react-native';
import { useAuth } from '@/auth';
import {
  forceStopListenMode,
  isDapurListenModeRunning,
  startDapurListenMode,
  stopDapurListenMode,
} from '@/dapur/dapurListenMode';
import {
  isKasirListenModeRunning,
  startKasirListenMode,
  stopKasirListenMode,
} from '@/kasir/kasirListenMode';
import { registerKasirPushToken, unregisterKasirPushToken } from '@/kasir/pushNotifications';

/**
 * Setelah login kasir/dapur:
 * - daftar FCM/Expo push token (throttle)
 * - pastikan Mode Dengar hidup — tanpa restart berulang (hindari app “keluar sendiri”)
 */
export function KasirPushKeepAlive() {
  const { user, loading, activeModule } = useAuth();
  const hasKasir = !!user?.has_kasir;
  const startedRef = useRef(false);
  const moduleRef = useRef(activeModule);
  moduleRef.current = activeModule;

  useEffect(() => {
    if (loading) {
      return;
    }

    if (!hasKasir) {
      startedRef.current = false;
      void unregisterKasirPushToken();
      void forceStopListenMode();
      return;
    }

    const ensureListenMode = async () => {
      if (Platform.OS !== 'android') {
        return;
      }

      const module = moduleRef.current;
      if (module === 'dapur') {
        if (await isDapurListenModeRunning()) {
          startedRef.current = true;
          return;
        }
        await stopKasirListenMode();
        startedRef.current = await startDapurListenMode();
        return;
      }

      if (module === 'kasir') {
        if (await isKasirListenModeRunning()) {
          startedRef.current = true;
          return;
        }
        await stopDapurListenMode();
        startedRef.current = await startKasirListenMode();
      }
    };

    const boot = async () => {
      await registerKasirPushToken();
      await ensureListenMode();
    };

    void boot();

    const onAppState = (state: AppStateStatus) => {
      if (state === 'active') {
        // Segarkan registrasi token sekali saat app dibuka, lalu pastikan
        // mode dengar masih hidup. Tidak ada polling endpoint pesanan.
        void registerKasirPushToken();
        void ensureListenMode();
      }
    };
    const sub = AppState.addEventListener('change', onAppState);

    return () => {
      sub.remove();
    };
  }, [hasKasir, loading, user?.id, activeModule]);

  return null;
}
