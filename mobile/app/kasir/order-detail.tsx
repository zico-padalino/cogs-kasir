import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { kasirApi } from '@/api/kasir';
import type { PosOrder } from '@/api/types';
import { AppScaffold } from '@/components/AppScaffold';
import { colors, font, radius, spacing } from '@/theme';
import { formatRupiah } from '@/utils/rupiah';

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

  return (
    <AppScaffold moduleType="kasir" title="Detail Pesanan" subtitle={order ? `#${order.order_number}` : ''}>
      {loading || !order ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.brand600} />
        </View>
      ) : (
        <ScrollView contentContainerStyle={{ padding: spacing.lg, gap: spacing.md }}>
          <View style={styles.card}>
            <Text style={styles.label}>Status</Text>
            <Text style={styles.value}>{order.status_label}</Text>
            <Text style={styles.label}>Tipe</Text>
            <Text style={styles.value}>
              {order.order_type_icon} {order.order_type_label}
            </Text>
            <Text style={styles.label}>Pelanggan</Text>
            <Text style={styles.value}>{order.customer_note || '-'}</Text>
            <Text style={styles.label}>Kasir</Text>
            <Text style={styles.value}>{order.cashier_name || '-'}</Text>
            <Text style={styles.label}>Pembayaran</Text>
            <Text style={styles.value}>{order.payment_method_label || '-'}</Text>
          </View>

          {(order.items || []).map((item) => (
            <View key={item.id} style={styles.card}>
              <Text style={styles.itemName}>{item.product_name}</Text>
              <Text style={styles.meta}>
                {item.quantity} × {formatRupiah(item.unit_price)}
              </Text>
              {item.notes ? <Text style={styles.meta}>{item.notes}</Text> : null}
              <Text style={styles.total}>{formatRupiah(item.line_total)}</Text>
            </View>
          ))}

          <View style={styles.card}>
            <View style={styles.row}>
              <Text style={styles.meta}>Subtotal</Text>
              <Text>{formatRupiah(order.subtotal)}</Text>
            </View>
            {order.discount_amount > 0 ? (
              <View style={styles.row}>
                <Text style={styles.meta}>Diskon</Text>
                <Text>- {formatRupiah(order.discount_amount)}</Text>
              </View>
            ) : null}
            <View style={styles.row}>
              <Text style={styles.totalLabel}>Total</Text>
              <Text style={styles.total}>{formatRupiah(order.total)}</Text>
            </View>
          </View>

          {order.status === 'paid' ? (
            <Pressable onPress={() => router.push(`/kasir/receipt?id=${order.id}` as never)} style={styles.btn}>
              <Text style={styles.btnText}>Lihat Struk</Text>
            </Pressable>
          ) : null}
        </ScrollView>
      )}
    </AppScaffold>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  card: {
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.md,
    gap: 4,
  },
  label: { fontSize: 11, color: colors.slate500, marginTop: 6, textTransform: 'uppercase', ...font('600') },
  value: { fontSize: 14, color: colors.slate900, ...font('500') },
  itemName: { fontSize: 15, color: colors.slate900, ...font('700') },
  meta: { fontSize: 12, color: colors.slate500 },
  row: { flexDirection: 'row', justifyContent: 'space-between', marginTop: 4 },
  totalLabel: { fontSize: 15, ...font('700') },
  total: { fontSize: 15, color: colors.brand700, ...font('700') },
  btn: {
    minHeight: 48,
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  btnText: { color: colors.white, ...font('700') },
});
