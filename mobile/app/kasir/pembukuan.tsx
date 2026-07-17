import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { ActivityIndicator, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { kasirApi } from '@/api/kasir';
import type { PosOrder } from '@/api/types';
import { AppScaffold } from '@/components/AppScaffold';
import { colors, font, radius, spacing } from '@/theme';
import { formatRupiah } from '@/utils/rupiah';

export default function PembukuanScreen() {
  const router = useRouter();
  const [period, setPeriod] = useState('day');
  const [loading, setLoading] = useState(true);
  const [data, setData] = useState<{
    omzet: number;
    count: number;
    average: number;
    range_label?: string;
    orders?: PosOrder[];
    by_payment?: Record<string, { label: string; count: number; total: number }>;
  } | null>(null);

  const refresh = useCallback(async () => {
    setLoading(true);
    try {
      const res = await kasirApi.pembukuan({ period });
      setData(res.data as typeof data);
    } catch {
      // PIN_LOCKED → redirect global
    } finally {
      setLoading(false);
    }
  }, [period]);

  useFocusEffect(
    useCallback(() => {
      void refresh();
    }, [refresh]),
  );

  return (
    <AppScaffold moduleType="kasir" title="Pembukuan" subtitle="Laporan penjualan">
      <View style={styles.periodRow}>
        {[
          { value: 'day', label: 'Hari' },
          { value: 'week', label: 'Minggu' },
          { value: 'month', label: 'Bulan' },
          { value: 'all', label: 'Semua' },
        ].map((p) => (
          <Pressable
            key={p.value}
            onPress={() => setPeriod(p.value)}
            style={[styles.periodChip, period === p.value && styles.periodChipOn]}
          >
            <Text style={[styles.periodText, period === p.value && styles.periodTextOn]}>{p.label}</Text>
          </Pressable>
        ))}
      </View>

      {loading || !data ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.brand600} />
        </View>
      ) : (
        <ScrollView contentContainerStyle={{ padding: spacing.lg, gap: spacing.md }}>
          <Text style={styles.range}>{data.range_label}</Text>
          <View style={styles.stats}>
            <View style={styles.stat}>
              <Text style={styles.statLabel}>Omzet</Text>
              <Text style={styles.statValue}>{formatRupiah(Number(data.omzet || 0))}</Text>
            </View>
            <View style={styles.stat}>
              <Text style={styles.statLabel}>Transaksi</Text>
              <Text style={styles.statValue}>{data.count}</Text>
            </View>
            <View style={styles.stat}>
              <Text style={styles.statLabel}>Rata-rata</Text>
              <Text style={styles.statValue}>{formatRupiah(Number(data.average || 0))}</Text>
            </View>
          </View>

          {data.by_payment
            ? Object.values(data.by_payment).map((p) => (
                <View key={p.label} style={styles.card}>
                  <Text style={styles.cardTitle}>{p.label}</Text>
                  <Text style={styles.meta}>
                    {p.count} trx · {formatRupiah(p.total)}
                  </Text>
                </View>
              ))
            : null}

          {(data.orders || []).slice(0, 30).map((order) => (
            <View key={order.id} style={styles.card}>
              <Text style={styles.cardTitle}>#{order.order_number}</Text>
              <Text style={styles.meta}>
                {order.payment_method_label} · {formatRupiah(order.total)}
              </Text>
            </View>
          ))}
        </ScrollView>
      )}
    </AppScaffold>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  periodRow: { flexDirection: 'row', gap: 8, padding: spacing.lg, paddingBottom: 0 },
  periodChip: {
    flex: 1,
    alignItems: 'center',
    paddingVertical: spacing.sm,
    borderRadius: radius.md,
    backgroundColor: colors.white,
    borderWidth: 1,
    borderColor: colors.slate200,
  },
  periodChipOn: { backgroundColor: colors.brand600, borderColor: colors.brand600 },
  periodText: { fontSize: 12, color: colors.slate600, ...font('600') },
  periodTextOn: { color: colors.white },
  range: { fontSize: 13, color: colors.slate500 },
  stats: { flexDirection: 'row', gap: spacing.sm },
  stat: {
    flex: 1,
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.md,
  },
  statLabel: { fontSize: 11, color: colors.slate500, ...font('600') },
  statValue: { fontSize: 13, color: colors.slate900, ...font('700'), marginTop: 4 },
  card: {
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.md,
  },
  cardTitle: { fontSize: 14, ...font('600'), color: colors.slate900 },
  meta: { fontSize: 12, color: colors.slate500, marginTop: 2 },
});
