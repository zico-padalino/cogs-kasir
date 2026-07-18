import { useEffect, useRef, useState } from 'react';
import { AppState, type AppStateStatus } from 'react-native';
import { kasirApi } from '@/api/kasir';
import { announceNewOrders } from '@/kasir/orderAlert';
import { takeNewPendingIds } from '@/kasir/pendingOrderTracker';

const POLL_MS = 5000;

export type KasirOrderAlertState = {
  title: string;
  message: string;
  orderId: number | null;
  pinLocked: boolean;
};

/** Poll pesanan + TTS — aman dipanggil di PIN maupun layar kasir lain. */
export function useKasirOrderAlerts(enabled: boolean) {
  const announcingRef = useRef(false);
  const [orderAlert, setOrderAlert] = useState<KasirOrderAlertState | null>(null);

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
        // ignore — PIN / absensi ditangani layar lain
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

  return { orderAlert, setOrderAlert };
}
