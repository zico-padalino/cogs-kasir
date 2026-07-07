import { useFocusEffect, useLocalSearchParams } from 'expo-router';
import { useCallback, useState } from 'react';
import { ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { Card, EmptyState, ScreenHeader, SectionTitle, StatCard } from '@/components/cogs-ui';
import { formatQty, formatRupiah } from '@/cogs/format';
import { getCogsCalculation, type CogsCalculationView } from '@/cogs/repo';
import { COSTING_METHOD_LABEL, type CostingMethod } from '@/cogs/types';
import { colors, radius, spacing } from '@/theme';

export default function CogsDetailScreen() {
  const insets = useSafeAreaInsets();
  const { id } = useLocalSearchParams<{ id: string }>();
  const [calc, setCalc] = useState<CogsCalculationView | null>(null);

  const refresh = useCallback(async () => {
    setCalc(await getCogsCalculation(Number(id)));
  }, [id]);

  useFocusEffect(
    useCallback(() => {
      refresh();
    }, [refresh]),
  );

  if (!calc) {
    return (
      <View style={styles.root}>
        <ScreenHeader title="Detail COGS" />
        <View style={{ padding: spacing.lg }}>
          <EmptyState icon="🔍" title="Data tidak ditemukan" />
        </View>
      </View>
    );
  }

  const methodLabel =
    COSTING_METHOD_LABEL[calc.calculation_method as CostingMethod] ?? calc.calculation_method;
  const date = new Date(calc.calculated_at);

  return (
    <View style={styles.root}>
      <ScreenHeader title={calc.product_name} subtitle="Rincian perhitungan COGS" />
      <ScrollView
        contentContainerStyle={{
          padding: spacing.lg,
          paddingBottom: insets.bottom + spacing.xxl,
          gap: spacing.lg,
        }}
      >
        <View style={styles.hero}>
          <Text style={styles.heroLabel}>Biaya per unit</Text>
          <Text style={styles.heroValue}>{formatRupiah(calc.unit_cogs)}</Text>
          <Text style={styles.heroFormula}>= Bahan + Tenaga Kerja + Overhead</Text>
        </View>

        <View style={styles.statGrid}>
          <StatCard label="Total COGS" value={formatRupiah(calc.total_cogs)} color="brand" />
          <StatCard label="Bahan Baku" value={formatRupiah(calc.direct_material)} color="green" />
          <StatCard label="Tenaga Kerja" value={formatRupiah(calc.direct_labor)} color="amber" />
          <StatCard label="Overhead" value={formatRupiah(calc.manufacturing_overhead)} color="rose" />
        </View>

        <Card>
          <SectionTitle>Informasi</SectionTitle>
          <Detail label="Produk" value={calc.product_name} />
          <Detail label="Jumlah" value={formatQty(calc.quantity)} />
          <Detail
            label="Sumber"
            value={calc.reference_type === 'ProductionOrder' ? 'Produksi' : 'Penjualan'}
          />
          <Detail label="Metode" value={methodLabel} />
          <Detail label="Tanggal" value={date.toLocaleString('id-ID')} />
        </Card>
      </ScrollView>
    </View>
  );
}

function Detail({ label, value }: { label: string; value: string }) {
  return (
    <View style={styles.detailRow}>
      <Text style={styles.detailLabel}>{label}</Text>
      <Text style={styles.detailValue}>{value}</Text>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.slate100 },
  hero: { borderRadius: radius.xl, backgroundColor: colors.brand600, padding: spacing.lg, gap: 4 },
  heroLabel: { fontSize: 13, color: 'rgba(255,255,255,0.85)' },
  heroValue: { fontSize: 30, fontWeight: '800', color: colors.white },
  heroFormula: { fontSize: 12, color: 'rgba(255,255,255,0.8)' },
  statGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  detailRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: spacing.md,
    borderTopWidth: 1,
    borderTopColor: colors.slate100,
    paddingTop: spacing.sm,
  },
  detailLabel: { fontSize: 13, color: colors.slate600 },
  detailValue: { fontSize: 13, fontWeight: '700', color: colors.slate900, flexShrink: 1, textAlign: 'right' },
});
