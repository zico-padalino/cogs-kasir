import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { kasirApi } from '@/api/kasir';
import type { PosOrder } from '@/api/types';
import { asApiError } from '@/auth';
import { AppScaffold } from '@/components/AppScaffold';
import { colors, font, radius, spacing } from '@/theme';
import { formatRupiah } from '@/utils/rupiah';

function sourceLabel(source?: string | null): string {
  if (source === 'online') return 'Online (Meja)';
  if (source === 'kasir') return 'Kasir';
  return source || '-';
}

function formatOrderTime(iso?: string | null): string {
  if (!iso) return '-';
  const d = new Date(iso);
  if (Number.isNaN(d.getTime())) return '-';
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function canShowStruk(status?: string | null): boolean {
  return status === 'paid' || status === 'served';
}

function canEditPaid(order: PosOrder): boolean {
  return Boolean(order.can_reopen_for_edit);
}

type OrdersMeta = {
  current_page?: number;
  last_page?: number;
};

export default function OrdersScreen() {
  const router = useRouter();
  const [orders, setOrders] = useState<PosOrder[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);

  const loadPage = useCallback(async (nextPage: number, append: boolean) => {
    if (append) setLoadingMore(true);
    else setLoading(true);
    try {
      const res = await kasirApi.orders(nextPage);
      const rows = res.data || [];
      const meta = (res.meta || {}) as OrdersMeta;
      setOrders((prev) => (append ? [...prev, ...rows] : rows));
      setPage(meta.current_page || nextPage);
      setLastPage(meta.last_page || 1);
    } catch {
      // PIN_LOCKED → redirect global
    } finally {
      setLoading(false);
      setLoadingMore(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      void loadPage(1, false);
    }, [loadPage]),
  );

  const openDetail = (order: PosOrder) => {
    router.push(`/kasir/order-detail?id=${order.id}` as never);
  };

  const openStruk = (order: PosOrder) => {
    router.push(`/kasir/receipt?id=${order.id}&from=history` as never);
  };

  const editPaid = (order: PosOrder) => {
    Alert.alert(
      'Edit pesanan?',
      'Pembayaran akan dibatalkan, stok dikembalikan, lalu pesanan dibuka di kasir untuk diedit.',
      [
        { text: 'Batal', style: 'cancel' },
        {
          text: 'Edit',
          style: 'destructive',
          onPress: () => {
            void (async () => {
              try {
                await kasirApi.editPaidOrder(order.id);
                router.push('/kasir' as never);
              } catch (e) {
                Alert.alert('Gagal', asApiError(e).message || 'Tidak bisa membuka pesanan.');
              }
            })();
          },
        },
      ],
    );
  };

  return (
    <AppScaffold moduleType="kasir" title="Riwayat Pesanan" subtitle="Semua Pesanan Kasir & Online">
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
          onEndReached={() => {
            if (!loadingMore && page < lastPage) {
              void loadPage(page + 1, true);
            }
          }}
          onEndReachedThreshold={0.4}
          ListFooterComponent={
            loadingMore ? (
              <ActivityIndicator color={colors.brand600} style={{ marginVertical: spacing.md }} />
            ) : null
          }
          renderItem={({ item }) => (
            <View style={styles.card}>
              <View style={styles.cardTop}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.orderNo}>{item.order_number}</Text>
                  <Text style={styles.meta}>Pemesan: {item.customer_note || '-'}</Text>
                  <Text style={styles.meta}>
                    {sourceLabel(item.source)} · Meja: {item.table?.label || '-'}
                  </Text>
                  <Text style={styles.meta}>Waktu: {formatOrderTime(item.created_at)}</Text>
                </View>
                <View style={styles.rightCol}>
                  {item.has_discount || (item.discount_amount ?? 0) > 0 ? (
                    <>
                      <Text style={styles.subtotalStrike}>{formatRupiah(item.subtotal)}</Text>
                      <Text style={styles.discountText}>-{formatRupiah(item.discount_amount || 0)}</Text>
                    </>
                  ) : null}
                  <Text style={styles.total}>{formatRupiah(item.total)}</Text>
                  <View style={styles.badge}>
                    <Text style={styles.badgeText}>{item.status_label || item.status || '-'}</Text>
                  </View>
                </View>
              </View>

              <View style={styles.actions}>
                <Pressable onPress={() => openDetail(item)} style={styles.actionGhost}>
                  <Text style={styles.actionGhostText}>Detail</Text>
                </Pressable>
                {canEditPaid(item) ? (
                  <Pressable onPress={() => editPaid(item)} style={styles.actionOutline}>
                    <Text style={styles.actionOutlineText}>Edit</Text>
                  </Pressable>
                ) : null}
                {canShowStruk(item.status) ? (
                  <Pressable onPress={() => openStruk(item)} style={styles.actionOutline}>
                    <Text style={styles.actionOutlineText}>Struk</Text>
                  </Pressable>
                ) : null}
              </View>
            </View>
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
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.md,
    gap: spacing.md,
  },
  cardTop: { flexDirection: 'row', gap: spacing.md },
  orderNo: { fontSize: 14, color: colors.slate900, ...font('700'), fontVariant: ['tabular-nums'] },
  meta: { fontSize: 12, color: colors.slate500, marginTop: 2 },
  rightCol: { alignItems: 'flex-end', gap: 4 },
  subtotalStrike: { fontSize: 12, color: colors.slate400, textDecorationLine: 'line-through' },
  discountText: { fontSize: 12, color: colors.amber700, ...font('600') },
  total: { fontSize: 14, color: colors.brand700, ...font('700') },
  badge: {
    backgroundColor: colors.brand50,
    borderRadius: radius.full,
    paddingHorizontal: spacing.sm,
    paddingVertical: 2,
  },
  badgeText: { fontSize: 11, color: colors.brand700, ...font('600') },
  actions: { flexDirection: 'row', justifyContent: 'flex-end', gap: spacing.sm },
  actionGhost: {
    minHeight: 36,
    paddingHorizontal: spacing.md,
    borderRadius: radius.md,
    alignItems: 'center',
    justifyContent: 'center',
  },
  actionGhostText: { color: colors.brand700, ...font('600'), fontSize: 13 },
  actionOutline: {
    minHeight: 36,
    paddingHorizontal: spacing.md,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate300,
    backgroundColor: colors.white,
    alignItems: 'center',
    justifyContent: 'center',
  },
  actionOutlineText: { color: colors.slate700, ...font('600'), fontSize: 13 },
});
