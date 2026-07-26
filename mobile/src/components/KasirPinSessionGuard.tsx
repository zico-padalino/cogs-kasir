import { useEffect, useRef, type ReactNode } from 'react';
import { AppState, type AppStateStatus, View } from 'react-native';
import { usePathname, useRouter } from 'expo-router';
import { pinApi } from '@/api/kasir';
import { useAuth } from '@/auth';

/** Hemat EP: touch server jarang; timer idle lokal yang utama. */
const TOUCH_THROTTLE_MS = 120_000;

/**
 * Sesi PIN kasir (bukan dapur):
 * - timer lokal dari remaining_seconds (tanpa poll /pin/status berkala)
 * - sync status hanya saat app kembali aktif
 * - sentuhan → pin/touch jarang (throttle 2 menit)
 */
export function KasirPinSessionGuard({ children }: { children?: ReactNode }) {
  const { activeModule, pin, setPin, lockPinSession } = useAuth();
  const pathname = usePathname();
  const router = useRouter();
  const expiryTimer = useRef<ReturnType<typeof setTimeout> | null>(null);
  const touchInFlight = useRef(false);
  const lastTouchAt = useRef(0);
  const ttlMinutes = useRef(20);

  const onPinPage = pathname.includes('/kasir/pin');
  const onUbahPin = pathname.includes('/kasir/ubah-pin');
  const onAttendance = pathname.includes('/kasir/attendance');
  // PIN hanya untuk modul kasir — dapur bebas tanpa PIN.
  const guardActive =
    activeModule === 'kasir' &&
    !onPinPage &&
    !onUbahPin &&
    !onAttendance;

  const goToPin = () => {
    lockPinSession();
    router.replace('/kasir/pin' as never);
  };

  const scheduleExpiry = (remainingSeconds: number) => {
    if (expiryTimer.current) {
      clearTimeout(expiryTimer.current);
      expiryTimer.current = null;
    }

    if (!guardActive) {
      return;
    }

    if (remainingSeconds <= 0) {
      goToPin();
      return;
    }

    expiryTimer.current = setTimeout(() => {
      goToPin();
    }, remainingSeconds * 1000 + 300);
  };

  const touchSession = async () => {
    if (!guardActive || !pin?.unlocked) {
      return;
    }

    const now = Date.now();
    // Selalu reset timer lokal dari sentuhan terakhir.
    scheduleExpiry(Math.max(1, ttlMinutes.current) * 60);

    if (touchInFlight.current || now - lastTouchAt.current < TOUCH_THROTTLE_MS) {
      return;
    }

    touchInFlight.current = true;
    lastTouchAt.current = now;

    try {
      const res = await pinApi.touch();
      const data = res.data;
      if (typeof data.ttl_minutes === 'number' && data.ttl_minutes > 0) {
        ttlMinutes.current = data.ttl_minutes;
      }
      setPin(data);
      if (!data.unlocked || (typeof data.remaining_seconds === 'number' && data.remaining_seconds <= 0)) {
        goToPin();
        return;
      }
      if (typeof data.remaining_seconds === 'number') {
        scheduleExpiry(data.remaining_seconds);
      }
    } catch {
      // 423 sudah di-handle listener global
    } finally {
      touchInFlight.current = false;
    }
  };

  useEffect(() => {
    if (!guardActive) {
      return;
    }
    if (!pin?.unlocked) {
      goToPin();
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [guardActive, pin?.unlocked]);

  useEffect(() => {
    if (expiryTimer.current) {
      clearTimeout(expiryTimer.current);
      expiryTimer.current = null;
    }

    if (!guardActive || !pin?.unlocked) {
      return;
    }

    const remaining = typeof pin.remaining_seconds === 'number' ? pin.remaining_seconds : null;
    if (remaining === null) {
      return;
    }

    scheduleExpiry(remaining);

    return () => {
      if (expiryTimer.current) {
        clearTimeout(expiryTimer.current);
        expiryTimer.current = null;
      }
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [guardActive, pin?.unlocked, pin?.remaining_seconds, pin?.expires_at]);

  useEffect(() => {
    if (!guardActive) {
      return;
    }

    const syncStatus = async () => {
      try {
        const res = await pinApi.status();
        const data = res.data;
        if (typeof data.ttl_minutes === 'number' && data.ttl_minutes > 0) {
          ttlMinutes.current = data.ttl_minutes;
        }
        setPin(data);
        if (!data.unlocked || (typeof data.remaining_seconds === 'number' && data.remaining_seconds <= 0)) {
          goToPin();
        }
      } catch {
        // 423 sudah di-handle listener global
      }
    };

    // Tanpa interval — hanya saat app kembali ke foreground.
    const onAppState = (state: AppStateStatus) => {
      if (state === 'active') {
        void syncStatus();
      }
    };
    const sub = AppState.addEventListener('change', onAppState);

    return () => {
      sub.remove();
    };
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [guardActive, setPin, lockPinSession, router]);

  return (
    <View
      style={{ flex: 1 }}
      onStartShouldSetResponderCapture={() => {
        if (guardActive && pin?.unlocked) {
          void touchSession();
        }
        return false;
      }}
      onMoveShouldSetResponderCapture={() => {
        if (guardActive && pin?.unlocked) {
          void touchSession();
        }
        return false;
      }}
    >
      {children}
    </View>
  );
}
