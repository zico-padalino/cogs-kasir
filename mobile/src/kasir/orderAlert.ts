import { Vibration } from 'react-native';
import * as Haptics from 'expo-haptics';
import * as Speech from 'expo-speech';
import type { PosOrder } from '@/api/types';
import { formatRupiah } from '@/utils/rupiah';

/** Pastikan TTS bisa bunyi meski layar fokus (halaman PIN). */
async function prepareSpeechPlayback(): Promise<void> {
  try {
    await Speech.stop();
  } catch {
    // ignore
  }
}

export type OrderAlertPayload = {
  title: string;
  message: string;
  speakText: string;
};

function orderLabel(order: PosOrder): string {
  const name = (order.customer_note || '').trim();
  if (name) {
    return name;
  }
  if (order.table?.label) {
    return `Meja ${order.table.label}`;
  }
  return 'Tanpa nama';
}

/** Susun teks notifikasi + ucapan AI (TTS) untuk pesanan baru. */
export function buildOrderAlert(orders: PosOrder[]): OrderAlertPayload {
  const count = orders.length;
  const first = orders[0];

  if (count === 1 && first) {
    const who = orderLabel(first);
    const number = first.order_number || String(first.id);
    const total = formatRupiah(first.total || 0);

    return {
      title: 'Pesanan baru masuk',
      message: `#${number} · ${who} · ${total}`,
      speakText: `Pesanan baru masuk, atas nama ${who}.`,
    };
  }

  const names = orders
    .slice(0, 3)
    .map(orderLabel)
    .join(', ');

  return {
    title: `${count} pesanan baru masuk`,
    message: 'Cek banner pesanan online di atas.',
    speakText: names
      ? `Pesanan baru masuk, atas nama ${names}.`
      : 'Pesanan baru masuk.',
  };
}

let speaking = false;

/** Bunyi AI (Text-to-Speech bahasa Indonesia) + getar. */
export async function announceNewOrders(orders: PosOrder[]): Promise<OrderAlertPayload | null> {
  if (orders.length === 0) {
    return null;
  }

  const alert = buildOrderAlert(orders);

  try {
    await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
  } catch {
    Vibration.vibrate([0, 220, 120, 220]);
  }

  try {
    await prepareSpeechPlayback();
    speaking = true;
    // Delay singkat agar stop() selesai sebelum speak (penting di layar PIN).
    await new Promise((resolve) => setTimeout(resolve, 80));
    Speech.speak(alert.speakText, {
      language: 'id-ID',
      pitch: 1.05,
      rate: 0.92,
      volume: 1.0,
      onDone: () => {
        speaking = false;
      },
      onStopped: () => {
        speaking = false;
      },
      onError: () => {
        speaking = false;
      },
    });
  } catch {
    speaking = false;
  }

  return alert;
}

export function stopOrderAnnouncement(): void {
  try {
    Speech.stop();
  } catch {
    // ignore
  }
  speaking = false;
}
