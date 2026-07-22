import { Vibration } from 'react-native';
import * as Haptics from 'expo-haptics';
import * as Speech from 'expo-speech';
import type { PosOrder } from '@/api/types';
import { formatRupiah } from '@/utils/rupiah';

const DEDUPE_MS = 12_000;
const VOICE_WARMUP_ATTEMPTS = 6;

let speaking = false;
let speakChain: Promise<void> = Promise.resolve();
let lastSpeakKey = '';
let lastSpeakAt = 0;
let cachedVoiceId: string | null | undefined;
let ttsWarmed = false;

function sleep(ms: number): Promise<void> {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

/** Inisialisasi engine TTS Android (sering kosong di call pertama). */
export async function warmupOrderSpeech(): Promise<void> {
  if (ttsWarmed) {
    return;
  }

  for (let i = 0; i < VOICE_WARMUP_ATTEMPTS; i += 1) {
    try {
      const voices = await Speech.getAvailableVoicesAsync();
      if (voices.length > 0) {
        cachedVoiceId = pickIndonesianVoice(voices);
        ttsWarmed = true;
        return;
      }
    } catch {
      // engine belum siap
    }
    await sleep(400);
  }

  // Meski daftar suara kosong, tetap izinkan speak (pakai language default).
  ttsWarmed = true;
}

function pickIndonesianVoice(
  voices: Speech.Voice[],
): string | null {
  const rank = (lang: string) => {
    const l = lang.toLowerCase().replace('_', '-');
    if (l === 'id-id' || l === 'in-id') return 3;
    if (l.startsWith('id') || l.startsWith('in')) return 2;
    return 0;
  };

  const sorted = [...voices].sort((a, b) => rank(b.language) - rank(a.language));
  const best = sorted.find((v) => rank(v.language) > 0);
  return best?.identifier ?? null;
}

async function resolveIndonesianVoice(): Promise<string | null> {
  if (cachedVoiceId !== undefined) {
    return cachedVoiceId;
  }

  await warmupOrderSpeech();
  if (cachedVoiceId !== undefined) {
    return cachedVoiceId;
  }

  try {
    const voices = await Speech.getAvailableVoicesAsync();
    cachedVoiceId = pickIndonesianVoice(voices);
  } catch {
    cachedVoiceId = null;
  }

  return cachedVoiceId ?? null;
}

async function prepareSpeechPlayback(): Promise<void> {
  try {
    const busy = await Speech.isSpeakingAsync();
    if (busy) {
      await Speech.stop();
      await sleep(180);
    }
  } catch {
    try {
      await Speech.stop();
      await sleep(180);
    } catch {
      // ignore
    }
  }
}

function speakOnce(
  text: string,
  options: Speech.SpeechOptions,
): Promise<'done' | 'stopped' | 'error'> {
  return new Promise((resolve) => {
    let settled = false;
    const finish = (result: 'done' | 'stopped' | 'error') => {
      if (settled) {
        return;
      }
      settled = true;
      resolve(result);
    };

    try {
      Speech.speak(text, {
        ...options,
        onDone: () => finish('done'),
        onStopped: () => finish('stopped'),
        onError: () => finish('error'),
      });
    } catch {
      finish('error');
    }

    // Safety: beberapa perangkat tidak memanggil callback — anggap gagal agar bisa retry/fallback.
    setTimeout(() => finish('error'), Math.max(10_000, text.length * 140));
  });
}

/** Hindari TTS dobel (poll + push) dalam beberapa detik — hanya setelah sukses. */
function shouldSkipDedupe(key: string): boolean {
  const now = Date.now();
  return Boolean(key && key === lastSpeakKey && now - lastSpeakAt < DEDUPE_MS);
}

function markSpoken(key: string): void {
  lastSpeakKey = key;
  lastSpeakAt = Date.now();
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

async function speakWithFallbacks(text: string): Promise<boolean> {
  await prepareSpeechPlayback();
  await sleep(120);

  const voice = await resolveIndonesianVoice();
  const attempts: Speech.SpeechOptions[] = [
    {
      language: 'id-ID',
      voice: voice || undefined,
      pitch: 1.05,
      rate: 0.92,
      volume: 1.0,
    },
    {
      language: 'id',
      pitch: 1.05,
      rate: 0.92,
      volume: 1.0,
    },
    {
      pitch: 1.05,
      rate: 0.92,
      volume: 1.0,
    },
  ];

  for (const opts of attempts) {
    speaking = true;
    const result = await speakOnce(text, opts);
    speaking = false;

    if (result === 'done') {
      return true;
    }

    // error / stopped → coba fallback berikutnya
    await sleep(220);
    await prepareSpeechPlayback();
  }

  return false;
}

/** TTS dari teks siap pakai (push saat HP terkunci / background). */
export async function announceSpeakText(speakText: string, dedupeKey = ''): Promise<void> {
  const text = speakText.trim();
  if (!text) {
    return;
  }

  const key = dedupeKey || text;
  if (shouldSkipDedupe(key)) {
    return;
  }

  speakChain = speakChain
    .catch(() => undefined)
    .then(async () => {
      // Cek ulang setelah antrean — push/poll sering datang hampir bersamaan.
      if (shouldSkipDedupe(key)) {
        return;
      }

      try {
        Vibration.vibrate([0, 220, 120, 220]);
      } catch {
        // ignore
      }

      const ok = await speakWithFallbacks(text);
      if (ok) {
        markSpoken(key);
      }
      // Gagal: jangan mark dedupe → poll berikutnya bisa coba lagi.
    });

  await speakChain;
}

/** Bunyi AI (Text-to-Speech bahasa Indonesia) + getar. */
export async function announceNewOrders(orders: PosOrder[]): Promise<OrderAlertPayload | null> {
  if (orders.length === 0) {
    return null;
  }

  const alert = buildOrderAlert(orders);
  const dedupeKey = String(orders[0]?.id ?? alert.speakText);

  try {
    await Haptics.notificationAsync(Haptics.NotificationFeedbackType.Success);
  } catch {
    // fallback di announceSpeakText
  }

  await announceSpeakText(alert.speakText, dedupeKey);

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
