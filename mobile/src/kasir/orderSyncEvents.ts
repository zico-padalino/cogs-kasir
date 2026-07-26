/**
 * Push → pull sekali (tanpa poll berkala) untuk hemat EP/NPROC hosting.
 */
export type OrderSyncType = 'new_order' | 'kitchen_order' | 'stock_out';

export type OrderSyncEvent = {
  type: OrderSyncType;
  orderId?: number | null;
};

type Handler = (event: OrderSyncEvent) => void;

const handlers = new Set<Handler>();

export function onOrderSyncEvent(handler: Handler): () => void {
  handlers.add(handler);
  return () => {
    handlers.delete(handler);
  };
}

export function emitOrderSyncEvent(event: OrderSyncEvent): void {
  handlers.forEach((handler) => {
    try {
      handler(event);
    } catch {
      // ignore listener errors
    }
  });
}
