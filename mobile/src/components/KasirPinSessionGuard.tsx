import { useEffect, useRef } from 'react';
import { AppState, type AppStateStatus } from 'react-native';
import { usePathname, useRouter } from 'expo-router';
import { pinApi } from '@/api/kasir';
import { useAuth } from '@/auth';

/**
 * Mirror web kasir-notifications.js:
 * - poll /kasir/pin/status
 * - jika unlocked=false / remaining <= 0 → halaman PIN
 * - schedule redirect saat sisa waktu habis
 */
export function KasirPinSessionGuard() {
  const { activeModule, pin, setPin, lockPinSession } = useAuth();
  const pathname = usePathname();
  const router = useRouter();
  const expiryTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const pollTimer = useRef<ReturnType<typeof setInterval> | null>(null);

  const onPinPage = pathname.includes('/kasir/pin');
  const onAttendance = pathname.includes('/kasir/attendance');

  useEffect(() => {
    if (activeModule !== 'kasir') {
      return;
    }
    if (onPinPage || onAttendance) {
      return;
    }
    if (!pin?.unlocked) {
      lockPinSession();
      router.replace('/kasir/pin' as never);
    }
  }, [activeModule, onPinPage, onAttendance, pin?.unlocked, lockPinSession, router]);

  useEffect(() => {
    if (expiryTimer.current) {
      clearTimeout(expiryTimer.current);
      expiryTimer.current = null;
    }

    if (activeModule !== 'kasir' || onPinPage || onAttendance || !pin?.unlocked) {
      return;
    }

    const remaining = typeof pin.remaining_seconds === 'number' ? pin.remaining_seconds : null;
    if (remaining === null) {
      return;
    }

    if (remaining <= 0) {
      lockPinSession();
      router.replace('/kasir/pin' as never);
      return;
    }

    expiryTimer.current = setTimeout(() => {
      lockPinSession();
      router.replace('/kasir/pin' as never);
    }, remaining * 1000 + 300);

    return () => {
      if (expiryTimer.current) {
        clearTimeout(expiryTimer.current);
        expiryTimer.current = null;
      }
    };
  }, [
    activeModule,
    onPinPage,
    onAttendance,
    pin?.unlocked,
    pin?.remaining_seconds,
    pin?.expires_at,
    lockPinSession,
    router,
  ]);

  useEffect(() => {
    if (activeModule !== 'kasir' || onPinPage || onAttendance) {
      return;
    }

    const syncStatus = async () => {
      try {
        const res = await pinApi.status();
        const data = res.data;
        setPin(data);
        if (!data.unlocked || (typeof data.remaining_seconds === 'number' && data.remaining_seconds <= 0)) {
          lockPinSession();
          router.replace('/kasir/pin' as never);
        }
      } catch {
        // 423 sudah di-handle listener global
      }
    };

    void syncStatus();
    pollTimer.current = setInterval(() => {
      void syncStatus();
    }, 30_000);

    const onAppState = (state: AppStateStatus) => {
      if (state === 'active') {
        void syncStatus();
      }
    };
    const sub = AppState.addEventListener('change', onAppState);

    return () => {
      if (pollTimer.current) {
        clearInterval(pollTimer.current);
        pollTimer.current = null;
      }
      sub.remove();
    };
  }, [activeModule, onPinPage, onAttendance, setPin, lockPinSession, router]);

  return null;
}
