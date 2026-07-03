import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import {
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { getLocalOrders } from '@/local-db/repository';
import type { LocalOrder } from '@/local-db/types';
import { colors, radius, spacing } from '@/theme';
import { formatRupiah } from '@/utils/rupiah';

export default function LocalOrdersScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [orders, setOrders] = useState<LocalOrder[]>([]);

  const refresh = useCallback(async () => {
    const rows = await getLocalOrders();
    setOrders(rows);
  }, []);

  useFocusEffect(
    useCallback(() => {
      refresh();
    }, [refresh]),
  );

  if (Platform.OS !== 'android') {
    return null;
  }

  return (
    <View style={styles.root}>
      <View style={[styles.toolbar, { paddingTop: insets.top + spacing.sm }]}>
        <Pressable onPress={() => router.back()} style={styles.iconBtn}>
          <Text style={styles.iconBtnText}>←</Text>
        </Pressable>
        <View style={styles.toolbarCopy}>
          <Text style={styles.toolbarTitle}>Riwayat Lokal</Text>
          <Text style={styles.toolbarMeta}>Tersimpan di perangkat Android</Text>
        </View>
      </View>

      <ScrollView
        contentContainerStyle={{
          padding: spacing.lg,
          paddingBottom: insets.bottom + spacing.xxl,
          gap: spacing.md,
        }}
      >
        {orders.length === 0 ? (
          <View style={styles.empty}>
            <Text style={styles.emptyTitle}>Belum ada transaksi</Text>
            <Text style={styles.emptyText}>Pesanan dari Kasir Lokal akan muncul di sini.</Text>
          </View>
        ) : (
          orders.map((order) => (
            <View key={order.id} style={styles.card}>
              <View style={styles.cardHead}>
                <Text style={styles.orderNumber}>#{order.order_number}</Text>
                <Text style={styles.orderTotal}>{formatRupiah(order.total)}</Text>
              </View>
              <Text style={styles.orderMeta}>
                {order.customer_name || 'Tanpa nama'} · {order.payment_method.toUpperCase()}
              </Text>
              <Text style={styles.orderDate}>
                {new Date(order.created_at).toLocaleString('id-ID')}
              </Text>
            </View>
          ))
        )}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.slate100 },
  toolbar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.slate200,
    backgroundColor: colors.white,
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing.md,
  },
  iconBtn: {
    width: 44,
    height: 44,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    alignItems: 'center',
    justifyContent: 'center',
  },
  iconBtnText: { fontSize: 20 },
  toolbarCopy: { flex: 1 },
  toolbarTitle: { fontSize: 16, fontWeight: '700', color: colors.slate900 },
  toolbarMeta: { fontSize: 11, color: colors.slate500 },
  empty: {
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    padding: spacing.xxl,
    alignItems: 'center',
    gap: spacing.sm,
  },
  emptyTitle: { fontSize: 16, fontWeight: '700', color: colors.slate900 },
  emptyText: { fontSize: 13, color: colors.slate600, textAlign: 'center' },
  card: {
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    padding: spacing.lg,
    gap: spacing.xs,
  },
  cardHead: { flexDirection: 'row', justifyContent: 'space-between', gap: spacing.md },
  orderNumber: { fontSize: 16, fontWeight: '800', color: colors.slate900, fontFamily: 'monospace' },
  orderTotal: { fontSize: 16, fontWeight: '800', color: colors.brand600 },
  orderMeta: { fontSize: 13, color: colors.slate600 },
  orderDate: { fontSize: 11, color: colors.slate500 },
});
