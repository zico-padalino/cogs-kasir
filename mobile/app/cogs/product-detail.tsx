import { useFocusEffect, useLocalSearchParams, useRouter } from 'expo-router';
import { useCallback, useMemo, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import {
  Badge,
  Card,
  EmptyState,
  Field,
  Input,
  PrimaryButton,
  ScreenHeader,
  SectionTitle,
  StatCard,
  StepHeader,
} from '@/components/cogs-ui';
import { getCogsDb } from '@/cogs/db';
import { rollUpCost } from '@/cogs/engine';
import { formatQty, formatRupiah, parseNumber } from '@/cogs/format';
import {
  deleteBom,
  deleteProduct,
  getProduct,
  getProductStat,
  listActiveProducts,
  listBom,
  listLots,
  upsertBom,
  type BomRowView,
  type LotView,
} from '@/cogs/repo';
import { PRODUCT_TYPE_LABEL, type BomNode, type Product } from '@/cogs/types';
import { colors, radius, spacing } from '@/theme';

export default function ProductDetailScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { id } = useLocalSearchParams<{ id: string }>();
  const productId = Number(id);

  const [product, setProduct] = useState<Product | null>(null);
  const [stat, setStat] = useState({ available: 0, average_cost: 0 });
  const [bom, setBom] = useState<BomRowView[]>([]);
  const [lots, setLots] = useState<LotView[]>([]);
  const [allProducts, setAllProducts] = useState<Product[]>([]);
  const [rollup, setRollup] = useState<BomNode | null>(null);

  const [childId, setChildId] = useState<number | null>(null);
  const [qty, setQty] = useState('');
  const [scrap, setScrap] = useState('0');

  const isManufacturable = product?.type === 'finished_good' || product?.type === 'semi_finished';

  const refresh = useCallback(async () => {
    const current = await getProduct(productId);
    setProduct(current);

    if (!current) {
      return;
    }

    setStat(await getProductStat(current));

    if (current.type === 'raw_material') {
      const allLots = await listLots();
      setLots(allLots.filter((lot) => lot.product_id === current.id));
    } else {
      const rows = await listBom(current.id);
      setBom(rows);
      const others = (await listActiveProducts()).filter((p) => p.id !== current.id);
      setAllProducts(others);

      if (rows.length > 0) {
        const db = await getCogsDb();
        setRollup(await rollUpCost(db, current, 1));
      } else {
        setRollup(null);
      }
    }
  }, [productId]);

  useFocusEffect(
    useCallback(() => {
      refresh();
    }, [refresh]),
  );

  const availableChildren = useMemo(
    () => allProducts.filter((p) => !bom.some((row) => row.child_product_id === p.id)),
    [allProducts, bom],
  );

  const handleAddBom = async () => {
    if (!childId) {
      Alert.alert('Lengkapi', 'Pilih bahan dulu.');
      return;
    }

    const quantity = parseNumber(qty);

    if (quantity <= 0) {
      Alert.alert('Lengkapi', 'Jumlah harus lebih dari 0.');
      return;
    }

    await upsertBom({
      parent_product_id: productId,
      child_product_id: childId,
      quantity,
      scrap_percentage: parseNumber(scrap),
      sequence: bom.length + 1,
    });

    setChildId(null);
    setQty('');
    setScrap('0');
    await refresh();
  };

  const handleDeleteBom = (bomId: number) => {
    Alert.alert('Hapus bahan?', 'Bahan ini akan dihapus dari resep.', [
      { text: 'Batal', style: 'cancel' },
      { text: 'Hapus', style: 'destructive', onPress: async () => { await deleteBom(bomId); await refresh(); } },
    ]);
  };

  const handleDeleteProduct = () => {
    Alert.alert('Hapus produk?', 'Produk beserta resep & stoknya akan dihapus.', [
      { text: 'Batal', style: 'cancel' },
      {
        text: 'Hapus',
        style: 'destructive',
        onPress: async () => {
          try {
            await deleteProduct(productId);
            router.back();
          } catch (error) {
            Alert.alert('Tidak bisa dihapus', error instanceof Error ? error.message : 'Terjadi kesalahan.');
          }
        },
      },
    ]);
  };

  if (!product) {
    return (
      <View style={styles.root}>
        <ScreenHeader title="Detail Produk" />
        <View style={{ padding: spacing.lg }}>
          <EmptyState icon="🔍" title="Produk tidak ditemukan" />
        </View>
      </View>
    );
  }

  return (
    <View style={styles.root}>
      <ScreenHeader title={product.name} subtitle={product.sku} />
      <ScrollView
        contentContainerStyle={{
          padding: spacing.lg,
          paddingBottom: insets.bottom + spacing.xxl,
          gap: spacing.lg,
        }}
      >
        <View style={styles.statGrid}>
          <StatCard label="Stok Tersedia" value={`${formatQty(stat.available)} ${product.unit}`} color="brand" />
          <StatCard label="Jenis" value={PRODUCT_TYPE_LABEL[product.type]} color="slate" />
          <StatCard
            label={isManufacturable ? 'Bahan Resep' : 'Harga Rata-rata'}
            value={isManufacturable ? `${bom.length}` : formatRupiah(stat.average_cost)}
            color="amber"
          />
        </View>

        {isManufacturable ? (
          <>
            <StepHeader
              number={3}
              title="Resep / BOM"
              description="Susun bahan per 1 unit produk (dengan scrap %) untuk roll-up biaya."
            />
            {rollup ? (
              <Card>
                <SectionTitle>Estimasi Biaya per {product.unit}</SectionTitle>
                <Text style={styles.rollupValue}>{formatRupiah(rollup.unit_cost)}</Text>
                <Text style={styles.rollupHint}>Dari roll-up resep (harga bahan saat ini)</Text>
                <View style={{ gap: spacing.xs }}>
                  {rollup.components.map((node) => (
                    <BomTreeNode key={`${node.product_id}`} node={node} depth={0} />
                  ))}
                </View>
              </Card>
            ) : null}

            <Card>
              <SectionTitle>Resep / BOM ({bom.length})</SectionTitle>
              {bom.length === 0 ? (
                <EmptyState icon="📝" title="Belum ada bahan" hint="Tambahkan bahan di bawah." />
              ) : (
                bom.map((row) => (
                  <View key={row.id} style={styles.bomRow}>
                    <View style={{ flex: 1 }}>
                      <Text style={styles.bomName}>{row.child_name}</Text>
                      <Text style={styles.bomMeta}>
                        {formatQty(row.quantity)} {row.child_unit} · scrap {formatQty(row.scrap_percentage)}%
                      </Text>
                    </View>
                    <Pressable onPress={() => handleDeleteBom(row.id)}>
                      <Text style={styles.deleteText}>Hapus</Text>
                    </Pressable>
                  </View>
                ))
              )}
            </Card>

            <Card>
              <SectionTitle>+ Tambah Bahan ke Resep</SectionTitle>
              {availableChildren.length === 0 ? (
                <Text style={styles.mutedText}>Semua produk lain sudah dipakai atau belum ada produk lain.</Text>
              ) : (
                <>
                  <Field label="Bahan">
                    <View style={styles.chipWrap}>
                      {availableChildren.map((child) => (
                        <Pressable
                          key={child.id}
                          onPress={() => setChildId(child.id)}
                          style={[styles.chip, childId === child.id && styles.chipActive]}
                        >
                          <Text style={[styles.chipText, childId === child.id && styles.chipTextActive]}>
                            {child.name}
                          </Text>
                        </Pressable>
                      ))}
                    </View>
                  </Field>
                  <View style={styles.row}>
                    <View style={{ flex: 1 }}>
                      <Field label="Jumlah per 1 unit">
                        <Input value={qty} onChangeText={setQty} keyboardType="numeric" placeholder="0.5" />
                      </Field>
                    </View>
                    <View style={{ width: 110 }}>
                      <Field label="Scrap %">
                        <Input value={scrap} onChangeText={setScrap} keyboardType="numeric" placeholder="0" />
                      </Field>
                    </View>
                  </View>
                  <PrimaryButton label="Tambah Bahan" onPress={handleAddBom} />
                </>
              )}
            </Card>
          </>
        ) : (
          <Card>
            <SectionTitle>Stok Bahan Ini</SectionTitle>
            {lots.length === 0 ? (
              <EmptyState icon="📥" title="Belum ada stok" hint="Tambahkan stok di langkah Inventory." />
            ) : (
              lots.map((lot) => (
                <View key={lot.id} style={styles.lotRow}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.bomName}>{lot.lot_number ?? 'Tanpa lot'}</Text>
                    <Text style={styles.bomMeta}>
                      Sisa {formatQty(lot.quantity_remaining)} / {formatQty(lot.quantity_received)} {lot.product_unit}
                    </Text>
                  </View>
                  <Text style={styles.lotCost}>{formatRupiah(lot.unit_cost)}</Text>
                </View>
              ))
            )}
          </Card>
        )}

        <View style={styles.footActions}>
          <Badge label={product.is_active ? 'Aktif' : 'Nonaktif'} tone={product.is_active ? 'green' : 'slate'} />
          <Pressable onPress={handleDeleteProduct}>
            <Text style={styles.deleteText}>Hapus Produk</Text>
          </Pressable>
        </View>
      </ScrollView>
    </View>
  );
}

function BomTreeNode({ node, depth }: { node: BomNode; depth: number }) {
  return (
    <View style={{ paddingLeft: depth * spacing.md, gap: spacing.xs }}>
      <View style={styles.treeRow}>
        <Text style={styles.treeName}>
          {depth > 0 ? '└ ' : ''}
          {node.name}
        </Text>
        <Text style={styles.treeCost}>{formatRupiah(node.total_cost)}</Text>
      </View>
      {node.components.map((child) => (
        <BomTreeNode key={`${child.product_id}-${depth}`} node={child} depth={depth + 1} />
      ))}
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.slate100 },
  statGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  rollupValue: { fontSize: 22, fontWeight: '800', color: colors.brand600 },
  rollupHint: { fontSize: 12, color: colors.slate500 },
  bomRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderTopWidth: 1,
    borderTopColor: colors.slate100,
    paddingTop: spacing.sm,
  },
  lotRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderTopWidth: 1,
    borderTopColor: colors.slate100,
    paddingTop: spacing.sm,
  },
  bomName: { fontSize: 14, fontWeight: '600', color: colors.slate900 },
  bomMeta: { fontSize: 12, color: colors.slate500, marginTop: 2 },
  lotCost: { fontSize: 14, fontWeight: '700', color: colors.brand600 },
  deleteText: { fontSize: 13, fontWeight: '700', color: '#dc2626' },
  mutedText: { fontSize: 13, color: colors.slate500 },
  row: { flexDirection: 'row', gap: spacing.md },
  chipWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  chip: {
    borderRadius: 999,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
  },
  chipActive: { borderColor: colors.brand600, backgroundColor: colors.brand50 },
  chipText: { fontSize: 13, color: colors.slate600 },
  chipTextActive: { color: colors.brand700, fontWeight: '700' },
  treeRow: { flexDirection: 'row', justifyContent: 'space-between', gap: spacing.md },
  treeName: { flex: 1, fontSize: 13, color: colors.slate700 },
  treeCost: { fontSize: 13, fontWeight: '600', color: colors.slate900 },
  footActions: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    paddingVertical: spacing.sm,
  },
});
