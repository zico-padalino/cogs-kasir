/** Dedup ID pesanan dapur agar TTS tidak dobel. */

let knownIds = new Set<number>();
let primed = false;

export function seedKitchenIds(ids: number[]): void {
  if (primed) {
    return;
  }
  knownIds = new Set(ids.map(Number).filter((id) => Number.isFinite(id)));
  primed = true;
}

export function takeNewKitchenIds(ids: number[], alertIds?: number[]): number[] {
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

export function resetKitchenTracker(): void {
  knownIds = new Set();
  primed = false;
}
