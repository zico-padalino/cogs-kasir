import { useEffect } from 'react';
import { AppState, type AppStateStatus } from 'react-native';
import { useAuth } from '@/auth';
import { registerKasirPushToken, unregisterKasirPushToken } from '@/kasir/pushNotifications';

/**
 * Setelah login akun kasir: pastikan push token selalu terdaftar di server
 * supaya notifikasi tetap jalan meski HP terkunci / app tidak dibuka.
 */
export function KasirPushKeepAlive() {
  const { user, loading } = useAuth();
  const hasKasir = !!user?.has_kasir;

  useEffect(() => {
    if (loading) {
      return;
    }

    if (!hasKasir) {
      void unregisterKasirPushToken();
      return;
    }

    void registerKasirPushToken();

    const onAppState = (state: AppStateStatus) => {
      if (state === 'active') {
        void registerKasirPushToken();
      }
    };
    const sub = AppState.addEventListener('change', onAppState);

    // Perbarui token ke server berkala selagi sesi login hidup.
    const timer = setInterval(
      () => {
        void registerKasirPushToken();
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
