import { useFocusEffect, useLocalSearchParams } from 'expo-router';
import { useCallback, useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Badge, EmptyState, ScreenHeader } from '@/components/cogs-ui';
import { getLocalOrderItems, getOrder } from '@/local-db/repository';
import type { LocalOrder, LocalOrderItem, OrderStatus } from '@/local-db/types';
import { colors, font, radius, spacing } from '@/theme';
import { formatRupiah } from '@/utils/rupiah';

const STATUS_LABEL: Record<OrderStatus, string> = {
  open: 'Draft',
  submitted: 'Menunggu Konfirmasi',
  confirmed: 'Siap Bayar',
  paid: 'Lunas',
  cancelled: 'Batal',
};

const STATUS_TONE: Record<OrderStatus, 'slate' | 'amber' | 'brand' | 'green'> = {
  open: 'slate',
  submitted: 'amber',
  confirmed: 'brand',
  paid: 'green',
  cancelled: 'slate',
};

const PAYMENT_LABEL: Record<string, string> = {
  cash: 'Tunai',
  qris: 'QRIS',
  transfer: 'Transfer',
  unpaid: 'Belum dibayar',
};

export default function OrderDetailScreen() {
  const insets = useSafeAreaInsets();
  const { id } = useLocalSearchParams<{ id: string }>();
  const orderId = Number(id);
  const [order, setOrder] = useState<LocalOrder | null>(null);
  const [items, setItems] = useState<LocalOrderItem[]>([]);

  const refresh = useCallback(async () => {
    const [nextOrder, nextItems] = await Promise.all([getOrder(orderId), getLocalOrderItems(orderId)]);
    setOrder(nextOrder);
    setItems(nextItems);
  }, [orderId]);

  useFocusEffect(
    useCallback(() => {
      refresh();
    }, [refresh]),
  );

  if (!order) {
    return (
      <View style={styles.root}>
        <ScreenHeader title="Detail Pesanan" />
        <View style={{ padding: spacing.lg }}>
          <EmptyState icon="🔍" title="Pesanan tidak ditemukan" />
        </View>
      </View>
    );
  }

  return (
    <View style={styles.root}>
      <ScreenHeader title={`Pesanan #${order.order_number}`} subtitle="Struk & detail" />
      <ScrollView
        contentContainerStyle={{
          padding: spacing.lg,
          paddingBottom: insets.bottom + spacing.xxl,
          gap: spacing.lg,
        }}
      >
        <View style={styles.struk}>
          <View style={styles.strukHead}>
            <Text style={styles.shopName}>COGS Sederhana</Text>
            <Text style={styles.shopMeta}>Kasir POS · offline</Text>
          </View>

          <View style={styles.metaGrid}>
            <View style={styles.metaRow}>
              <Text style={styles.metaLabel}>No. Pesanan</Text>
              <Text style={styles.metaValue}>#{order.order_number}</Text>
            </View>
            <View style={styles.metaRow}>
              <Text style={styles.metaLabel}>Waktu</Text>
              <Text style={styles.metaValue}>{new Date(order.created_at).toLocaleString('id-ID')}</Text>
            </View>
            <View style={styles.metaRow}>
              <Text style={styles.metaLabel}>Pemesan</Text>
              <Text style={styles.metaValue}>{order.customer_name || 'Tanpa nama'}</Text>
            </View>
            <View style={styles.metaRow}>
              <Text style={styles.metaLabel}>Tipe</Text>
              <Text style={styles.metaValue}>
                {order.order_type === 'dine_in' ? '🪑 Dine In' : '🥡 Take Away'}
                {order.table_label ? ` · ${order.table_label}` : ''}
              </Text>
            </View>
            <View style={styles.metaRow}>
              <Text style={styles.metaLabel}>Sumber</Text>
              <Text style={styles.metaValue}>{order.source === 'online' ? 'Online' : 'Kasir'}</Text>
            </View>
          </View>

          <View style={styles.badgeRow}>
            <Badge label={STATUS_LABEL[order.status]} tone={STATUS_TONE[order.status]} />
          </View>

          <View style={styles.divider} />

          {items.map((item) => (
            <View key={item.id} style={styles.itemRow}>
              <View style={{ flex: 1 }}>
                <Text style={styles.itemName}>{item.product_name}</Text>
                <Text style={styles.itemMeta}>
                  {item.quantity} × {formatRupiah(item.unit_price)}
                </Text>
              </View>
              <Text style={styles.itemTotal}>{formatRupiah(item.line_total)}</Text>
            </View>
          ))}

          <View style={styles.divider} />

          <View style={styles.sumRow}>
            <Text style={styles.sumLabel}>Subtotal</Text>
            <Text style={styles.sumValue}>{formatRupiah(order.subtotal)}</Text>
          </View>
          <View style={styles.sumRow}>
            <Text style={styles.totalLabel}>Total</Text>
            <Text style={styles.totalValue}>{formatRupiah(order.total)}</Text>
          </View>

          {order.status === 'paid' ? (
            <>
              <View style={styles.divider} />
              <View style={styles.sumRow}>
                <Text style={styles.sumLabel}>Metode Bayar</Text>
                <Text style={styles.sumValue}>{PAYMENT_LABEL[order.payment_method] ?? order.payment_method}</Text>
              </View>
              {order.amount_received != null ? (
                <>
                  <View style={styles.sumRow}>
                    <Text style={styles.sumLabel}>Uang Diterima</Text>
                    <Text style={styles.sumValue}>{formatRupiah(order.amount_received)}</Text>
                  </View>
                  <View style={styles.sumRow}>
                    <Text style={styles.sumLabel}>Kembalian</Text>
                    <Text style={styles.sumValue}>{formatRupiah(order.change_amount ?? 0)}</Text>
                  </View>
                </>
              ) : null}
            </>
          ) : null}

          <View style={styles.footerNote}>
            <Text style={styles.footerNoteText}>Terima kasih 🙏</Text>
          </View>
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.slate100 },
  struk: {
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    padding: spacing.lg,
    gap: spacing.md,
  },
  strukHead: { alignItems: 'center', gap: 2 },
  shopName: { fontSize: 18, color: colors.slate900, ...font('700') },
  shopMeta: { fontSize: 12, color: colors.slate500 },
  metaGrid: { gap: spacing.xs },
  metaRow: { flexDirection: 'row', justifyContent: 'space-between', gap: spacing.md },
  metaLabel: { fontSize: 13, color: colors.slate500 },
  metaValue: { fontSize: 13, color: colors.slate900, ...font('600') },
  badgeRow: { flexDirection: 'row', gap: spacing.xs },
  divider: { height: 1, backgroundColor: colors.slate200 },
  itemRow: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.md },
  itemName: { fontSize: 14, color: colors.slate900, ...font('600') },
  itemMeta: { fontSize: 12, color: colors.slate500, marginTop: 2 },
  itemTotal: { fontSize: 14, color: colors.slate900, ...font('700') },
  sumRow: { flexDirection: 'row', justifyContent: 'space-between', gap: spacing.md },
  sumLabel: { fontSize: 13, color: colors.slate600 },
  sumValue: { fontSize: 13, color: colors.slate900, ...font('600') },
  totalLabel: { fontSize: 15, color: colors.slate900, ...font('700') },
  totalValue: { fontSize: 18, color: colors.brand600, ...font('700') },
  footerNote: { alignItems: 'center', paddingTop: spacing.sm },
  footerNoteText: { fontSize: 13, color: colors.slate500 },
});
