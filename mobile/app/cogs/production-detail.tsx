import { useFocusEffect, useLocalSearchParams, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import {
  Badge,
  Card,
  EmptyState,
  PrimaryButton,
  ScreenHeader,
  SectionTitle,
  StatCard,
} from '@/components/cogs-ui';
import { getCogsDb } from '@/cogs/db';
import { completeProduction, startProduction } from '@/cogs/engine';
import { formatQty, formatRupiah } from '@/cogs/format';
import {
  deleteProductionOrder,
  getProductionLabors,
  getProductionMaterials,
  getProductionOrder,
  listCogsCalculations,
  type CogsCalculationView,
  type ProductionMaterialView,
  type ProductionView,
} from '@/cogs/repo';
import { PRODUCTION_STATUS_LABEL, type ProductionLabor, type ProductionStatus } from '@/cogs/types';
import { colors, radius, spacing } from '@/theme';

const STATUS_TONE: Record<ProductionStatus, 'slate' | 'brand' | 'green' | 'rose'> = {
  draft: 'slate',
  in_progress: 'brand',
  completed: 'green',
  cancelled: 'rose',
};

export default function ProductionDetailScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { id } = useLocalSearchParams<{ id: string }>();
  const orderId = Number(id);

  const [order, setOrder] = useState<ProductionView | null>(null);
  const [materials, setMaterials] = useState<ProductionMaterialView[]>([]);
  const [labors, setLabors] = useState<ProductionLabor[]>([]);
  const [cogs, setCogs] = useState<CogsCalculationView | null>(null);
  const [busy, setBusy] = useState(false);

  const refresh = useCallback(async () => {
    const [nextOrder, nextMaterials, nextLabors] = await Promise.all([
      getProductionOrder(orderId),
      getProductionMaterials(orderId),
      getProductionLabors(orderId),
    ]);
    setOrder(nextOrder);
    setMaterials(nextMaterials);
    setLabors(nextLabors);

    const calcs = await listCogsCalculations();
    setCogs(
      calcs.find((c) => c.reference_type === 'ProductionOrder' && c.reference_id === orderId) ?? null,
    );
  }, [orderId]);

  useFocusEffect(
    useCallback(() => {
      refresh();
    }, [refresh]),
  );

  const handleStart = async () => {
    if (!order) return;
    setBusy(true);
    try {
      const db = await getCogsDb();
      await startProduction(db, order);
      await refresh();
    } catch (error) {
      Alert.alert('Gagal', error instanceof Error ? error.message : 'Terjadi kesalahan.');
    } finally {
      setBusy(false);
    }
  };

  const handleComplete = () => {
    if (!order) return;
    Alert.alert(
      'Selesaikan & hitung COGS?',
      'Stok bahan akan dikurangi, produk jadi ditambahkan ke stok, dan COGS dihitung otomatis.',
      [
        { text: 'Batal', style: 'cancel' },
        {
          text: 'Selesaikan',
          onPress: async () => {
            setBusy(true);
            try {
              const db = await getCogsDb();
              await completeProduction(db, order);
              await refresh();
            } catch (error) {
              Alert.alert('Gagal', error instanceof Error ? error.message : 'Terjadi kesalahan.');
            } finally {
              setBusy(false);
            }
          },
        },
      ],
    );
  };

  const handleDelete = () => {
    if (!order) return;
    Alert.alert('Hapus order?', 'Order produksi ini akan dihapus.', [
      { text: 'Batal', style: 'cancel' },
      {
        text: 'Hapus',
        style: 'destructive',
        onPress: async () => {
          try {
            await deleteProductionOrder(order);
            router.back();
          } catch (error) {
            Alert.alert('Tidak bisa dihapus', error instanceof Error ? error.message : 'Terjadi kesalahan.');
          }
        },
      },
    ]);
  };

  if (!order) {
    return (
      <View style={styles.root}>
        <ScreenHeader title="Detail Produksi" />
        <View style={{ padding: spacing.lg }}>
          <EmptyState icon="🔍" title="Order tidak ditemukan" />
        </View>
      </View>
    );
  }

  return (
    <View style={styles.root}>
      <ScreenHeader title={order.order_number} subtitle={order.product_name} />
      <ScrollView
        contentContainerStyle={{
          padding: spacing.lg,
          paddingBottom: insets.bottom + spacing.xxl,
          gap: spacing.lg,
        }}
      >
        <Card>
          <View style={styles.statusRow}>
            <View>
              <Text style={styles.mutedLabel}>Status</Text>
              <Badge label={PRODUCTION_STATUS_LABEL[order.status]} tone={STATUS_TONE[order.status]} />
            </View>
            <View style={{ alignItems: 'flex-end' }}>
              <Text style={styles.mutedLabel}>Rencana</Text>
              <Text style={styles.qtyText}>
                {formatQty(order.quantity_planned)} {order.product_unit}
              </Text>
            </View>
          </View>

          {order.status === 'draft' ? (
            <PrimaryButton label={busy ? 'Memproses…' : 'Mulai Produksi'} onPress={handleStart} disabled={busy} />
          ) : null}
          {order.status === 'draft' || order.status === 'in_progress' ? (
            <PrimaryButton
              label={busy ? 'Memproses…' : 'Selesaikan & Hitung COGS'}
              tone="green"
              onPress={handleComplete}
              disabled={busy}
            />
          ) : null}
          {order.status !== 'completed' ? (
            <Pressable onPress={handleDelete} style={styles.deleteBtn}>
              <Text style={styles.deleteText}>Hapus Order</Text>
            </Pressable>
          ) : null}
        </Card>

        {cogs ? (
          <>
            <View style={styles.hero}>
              <Text style={styles.heroLabel}>Biaya per 1 {order.product_unit}</Text>
              <Text style={styles.heroValue}>{formatRupiah(cogs.unit_cogs)}</Text>
              <Text style={styles.heroFormula}>= Bahan + Tenaga Kerja + Overhead</Text>
            </View>

            <View style={styles.statGrid}>
              <StatCard label="Total Biaya" value={formatRupiah(cogs.total_cogs)} color="brand" />
              <StatCard label="Bahan Baku" value={formatRupiah(cogs.direct_material)} color="green" />
              <StatCard label="Tenaga Kerja" value={formatRupiah(cogs.direct_labor)} color="amber" />
              <StatCard label="Overhead" value={formatRupiah(cogs.manufacturing_overhead)} color="rose" />
            </View>
          </>
        ) : null}

        <Card>
          <SectionTitle>Bahan yang Dipakai ({materials.length})</SectionTitle>
          {materials.map((material) => (
            <View key={material.id} style={styles.itemRow}>
              <View style={{ flex: 1 }}>
                <Text style={styles.itemName}>{material.product_name}</Text>
                <Text style={styles.itemMeta}>
                  {formatQty(order.status === 'completed' ? material.quantity_used : material.quantity_planned)}{' '}
                  {material.product_unit}
                  {material.unit_cost > 0 ? ` · ${formatRupiah(material.unit_cost)}` : ''}
                </Text>
              </View>
              <Text style={styles.itemCost}>
                {material.total_cost > 0 ? formatRupiah(material.total_cost) : '—'}
              </Text>
            </View>
          ))}
        </Card>

        {labors.length > 0 ? (
          <Card>
            <SectionTitle>Tenaga Kerja ({labors.length})</SectionTitle>
            {labors.map((labor) => (
              <View key={labor.id} style={styles.itemRow}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.itemName}>{labor.description}</Text>
                  <Text style={styles.itemMeta}>
                    {formatQty(labor.labor_hours)} jam · {formatRupiah(labor.hourly_rate)}/jam
                  </Text>
                </View>
                <Text style={styles.itemCost}>{formatRupiah(labor.total_cost)}</Text>
              </View>
            ))}
          </Card>
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.slate100 },
  statusRow: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'flex-start' },
  mutedLabel: { fontSize: 11, color: colors.slate500, marginBottom: 4 },
  qtyText: { fontSize: 16, fontWeight: '800', color: colors.slate900 },
  deleteBtn: { alignItems: 'center', paddingVertical: spacing.sm },
  deleteText: { fontSize: 13, fontWeight: '700', color: '#dc2626' },
  hero: {
    borderRadius: radius.xl,
    backgroundColor: colors.brand600,
    padding: spacing.lg,
    gap: 4,
  },
  heroLabel: { fontSize: 13, color: 'rgba(255,255,255,0.85)' },
  heroValue: { fontSize: 30, fontWeight: '800', color: colors.white },
  heroFormula: { fontSize: 12, color: 'rgba(255,255,255,0.8)' },
  statGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  itemRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderTopWidth: 1,
    borderTopColor: colors.slate100,
    paddingTop: spacing.sm,
  },
  itemName: { fontSize: 14, fontWeight: '600', color: colors.slate900 },
  itemMeta: { fontSize: 12, color: colors.slate500, marginTop: 2 },
  itemCost: { fontSize: 14, fontWeight: '700', color: colors.slate900 },
});
