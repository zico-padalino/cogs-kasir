import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { ActivityIndicator, FlatList, Pressable, StyleSheet, Text, View } from 'react-native';
import { kasirApi } from '@/api/kasir';
import type { PosOrder } from '@/api/types';
import { asApiError } from '@/auth';
import { AppScaffold } from '@/components/AppScaffold';
import { colors, font, radius, spacing } from '@/theme';
import { formatRupiah } from '@/utils/rupiah';

export default function OrdersScreen() {
  const router = useRouter();
  const [orders, setOrders] = useState<PosOrder[]>([]);
  const [loading, setLoading] = useState(true);

  const refresh = useCallback(async () => {
    setLoading(true);
    try {
      const res = await kasirApi.orders();
      setOrders(res.data || []);
    } catch (err) {
      if (asApiError(err).status === 423) {
        router.replace('/kasir/pin' as never);
      }
    } finally {
      setLoading(false);
    }
  }, [router]);

  useFocusEffect(
    useCallback(() => {
      void refresh();
    }, [refresh]),
  );

  return (
    <AppScaffold moduleType="kasir" title="Riwayat Pesanan" subtitle="Pesanan masuk & selesai">
      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.brand600} />
        </View>
      ) : (
        <FlatList
          data={orders}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={{ padding: spacing.lg, gap: spacing.sm, paddingBottom: spacing.xxl }}
          ListEmptyComponent={<Text style={styles.muted}>Belum ada pesanan.</Text>}
          renderItem={({ item }) => (
            <Pressable
              onPress={() => router.push(`/kasir/order-detail?id=${item.id}` as never)}
              style={styles.card}
            >
              <View style={{ flex: 1 }}>
                <Text style={styles.orderNo}>#{item.order_number}</Text>
                <Text style={styles.meta}>
                  {item.status_label} · {item.order_type_label || item.order_type} · {item.cashier_name || '-'}
                </Text>
              </View>
              <Text style={styles.total}>{formatRupiah(item.total)}</Text>
            </Pressable>
          )}
        />
      )}
    </AppScaffold>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  muted: { color: colors.slate500, textAlign: 'center', marginTop: spacing.xl },
  card: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.md,
  },
  orderNo: { fontSize: 15, color: colors.slate900, ...font('700') },
  meta: { fontSize: 12, color: colors.slate500, marginTop: 2 },
  total: { fontSize: 14, color: colors.brand700, ...font('700') },
});
