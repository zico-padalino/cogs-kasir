import type { OrderItem, PosOrder } from '@/api/types';
import {
  announceSpeakText,
  type OrderAlertPayload,
  stopOrderAnnouncement,
  warmupOrderSpeech,
} from '@/kasir/orderAlert';
import * as Haptics from 'expo-haptics';

export { warmupOrderSpeech, stopOrderAnnouncement, announceSpeakText };

function formatQty(qty: number): string {
  if (!Number.isFinite(qty)) {
    return '1';
  }
  if (Math.abs(qty - Math.round(qty)) < 0.001) {
    return String(Math.round(qty));
  }
  return String(qty).replace(/\.?0+$/, '');
}

function itemSpeakLabel(item: OrderItem): string {
  const name = (item.product_name || 'Item').trim() || 'Item';
  return `${formatQty(Number(item.quantity || 1))} ${name}`;
}

function menuSpeakList(order: PosOrder): string {
  const items = order.items || [];
  if (items.length === 0) {
    return '';
  }
  return items.map(itemSpeakLabel).join(', ');
}

function orderWhere(order: PosOrder): string {
  const name = (order.customer_note || '').trim();
  if (name) {
    return name;
  }
  if (order.table?.label) {
    return `Meja ${order.table.label}`;
  }
  return order.order_number || 'Pesanan';
}

/** Susun teks toast + ucapan AI dari nama menu. */
export function buildKitchenOrderAlert(orders: PosOrder[]): OrderAlertPayload {
  const count = orders.length;
  const first = orders[0];

  if (count === 1 && first) {
    const menus = menuSpeakList(first);
    const where = orderWhere(first);
    const number = first.order_number || String(first.id);

    return {
      title: 'Pesanan dapur baru',
      message: menus ? `#${number} · ${where} · ${menus}` : `#${number} · ${where}`,
      speakText: menus ? `Pesanan dapur: ${menus}.` : `Pesanan dapur baru, ${where}.`,
    };
  }

  const menus = orders
    .slice(0, 2)
    .map(menuSpeakList)
    .filter(Boolean)
    .join('. ');

  return {
    title: `${count} pesanan dapur baru`,
    message: menus || 'Cek layar dapur.',
    speakText: menus
      ? `Ada ${count} pesanan dapur. ${menus}.`
      : `Ada ${count} pesanan dapur baru.`,
  };
}

/** TTS dapur — baca nama menu (+ getar). */
export async function announceKitchenOrders(orders: PosOrder[]): Promise<OrderAlertPayload | null> {
  if (orders.length === 0) {
    return null;
  }

  const alert = buildKitchenOrderAlert(orders);
  const dedupeKey = `kitchen-${orders[0]?.id ?? alert.speakText}`;

  try {
    await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
  } catch {
    // vibrate fallback di announceSpeakText
  }

  await announceSpeakText(alert.speakText, dedupeKey);

  return alert;
}
