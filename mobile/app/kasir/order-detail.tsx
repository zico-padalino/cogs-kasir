import { useLocalSearchParams, useRouter } from 'expo-router';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { kasirApi } from '@/api/kasir';
import type { OrderItem, PosOrder } from '@/api/types';
import { asApiError } from '@/auth';
import { AppScaffold } from '@/components/AppScaffold';
import { colors, font, radius, spacing } from '@/theme';
import { formatRupiah } from '@/utils/rupiah';

function sourceLabel(source?: string | null): string {
  if (source === 'online') return 'Online (Meja)';
  if (source === 'kasir') return 'Kasir';
  return source || '-';
}

function formatPaidAt(iso?: string | null): string {
  if (!iso) return '-';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '-';
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

export default function OrderDetailScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const [order, setOrder] = useState<PosOrder | null>(null);
  const [loading, setLoading] = useState(true);
  const [togglingId, setTogglingId] = useState<number | null>(null);

  const load = useCallback(async () => {
    try {
      const res = await kasirApi.order(Number(id));
      setOrder(res.data);
    } catch {
      // PIN_LOCKED → redirect global
    } finally {
      setLoading(false);
    }
  }, [id]);

  useEffect(() => {
    void load();
  }, [load]);

  const canChecklist = Boolean(
    order?.can_checklist_delivered ||
      order?.status === 'unpaid' ||
      order?.is_open_bill ||
      order?.status === 'paid' ||
      order?.status === 'served',
  );
  const canLihatStruk = order?.status === 'paid' || order?.status === 'served';
  const deliveredCount = useMemo(
    () => (order?.items || []).filter((item) => item.is_delivered).length,
    [order?.items],
  );

  const toggleDelivered = async (item: OrderItem) => {
    if (!canChecklist || togglingId) return;
    const next = !item.is_delivered;
    setTogglingId(item.id);
    try {
      const res = await kasirApi.setItemDelivered(item.id, next);
      setOrder(res.data);
    } catch (e) {
      Alert.alert('Gagal', asApiError(e).message || 'Tidak bisa menyimpan ceklis.');
    } finally {
      setTogglingId(null);
    }
  };

  return (
    <AppScaffold
      moduleType="kasir"
      title="Detail Pesanan"
      subtitle={order ? order.order_number : ''}
    >
      {loading || !order ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.brand600} />
        </View>
      ) : (
        <ScrollView contentContainerStyle={{ padding: spacing.lg, gap: spacing.md, paddingBottom: spacing.xxl }}>
          <View style={styles.navRow}>
            <Pressable onPress={() => router.push('/kasir' as never)}>
              <Text style={styles.back}>← Kembali ke POS</Text>
            </Pressable>
            <Pressable onPress={() => router.push('/kasir/orders' as never)}>
              <Text style={styles.backMuted}>Riwayat</Text>
            </Pressable>
          </View>

          <View>
            <Text style={styles.heading}>{order.order_number}</Text>
            <Text style={styles.subheading}>
              {sourceLabel(order.source)} · {order.table?.label || 'Walk-in'}
            </Text>
          </View>

          <View style={styles.card}>
            <Text style={styles.sectionTitle}>Item Pesanan</Text>
            {canChecklist && (order.items || []).length > 0 ? (
              <Text style={styles.progress}>
                Diantar: {deliveredCount}/{(order.items || []).length}
              </Text>
            ) : null}
            {(order.items || []).map((item) => {
              const delivered = Boolean(item.is_delivered);
              return (
                <View
                  key={item.id}
                  style={[styles.itemRow, delivered && styles.itemRowDelivered]}
                >
                  {canChecklist ? (
                    <Pressable
                      onPress={() => void toggleDelivered(item)}
                      disabled={togglingId === item.id}
                      style={[styles.checkBox, delivered && styles.checkBoxOn]}
                      accessibilityRole="checkbox"
                      accessibilityState={{ checked: delivered }}
                      accessibilityLabel={`Sudah diantar: ${item.product_name || 'item'}`}
                    >
                      <Text style={styles.checkMark}>{delivered ? '✓' : ''}</Text>
                    </Pressable>
                  ) : null}
                  <View style={{ flex: 1, gap: 2 }}>
                    <Text style={[styles.itemName, delivered && styles.itemNameDelivered]}>
                      {item.product_name}
                    </Text>
                    {item.notes ? <Text style={styles.note}>Catatan: {item.notes}</Text> : null}
                    <Text style={styles.meta}>
                      Qty {item.quantity} · {formatRupiah(item.unit_price)}
                    </Text>
                  </View>
                  <Text style={styles.lineTotal}>{formatRupiah(item.line_total)}</Text>
                </View>
              );
            })}
            <View style={styles.divider} />
            {order.discount_amount > 0 ? (
              <>
                <View style={styles.row}>
                  <Text style={styles.meta}>Subtotal</Text>
                  <Text>{formatRupiah(order.subtotal)}</Text>
                </View>
                <View style={styles.row}>
                  <Text style={styles.meta}>Diskon</Text>
                  <Text>- {formatRupiah(order.discount_amount)}</Text>
                </View>
              </>
            ) : null}
            <View style={styles.row}>
              <Text style={styles.totalLabel}>Total</Text>
              <Text style={styles.total}>{formatRupiah(order.total)}</Text>
            </View>
            {canLihatStruk ? (
              <Pressable
                onPress={() => router.push(`/kasir/receipt?id=${order.id}&from=history` as never)}
                style={styles.btn}
              >
                <Text style={styles.btnText}>Lihat Struk</Text>
              </Pressable>
            ) : null}
          </View>

          <View style={styles.card}>
            <Text style={styles.sectionTitle}>Info Pembayaran</Text>
            <View style={styles.metaRow}>
              <Text style={styles.label}>Status</Text>
              <Text style={styles.value}>{order.status_label || order.status || '-'}</Text>
            </View>
            <View style={styles.metaRow}>
              <Text style={styles.label}>Metode</Text>
              <Text style={styles.value}>{order.payment_method_label || '-'}</Text>
            </View>
            <View style={styles.metaRow}>
              <Text style={styles.label}>Kasir</Text>
              <Text style={styles.value}>{order.cashier_name || '-'}</Text>
            </View>
            <View style={styles.metaRow}>
              <Text style={styles.label}>Dibayar</Text>
              <Text style={styles.value}>{formatPaidAt(order.paid_at)}</Text>
            </View>
            {order.customer_note ? (
              <View style={styles.metaRow}>
                <Text style={styles.label}>Pemesan</Text>
                <Text style={styles.value}>{order.customer_note}</Text>
              </View>
            ) : null}
          </View>
        </ScrollView>
      )}
    </AppScaffold>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  navRow: { flexDirection: 'row', flexWrap: 'wrap', alignItems: 'center', gap: spacing.md },
  back: { color: colors.brand700, ...font('600'), fontSize: 14 },
  backMuted: { color: colors.slate500, ...font('600'), fontSize: 14 },
  heading: { fontSize: 20, color: colors.slate900, ...font('700') },
  subheading: { fontSize: 13, color: colors.slate500, marginTop: 4 },
  card: {
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.md,
    gap: spacing.sm,
  },
  sectionTitle: { fontSize: 15, color: colors.slate900, ...font('700'), marginBottom: 4 },
  progress: { fontSize: 12, color: colors.slate500, marginBottom: 2 },
  itemRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: spacing.sm,
    paddingVertical: spacing.xs,
  },
  itemRowDelivered: {
    backgroundColor: '#ecfdf5',
    borderRadius: radius.md,
    paddingHorizontal: spacing.xs,
  },
  checkBox: {
    width: 24,
    height: 24,
    borderRadius: 6,
    borderWidth: 1.5,
    borderColor: colors.slate300,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 2,
    backgroundColor: colors.white,
  },
  checkBoxOn: {
    borderColor: colors.brand600,
    backgroundColor: colors.brand600,
  },
  checkMark: { color: colors.white, fontSize: 14, ...font('700'), lineHeight: 16 },
  itemName: { fontSize: 14, color: colors.slate900, ...font('600') },
  itemNameDelivered: { color: colors.slate500, textDecorationLine: 'line-through' },
  note: { fontSize: 12, color: colors.amber700 },
  meta: { fontSize: 12, color: colors.slate500 },
  lineTotal: { fontSize: 13, color: colors.slate800, ...font('600') },
  divider: { height: 1, backgroundColor: colors.slate200, marginVertical: spacing.xs },
  row: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 2 },
  totalLabel: { fontSize: 15, ...font('700') },
  total: { fontSize: 15, color: colors.brand700, ...font('700') },
  metaRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: spacing.md,
    paddingVertical: 4,
  },
  label: { fontSize: 13, color: colors.slate500 },
  value: { fontSize: 13, color: colors.slate900, ...font('500'), textAlign: 'right', flexShrink: 1 },
  btn: {
    marginTop: spacing.sm,
    minHeight: 48,
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  btnText: { color: colors.white, ...font('700') },
});
