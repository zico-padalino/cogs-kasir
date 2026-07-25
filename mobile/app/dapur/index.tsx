import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  RefreshControl,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { kasirApi, pinApi } from '@/api/kasir';
import type { OrderItem, PosOrder } from '@/api/types';
import { reportApiError, useAuth } from '@/auth';
import { AppScaffold } from '@/components/AppScaffold';
import { seedKitchenIds } from '@/dapur/kitchenOrderTracker';
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
  const { pin, setPin } = useAuth();
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
      if (res.data.unlocked !== undefined) {
        setPin({
          unlocked: !!res.data.unlocked,
          expires_at: res.data.expires_at ?? null,
          server_now: res.data.server_now ?? Math.floor(Date.now() / 1000),
          remaining_seconds: res.data.remaining_seconds ?? 0,
          operator_name: res.data.operator_name ?? null,
        });
      }
      void pinApi.touch().catch(() => {});
    } catch (err) {
      reportApiError(err, 'Gagal memuat dapur');
    } finally {
      setLoading(false);
      setRefreshing(false);
    }
  }, [setPin]);

  useEffect(() => {
    void load();
    const timer = setInterval(() => {
      void load({ soft: true });
    }, 5000);
    return () => clearInterval(timer);
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
            orders.map((order) => {
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
                      <Text style={styles.orderNumber}>{order.order_number}</Text>
                      <Text style={styles.customer} numberOfLines={1}>
                        {order.customer_note?.trim() || 'Tanpa nama'}
                      </Text>
                    </View>
                    <View style={styles.headSide}>
                      <Text style={styles.elapsed}>{elapsed}</Text>
                      <Text style={[styles.badge, isBill ? styles.badgeBill : styles.badgePaid]}>
                        {isBill ? 'Tagihan terbuka' : 'Sudah bayar'}
                      </Text>
                    </View>
                  </View>

                  <View style={styles.chips}>
                    {order.order_type_label ? (
                      <Text style={styles.chip}>
                        {order.order_type_icon || ''} {order.order_type_label}
                      </Text>
                    ) : null}
                    {order.table?.label ? (
                      <Text style={[styles.chip, styles.chipTable]}>🪑 {order.table.label}</Text>
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
                            <Text style={[styles.itemName, doneItem && styles.itemNameDone]}>
                              {item.product_name || 'Item'}
                            </Text>
                            {notes.customer ? (
                              <Text style={styles.itemNote}>{notes.customer}</Text>
                            ) : null}
                            {notes.addons.map((addon) => (
                              <Text key={addon} style={styles.itemAddon}>
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
            })
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
  list: { paddingHorizontal: spacing.lg, gap: spacing.md },
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
    backgroundColor: colors.white,
    borderRadius: radius['3xl'],
    borderWidth: 1,
    borderColor: colors.brand100,
    overflow: 'hidden',
    borderLeftWidth: 4,
  },
  ticketBill: { borderLeftColor: colors.blue700 },
  ticketPaid: { borderLeftColor: colors.brand600 },
  ticketHead: {
    flexDirection: 'row',
    gap: spacing.md,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
    backgroundColor: '#faf7f2',
    borderBottomWidth: 1,
    borderBottomColor: colors.slate100,
  },
  orderNumber: { fontSize: 20, color: colors.espresso, ...fontDisplay('700') },
  customer: { marginTop: 2, fontSize: 13, color: colors.slate600, ...font('600') },
  headSide: { alignItems: 'flex-end', gap: 4 },
  elapsed: { fontSize: 18, color: colors.espresso, ...font('700') },
  badge: {
    borderRadius: radius.full,
    paddingHorizontal: 8,
    paddingVertical: 2,
    fontSize: 10,
    overflow: 'hidden',
    textTransform: 'uppercase',
    ...font('700'),
  },
  badgeBill: { backgroundColor: colors.blue50, color: colors.blue700 },
  badgePaid: { backgroundColor: colors.brand100, color: colors.brand800 },
  chips: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 6,
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.md,
  },
  chip: {
    backgroundColor: colors.slate100,
    color: colors.slate700,
    borderRadius: radius.md,
    paddingHorizontal: 8,
    paddingVertical: 4,
    fontSize: 11,
    overflow: 'hidden',
    ...font('600'),
  },
  chipTable: { backgroundColor: colors.brand50, color: colors.brand800 },
  items: { paddingHorizontal: spacing.sm, paddingVertical: spacing.sm, gap: 2 },
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
    width: 28,
    height: 28,
    borderRadius: radius.lg,
    borderWidth: 2,
    borderColor: colors.brand300,
    backgroundColor: colors.white,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 1,
  },
  checkOn: {
    borderColor: colors.green600,
    backgroundColor: colors.green600,
  },
  checkText: { color: colors.white, fontSize: 14, ...font('700') },
  qty: {
    minWidth: 36,
    marginTop: 2,
    fontSize: 16,
    color: colors.espresso,
    ...font('700'),
  },
  itemName: { fontSize: 16, color: colors.slate900, ...font('700') },
  itemNameDone: { color: colors.slate400, textDecorationLine: 'line-through' },
  itemNote: { marginTop: 2, fontSize: 13, color: colors.amber800, ...font('600') },
  itemAddon: { marginTop: 1, fontSize: 12, color: colors.slate500, ...font('500') },
  serveBtn: {
    margin: spacing.md,
    marginTop: spacing.sm,
    backgroundColor: colors.brand600,
    borderRadius: radius.xl,
    minHeight: 44,
    alignItems: 'center',
    justifyContent: 'center',
  },
  serveText: { color: colors.white, fontSize: 14, ...font('700') },
});
