import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useMemo, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import {
  addProductToLocalCart,
  checkoutLocalCart,
  getIncomingOnlineOrders,
  getLocalCart,
  getLocalOrderItems,
  getLocalProducts,
  payOnlineOrder,
  removeLocalCartItem,
  updateLocalCartQuantity,
} from '@/local-db/repository';
import type { LocalCartItem, LocalOrder, LocalOrderItem, LocalProduct } from '@/local-db/types';
import { colors, radius, spacing } from '@/theme';
import { formatRupiah, parseRupiahInput } from '@/utils/rupiah';

type TabKey = 'menu' | 'cart' | 'online';

const CATEGORY_LABELS: Record<string, string> = {
  minuman: 'Minuman',
  makanan: 'Makanan',
  pastry: 'Pastry',
  snack: 'Snack',
};

export default function LocalKasirScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [tab, setTab] = useState<TabKey>('menu');
  const [products, setProducts] = useState<LocalProduct[]>([]);
  const [cart, setCart] = useState<LocalCartItem[]>([]);
  const [onlineOrders, setOnlineOrders] = useState<LocalOrder[]>([]);
  const [onlineItems, setOnlineItems] = useState<Record<number, LocalOrderItem[]>>({});
  const [customerName, setCustomerName] = useState('');
  const [paymentMethod, setPaymentMethod] = useState<'cash' | 'qris' | 'transfer'>('cash');
  const [amountReceived, setAmountReceived] = useState('');
  const [payingId, setPayingId] = useState<number | null>(null);
  const [onlinePayMethod, setOnlinePayMethod] = useState<'cash' | 'qris' | 'transfer'>('cash');
  const [onlineAmount, setOnlineAmount] = useState('');
  const [loading, setLoading] = useState(true);

  const refresh = useCallback(async () => {
    setLoading(true);

    try {
      const [nextProducts, nextCart, nextOnline] = await Promise.all([
        getLocalProducts(),
        getLocalCart(),
        getIncomingOnlineOrders(),
      ]);
      setProducts(nextProducts);
      setCart(nextCart);
      setOnlineOrders(nextOnline);

      const itemEntries = await Promise.all(
        nextOnline.map(async (order) => [order.id, await getLocalOrderItems(order.id)] as const),
      );
      setOnlineItems(Object.fromEntries(itemEntries));
    } finally {
      setLoading(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      refresh();
    }, [refresh]),
  );

  const total = useMemo(
    () => cart.reduce((sum, item) => sum + item.line_total, 0),
    [cart],
  );

  const changeAmount = useMemo(() => {
    if (paymentMethod !== 'cash') {
      return 0;
    }

    const received = parseRupiahInput(amountReceived);

    return Math.max(0, received - total);
  }, [amountReceived, paymentMethod, total]);

  const categories = useMemo(
    () => [...new Set(products.map((product) => product.category))],
    [products],
  );

  const onlineChange = useMemo(() => {
    if (onlinePayMethod !== 'cash' || payingId === null) {
      return 0;
    }

    const order = onlineOrders.find((entry) => entry.id === payingId);

    if (!order) {
      return 0;
    }

    return Math.max(0, parseRupiahInput(onlineAmount) - order.total);
  }, [onlineAmount, onlinePayMethod, onlineOrders, payingId]);

  const startPaying = (order: LocalOrder) => {
    setPayingId(order.id);
    setOnlinePayMethod('cash');
    setOnlineAmount('');
  };

  const handlePayOnline = async (order: LocalOrder) => {
    try {
      await payOnlineOrder(order.id, {
        paymentMethod: onlinePayMethod,
        amountReceived: onlinePayMethod === 'cash' ? parseRupiahInput(onlineAmount) : undefined,
      });

      setPayingId(null);
      setOnlineAmount('');
      setOnlinePayMethod('cash');
      await refresh();

      Alert.alert('Pembayaran berhasil', `Pesanan online #${order.order_number} sudah dibayar.`, [
        { text: 'Riwayat', onPress: () => router.push('/local-orders') },
        { text: 'OK' },
      ]);
    } catch (error) {
      Alert.alert('Gagal', error instanceof Error ? error.message : 'Terjadi kesalahan.');
    }
  };

  const handleAdd = async (productId: number) => {
    await addProductToLocalCart(productId, 1);
    await refresh();
    setTab('cart');
  };

  const handleCheckout = async () => {
    try {
      const order = await checkoutLocalCart({
        customerName,
        paymentMethod,
        amountReceived: paymentMethod === 'cash' ? parseRupiahInput(amountReceived) : undefined,
      });

      setCustomerName('');
      setAmountReceived('');
      setPaymentMethod('cash');
      await refresh();
      setTab('menu');

      Alert.alert(
        'Pembayaran berhasil',
        `Pesanan #${order.order_number} tersimpan di perangkat.`,
        [
          { text: 'Riwayat', onPress: () => router.push('/local-orders') },
          { text: 'OK' },
        ],
      );
    } catch (error) {
      Alert.alert('Gagal', error instanceof Error ? error.message : 'Terjadi kesalahan.');
    }
  };

  return (
    <View style={styles.root}>
      <View style={[styles.toolbar, { paddingTop: insets.top + spacing.sm }]}>
        <Pressable onPress={() => router.back()} style={styles.iconBtn}>
          <Text style={styles.iconBtnText}>←</Text>
        </Pressable>
        <View style={styles.toolbarCopy}>
          <Text style={styles.toolbarTitle}>Kasir POS</Text>
          <Text style={styles.toolbarMeta}>Data tersimpan di perangkat</Text>
        </View>
        <View style={styles.localBadge}>
          <Text style={styles.localBadgeText}>OFFLINE</Text>
        </View>
      </View>

      <View style={styles.tabs}>
        <Pressable
          onPress={() => setTab('menu')}
          style={[styles.tab, tab === 'menu' && styles.tabActive]}
        >
          <Text style={[styles.tabText, tab === 'menu' && styles.tabTextActive]}>☕ Menu</Text>
        </Pressable>
        <Pressable
          onPress={() => setTab('cart')}
          style={[styles.tab, tab === 'cart' && styles.tabActive]}
        >
          <Text style={[styles.tabText, tab === 'cart' && styles.tabTextActive]}>
            🧾 Kasir{cart.length > 0 ? ` (${cart.length})` : ''}
          </Text>
        </Pressable>
        <Pressable
          onPress={() => setTab('online')}
          style={[styles.tab, tab === 'online' && styles.tabActive]}
        >
          <Text style={[styles.tabText, tab === 'online' && styles.tabTextActive]}>
            🛎️ Online{onlineOrders.length > 0 ? ` (${onlineOrders.length})` : ''}
          </Text>
        </Pressable>
      </View>

      {loading ? (
        <View style={styles.loadingWrap}>
          <Text style={styles.loadingText}>Memuat data lokal…</Text>
        </View>
      ) : tab === 'menu' ? (
        <ScrollView
          style={styles.scroll}
          contentContainerStyle={[
            styles.menuContent,
            { paddingBottom: cart.length > 0 ? 96 + insets.bottom : insets.bottom + spacing.lg },
          ]}
        >
          {categories.map((category) => (
            <View key={category} style={styles.categoryBlock}>
              <Text style={styles.categoryTitle}>
                {CATEGORY_LABELS[category] ?? category}
              </Text>
              {products
                .filter((product) => product.category === category)
                .map((product) => (
                  <Pressable
                    key={product.id}
                    onPress={() => handleAdd(product.id)}
                    style={({ pressed }) => [styles.productCard, pressed && styles.pressed]}
                  >
                    <View style={styles.productEmojiWrap}>
                      <Text style={styles.productEmoji}>{product.emoji}</Text>
                    </View>
                    <View style={styles.productCopy}>
                      <Text style={styles.productName}>{product.name}</Text>
                      <Text style={styles.productPrice}>{formatRupiah(product.price)}</Text>
                    </View>
                    <Text style={styles.productAdd}>+</Text>
                  </Pressable>
                ))}
            </View>
          ))}
        </ScrollView>
      ) : tab === 'cart' ? (
        <ScrollView
          style={styles.scroll}
          contentContainerStyle={[
            styles.cartContent,
            { paddingBottom: insets.bottom + spacing.xxl },
          ]}
        >
          <View style={styles.receiptCard}>
            <View style={styles.receiptHead}>
              <Text style={styles.receiptTitle}>Pesanan</Text>
              <Text style={styles.receiptMeta}>{cart.length} item · mode lokal</Text>
            </View>

            {cart.length === 0 ? (
              <View style={styles.emptyCart}>
                <Text style={styles.emptyCartIcon}>☕</Text>
                <Text style={styles.emptyCartTitle}>Belum ada item</Text>
                <Text style={styles.emptyCartHint}>Tap menu di tab kiri untuk mulai pesanan</Text>
              </View>
            ) : (
              <>
                {cart.map((item) => (
                  <View key={item.id} style={styles.cartLine}>
                    <View style={styles.cartLineCopy}>
                      <Text style={styles.cartLineName}>{item.name}</Text>
                      <Text style={styles.cartLinePrice}>{formatRupiah(item.unit_price)}</Text>
                    </View>
                    <View style={styles.qtyRow}>
                      <Pressable
                        onPress={() => updateLocalCartQuantity(item.id, item.quantity - 1).then(refresh)}
                        style={styles.qtyBtn}
                      >
                        <Text style={styles.qtyBtnText}>−</Text>
                      </Pressable>
                      <Text style={styles.qtyValue}>{item.quantity}</Text>
                      <Pressable
                        onPress={() => updateLocalCartQuantity(item.id, item.quantity + 1).then(refresh)}
                        style={styles.qtyBtn}
                      >
                        <Text style={styles.qtyBtnText}>+</Text>
                      </Pressable>
                      <Pressable
                        onPress={() => removeLocalCartItem(item.id).then(refresh)}
                        style={styles.removeBtn}
                      >
                        <Text style={styles.removeBtnText}>Hapus</Text>
                      </Pressable>
                    </View>
                    <Text style={styles.cartLineTotal}>{formatRupiah(item.line_total)}</Text>
                  </View>
                ))}

                <View style={styles.customerCard}>
                  <Text style={styles.fieldLabel}>Nama pelanggan</Text>
                  <TextInput
                    value={customerName}
                    onChangeText={setCustomerName}
                    placeholder="Contoh: Budi"
                    placeholderTextColor={colors.slate500}
                    style={styles.input}
                  />
                </View>

                <View style={styles.payCard}>
                  <Text style={styles.fieldLabel}>Metode pembayaran</Text>
                  <View style={styles.payGrid}>
                    {(['cash', 'qris', 'transfer'] as const).map((method) => (
                      <Pressable
                        key={method}
                        onPress={() => setPaymentMethod(method)}
                        style={[
                          styles.payOption,
                          paymentMethod === method && styles.payOptionActive,
                        ]}
                      >
                        <Text
                          style={[
                            styles.payOptionText,
                            paymentMethod === method && styles.payOptionTextActive,
                          ]}
                        >
                          {method === 'cash' ? 'Tunai' : method === 'qris' ? 'QRIS' : 'Transfer'}
                        </Text>
                      </Pressable>
                    ))}
                  </View>

                  {paymentMethod === 'cash' ? (
                    <View style={styles.cashPanel}>
                      <Text style={styles.fieldLabel}>Uang diterima</Text>
                      <TextInput
                        value={amountReceived}
                        onChangeText={setAmountReceived}
                        keyboardType="numeric"
                        placeholder="0"
                        placeholderTextColor={colors.slate500}
                        style={styles.input}
                      />
                      <Text style={styles.changeText}>
                        Kembalian: <Text style={styles.changeStrong}>{formatRupiah(changeAmount)}</Text>
                      </Text>
                    </View>
                  ) : null}

                  <View style={styles.totalRow}>
                    <Text style={styles.totalLabel}>Total Bayar</Text>
                    <Text style={styles.totalValue}>{formatRupiah(total)}</Text>
                  </View>

                  <Pressable
                    onPress={handleCheckout}
                    style={({ pressed }) => [styles.payBtn, pressed && styles.pressed]}
                  >
                    <Text style={styles.payBtnText}>Bayar {formatRupiah(total)}</Text>
                  </Pressable>
                </View>
              </>
            )}
          </View>
        </ScrollView>
      ) : (
        <ScrollView
          style={styles.scroll}
          contentContainerStyle={[
            styles.cartContent,
            { paddingBottom: insets.bottom + spacing.xxl },
          ]}
        >
          {onlineOrders.length === 0 ? (
            <View style={styles.receiptCard}>
              <View style={styles.emptyCart}>
                <Text style={styles.emptyCartIcon}>🛎️</Text>
                <Text style={styles.emptyCartTitle}>Belum ada pesanan online</Text>
                <Text style={styles.emptyCartHint}>
                  Pesanan dari layar "Pesan Online" akan muncul di sini untuk dibayar.
                </Text>
              </View>
            </View>
          ) : (
            onlineOrders.map((order) => {
              const lines = onlineItems[order.id] ?? [];
              const isPaying = payingId === order.id;

              return (
                <View key={order.id} style={styles.onlineCard}>
                  <View style={styles.onlineHead}>
                    <View style={styles.onlineHeadCopy}>
                      <Text style={styles.onlineNumber}>#{order.order_number}</Text>
                      <Text style={styles.onlineCustomer}>{order.customer_name ?? 'Tanpa nama'}</Text>
                    </View>
                    <View style={styles.onlineTag}>
                      <Text style={styles.onlineTagText}>MENUNGGU</Text>
                    </View>
                  </View>

                  {lines.map((line) => (
                    <View key={line.id} style={styles.onlineLine}>
                      <Text style={styles.onlineLineName}>
                        {line.quantity}× {line.product_name}
                      </Text>
                      <Text style={styles.onlineLineTotal}>{formatRupiah(line.line_total)}</Text>
                    </View>
                  ))}

                  <View style={styles.onlineTotalRow}>
                    <Text style={styles.totalLabel}>Total</Text>
                    <Text style={styles.totalValue}>{formatRupiah(order.total)}</Text>
                  </View>

                  {isPaying ? (
                    <View style={styles.payCardInline}>
                      <Text style={styles.fieldLabel}>Metode pembayaran</Text>
                      <View style={styles.payGrid}>
                        {(['cash', 'qris', 'transfer'] as const).map((method) => (
                          <Pressable
                            key={method}
                            onPress={() => setOnlinePayMethod(method)}
                            style={[styles.payOption, onlinePayMethod === method && styles.payOptionActive]}
                          >
                            <Text
                              style={[
                                styles.payOptionText,
                                onlinePayMethod === method && styles.payOptionTextActive,
                              ]}
                            >
                              {method === 'cash' ? 'Tunai' : method === 'qris' ? 'QRIS' : 'Transfer'}
                            </Text>
                          </Pressable>
                        ))}
                      </View>

                      {onlinePayMethod === 'cash' ? (
                        <View style={styles.cashPanel}>
                          <Text style={styles.fieldLabel}>Uang diterima</Text>
                          <TextInput
                            value={onlineAmount}
                            onChangeText={setOnlineAmount}
                            keyboardType="numeric"
                            placeholder="0"
                            placeholderTextColor={colors.slate500}
                            style={styles.input}
                          />
                          <Text style={styles.changeText}>
                            Kembalian: <Text style={styles.changeStrong}>{formatRupiah(onlineChange)}</Text>
                          </Text>
                        </View>
                      ) : null}

                      <View style={styles.onlineActions}>
                        <Pressable onPress={() => setPayingId(null)} style={styles.cancelBtn}>
                          <Text style={styles.cancelBtnText}>Batal</Text>
                        </Pressable>
                        <Pressable
                          onPress={() => handlePayOnline(order)}
                          style={({ pressed }) => [styles.payBtnFlex, pressed && styles.pressed]}
                        >
                          <Text style={styles.payBtnText}>Bayar {formatRupiah(order.total)}</Text>
                        </Pressable>
                      </View>
                    </View>
                  ) : (
                    <Pressable
                      onPress={() => startPaying(order)}
                      style={({ pressed }) => [styles.payBtn, pressed && styles.pressed]}
                    >
                      <Text style={styles.payBtnText}>Proses Pembayaran</Text>
                    </Pressable>
                  )}
                </View>
              );
            })
          )}
        </ScrollView>
      )}

      {tab === 'menu' && cart.length > 0 ? (
        <View style={[styles.mobileCheckout, { paddingBottom: Math.max(spacing.md, insets.bottom) }]}>
          <View>
            <Text style={styles.mobileCheckoutLabel}>{cart.length} item</Text>
            <Text style={styles.mobileCheckoutTotal}>{formatRupiah(total)}</Text>
          </View>
          <Pressable onPress={() => setTab('cart')} style={styles.mobileCheckoutBtn}>
            <Text style={styles.mobileCheckoutBtnText}>Bayar</Text>
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
  localBadge: {
    borderRadius: 999,
    backgroundColor: colors.brand50,
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  localBadgeText: { fontSize: 10, fontWeight: '800', color: colors.brand700 },
  tabs: {
    flexDirection: 'row',
    gap: spacing.sm,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
    backgroundColor: colors.white,
    borderBottomWidth: 1,
    borderBottomColor: colors.slate200,
  },
  tab: {
    flex: 1,
    minHeight: 44,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.slate50,
  },
  tabActive: {
    borderColor: colors.brand600,
    backgroundColor: colors.brand50,
  },
  tabText: { fontSize: 13, fontWeight: '600', color: colors.slate600 },
  tabTextActive: { color: colors.brand700 },
  scroll: { flex: 1 },
  menuContent: { padding: spacing.lg, gap: spacing.lg },
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
  productAdd: {
    width: 36,
    height: 36,
    borderRadius: 999,
    backgroundColor: colors.brand600,
    color: colors.white,
    textAlign: 'center',
    lineHeight: 34,
    fontSize: 22,
    fontWeight: '700',
    overflow: 'hidden',
  },
  cartContent: { padding: spacing.lg },
  receiptCard: {
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    overflow: 'hidden',
  },
  receiptHead: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.slate200,
    padding: spacing.lg,
  },
  receiptTitle: { fontSize: 18, fontWeight: '800', color: colors.slate900 },
  receiptMeta: { fontSize: 12, color: colors.slate500 },
  emptyCart: { alignItems: 'center', padding: spacing.xxl, gap: spacing.sm },
  emptyCartIcon: { fontSize: 36 },
  emptyCartTitle: { fontSize: 16, fontWeight: '700', color: colors.slate900 },
  emptyCartHint: { fontSize: 13, color: colors.slate500, textAlign: 'center' },
  cartLine: {
    borderBottomWidth: 1,
    borderBottomColor: colors.slate100,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
    gap: spacing.sm,
  },
  cartLineCopy: { flexDirection: 'row', justifyContent: 'space-between', gap: spacing.md },
  cartLineName: { flex: 1, fontSize: 14, fontWeight: '700', color: colors.slate900 },
  cartLinePrice: { fontSize: 12, color: colors.slate500 },
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
  removeBtn: {
    marginLeft: 'auto',
    borderRadius: radius.md,
    backgroundColor: '#fef2f2',
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
  },
  removeBtnText: { fontSize: 12, fontWeight: '700', color: '#b91c1c' },
  cartLineTotal: { fontSize: 14, fontWeight: '700', color: colors.brand600 },
  customerCard: { padding: spacing.lg, gap: spacing.sm },
  payCard: { borderTopWidth: 1, borderTopColor: colors.slate200, padding: spacing.lg, gap: spacing.md },
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
  payGrid: { flexDirection: 'row', gap: spacing.sm },
  payOption: {
    flex: 1,
    minHeight: 44,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.white,
  },
  payOptionActive: {
    borderColor: colors.brand600,
    backgroundColor: colors.brand50,
  },
  payOptionText: { fontSize: 13, fontWeight: '600', color: colors.slate600 },
  payOptionTextActive: { color: colors.brand700 },
  cashPanel: { gap: spacing.sm },
  changeText: { fontSize: 13, color: colors.slate600 },
  changeStrong: { fontWeight: '800', color: colors.slate900 },
  totalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: spacing.sm,
  },
  totalLabel: { fontSize: 14, fontWeight: '700', color: colors.slate900 },
  totalValue: { fontSize: 20, fontWeight: '800', color: colors.brand600 },
  payBtn: {
    minHeight: 48,
    borderRadius: radius.lg,
    backgroundColor: colors.green600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  payBtnText: { color: colors.white, fontSize: 16, fontWeight: '800' },
  onlineCard: {
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    padding: spacing.lg,
    marginBottom: spacing.md,
    gap: spacing.sm,
  },
  onlineHead: { flexDirection: 'row', alignItems: 'center', gap: spacing.md },
  onlineHeadCopy: { flex: 1, minWidth: 0 },
  onlineNumber: { fontSize: 16, fontWeight: '800', color: colors.slate900 },
  onlineCustomer: { fontSize: 13, color: colors.slate500, marginTop: 2 },
  onlineTag: {
    borderRadius: 999,
    backgroundColor: '#fef3c7',
    paddingHorizontal: 10,
    paddingVertical: 6,
  },
  onlineTagText: { fontSize: 10, fontWeight: '800', color: '#b45309' },
  onlineLine: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    gap: spacing.md,
    paddingVertical: 2,
  },
  onlineLineName: { flex: 1, fontSize: 14, color: colors.slate700 },
  onlineLineTotal: { fontSize: 14, fontWeight: '600', color: colors.slate900 },
  onlineTotalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    borderTopWidth: 1,
    borderTopColor: colors.slate100,
    paddingTop: spacing.sm,
  },
  payCardInline: {
    borderTopWidth: 1,
    borderTopColor: colors.slate200,
    paddingTop: spacing.md,
    gap: spacing.md,
  },
  onlineActions: { flexDirection: 'row', gap: spacing.sm },
  cancelBtn: {
    minHeight: 48,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    paddingHorizontal: spacing.lg,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.white,
  },
  cancelBtnText: { fontSize: 14, fontWeight: '700', color: colors.slate600 },
  payBtnFlex: {
    flex: 1,
    minHeight: 48,
    borderRadius: radius.lg,
    backgroundColor: colors.green600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  mobileCheckout: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.md,
    borderTopWidth: 1,
    borderTopColor: '#334155',
    backgroundColor: colors.slate900,
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.md,
  },
  mobileCheckoutLabel: { fontSize: 11, color: '#94a3b8' },
  mobileCheckoutTotal: { fontSize: 18, fontWeight: '800', color: colors.white },
  mobileCheckoutBtn: {
    minHeight: 44,
    borderRadius: radius.lg,
    backgroundColor: colors.green600,
    paddingHorizontal: spacing.xl,
    alignItems: 'center',
    justifyContent: 'center',
  },
  mobileCheckoutBtnText: { color: colors.white, fontSize: 14, fontWeight: '800' },
  loadingWrap: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  loadingText: { color: colors.slate600 },
  pressed: { opacity: 0.9, transform: [{ scale: 0.98 }] },
});
