import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import {
  Badge,
  Callout,
  Card,
  EmptyState,
  Field,
  Input,
  PrimaryButton,
  RupiahInput,
  ScreenHeader,
  SectionTitle,
  Segmented,
  StepHeader,
} from '@/components/cogs-ui';
import { parseRupiah } from '@/cogs/format';
import { createProduct, listProducts } from '@/cogs/repo';
import {
  COSTING_METHOD_LABEL,
  PRODUCT_TYPE_LABEL,
  type CostingMethod,
  type Product,
  type ProductType,
} from '@/cogs/types';
import { colors, radius, spacing } from '@/theme';

const TYPE_OPTIONS: { value: ProductType; label: string }[] = [
  { value: 'raw_material', label: 'Bahan Baku' },
  { value: 'semi_finished', label: 'Setengah Jadi' },
  { value: 'finished_good', label: 'Barang Jadi' },
];

const METHOD_OPTIONS: { value: CostingMethod; label: string }[] = [
  { value: 'weighted_average', label: 'Rata-rata' },
  { value: 'fifo', label: 'FIFO' },
  { value: 'standard', label: 'Standar' },
];

const TYPE_TONE: Record<ProductType, 'slate' | 'amber' | 'green'> = {
  raw_material: 'slate',
  semi_finished: 'amber',
  finished_good: 'green',
};

export default function ProductsScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [products, setProducts] = useState<(Product & { bom_count: number })[]>([]);
  const [showForm, setShowForm] = useState(false);

  const [sku, setSku] = useState('');
  const [name, setName] = useState('');
  const [type, setType] = useState<ProductType>('raw_material');
  const [unit, setUnit] = useState('pcs');
  const [method, setMethod] = useState<CostingMethod>('weighted_average');
  const [standardCost, setStandardCost] = useState('');

  const refresh = useCallback(async () => {
    setProducts(await listProducts());
  }, []);

  useFocusEffect(
    useCallback(() => {
      refresh();
    }, [refresh]),
  );

  const resetForm = () => {
    setSku('');
    setName('');
    setType('raw_material');
    setUnit('pcs');
    setMethod('weighted_average');
    setStandardCost('');
  };

  const handleAdd = async () => {
    if (!sku.trim() || !name.trim()) {
      Alert.alert('Lengkapi', 'Kode (SKU) dan nama produk wajib diisi.');
      return;
    }

    try {
      await createProduct({
        sku: sku.trim(),
        name: name.trim(),
        type,
        unit: unit.trim() || 'pcs',
        standard_cost: parseRupiah(standardCost),
        costing_method: method,
      });
      resetForm();
      setShowForm(false);
      await refresh();
    } catch {
      Alert.alert('Gagal', 'SKU sudah dipakai produk lain.');
    }
  };

  return (
    <View style={styles.root}>
      <ScreenHeader title="Daftar Produk" subtitle="Langkah 2 dari 6" />
      <ScrollView
        contentContainerStyle={{
          padding: spacing.lg,
          paddingBottom: insets.bottom + spacing.xxl,
          gap: spacing.lg,
        }}
      >
        <StepHeader
          number={2}
          title="Daftar Produk"
          description="Daftarkan bahan baku serta produk setengah jadi / jadi."
        />
        <Callout tone="tip">
          Resep (BOM) diatur di dalam detail produk jadi. Tap produk untuk buka detail & resepnya.
        </Callout>

        {showForm ? (
          <Card>
            <SectionTitle>Produk Baru</SectionTitle>
            <View style={styles.row}>
              <View style={{ flex: 1 }}>
                <Field label="Kode (SKU)">
                  <Input value={sku} onChangeText={setSku} placeholder="RM-XXX-001" autoCapitalize="characters" />
                </Field>
              </View>
              <View style={{ width: 96 }}>
                <Field label="Satuan">
                  <Input value={unit} onChangeText={setUnit} placeholder="kg" />
                </Field>
              </View>
            </View>
            <Field label="Nama produk">
              <Input value={name} onChangeText={setName} placeholder="Contoh: Tepung Terigu" />
            </Field>
            <Field label="Jenis">
              <Segmented options={TYPE_OPTIONS} value={type} onChange={setType} />
            </Field>
            <Field label="Metode biaya">
              <Segmented options={METHOD_OPTIONS} value={method} onChange={setMethod} />
            </Field>
            <Field label="Biaya standar (opsional)" hint="Dipakai bila metode Standar atau belum ada stok.">
              <RupiahInput value={standardCost} onChangeText={setStandardCost} placeholder="0" />
            </Field>
            <View style={styles.formActions}>
              <View style={{ flex: 1 }}>
                <PrimaryButton
                  label="Batal"
                  tone="outline"
                  onPress={() => {
                    resetForm();
                    setShowForm(false);
                  }}
                />
              </View>
              <View style={{ flex: 1 }}>
                <PrimaryButton label="Simpan" onPress={handleAdd} />
              </View>
            </View>
          </Card>
        ) : (
          <PrimaryButton label="+ Tambah Produk" onPress={() => setShowForm(true)} />
        )}

        <View style={{ gap: spacing.sm }}>
          <SectionTitle>Semua Produk ({products.length})</SectionTitle>
          {products.length === 0 ? (
            <Card>
              <EmptyState icon="📦" title="Belum ada produk" hint="Tambahkan bahan baku dan produk jadi." />
            </Card>
          ) : (
            products.map((product) => (
              <Pressable
                key={product.id}
                onPress={() =>
                  router.push({ pathname: '/cogs/product-detail', params: { id: String(product.id) } })
                }
                style={({ pressed }) => [styles.productCard, pressed && styles.pressed]}
              >
                <View style={{ flex: 1, gap: 4 }}>
                  <Text style={styles.productName}>{product.name}</Text>
                  <Text style={styles.productSku}>{product.sku}</Text>
                  <View style={styles.productBadges}>
                    <Badge label={PRODUCT_TYPE_LABEL[product.type]} tone={TYPE_TONE[product.type]} />
                    {product.bom_count > 0 ? <Badge label={`${product.bom_count} bahan`} tone="brand" /> : null}
                    <Text style={styles.methodText}>{COSTING_METHOD_LABEL[product.costing_method].split(' ')[0]}</Text>
                  </View>
                </View>
                <Text style={styles.chevron}>›</Text>
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
  row: { flexDirection: 'row', gap: spacing.md },
  formActions: { flexDirection: 'row', gap: spacing.md },
  productCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    padding: spacing.lg,
  },
  productName: { fontSize: 15, fontWeight: '700', color: colors.slate900 },
  productSku: { fontSize: 12, color: colors.slate500, fontFamily: 'monospace' },
  productBadges: { flexDirection: 'row', alignItems: 'center', gap: spacing.xs, flexWrap: 'wrap' },
  methodText: { fontSize: 11, color: colors.slate500 },
  chevron: { fontSize: 26, color: colors.slate500 },
  pressed: { opacity: 0.9, transform: [{ scale: 0.99 }] },
});
