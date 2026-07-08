import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { AppScaffold } from '@/components/AppScaffold';
import {
  Badge,
  Card,
  EmptyState,
  Field,
  Input,
  PrimaryButton,
  RupiahInput,
  SectionTitle,
  StepHeader,
} from '@/components/cogs-ui';
import { getCogsDb } from '@/cogs/db';
import { createProductionFromBom } from '@/cogs/engine';
import { formatQty, parseNumber, parseRupiah } from '@/cogs/format';
import { listManufacturableProducts, listProductionOrders, type ProductionView } from '@/cogs/repo';
import { PRODUCTION_STATUS_LABEL, type Product, type ProductionStatus } from '@/cogs/types';
import { colors, radius, spacing } from '@/theme';

const STATUS_TONE: Record<ProductionStatus, 'slate' | 'brand' | 'green' | 'rose'> = {
  draft: 'slate',
  in_progress: 'brand',
  completed: 'green',
  cancelled: 'rose',
};

export default function ProductionScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [orders, setOrders] = useState<ProductionView[]>([]);
  const [products, setProducts] = useState<Product[]>([]);
  const [showForm, setShowForm] = useState(false);

  const [productId, setProductId] = useState<number | null>(null);
  const [quantity, setQuantity] = useState('100');
  const [machineHours, setMachineHours] = useState('6');
  const [laborDesc, setLaborDesc] = useState('Operator');
  const [laborHours, setLaborHours] = useState('8');
  const [hourlyRate, setHourlyRate] = useState('20000');

  const refresh = useCallback(async () => {
    const [nextOrders, nextProducts] = await Promise.all([
      listProductionOrders(),
      listManufacturableProducts(),
    ]);
    setOrders(nextOrders);
    setProducts(nextProducts);
  }, []);

  useFocusEffect(
    useCallback(() => {
      refresh();
    }, [refresh]),
  );

  const handleCreate = async () => {
    if (!productId) {
      Alert.alert('Lengkapi', 'Pilih produk yang akan diproduksi.');
      return;
    }

    const qty = parseNumber(quantity);

    if (qty <= 0) {
      Alert.alert('Lengkapi', 'Jumlah produksi harus lebih dari 0.');
      return;
    }

    const product = products.find((p) => p.id === productId);

    if (!product) {
      return;
    }

    const labors = laborDesc.trim()
      ? [
          {
            description: laborDesc.trim(),
            labor_hours: parseNumber(laborHours),
            hourly_rate: parseRupiah(hourlyRate),
          },
        ]
      : [];

    const db = await getCogsDb();

    try {
      const orderId = await createProductionFromBom(
        db,
        product,
        qty,
        labors,
        parseNumber(machineHours),
        null,
      );
      setShowForm(false);
      setProductId(null);
      router.push({ pathname: '/cogs/production-detail', params: { id: String(orderId) } });
    } catch (error) {
      Alert.alert('Gagal', error instanceof Error ? error.message : 'Terjadi kesalahan.');
    }
  };

  return (
    <AppScaffold moduleType="cogs" title="Produksi" subtitle="Langkah 5 dari 6">
      <ScrollView
        contentContainerStyle={{
          padding: spacing.lg,
          paddingBottom: insets.bottom + spacing.xxl,
          gap: spacing.lg,
        }}
      >
        <StepHeader
          number={5}
          title="Produksi"
          description="Buat order produksi, mulai, lalu selesaikan — COGS dihitung otomatis."
        />
        {showForm ? (
          <Card>
            <SectionTitle>Order Produksi Baru</SectionTitle>
            {products.length === 0 ? (
              <Text style={styles.mutedText}>Buat produk jadi & resepnya dulu.</Text>
            ) : (
              <>
                <Field label="Produk">
                  <View style={styles.chipWrap}>
                    {products.map((product) => (
                      <Pressable
                        key={product.id}
                        onPress={() => setProductId(product.id)}
                        style={[styles.chip, productId === product.id && styles.chipActive]}
                      >
                        <Text style={[styles.chipText, productId === product.id && styles.chipTextActive]}>
                          {product.name}
                        </Text>
                      </Pressable>
                    ))}
                  </View>
                </Field>
                <View style={styles.row}>
                  <View style={{ flex: 1 }}>
                    <Field label="Jumlah produksi">
                      <Input value={quantity} onChangeText={setQuantity} keyboardType="numeric" />
                    </Field>
                  </View>
                  <View style={{ flex: 1 }}>
                    <Field label="Jam mesin">
                      <Input value={machineHours} onChangeText={setMachineHours} keyboardType="numeric" />
                    </Field>
                  </View>
                </View>
                <Field label="Tenaga kerja">
                  <Input value={laborDesc} onChangeText={setLaborDesc} placeholder="Operator" />
                </Field>
                <View style={styles.row}>
                  <View style={{ flex: 1 }}>
                    <Field label="Jam kerja">
                      <Input value={laborHours} onChangeText={setLaborHours} keyboardType="numeric" />
                    </Field>
                  </View>
                  <View style={{ flex: 1 }}>
                    <Field label="Upah / jam">
                      <RupiahInput value={hourlyRate} onChangeText={setHourlyRate} />
                    </Field>
                  </View>
                </View>
                <View style={styles.formActions}>
                  <View style={{ flex: 1 }}>
                    <PrimaryButton label="Batal" tone="outline" onPress={() => setShowForm(false)} />
                  </View>
                  <View style={{ flex: 1 }}>
                    <PrimaryButton label="Buat Order" onPress={handleCreate} />
                  </View>
                </View>
              </>
            )}
          </Card>
        ) : (
          <PrimaryButton label="+ Order Produksi Baru" onPress={() => setShowForm(true)} />
        )}

        <View style={{ gap: spacing.sm }}>
          <SectionTitle>Daftar Order ({orders.length})</SectionTitle>
          {orders.length === 0 ? (
            <Card>
              <EmptyState icon="🏭" title="Belum ada produksi" hint="Buat order produksi untuk hitung COGS." />
            </Card>
          ) : (
            orders.map((order) => (
              <Pressable
                key={order.id}
                onPress={() =>
                  router.push({ pathname: '/cogs/production-detail', params: { id: String(order.id) } })
                }
                style={({ pressed }) => [styles.orderCard, pressed && styles.pressed]}
              >
                <View style={{ flex: 1, gap: 4 }}>
                  <Text style={styles.orderNumber}>{order.order_number}</Text>
                  <Text style={styles.orderProduct}>
                    {order.product_name} · {formatQty(order.quantity_planned)} {order.product_unit}
                  </Text>
                  <Badge label={PRODUCTION_STATUS_LABEL[order.status]} tone={STATUS_TONE[order.status]} />
                </View>
                <Text style={styles.chevron}>›</Text>
              </Pressable>
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
  formActions: { flexDirection: 'row', gap: spacing.md },
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
  orderCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    padding: spacing.lg,
  },
  orderNumber: { fontSize: 14, fontWeight: '700', color: colors.slate900, fontFamily: 'monospace' },
  orderProduct: { fontSize: 13, color: colors.slate600 },
  chevron: { fontSize: 26, color: colors.slate500 },
  pressed: { opacity: 0.9, transform: [{ scale: 0.99 }] },
});
