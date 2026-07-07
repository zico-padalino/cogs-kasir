import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import {
  Card,
  EmptyState,
  PrimaryButton,
  ScreenHeader,
  SectionTitle,
  StepHeader,
} from '@/components/cogs-ui';
import { formatQty, formatRupiah } from '@/cogs/format';
import { deleteCogsCalculation, listCogsCalculations, type CogsCalculationView } from '@/cogs/repo';
import { colors, radius, spacing } from '@/theme';

export default function CogsHistoryScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [calcs, setCalcs] = useState<CogsCalculationView[]>([]);

  const refresh = useCallback(async () => {
    setCalcs(await listCogsCalculations());
  }, []);

  useFocusEffect(
    useCallback(() => {
      refresh();
    }, [refresh]),
  );

  const handleDelete = (id: number) => {
    Alert.alert('Hapus hasil?', 'Catatan COGS ini akan dihapus.', [
      { text: 'Batal', style: 'cancel' },
      { text: 'Hapus', style: 'destructive', onPress: async () => { await deleteCogsCalculation(id); await refresh(); } },
    ]);
  };

  return (
    <View style={styles.root}>
      <ScreenHeader title="Hasil COGS" subtitle="Langkah 6 dari 6" />
      <ScrollView
        contentContainerStyle={{
          padding: spacing.lg,
          paddingBottom: insets.bottom + spacing.xxl,
          gap: spacing.lg,
        }}
      >
        <StepHeader
          number={6}
          title="Hasil COGS"
          description="Rincian Bahan + Tenaga Kerja + Overhead, per total dan per unit."
        />
        <View style={styles.formulaCard}>
          <Text style={styles.formulaTitle}>Rumus sederhana</Text>
          <Text style={styles.formulaLine}>COGS = Bahan Baku + Tenaga Kerja + Overhead</Text>
          <Text style={styles.formulaLine}>Biaya per unit = COGS ÷ Jumlah produk</Text>
        </View>

        <PrimaryButton label="Hitung / Simulasi COGS" onPress={() => router.push('/cogs/calculate')} />

        <View style={{ gap: spacing.sm }}>
          <SectionTitle>Riwayat ({calcs.length})</SectionTitle>
          {calcs.length === 0 ? (
            <Card>
              <EmptyState
                icon="📊"
                title="Belum ada hasil COGS"
                hint="Selesaikan produksi atau simulasikan perhitungan."
              />
            </Card>
          ) : (
            calcs.map((calc) => (
              <Pressable
                key={calc.id}
                onPress={() => router.push({ pathname: '/cogs/cogs-detail', params: { id: String(calc.id) } })}
                style={({ pressed }) => [styles.calcCard, pressed && styles.pressed]}
              >
                <View style={styles.calcHead}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.calcName}>{calc.product_name}</Text>
                    <Text style={styles.calcMeta}>
                      {formatQty(calc.quantity)} unit ·{' '}
                      {calc.reference_type === 'ProductionOrder' ? 'Produksi' : 'Penjualan'}
                    </Text>
                  </View>
                  <View style={{ alignItems: 'flex-end' }}>
                    <Text style={styles.calcTotal}>{formatRupiah(calc.total_cogs)}</Text>
                    <Text style={styles.calcUnit}>{formatRupiah(calc.unit_cogs)}/unit</Text>
                  </View>
                </View>
                <View style={styles.calcFoot}>
                  <Text style={styles.calcBreakdown}>
                    Bahan {formatRupiah(calc.direct_material)} · TK {formatRupiah(calc.direct_labor)} · OH{' '}
                    {formatRupiah(calc.manufacturing_overhead)}
                  </Text>
                  <Pressable onPress={() => handleDelete(calc.id)} hitSlop={8}>
                    <Text style={styles.deleteText}>Hapus</Text>
                  </Pressable>
                </View>
              </Pressable>
            ))
          )}
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.slate100 },
  formulaCard: { borderRadius: radius.lg, backgroundColor: colors.brand50, padding: spacing.md, gap: 4 },
  formulaTitle: { fontSize: 12, fontWeight: '700', color: colors.brand700, textTransform: 'uppercase' },
  formulaLine: { fontSize: 13, color: colors.brand700 },
  calcCard: {
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    padding: spacing.lg,
    gap: spacing.sm,
  },
  calcHead: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.md },
  calcName: { fontSize: 15, fontWeight: '700', color: colors.slate900 },
  calcMeta: { fontSize: 12, color: colors.slate500, marginTop: 2 },
  calcTotal: { fontSize: 16, fontWeight: '800', color: colors.brand600 },
  calcUnit: { fontSize: 12, color: colors.slate500 },
  calcFoot: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.md,
    borderTopWidth: 1,
    borderTopColor: colors.slate100,
    paddingTop: spacing.sm,
  },
  calcBreakdown: { flex: 1, fontSize: 11, color: colors.slate500 },
  deleteText: { fontSize: 13, fontWeight: '700', color: '#dc2626' },
  pressed: { opacity: 0.9, transform: [{ scale: 0.99 }] },
});
