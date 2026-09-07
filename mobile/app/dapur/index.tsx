import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  AppState,
  type AppStateStatus,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  useWindowDimensions,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { kasirApi } from '@/api/kasir';
import type { OrderItem, PosOrder } from '@/api/types';
import { reportApiError, useAuth } from '@/auth';
import { AppScaffold } from '@/components/AppScaffold';
import { seedKitchenIds } from '@/dapur/kitchenOrderTracker';
import { onOrderSyncEvent } from '@/kasir/orderSyncEvents';
import { colors, font, fontDisplay, radius, spacing } from '@/theme';

/** Skala teks dapur: phone (full-width) vs tablet (≥700). */
function dapurType(wide: boolean) {
  return {
    orderNumber: wide ? 18 : 20,
    customer: wide ? 17 : 19,
    elapsed: wide ? 17 : 19,
    badge: wide ? 11 : 12,
    chip: wide ? 14 : 16,
    orderType: wide ? 16 : 18,
    qty: wide ? 17 : 20,
    itemName: wide ? 17 : 20,
    itemNameLine: wide ? 22 : 26,
    itemNote: wide ? 14 : 16,
    itemAddon: wide ? 14 : 16,
    serve: wide ? 15 : 17,
    check: wide ? 44 : 48,
  } as const;
}

function formatQty(qty: number): string {
  if (Math.abs(qty - Math.round(qty)) < 0.001) {
    return String(Math.round(qty));
  }
  return String(qty);
}

function categoryLabel(category?: string | null): string | null {
  const value = (category || '').trim().toLowerCase();
  if (!value) return null;
  if (value === 'makanan') return 'Makanan';
  if (value === 'snack') return 'Snack';
  return value.charAt(0).toUpperCase() + value.slice(1);
}

function elapsedLabel(iso?: string | null): string {
  if (!iso) return '—';
  const start = new Date(iso).getTime();
  if (!Number.isFinite(start)) return '—';
  const seconds = Math.max(0, Math.floor((Date.now() - start) / 1000));
  const h = Math.floor(seconds / 3600);
  const m = Math.floor((seconds % 3600) / 60);
  const s = seconds % 60;
  if (h > 0) return `${h}j ${String(m).padStart(2, '0')}m`;
  return `${m}:${String(s).padStart(2, '0')}`;
}

function startedAt(order: PosOrder): string | null {
  return order.paid_at || order.created_at || null;
}

function splitNotes(notes?: string | null): { customer?: string; addons: string[] } {
  const raw = (notes || '').trim();
  if (!raw) return { addons: [] };
  const parts = raw.split(' · ').map((p) => p.trim()).filter(Boolean);
  const customer: string[] = [];
  let addonsRaw: string | null = null;
  for (const part of parts) {
    if (addonsRaw === null && part.startsWith('+')) {
      addonsRaw = part;
      continue;
    }
    customer.push(part);
  }
  const addons = addonsRaw
    ? addonsRaw.split(/\s+(?=\+)/).map((s) => s.trim()).filter(Boolean)
    : [];
  return {
    customer: customer.join(' · ') || undefined,
    addons,
  };
}

export default function DapurBoardScreen() {
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const isWide = width >= 700;
  const type = useMemo(() => dapurType(isWide), [isWide]);
  const { pin } = useAuth();
  const [orders, setOrders] = useState<PosOrder[]>([]);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [busyItemId, setBusyItemId] = useState<number | null>(null);
  const [nowTick, setNowTick] = useState(0);

  const load = useCallback(async (opts?: { soft?: boolean }) => {
    if (!opts?.soft) {
      setLoading(true);
    }
    try {
      const res = await kasirApi.dapurPoll();
      setOrders(res.data.orders || []);
      seedKitchenIds((res.data.order_ids || []).map(Number));
    } catch (err) {
      reportApiError(err, 'Gagal memuat dapur');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, []);

  useEffect(() => {
    void load();
    // Push kitchen_order memicu satu kali pull; app aktif juga sinkron satu kali.
    const unsub = onOrderSyncEvent((event) => {
      if (event.type === 'kitchen_order') {
        void load({ soft: true });
      }
    });
    const onAppState = (state: AppStateStatus) => {
      if (state === 'active') {
        void load({ soft: true });
      }
    };
    const sub = AppState.addEventListener('change', onAppState);

    return () => {
      unsub();
      sub.remove();
    };
  }, [load]);

  useEffect(() => {
    const timer = setInterval(() => setNowTick((n) => n + 1), 1000);
    return () => clearInterval(timer);
  }, []);

  const toggleItem = async (item: OrderItem) => {
    if (busyItemId) return;
    setBusyItemId(item.id);
    try {
      const next = !item.is_delivered;
      const res = await kasirApi.setItemDelivered(item.id, next);
      const updated = res.data;
      if (updated.status === 'served') {
        setOrders((prev) => prev.filter((o) => o.id !== updated.id));
      } else {
        setOrders((prev) =>
          prev.map((order) => {
            if (order.id !== updated.id) return order;

            // Endpoint ceklis dipakai juga oleh kasir dan mengembalikan semua
            // kategori. Pertahankan daftar tiket dapur yang sudah difilter API.
            return {
              ...order,
              ...updated,
              items: (order.items || []).map((row) =>
                row.id === item.id ? { ...row, is_delivered: next } : row,
              ),
            };
          }),
        );
      }
    } catch (err) {
      reportApiError(err, 'Gagal ceklis item');
    } finally {
      setBusyItemId(null);
    }
  };

  const markServed = (order: PosOrder) => {
    Alert.alert('Tandai selesai', `Pesanan ${order.order_number} selesai / siap antar?`, [
      { text: 'Batal', style: 'cancel' },
      {
        text: 'Selesai',
        onPress: () => {
          void (async () => {
            try {
              await kasirApi.markServed(order.id);
              setOrders((prev) => prev.filter((o) => o.id !== order.id));
            } catch (err) {
              reportApiError(err, 'Gagal menandai selesai');
            }
          })();
        },
      },
    ]);
  };

  const countLabel = useMemo(() => `${orders.length} pesanan`, [orders.length]);

  return (
    <AppScaffold
      moduleType="dapur"
      title="Layar Dapur"
      subtitle={pin?.operator_name ? `Operator · ${pin.operator_name}` : 'Antrian masak & siap antar'}
    >
      <View style={styles.topMeta}>
        <View style={styles.countPill}>
          <Text style={styles.countText}>{countLabel}</Text>
        </View>
        <Text style={styles.hint}>Suara AI membacakan nama menu pesanan baru</Text>
      </View>

      {loading && orders.length === 0 ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.brand600} />
        </View>
      ) : (
        <ScrollView
          contentContainerStyle={[
            styles.list,
            { paddingBottom: Math.max(insets.bottom, spacing.xl) + 24 },
          ]}
          refreshControl={
            <RefreshControl
              refreshing={refreshing}
              onRefresh={() => {
                setRefreshing(true);
                void load({ soft: true });
              }}
              tintColor={colors.brand600}
            />
          }
        >
          {orders.length === 0 ? (
            <View style={styles.empty}>
              <Text style={styles.emptyTitle}>Belum ada pesanan dapur</Text>
              <Text style={styles.emptyHint}>
                Pesanan muncul setelah open bill atau pembayaran di kasir. Suara AI akan membaca nama menunya.
              </Text>
            </View>
          ) : (
            <View style={[styles.grid, isWide && styles.gridWide]}>
              {orders.map((order) => {
                const items = order.items || [];
                const done = items.filter((i) => i.is_delivered).length;
                const elapsed = elapsedLabel(startedAt(order));
                void nowTick;
                const isBill = order.status === 'unpaid' || order.is_open_bill;
                return (
                  <View
                    key={order.id}
                    style={[
                      styles.ticket,
                      isWide && styles.ticketWide,
                      isBill ? styles.ticketBill : styles.ticketPaid,
                    ]}
                  >
                    <View style={styles.ticketHead}>
                      <View style={{ flex: 1, minWidth: 0 }}>
                        <View style={styles.numberRow}>
                          <Text style={[styles.orderNumber, { fontSize: type.orderNumber }]} numberOfLines={1}>
                            {order.order_number}
                          </Text>
                          {order.order_type_label ? (
                            <Text style={[styles.orderType, { fontSize: type.orderType }]} numberOfLines={1}>
                              {order.order_type_icon || ''} {order.order_type_label}
                            </Text>
                          ) : null}
                        </View>
                        <Text style={[styles.customer, { fontSize: type.customer }]} numberOfLines={2}>
                          {order.customer_note?.trim() || 'Tanpa nama'}
                        </Text>
                      </View>
                      <View style={styles.headSide}>
                        <Text style={[styles.elapsed, { fontSize: type.elapsed }]}>{elapsed}</Text>
                        <Text
                          style={[
                            styles.badge,
                            { fontSize: type.badge },
                            isBill ? styles.badgeBill : styles.badgePaid,
                          ]}
                        >
                          {isBill ? 'Open' : 'Bayar'}
                        </Text>
                      </View>
                    </View>

                    <View style={styles.chips}>
                      {order.table?.label ? (
                        <Text
                          style={[styles.chip, styles.chipTable, { fontSize: type.chip }]}
                          numberOfLines={1}
                        >
                          🪑 {order.table.label}
                        </Text>
                      ) : null}
                      <Text style={[styles.chip, { fontSize: type.chip }]}>
                        {done}/{items.length} siap
                      </Text>
                    </View>

                    <View style={styles.items}>
                      {items.map((item) => {
                        const notes = splitNotes(item.notes);
                        const doneItem = !!item.is_delivered;
                        const category = categoryLabel(item.menu_category);
                        return (
                          <Pressable
                            key={item.id}
                            style={[styles.itemRow, doneItem && styles.itemDone]}
                            onPress={() => void toggleItem(item)}
                            disabled={busyItemId === item.id || !order.can_checklist_delivered}
                          >
                            <View
                              style={[
                                styles.check,
                                { width: type.check, height: type.check },
                                doneItem && styles.checkOn,
                              ]}
                            >
                              <Text style={styles.checkText}>{doneItem ? '✓' : ''}</Text>
                            </View>
                            <Text style={[styles.qty, { fontSize: type.qty }]}>
                              {formatQty(item.quantity)}×
                            </Text>
                            <View style={{ flex: 1, minWidth: 0 }}>
                              <Text
                                style={[
                                  styles.itemName,
                                  { fontSize: type.itemName, lineHeight: type.itemNameLine },
                                  doneItem && styles.itemNameDone,
                                ]}
                                numberOfLines={3}
                              >
                                {item.product_name || 'Item'}
                              </Text>
                              {category ? (
                                <Text
                                  style={[
                                    styles.itemCategory,
                                    item.menu_category?.trim().toLowerCase() === 'snack' &&
                                      styles.itemCategorySnack,
                                  ]}
                                >
                                  {category}
                                </Text>
                              ) : null}
                              {notes.customer ? (
                                <Text
                                  style={[styles.itemNote, { fontSize: type.itemNote }]}
                                  numberOfLines={3}
                                >
                                  {notes.customer}
                                </Text>
                              ) : null}
                              {notes.addons.map((addon) => (
                                <Text
                                  key={addon}
                                  style={[styles.itemAddon, { fontSize: type.itemAddon }]}
                                  numberOfLines={2}
                                >
                                  {addon}
                                </Text>
                              ))}
                            </View>
                          </Pressable>
                        );
                      })}
                    </View>

                    {order.can_mark_served ? (
                      <Pressable style={styles.serveBtn} onPress={() => markServed(order)}>
                        <Text style={[styles.serveText, { fontSize: type.serve }]}>Tandai selesai</Text>
                      </Pressable>
                    ) : null}
                  </View>
                );
              })}
            </View>
          )}
        </ScrollView>
      )}
    </AppScaffold>
  );
}

const styles = StyleSheet.create({
  topMeta: {
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.sm,
    paddingBottom: spacing.md,
    gap: 6,
  },
  countPill: {
    alignSelf: 'flex-start',
    backgroundColor: colors.brand600,
    borderRadius: radius.full,
    paddingHorizontal: 12,
    paddingVertical: 6,
  },
  countText: { color: colors.white, fontSize: 12, ...font('700') },
  hint: { color: colors.slate500, fontSize: 12, ...font('500') },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  list: { paddingHorizontal: spacing.md, gap: spacing.md },
  grid: {
    flexDirection: 'column',
    gap: spacing.md,
  },
  gridWide: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    rowGap: spacing.md,
  },
  empty: {
    marginTop: 48,
    borderWidth: 1,
    borderStyle: 'dashed',
    borderColor: colors.brand200,
    backgroundColor: 'rgba(255,255,255,0.7)',
    borderRadius: radius['3xl'],
    padding: spacing.xxl,
    alignItems: 'center',
  },
  emptyTitle: { fontSize: 17, color: colors.espresso, ...fontDisplay('700') },
  emptyHint: {
    marginTop: 8,
    textAlign: 'center',
    color: colors.slate500,
    fontSize: 13,
    lineHeight: 18,
    ...font('400'),
  },
  ticket: {
    width: '100%',
    backgroundColor: colors.white,
    borderRadius: radius['2xl'],
    borderWidth: 1,
    borderColor: colors.brand100,
    overflow: 'hidden',
    borderLeftWidth: 4,
  },
  ticketWide: {
    width: '48.5%',
  },
  ticketBill: { borderLeftColor: colors.blue700 },
  ticketPaid: { borderLeftColor: colors.brand600 },
  ticketHead: {
    flexDirection: 'row',
    gap: spacing.sm,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.md,
    backgroundColor: '#faf7f2',
    borderBottomWidth: 1,
    borderBottomColor: colors.slate100,
  },
  numberRow: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    alignItems: 'center',
    gap: 8,
  },
  orderNumber: { color: colors.espresso, ...fontDisplay('700') },
  orderType: {
    backgroundColor: colors.amber100,
    color: colors.amber800,
    borderRadius: radius.lg,
    paddingHorizontal: 10,
    paddingVertical: 5,
    overflow: 'hidden',
    ...font('700'),
  },
  customer: { marginTop: 4, color: colors.slate700, ...font('700') },
  headSide: { alignItems: 'flex-end', gap: 4 },
  elapsed: { color: colors.espresso, ...font('700') },
  badge: {
    borderRadius: radius.full,
    paddingHorizontal: 8,
    paddingVertical: 3,
    overflow: 'hidden',
    textTransform: 'uppercase',
    ...font('700'),
  },
  badgeBill: { backgroundColor: colors.blue50, color: colors.blue700 },
  badgePaid: { backgroundColor: colors.brand100, color: colors.brand800 },
  chips: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    paddingHorizontal: spacing.md,
    paddingTop: spacing.md,
  },
  chip: {
    backgroundColor: colors.slate100,
    color: colors.slate800,
    borderRadius: radius.lg,
    paddingHorizontal: 10,
    paddingVertical: 6,
    overflow: 'hidden',
    maxWidth: '100%',
    ...font('700'),
  },
  chipTable: { backgroundColor: colors.brand50, color: colors.brand800 },
  items: { paddingHorizontal: spacing.xs, paddingVertical: spacing.sm, gap: 6 },
  itemRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: spacing.sm,
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.sm,
    borderRadius: radius.xl,
  },
  itemDone: { opacity: 0.72 },
  check: {
    borderRadius: radius.xl,
    borderWidth: 2.5,
    borderColor: colors.brand300,
    backgroundColor: colors.white,
    alignItems: 'center',
    justifyContent: 'center',
  },
  checkOn: {
    borderColor: colors.green600,
    backgroundColor: colors.green600,
  },
  checkText: { color: colors.white, fontSize: 22, ...font('700') },
  qty: {
    minWidth: 36,
    marginTop: 2,
    color: colors.espresso,
    ...font('700'),
  },
  itemName: { color: colors.slate900, ...font('700') },
  itemNameDone: { color: colors.slate400, textDecorationLine: 'line-through' },
  itemCategory: {
    alignSelf: 'flex-start',
    marginTop: 4,
    borderRadius: radius.full,
    backgroundColor: colors.brand50,
    color: colors.brand800,
    paddingHorizontal: 8,
    paddingVertical: 3,
    fontSize: 11,
    overflow: 'hidden',
    textTransform: 'uppercase',
    ...font('700'),
  },
  itemCategorySnack: { backgroundColor: colors.amber50, color: colors.amber800 },
  itemNote: { marginTop: 4, lineHeight: 20, color: colors.amber800, ...font('700') },
  itemAddon: { marginTop: 2, lineHeight: 20, color: colors.slate600, ...font('600') },
  serveBtn: {
    margin: spacing.sm,
    marginTop: spacing.xs,
    backgroundColor: colors.brand600,
    borderRadius: radius.xl,
    minHeight: 48,
    alignItems: 'center',
    justifyContent: 'center',
  },
  serveText: { color: colors.white, ...font('700') },
});
