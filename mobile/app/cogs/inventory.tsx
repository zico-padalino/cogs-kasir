import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { AppScaffold } from '@/components/AppScaffold';
import {
  Card,
  EmptyState,
  Field,
  Input,
  PrimaryButton,
  RupiahInput,
  SectionTitle,
  StepHeader,
} from '@/components/cogs-ui';
import { formatQty, formatRupiah, parseNumber, parseRupiah } from '@/cogs/format';
import { deleteLot, listLots, listRawMaterials, receiveInventory, type LotView } from '@/cogs/repo';
import type { Product } from '@/cogs/types';
import { colors, radius, spacing } from '@/theme';

export default function InventoryScreen() {
  const insets = useSafeAreaInsets();
  const [materials, setMaterials] = useState<Product[]>([]);
  const [lots, setLots] = useState<LotView[]>([]);
  const [productId, setProductId] = useState<number | null>(null);
  const [qty, setQty] = useState('');
  const [unitCost, setUnitCost] = useState('');
  const [lotNumber, setLotNumber] = useState('');

  const refresh = useCallback(async () => {
    const [mats, allLots] = await Promise.all([listRawMaterials(), listLots()]);
    setMaterials(mats);
    setLots(allLots);
  }, []);

  useFocusEffect(
    useCallback(() => {
      refresh();
    }, [refresh]),
  );

  const handleReceive = async () => {
    if (!productId) {
      Alert.alert('Lengkapi', 'Pilih bahan dulu.');
      return;
    }

    const quantity = parseNumber(qty);

    if (quantity <= 0) {
      Alert.alert('Lengkapi', 'Jumlah masuk harus lebih dari 0.');
      return;
    }

    await receiveInventory({
      product_id: productId,
      quantity,
      unit_cost: parseRupiah(unitCost),
      lot_number: lotNumber.trim() || null,
    });

    setQty('');
    setUnitCost('');
    setLotNumber('');
    await refresh();
  };

  const handleDelete = (lot: LotView) => {
    Alert.alert('Hapus lot?', 'Lot stok ini akan dihapus.', [
      { text: 'Batal', style: 'cancel' },
      {
        text: 'Hapus',
        style: 'destructive',
        onPress: async () => {
          try {
            await deleteLot(lot);
            await refresh();
          } catch (error) {
            Alert.alert('Tidak bisa dihapus', error instanceof Error ? error.message : 'Terjadi kesalahan.');
          }
        },
      },
    ]);
  };

  return (
    <AppScaffold moduleType="cogs" title="Stok Bahan Baku" subtitle="Langkah 4 dari 6">
      <ScrollView
        contentContainerStyle={{
          padding: spacing.lg,
          paddingBottom: insets.bottom + spacing.xxl,
          gap: spacing.lg,
        }}
      >
        <StepHeader
          number={4}
          title="Stok Bahan Baku"
          description="Catat pembelian bahan per lot (jumlah & harga) sebagai dasar FIFO / rata-rata."
        />
        <Card>
          <SectionTitle>+ Tambah Stok</SectionTitle>
          {materials.length === 0 ? (
            <Text style={styles.mutedText}>Buat bahan baku dulu di langkah Produk.</Text>
          ) : (
            <>
              <Field label="Bahan Baku">
                <View style={styles.chipWrap}>
                  {materials.map((material) => (
                    <Pressable
                      key={material.id}
                      onPress={() => setProductId(material.id)}
                      style={[styles.chip, productId === material.id && styles.chipActive]}
                    >
                      <Text style={[styles.chipText, productId === material.id && styles.chipTextActive]}>
                        {material.name}
                      </Text>
                    </Pressable>
                  ))}
                </View>
              </Field>
              <View style={styles.row}>
                <View style={{ flex: 1 }}>
                  <Field label="Jumlah masuk">
                    <Input value={qty} onChangeText={setQty} keyboardType="numeric" placeholder="100" />
                  </Field>
                </View>
                <View style={{ flex: 1 }}>
                  <Field label="Harga / satuan">
                    <RupiahInput value={unitCost} onChangeText={setUnitCost} placeholder="0" />
                  </Field>
                </View>
              </View>
              <Field label="No. Lot (opsional)">
                <Input value={lotNumber} onChangeText={setLotNumber} placeholder="LOT-001" />
              </Field>
              <PrimaryButton label="Simpan Stok" onPress={handleReceive} />
            </>
          )}
        </Card>

        <View style={{ gap: spacing.sm }}>
          <SectionTitle>Daftar Lot Stok ({lots.length})</SectionTitle>
          {lots.length === 0 ? (
            <Card>
              <EmptyState icon="📦" title="Belum ada stok" hint="Tambahkan pembelian bahan di atas." />
            </Card>
          ) : (
            lots.map((lot) => (
              <Card key={lot.id} style={styles.lotCard}>
                <View style={styles.lotHead}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.lotName}>{lot.product_name}</Text>
                    <Text style={styles.lotMeta}>{lot.lot_number ?? 'Tanpa lot'}</Text>
                  </View>
                  <Text style={styles.lotCost}>{formatRupiah(lot.unit_cost)}</Text>
                </View>
                <View style={styles.lotFoot}>
                  <Text style={styles.lotQty}>
                    Sisa {formatQty(lot.quantity_remaining)} / {formatQty(lot.quantity_received)} {lot.product_unit}
                  </Text>
                  {lot.quantity_remaining >= lot.quantity_received ? (
                    <Pressable onPress={() => handleDelete(lot)}>
                      <Text style={styles.deleteText}>Hapus</Text>
                    </Pressable>
                  ) : (
                    <Text style={styles.usedText}>Terpakai sebagian</Text>
                  )}
                </View>
              </Card>
            ))
          )}
        </View>
      </ScrollView>
    </AppScaffold>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.slate100 },
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
  lotCard: { gap: spacing.sm },
  lotHead: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.md },
  lotName: { fontSize: 15, fontWeight: '700', color: colors.slate900 },
  lotMeta: { fontSize: 12, color: colors.slate500, marginTop: 2, fontFamily: 'monospace' },
  lotCost: { fontSize: 15, fontWeight: '800', color: colors.brand600 },
  lotFoot: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderTopWidth: 1,
    borderTopColor: colors.slate100,
    paddingTop: spacing.sm,
  },
  lotQty: { fontSize: 13, color: colors.slate600 },
  deleteText: { fontSize: 13, fontWeight: '700', color: '#dc2626' },
  usedText: { fontSize: 12, color: colors.slate500, fontStyle: 'italic' },
});
