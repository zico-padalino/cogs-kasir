import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { kasirApi } from '@/api/kasir';
import type { PosOrder } from '@/api/types';
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

  useEffect(() => {
    (async () => {
      try {
        const res = await kasirApi.order(Number(id));
        setOrder(res.data);
      } catch {
        // PIN_LOCKED → redirect global
      } finally {
        setLoading(false);
      }
    })();
  }, [id]);

  const canLihatStruk = order?.status === 'paid' || order?.status === 'served';

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
          <Pressable onPress={() => router.push('/kasir/orders' as never)}>
            <Text style={styles.back}>← Kembali ke Riwayat</Text>
          </Pressable>

          <View>
            <Text style={styles.heading}>{order.order_number}</Text>
            <Text style={styles.subheading}>
              {sourceLabel(order.source)} · {order.table?.label || 'Walk-in'}
            </Text>
          </View>

          <View style={styles.card}>
            <Text style={styles.sectionTitle}>Item Pesanan</Text>
            {(order.items || []).map((item) => (
              <View key={item.id} style={styles.itemRow}>
                <View style={{ flex: 1, gap: 2 }}>
                  <Text style={styles.itemName}>{item.product_name}</Text>
                  {item.notes ? <Text style={styles.note}>Catatan: {item.notes}</Text> : null}
                  <Text style={styles.meta}>
                    Qty {item.quantity} · {formatRupiah(item.unit_price)}
                  </Text>
                </View>
                <Text style={styles.lineTotal}>{formatRupiah(item.line_total)}</Text>
              </View>
            ))}
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
  back: { color: colors.brand700, ...font('600'), fontSize: 14 },
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
  itemRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: spacing.md,
    paddingVertical: spacing.xs,
  },
  itemName: { fontSize: 14, color: colors.slate900, ...font('600') },
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
