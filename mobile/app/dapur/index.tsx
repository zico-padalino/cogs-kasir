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

function formatQty(qty: number): string {
  if (Math.abs(qty - Math.round(qty)) < 0.001) {
    return String(Math.round(qty));
  }
  return String(qty);
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
    // Board di-refresh saat push kitchen_order / app aktif / pull manual — bukan tiap 30 dtk.
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
        setOrders((prev) => prev.map((o) => (o.id === updated.id ? updated : o)));
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
            <View style={styles.grid}>
              {orders.map((order) => {
                const items = order.items || [];
                const done = items.filter((i) => i.is_delivered).length;
                const elapsed = elapsedLabel(startedAt(order));
                void nowTick;
                const isBill = order.status === 'unpaid' || order.is_open_bill;
                return (
                  <View
                    key={order.id}
                    style={[styles.ticket, isBill ? styles.ticketBill : styles.ticketPaid]}
                  >
                    <View style={styles.ticketHead}>
                      <View style={{ flex: 1, minWidth: 0 }}>
                        <Text style={styles.orderNumber} numberOfLines={1}>
                          {order.order_number}
                        </Text>
                        <Text style={styles.customer} numberOfLines={1}>
                          {order.customer_note?.trim() || 'Tanpa nama'}
                        </Text>
                      </View>
                      <View style={styles.headSide}>
                        <Text style={styles.elapsed}>{elapsed}</Text>
                        <Text style={[styles.badge, isBill ? styles.badgeBill : styles.badgePaid]}>
                          {isBill ? 'Open' : 'Bayar'}
                        </Text>
                      </View>
                    </View>

                    <View style={styles.chips}>
                      {order.order_type_label ? (
                        <Text style={styles.chip} numberOfLines={1}>
                          {order.order_type_icon || ''} {order.order_type_label}
                        </Text>
                      ) : null}
                      {order.table?.label ? (
                        <Text style={[styles.chip, styles.chipTable]} numberOfLines={1}>
                          🪑 {order.table.label}
                        </Text>
                      ) : null}
                      <Text style={styles.chip}>
                        {done}/{items.length} siap
                      </Text>
                    </View>

                    <View style={styles.items}>
                      {items.map((item) => {
                        const notes = splitNotes(item.notes);
                        const doneItem = !!item.is_delivered;
                        return (
                          <Pressable
                            key={item.id}
                            style={[styles.itemRow, doneItem && styles.itemDone]}
                            onPress={() => void toggleItem(item)}
                            disabled={busyItemId === item.id || !order.can_checklist_delivered}
                          >
                            <View style={[styles.check, doneItem && styles.checkOn]}>
                              <Text style={styles.checkText}>{doneItem ? '✓' : ''}</Text>
                            </View>
                            <Text style={styles.qty}>{formatQty(item.quantity)}×</Text>
                            <View style={{ flex: 1, minWidth: 0 }}>
                              <Text
                                style={[styles.itemName, doneItem && styles.itemNameDone]}
                                numberOfLines={2}
                              >
                                {item.product_name || 'Item'}
                              </Text>
                              {notes.customer ? (
                                <Text style={styles.itemNote} numberOfLines={2}>
                                  {notes.customer}
                                </Text>
                              ) : null}
                              {notes.addons.map((addon) => (
                                <Text key={addon} style={styles.itemAddon} numberOfLines={1}>
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
                        <Text style={styles.serveText}>Tandai selesai</Text>
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
    flexDirection: 'row',
    flexWrap: 'wrap',
    justifyContent: 'space-between',
    rowGap: spacing.sm,
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
    width: '48.5%',
    backgroundColor: colors.white,
    borderRadius: radius['2xl'],
    borderWidth: 1,
    borderColor: colors.brand100,
    overflow: 'hidden',
    borderLeftWidth: 4,
  },
  ticketBill: { borderLeftColor: colors.blue700 },
  ticketPaid: { borderLeftColor: colors.brand600 },
  ticketHead: {
    flexDirection: 'row',
    gap: spacing.sm,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    backgroundColor: '#faf7f2',
    borderBottomWidth: 1,
    borderBottomColor: colors.slate100,
  },
  orderNumber: { fontSize: 15, color: colors.espresso, ...fontDisplay('700') },
  customer: { marginTop: 2, fontSize: 12, color: colors.slate600, ...font('600') },
  headSide: { alignItems: 'flex-end', gap: 4 },
  elapsed: { fontSize: 14, color: colors.espresso, ...font('700') },
  badge: {
    borderRadius: radius.full,
    paddingHorizontal: 7,
    paddingVertical: 2,
    fontSize: 9,
    overflow: 'hidden',
    textTransform: 'uppercase',
    ...font('700'),
  },
  badgeBill: { backgroundColor: colors.blue50, color: colors.blue700 },
  badgePaid: { backgroundColor: colors.brand100, color: colors.brand800 },
  chips: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 4,
    paddingHorizontal: spacing.md,
    paddingTop: spacing.sm,
  },
  chip: {
    backgroundColor: colors.slate100,
    color: colors.slate700,
    borderRadius: radius.md,
    paddingHorizontal: 6,
    paddingVertical: 3,
    fontSize: 10,
    overflow: 'hidden',
    maxWidth: '100%',
    ...font('600'),
  },
  chipTable: { backgroundColor: colors.brand50, color: colors.brand800 },
  items: { paddingHorizontal: spacing.xs, paddingVertical: spacing.sm, gap: 4 },
  itemRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.sm,
    borderRadius: radius.xl,
  },
  itemDone: { opacity: 0.72 },
  check: {
    width: 44,
    height: 44,
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
    minWidth: 28,
    fontSize: 14,
    color: colors.espresso,
    ...font('700'),
  },
  itemName: { fontSize: 14, color: colors.slate900, ...font('700') },
  itemNameDone: { color: colors.slate400, textDecorationLine: 'line-through' },
  itemNote: { marginTop: 2, fontSize: 11, color: colors.amber800, ...font('600') },
  itemAddon: { marginTop: 1, fontSize: 11, color: colors.slate500, ...font('500') },
  serveBtn: {
    margin: spacing.sm,
    marginTop: spacing.xs,
    backgroundColor: colors.brand600,
    borderRadius: radius.xl,
    minHeight: 40,
    alignItems: 'center',
    justifyContent: 'center',
  },
  serveText: { color: colors.white, fontSize: 13, ...font('700') },
});
