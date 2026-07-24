import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useMemo, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { getLocalProducts, submitOnlineOrder } from '@/local-db/repository';
import type { LocalProduct } from '@/local-db/types';
import { colors, radius, spacing } from '@/theme';
import { formatRupiah } from '@/utils/rupiah';

const CATEGORY_LABELS: Record<string, string> = {
  minuman: 'Minuman',
  makanan: 'Makanan',
  pastry: 'Pastry',
  snack: 'Snack',
};

export default function PesanOnlineScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [products, setProducts] = useState<LocalProduct[]>([]);
  const [quantities, setQuantities] = useState<Record<number, number>>({});
  const [customerName, setCustomerName] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [errorText, setErrorText] = useState<string | null>(null);
  const [toast, setToast] = useState<{ orderNumber: string; customerName: string | null } | null>(null);

  const handleBack = () => {
    if (router.canGoBack()) {
      router.back();
    } else {
      router.replace('/');
    }
  };

  const refresh = useCallback(async () => {
    setProducts(await getLocalProducts());
  }, []);

  useFocusEffect(
    useCallback(() => {
      refresh();
    }, [refresh]),
  );

  const categories = useMemo(
    () => [...new Set(products.map((product) => product.category))],
    [products],
  );

  const items = useMemo(
    () =>
      products
        .map((product) => ({ product, quantity: quantities[product.id] ?? 0 }))
        .filter((entry) => entry.quantity > 0),
    [products, quantities],
  );

  const total = useMemo(
    () => items.reduce((sum, entry) => sum + entry.product.price * entry.quantity, 0),
    [items],
  );

  const totalQty = items.reduce((sum, entry) => sum + entry.quantity, 0);

  const setQuantity = (productId: number, next: number) => {
    setQuantities((prev) => ({ ...prev, [productId]: Math.max(0, next) }));
  };

  const handleSubmit = async () => {
    setErrorText(null);

    if (items.length === 0) {
      setErrorText('Pilih minimal satu menu dulu.');
      return;
    }

    setSubmitting(true);
    try {
      const order = await submitOnlineOrder({
        customerName,
        items: items.map((entry) => ({ productId: entry.product.id, quantity: entry.quantity })),
      });

      setQuantities({});
      setCustomerName('');
      setToast({ orderNumber: order.order_number, customerName: order.customer_name });
    } catch (error) {
      setErrorText(error instanceof Error ? error.message : 'Terjadi kesalahan.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <View style={styles.root}>
      <View style={[styles.toolbar, { paddingTop: insets.top + spacing.sm }]}>
        <Pressable onPress={handleBack} style={styles.iconBtn} hitSlop={8}>
          <Text style={styles.iconBtnText}>←</Text>
        </Pressable>
        <View style={styles.toolbarCopy}>
          <Text style={styles.toolbarTitle}>Pesan Online</Text>
          <Text style={styles.toolbarMeta}>Pilih menu, kirim ke kasir</Text>
        </View>
        <View style={styles.localBadge}>
          <Text style={styles.localBadgeText}>OFFLINE</Text>
        </View>
      </View>

      <ScrollView
        style={styles.scroll}
        contentContainerStyle={[
          styles.content,
          { paddingBottom: spacing.xl },
        ]}
      >
        {errorText ? (
          <View style={styles.errorBanner}>
            <Text style={styles.errorBannerText}>{errorText}</Text>
          </View>
        ) : null}

        <View style={styles.customerCard}>
          <Text style={styles.fieldLabel}>Nama pemesan</Text>
          <TextInput
            value={customerName}
            onChangeText={setCustomerName}
            placeholder="Contoh: Budi"
            placeholderTextColor={colors.slate500}
            style={styles.input}
          />
        </View>

        {categories.map((category) => (
          <View key={category} style={styles.categoryBlock}>
            <Text style={styles.categoryTitle}>{CATEGORY_LABELS[category] ?? category}</Text>
            {products
              .filter((product) => product.category === category)
              .map((product) => {
                const quantity = quantities[product.id] ?? 0;

                return (
                  <View key={product.id} style={styles.productCard}>
                    <View style={styles.productEmojiWrap}>
                      <Text style={styles.productEmoji}>{product.emoji}</Text>
                    </View>
                    <View style={styles.productCopy}>
                      <Text style={styles.productName}>{product.name}</Text>
                      <Text style={styles.productPrice}>{formatRupiah(product.price)}</Text>
                    </View>
                    {quantity > 0 ? (
                      <View style={styles.qtyRow}>
                        <Pressable
                          onPress={() => setQuantity(product.id, quantity - 1)}
                          style={styles.qtyBtn}
                        >
                          <Text style={styles.qtyBtnText}>−</Text>
                        </Pressable>
                        <Text style={styles.qtyValue}>{quantity}</Text>
                        <Pressable
                          onPress={() => setQuantity(product.id, quantity + 1)}
                          style={styles.qtyBtn}
                        >
                          <Text style={styles.qtyBtnText}>+</Text>
                        </Pressable>
                      </View>
                    ) : (
                      <Pressable onPress={() => setQuantity(product.id, 1)} style={styles.addBtn}>
                        <Text style={styles.addBtnText}>Tambah</Text>
                      </Pressable>
                    )}
                  </View>
                );
              })}
          </View>
        ))}
      </ScrollView>

      {totalQty > 0 ? (
          <View style={[styles.footer, { paddingBottom: Math.max(spacing.lg, insets.bottom + spacing.md) }]}>
          <View>
            <Text style={styles.footerLabel}>{totalQty} item</Text>
            <Text style={styles.footerTotal}>{formatRupiah(total)}</Text>
          </View>
          <Pressable
            onPress={handleSubmit}
            disabled={submitting}
            hitSlop={8}
            style={({ pressed }) => [styles.submitBtn, pressed && styles.pressed, submitting && styles.submitDisabled]}
          >
            <Text style={styles.submitBtnText}>{submitting ? 'Mengirim…' : 'Kirim Pesanan'}</Text>
          </Pressable>
        </View>
      ) : null}

      {toast ? (
        <View style={[styles.toast, { paddingBottom: Math.max(spacing.md, insets.bottom) }]}>
          <View style={{ flex: 1, minWidth: 0 }}>
            <Text style={styles.toastTitle}>Pesanan #{toast.orderNumber} terkirim</Text>
            <Text style={styles.toastText}>
              {toast.customerName ? `Atas nama ${toast.customerName}. ` : ''}Tunjukkan nomor ini di kasir.
            </Text>
          </View>
          <Pressable onPress={() => router.replace('/kasir')} style={styles.toastBtn} hitSlop={8}>
            <Text style={styles.toastBtnText}>Ke Kasir</Text>
          </Pressable>
          <Pressable onPress={() => setToast(null)} style={styles.toastClose} hitSlop={8}>
            <Text style={styles.toastCloseText}>✕</Text>
          </Pressable>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.slate100 },
  toolbar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.slate200,
    backgroundColor: colors.white,
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing.md,
  },
  iconBtn: {
    width: 44,
    height: 44,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.white,
  },
  iconBtnText: { fontSize: 20, color: colors.slate900 },
  toolbarCopy: { flex: 1, minWidth: 0 },
  toolbarTitle: { fontSize: 16, fontWeight: '700', color: colors.slate900 },
  toolbarMeta: { fontSize: 11, color: colors.slate500, marginTop: 2 },
  localBadge: { borderRadius: 999, backgroundColor: colors.brand50, paddingHorizontal: 10, paddingVertical: 6 },
  localBadgeText: { fontSize: 10, fontWeight: '800', color: colors.brand700 },
  scroll: { flex: 1 },
  content: { padding: spacing.lg, gap: spacing.lg },
  customerCard: {
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    padding: spacing.lg,
    gap: spacing.sm,
  },
  fieldLabel: { fontSize: 12, fontWeight: '700', color: colors.slate500 },
  input: {
    minHeight: 48,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.slate50,
    paddingHorizontal: spacing.md,
    fontSize: 15,
    color: colors.slate900,
  },
  categoryBlock: { gap: spacing.sm },
  categoryTitle: {
    fontSize: 12,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.4,
    color: colors.slate500,
  },
  productCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    padding: spacing.md,
  },
  productEmojiWrap: {
    width: 52,
    height: 52,
    borderRadius: radius.md,
    backgroundColor: colors.brand50,
    alignItems: 'center',
    justifyContent: 'center',
  },
  productEmoji: { fontSize: 26 },
  productCopy: { flex: 1, minWidth: 0 },
  productName: { fontSize: 15, fontWeight: '700', color: colors.slate900 },
  productPrice: { marginTop: 2, fontSize: 13, fontWeight: '600', color: colors.brand600 },
  addBtn: {
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.brand600,
    backgroundColor: colors.brand50,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
  },
  addBtnText: { fontSize: 13, fontWeight: '700', color: colors.brand700 },
  qtyRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  qtyBtn: {
    width: 36,
    height: 36,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.white,
  },
  qtyBtnText: { fontSize: 18, fontWeight: '700', color: colors.slate900 },
  qtyValue: { minWidth: 24, textAlign: 'center', fontWeight: '700', color: colors.slate900 },
  footer: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.md,
    borderTopWidth: 1,
    borderTopColor: colors.slate800,
    backgroundColor: colors.slate900,
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.md,
  },
  footerLabel: { fontSize: 11, color: colors.slate400 },
  footerTotal: { fontSize: 18, fontWeight: '800', color: colors.white },
  submitBtn: {
    minHeight: 44,
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    paddingHorizontal: spacing.xl,
    alignItems: 'center',
    justifyContent: 'center',
  },
  submitBtnText: { color: colors.white, fontSize: 14, fontWeight: '800' },
  submitDisabled: { opacity: 0.6 },
  errorBanner: {
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.red200,
    backgroundColor: colors.red50,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
  },
  errorBannerText: { fontSize: 13, color: colors.red700, fontWeight: '600' },
  toast: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderTopWidth: 1,
    borderTopColor: colors.green700,
    backgroundColor: colors.green600,
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.md,
  },
  toastTitle: { fontSize: 14, fontWeight: '800', color: colors.white },
  toastText: { fontSize: 12, color: '#dcfce7', marginTop: 2 },
  toastBtn: {
    minHeight: 40,
    borderRadius: radius.md,
    backgroundColor: colors.white,
    paddingHorizontal: spacing.lg,
    alignItems: 'center',
    justifyContent: 'center',
  },
  toastBtnText: { fontSize: 13, fontWeight: '800', color: colors.green700 },
  toastClose: {
    width: 32,
    height: 32,
    alignItems: 'center',
    justifyContent: 'center',
  },
  toastCloseText: { fontSize: 16, color: colors.white, fontWeight: '700' },
  pressed: { opacity: 0.9, transform: [{ scale: 0.98 }] },
});
