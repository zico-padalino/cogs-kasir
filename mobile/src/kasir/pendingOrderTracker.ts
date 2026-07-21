/** Dedup ID pesanan antar layar (PIN / POS / menu) agar TTS tidak dobel. */

let knownIds = new Set<number>();
let primed = false;
let openOrderId: number | null = null;

/** Seed awal (POS refresh) — tidak memicu “pesanan baru”. */
export function seedPendingIds(ids: number[]): void {
  if (primed) {
    return;
  }
  knownIds = new Set(ids.map(Number).filter((id) => Number.isFinite(id)));
  primed = true;
}

/**
 * Sinkronkan semua ID menunggu, kembalikan ID baru yang boleh di-alert.
 * @param ids semua order menunggu (online + open bill + siap antar)
 * @param alertIds subset yang memicu notifikasi (biasanya hanya online)
 */
export function takeNewPendingIds(ids: number[], alertIds?: number[]): number[] {
  const next = ids.map(Number).filter((id) => Number.isFinite(id));
  const alertPool = (alertIds ?? ids).map(Number).filter((id) => Number.isFinite(id));

  if (!primed) {
    knownIds = new Set(next);
    primed = true;
    return [];
  }

  const fresh = alertPool.filter((id) => !knownIds.has(id));
  knownIds = new Set(next);
  return fresh;
}

export function resetPendingTracker(): void {
  knownIds = new Set();
  primed = false;
  openOrderId = null;
}

export function setPendingOpenOrderId(id: number | null): void {
  openOrderId = id;
}

export function consumePendingOpenOrderId(): number | null {
  const id = openOrderId;
  openOrderId = null;
  return id;
}
