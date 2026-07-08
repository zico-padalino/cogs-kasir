import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { AppScaffold } from '@/components/AppScaffold';
import { Badge, EmptyState } from '@/components/cogs-ui';
import { getOrdersHistory } from '@/local-db/repository';
import type { LocalOrder, OrderStatus } from '@/local-db/types';
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

export default function KasirOrdersScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [orders, setOrders] = useState<LocalOrder[]>([]);

  const refresh = useCallback(async () => {
    setOrders(await getOrdersHistory());
  }, []);

  useFocusEffect(
    useCallback(() => {
      refresh();
    }, [refresh]),
  );

  return (
    <AppScaffold moduleType="kasir" title="Riwayat Pesanan" subtitle="Kasir & online">
      <ScrollView
        contentContainerStyle={{
          padding: spacing.lg,
          paddingBottom: insets.bottom + spacing.xxl,
          gap: spacing.md,
        }}
      >
        {orders.length === 0 ? (
          <EmptyState
            icon="🧾"
            title="Belum ada pesanan"
            hint="Pesanan kasir & online akan muncul di sini."
          />
        ) : (
          orders.map((order) => (
            <Pressable
              key={order.id}
              onPress={() =>
                router.push({ pathname: '/kasir/order-detail', params: { id: String(order.id) } })
              }
              style={({ pressed }) => [styles.card, pressed && styles.pressed]}
            >
              <View style={styles.cardHead}>
                <Text style={styles.orderNumber}>#{order.order_number}</Text>
                <Text style={styles.orderTotal}>{formatRupiah(order.total)}</Text>
              </View>
              <Text style={styles.orderMeta}>
                {order.customer_name || 'Tanpa nama'}
                {order.table_label ? ` · ${order.table_label}` : ''}
              </Text>
              <View style={styles.badgeRow}>
                <Badge label={STATUS_LABEL[order.status]} tone={STATUS_TONE[order.status]} />
                <Badge
                  label={order.source === 'online' ? 'Online' : 'Kasir'}
                  tone={order.source === 'online' ? 'blue' : 'slate'}
                />
                <Badge
                  label={order.order_type === 'dine_in' ? '🪑 Dine In' : '🥡 Take Away'}
                  tone="slate"
                />
                <Text style={styles.orderDate}>{new Date(order.created_at).toLocaleString('id-ID')}</Text>
              </View>
            </Pressable>
          ))
        )}
      </ScrollView>
    </AppScaffold>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.slate100 },
  card: {
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    padding: spacing.lg,
    gap: spacing.xs,
  },
  cardHead: { flexDirection: 'row', justifyContent: 'space-between', gap: spacing.md },
  orderNumber: { fontSize: 16, color: colors.slate900, ...font('700'), fontFamily: 'monospace' },
  orderTotal: { fontSize: 16, color: colors.brand600, ...font('700') },
  orderMeta: { fontSize: 13, color: colors.slate600 },
  badgeRow: { flexDirection: 'row', alignItems: 'center', flexWrap: 'wrap', gap: spacing.xs, marginTop: 4 },
  orderDate: { fontSize: 11, color: colors.slate500, marginLeft: 'auto' },
  pressed: { opacity: 0.9, transform: [{ scale: 0.99 }] },
});
