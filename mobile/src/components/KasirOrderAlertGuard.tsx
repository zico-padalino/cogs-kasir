import { usePathname, useRouter } from 'expo-router';
import { useEffect, useRef, useState, type ReactNode } from 'react';
import { AppState, type AppStateStatus, View } from 'react-native';
import { kasirApi } from '@/api/kasir';
import { useAuth } from '@/auth';
import { OrderToast } from '@/components/OrderToast';
import { announceNewOrders } from '@/kasir/orderAlert';
import {
  setPendingOpenOrderId,
  takeNewPendingIds,
} from '@/kasir/pendingOrderTracker';

const POLL_MS = 5000;

/**
 * Poll pesanan online di semua layar kasir (termasuk PIN).
 * TTS + toast tetap jalan meski sesi terkunci.
 */
export function KasirOrderAlertGuard({ children }: { children?: ReactNode }) {
  const { activeModule, user, pin } = useAuth();
  const pathname = usePathname();
  const router = useRouter();
  const announcingRef = useRef(false);
  const [orderAlert, setOrderAlert] = useState<{
    title: string;
    message: string;
    orderId: number | null;
    pinLocked: boolean;
  } | null>(null);

  const enabled =
    activeModule === 'kasir' &&
    !!user?.has_kasir &&
    !pathname.includes('/kasir/attendance');

  useEffect(() => {
    if (!enabled) {
      return;
    }

    const poll = async () => {
      try {
        const res = await kasirApi.poll();
        const data = res.data;
        const orders = data.orders || [];
        const ids = (data.order_ids || []).map(Number);
        const newIds = takeNewPendingIds(ids);

        if (newIds.length === 0 || announcingRef.current) {
          return;
        }

        const newOrders = orders.filter((o) => newIds.includes(o.id));
        const newest =
          newIds.includes(Number(data.latest_order_id)) && data.latest_order_id
            ? Number(data.latest_order_id)
            : Math.max(...newIds);
        const pinLocked = !data.unlocked;

        announcingRef.current = true;
        try {
          const alert = await announceNewOrders(
            newOrders.length > 0 ? newOrders : orders.slice(0, 1),
          );
          if (alert) {
            setOrderAlert({
              title: alert.title,
              message: pinLocked
                ? `${alert.message} · Masukkan PIN untuk membuka.`
                : alert.message,
              orderId: Number.isFinite(newest) ? newest : null,
              pinLocked,
            });
          }
        } finally {
          announcingRef.current = false;
        }
      } catch {
        // 423 / offline diabaikan — guard PIN menangani redirect
      }
    };

    void poll();
    const timer = setInterval(() => {
      void poll();
    }, POLL_MS);

    const onAppState = (state: AppStateStatus) => {
      if (state === 'active') {
        void poll();
      }
    };
    const sub = AppState.addEventListener('change', onAppState);

    return () => {
      clearInterval(timer);
      sub.remove();
    };
  }, [enabled]);

  return (
    <View style={{ flex: 1 }}>
      {children}
      <OrderToast
        title={orderAlert?.title ?? null}
        message={orderAlert?.message ?? null}
        sticky
        actionLabel={orderAlert?.pinLocked ? 'Masukkan PIN' : 'Buka Pesanan'}
        onAction={
          orderAlert
            ? async () => {
                if (orderAlert.pinLocked) {
                  router.replace('/kasir/pin' as never);
                  return;
                }
                if (orderAlert.orderId) {
                  setPendingOpenOrderId(orderAlert.orderId);
                }
                router.replace('/kasir' as never);
              }
            : undefined
        }
        onDismiss={() => setOrderAlert(null)}
      />
    </View>
  );
}
