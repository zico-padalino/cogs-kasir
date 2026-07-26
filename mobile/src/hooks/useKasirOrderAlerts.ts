import { useEffect, useRef, useState } from 'react';
import { AppState, type AppStateStatus } from 'react-native';
import { kasirApi } from '@/api/kasir';
import { announceNewOrders } from '@/kasir/orderAlert';
import { onOrderSyncEvent } from '@/kasir/orderSyncEvents';
import { seedPendingIds, takeNewPendingIds } from '@/kasir/pendingOrderTracker';

export type KasirOrderAlertState = {
  title: string;
  message: string;
  orderId: number | null;
  pinLocked: boolean;
};

/**
 * Pull kasir on-demand: seed sekali + saat push new_order.
 * Interval hanya jika server kasir_poll_enabled=true.
 */
export function useKasirOrderAlerts(enabled: boolean) {
  const announcingRef = useRef(false);
  const [orderAlert, setOrderAlert] = useState<KasirOrderAlertState | null>(null);

  useEffect(() => {
    if (!enabled) {
      return;
    }

    let continuous = false;
    let timer: ReturnType<typeof setInterval> | null = null;

    const pull = async (opts?: { announceNew?: boolean }) => {
      try {
        const res = await kasirApi.poll();
        const data = res.data;
        continuous = !!data.kasir_poll_enabled;
        const orders = data.orders || [];
        const ids = (data.order_ids || []).map(Number);
        const notifyIds = (data.notify_order_ids || data.order_ids || []).map(Number);

        if (!opts?.announceNew) {
          seedPendingIds(ids);
          return;
        }

        const newIds = takeNewPendingIds(ids, notifyIds);
        if (newIds.length === 0 || announcingRef.current) {
          return;
        }

        const newOrders = orders.filter(
          (o) => newIds.includes(o.id) && o.source !== 'kasir' && o.status !== 'paid' && o.status !== 'served',
        );
        if (newOrders.length === 0) {
          return;
        }

        const newest =
          newIds.includes(Number(data.latest_order_id)) && data.latest_order_id
            ? Number(data.latest_order_id)
            : Math.max(...newIds);
        const pinLocked = !data.unlocked;

        announcingRef.current = true;
        try {
          const alert = await announceNewOrders(newOrders);
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
        // ignore
      }
    };

    void (async () => {
      await pull({ announceNew: false });
      if (continuous && !timer) {
        timer = setInterval(() => {
          void pull({ announceNew: true });
        }, 60_000);
      }
    })();

    const unsub = onOrderSyncEvent((event) => {
      if (event.type !== 'new_order' && event.type !== 'stock_out') {
        return;
      }
      void pull({ announceNew: true });
    });

    const onAppState = (state: AppStateStatus) => {
      if (state === 'active') {
        void pull({ announceNew: false });
      }
    };
    const sub = AppState.addEventListener('change', onAppState);

    return () => {
      unsub();
      sub.remove();
      if (timer) {
        clearInterval(timer);
      }
    };
  }, [enabled]);

  return { orderAlert, setOrderAlert };
}
