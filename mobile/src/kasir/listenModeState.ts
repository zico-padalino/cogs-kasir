/** Shared state: satu foreground service dipakai kasir ATAU dapur. */
export type ListenKind = 'dapur' | 'kasir';

let activeKind: ListenKind | null = null;

export function getListenModeKind(): ListenKind | null {
  return activeKind;
}

export function setListenModeKind(kind: ListenKind | null): void {
  activeKind = kind;
}
