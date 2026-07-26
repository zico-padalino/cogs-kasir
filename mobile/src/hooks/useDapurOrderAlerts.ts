import { useEffect, useState } from 'react';
import { AppState, type AppStateStatus } from 'react-native';
import { kasirApi } from '@/api/kasir';
import { buildKitchenOrderAlert } from '@/dapur/kitchenAlert';
import { seedKitchenIds, takeNewKitchenIds } from '@/dapur/kitchenOrderTracker';
import { onOrderSyncEvent } from '@/kasir/orderSyncEvents';

export type DapurOrderAlertState = {
  title: string;
  message: string;
  orderId: number | null;
  pinLocked: boolean;
};

/**
 * Pull dapur on-demand: seed sekali + saat push kitchen_order.
 * TTS dari push; hook ini toast + sync tracker (hindari dobel suara).
 */
export function useDapurOrderAlerts(enabled: boolean) {
  const [orderAlert, setOrderAlert] = useState<DapurOrderAlertState | null>(null);

  useEffect(() => {
    if (!enabled) {
      return;
    }

    const pull = async (opts?: { showToast?: boolean }) => {
      try {
        const res = await kasirApi.dapurPoll();
        const data = res.data;
        const orders = data.orders || [];
        const ids = (data.order_ids || []).map(Number);
        const notifyIds = (data.notify_order_ids || data.order_ids || []).map(Number);

        if (!opts?.showToast) {
          seedKitchenIds(ids);
          return;
        }

        const newIds = takeNewKitchenIds(ids, notifyIds);
        if (newIds.length === 0) {
          return;
        }

        const newOrders = orders.filter((o) => newIds.includes(o.id));
        if (newOrders.length === 0) {
          return;
        }

        const newest =
          newIds.includes(Number(data.latest_order_id)) && data.latest_order_id
            ? Number(data.latest_order_id)
            : Math.max(...newIds);
        const pinLocked = false;
        const alert = buildKitchenOrderAlert(newOrders);
        setOrderAlert({
          title: alert.title,
          message: alert.message,
          orderId: Number.isFinite(newest) ? newest : null,
          pinLocked,
        });
      } catch {
        // ignore
      }
    };

    void pull({ showToast: false });

    const unsub = onOrderSyncEvent((event) => {
      if (event.type !== 'kitchen_order') {
        return;
      }
      void pull({ showToast: true });
    });

    const onAppState = (state: AppStateStatus) => {
      if (state === 'active') {
        void pull({ showToast: false });
      }
    };
    const sub = AppState.addEventListener('change', onAppState);

    return () => {
      unsub();
      sub.remove();
    };
  }, [enabled]);

  return { orderAlert, setOrderAlert };
}
