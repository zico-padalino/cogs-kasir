import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import {
  Card,
  Field,
  Input,
  PrimaryButton,
  ScreenHeader,
  SectionTitle,
  StatCard,
} from '@/components/cogs-ui';
import { getCogsDb } from '@/cogs/db';
import { calculateSaleCogs, recordSaleCogs, type CogsResult } from '@/cogs/engine';
import { formatRupiah, parseNumber } from '@/cogs/format';
import { listActiveProducts } from '@/cogs/repo';
import type { Product } from '@/cogs/types';
import { colors, radius, spacing } from '@/theme';

export default function CalculateScreen() {
  const insets = useSafeAreaInsets();
  const [products, setProducts] = useState<Product[]>([]);
  const [productId, setProductId] = useState<number | null>(null);
  const [quantity, setQuantity] = useState('1');
  const [consumeInventory, setConsumeInventory] = useState(false);
  const [recordSale, setRecordSale] = useState(false);
  const [result, setResult] = useState<CogsResult | null>(null);
  const [busy, setBusy] = useState(false);

  const refresh = useCallback(async () => {
    const all = await listActiveProducts();
    const sellable = all.filter((p) => p.type !== 'raw_material');
    setProducts(sellable);
  }, []);

  useFocusEffect(
    useCallback(() => {
      refresh();
    }, [refresh]),
  );

  const handleCalculate = async () => {
    if (!productId) {
      Alert.alert('Lengkapi', 'Pilih produk dulu.');
      return;
    }

    const product = products.find((p) => p.id === productId);
    const qty = parseNumber(quantity);

    if (!product || qty <= 0) {
      Alert.alert('Lengkapi', 'Jumlah harus lebih dari 0.');
      return;
    }

    setBusy(true);
    try {
      const db = await getCogsDb();

      if (recordSale) {
        setResult(await recordSaleCogs(db, product, qty));
        Alert.alert('Tersimpan', 'Perhitungan COGS dicatat ke riwayat & stok dikurangi.');
      } else {
        setResult(await calculateSaleCogs(db, product, qty, consumeInventory));
      }
    } catch (error) {
      Alert.alert('Gagal', error instanceof Error ? error.message : 'Terjadi kesalahan.');
    } finally {
      setBusy(false);
    }
  };

  return (
    <View style={styles.root}>
      <ScreenHeader title="Hitung / Simulasi COGS" subtitle="Perkiraan biaya per produk" />
      <ScrollView
        contentContainerStyle={{
          padding: spacing.lg,
          paddingBottom: insets.bottom + spacing.xxl,
          gap: spacing.lg,
        }}
      >
        <Card>
          <SectionTitle>Parameter</SectionTitle>
          <Field label="Produk">
            <View style={styles.chipWrap}>
              {products.map((product) => (
                <Pressable
                  key={product.id}
                  onPress={() => {
                    setProductId(product.id);
                    setResult(null);
                  }}
                  style={[styles.chip, productId === product.id && styles.chipActive]}
                >
                  <Text style={[styles.chipText, productId === product.id && styles.chipTextActive]}>
                    {product.name}
                  </Text>
                </Pressable>
              ))}
            </View>
          </Field>
          <Field label="Jumlah">
            <Input value={quantity} onChangeText={setQuantity} keyboardType="numeric" placeholder="1" />
          </Field>

          <View style={styles.toggleRow}>
            <View style={{ flex: 1 }}>
              <Text style={styles.toggleTitle}>Kurangi stok persediaan</Text>
              <Text style={styles.toggleHint}>Matikan untuk simulasi tanpa memotong stok.</Text>
            </View>
            <Switch
              value={consumeInventory || recordSale}
              disabled={recordSale}
              onValueChange={setConsumeInventory}
              trackColor={{ true: colors.brand600, false: colors.slate200 }}
            />
          </View>

          <View style={styles.toggleRow}>
            <View style={{ flex: 1 }}>
              <Text style={styles.toggleTitle}>Catat sebagai penjualan</Text>
              <Text style={styles.toggleHint}>Simpan ke riwayat & kurangi stok.</Text>
            </View>
            <Switch
              value={recordSale}
              onValueChange={(value) => {
                setRecordSale(value);
                if (value) setConsumeInventory(true);
              }}
              trackColor={{ true: colors.brand600, false: colors.slate200 }}
            />
          </View>

          <PrimaryButton
            label={busy ? 'Menghitung…' : recordSale ? 'Hitung & Catat' : 'Hitung COGS'}
            onPress={handleCalculate}
            disabled={busy}
          />
        </Card>

        {result ? (
          <>
            <View style={styles.statGrid}>
              <StatCard label="Total COGS" value={formatRupiah(result.total_cogs)} color="brand" />
              <StatCard label="Bahan Langsung" value={formatRupiah(result.direct_material)} color="green" />
              <StatCard label="Overhead" value={formatRupiah(result.manufacturing_overhead)} color="rose" />
              <StatCard label="COGS / Unit" value={formatRupiah(result.unit_cogs)} color="amber" />
            </View>
            <Card>
              <SectionTitle>Rincian Overhead</SectionTitle>
              {(result.breakdown.overhead as { name: string; allocated_cost: number }[] | undefined)?.length ? (
                (result.breakdown.overhead as { name: string; allocated_cost: number }[]).map((row, index) => (
                  <View key={index} style={styles.detailRow}>
                    <Text style={styles.detailName}>{row.name}</Text>
                    <Text style={styles.detailValue}>{formatRupiah(row.allocated_cost)}</Text>
                  </View>
                ))
              ) : (
                <Text style={styles.mutedText}>Tidak ada overhead aktif.</Text>
              )}
              <View style={styles.detailRow}>
                <Text style={styles.detailName}>Metode</Text>
                <Text style={styles.detailValue}>
                  {String(result.breakdown.consumption_mode ?? result.calculation_method)}
                </Text>
              </View>
            </Card>
          </>
        ) : null}
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.slate100 },
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
  toggleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderTopWidth: 1,
    borderTopColor: colors.slate100,
    paddingTop: spacing.md,
  },
  toggleTitle: { fontSize: 14, fontWeight: '600', color: colors.slate900 },
  toggleHint: { fontSize: 12, color: colors.slate500, marginTop: 2 },
  statGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  detailRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: spacing.md,
    borderTopWidth: 1,
    borderTopColor: colors.slate100,
    paddingTop: spacing.sm,
  },
  detailName: { flex: 1, fontSize: 13, color: colors.slate600 },
  detailValue: { fontSize: 13, fontWeight: '700', color: colors.slate900 },
  mutedText: { fontSize: 13, color: colors.slate500 },
});
