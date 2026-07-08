import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useMemo, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, TextInput, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import {
  addProductToLocalCart,
  checkoutLocalCart,
  confirmOnlineOrder,
  getIncomingOnlineOrders,
  getLocalCart,
  getLocalOrderItems,
  getLocalProducts,
  listTables,
  payOnlineOrder,
  removeLocalCartItem,
  updateLocalCartQuantity,
} from '@/local-db/repository';
import type {
  LocalCartItem,
  LocalOrder,
  LocalOrderItem,
  LocalProduct,
  LocalTable,
  OrderType,
  PaymentMethod,
} from '@/local-db/types';
import { colors, font, radius, spacing } from '@/theme';
import { formatRupiah, parseRupiahInput } from '@/utils/rupiah';

type TabKey = 'menu' | 'cart' | 'online';

const CATEGORY_LABELS: Record<string, string> = {
  minuman: 'Minuman',
  makanan: 'Makanan',
  pastry: 'Pastry',
  snack: 'Snack',
  lainnya: 'Lainnya',
};

const PAYMENT_LABEL: Record<PaymentMethod, string> = {
  cash: 'Tunai',
  qris: 'QRIS',
  transfer: 'Transfer',
};

const ORDER_TYPES: { value: OrderType; label: string; icon: string; hint: string }[] = [
  { value: 'dine_in', label: 'Dine In', icon: '🪑', hint: 'Makan di tempat' },
  { value: 'takeaway', label: 'Take Away', icon: '🥡', hint: 'Bawa pulang' },
];

export default function KasirPosScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [tab, setTab] = useState<TabKey>('menu');
  const [products, setProducts] = useState<LocalProduct[]>([]);
  const [cart, setCart] = useState<LocalCartItem[]>([]);
  const [tables, setTables] = useState<LocalTable[]>([]);
  const [onlineOrders, setOnlineOrders] = useState<LocalOrder[]>([]);
  const [onlineItems, setOnlineItems] = useState<Record<number, LocalOrderItem[]>>({});
  const [customerName, setCustomerName] = useState('');
  const [orderType, setOrderType] = useState<OrderType>('takeaway');
  const [tableLabel, setTableLabel] = useState<string | null>(null);
  const [paymentMethod, setPaymentMethod] = useState<PaymentMethod>('cash');
  const [amountReceived, setAmountReceived] = useState('');
  const [payingId, setPayingId] = useState<number | null>(null);
  const [onlinePayMethod, setOnlinePayMethod] = useState<PaymentMethod>('cash');
  const [onlineAmount, setOnlineAmount] = useState('');
  const [loading, setLoading] = useState(true);

  const refresh = useCallback(async () => {
    setLoading(true);

    try {
      const [nextProducts, nextCart, nextOnline, nextTables] = await Promise.all([
        getLocalProducts(),
        getLocalCart(),
        getIncomingOnlineOrders(),
        listTables(),
      ]);
      setProducts(nextProducts);
      setCart(nextCart);
      setOnlineOrders(nextOnline);
      setTables(nextTables.filter((table) => table.is_active === 1));

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

  const total = useMemo(() => cart.reduce((sum, item) => sum + item.line_total, 0), [cart]);

  const changeAmount = useMemo(() => {
    if (paymentMethod !== 'cash') {
      return 0;
    }

    return Math.max(0, parseRupiahInput(amountReceived) - total);
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

    return order ? Math.max(0, parseRupiahInput(onlineAmount) - order.total) : 0;
  }, [onlineAmount, onlinePayMethod, onlineOrders, payingId]);

  const startPaying = (order: LocalOrder) => {
    setPayingId(order.id);
    setOnlinePayMethod('cash');
    setOnlineAmount('');
  };

  const handleConfirmOnline = async (order: LocalOrder) => {
    try {
      await confirmOnlineOrder(order.id);
      await refresh();
      startPaying(order);
    } catch (error) {
      Alert.alert('Gagal', error instanceof Error ? error.message : 'Terjadi kesalahan.');
    }
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
        { text: 'Lihat Struk', onPress: () => router.push({ pathname: '/kasir/order-detail', params: { id: String(order.id) } }) },
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
        orderType,
        tableLabel: orderType === 'dine_in' ? tableLabel : null,
        paymentMethod,
        amountReceived: paymentMethod === 'cash' ? parseRupiahInput(amountReceived) : undefined,
      });

      setCustomerName('');
      setAmountReceived('');
      setPaymentMethod('cash');
      setOrderType('takeaway');
      setTableLabel(null);
      await refresh();
      setTab('menu');

      Alert.alert('Pembayaran berhasil', `Pesanan #${order.order_number} tersimpan di perangkat.`, [
        { text: 'Lihat Struk', onPress: () => router.push({ pathname: '/kasir/order-detail', params: { id: String(order.id) } }) },
        { text: 'OK' },
      ]);
    } catch (error) {
      Alert.alert('Gagal', error instanceof Error ? error.message : 'Terjadi kesalahan.');
    }
  };

  return (
    <View style={styles.root}>
      <View style={[styles.toolbar, { paddingTop: insets.top + spacing.sm }]}>
        <Pressable onPress={() => router.replace('/')} style={styles.iconBtn}>
          <Text style={styles.iconBtnText}>←</Text>
        </Pressable>
        <View style={styles.toolbarCopy}>
          <Text style={styles.toolbarTitle}>Point of Sale</Text>
          <Text style={styles.toolbarMeta}>Modul Kasir · offline</Text>
        </View>
        <Pressable onPress={() => router.push('/kasir/orders')} style={styles.navChip}>
          <Text style={styles.navChipText}>🧾</Text>
        </Pressable>
        <Pressable onPress={() => router.push('/kasir/tables')} style={styles.navChip}>
          <Text style={styles.navChipText}>🪑</Text>
        </Pressable>
        <Pressable onPress={() => router.push('/kasir/menu')} style={styles.navChip}>
          <Text style={styles.navChipText}>🍽️</Text>
        </Pressable>
      </View>

      <View style={styles.tabs}>
        <Pressable onPress={() => setTab('menu')} style={[styles.tab, tab === 'menu' && styles.tabActive]}>
          <Text style={[styles.tabText, tab === 'menu' && styles.tabTextActive]}>☕ Menu</Text>
        </Pressable>
        <Pressable onPress={() => setTab('cart')} style={[styles.tab, tab === 'cart' && styles.tabActive]}>
          <Text style={[styles.tabText, tab === 'cart' && styles.tabTextActive]}>
            🧾 Kasir{cart.length > 0 ? ` (${cart.length})` : ''}
          </Text>
        </Pressable>
        <Pressable onPress={() => setTab('online')} style={[styles.tab, tab === 'online' && styles.tabActive]}>
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
              <Text style={styles.categoryTitle}>{CATEGORY_LABELS[category] ?? category}</Text>
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
          contentContainerStyle={[styles.cartContent, { paddingBottom: insets.bottom + spacing.xxl }]}
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

                <View style={styles.section}>
                  <Text style={styles.fieldLabel}>Tipe pesanan</Text>
                  <View style={styles.payGrid}>
                    {ORDER_TYPES.map((type) => (
                      <Pressable
                        key={type.value}
                        onPress={() => setOrderType(type.value)}
                        style={[styles.typeOption, orderType === type.value && styles.typeOptionActive]}
                      >
                        <Text style={styles.typeIcon}>{type.icon}</Text>
                        <Text
                          style={[styles.typeLabel, orderType === type.value && styles.typeLabelActive]}
                        >
                          {type.label}
                        </Text>
                        <Text style={styles.typeHint}>{type.hint}</Text>
                      </Pressable>
                    ))}
                  </View>

                  {orderType === 'dine_in' ? (
                    <View style={styles.tablePickerWrap}>
                      <Text style={styles.fieldLabel}>Meja</Text>
                      {tables.length === 0 ? (
                        <Text style={styles.mutedText}>Belum ada meja. Tambah di menu Meja QR.</Text>
                      ) : (
                        <View style={styles.chipWrap}>
                          {tables.map((table) => (
                            <Pressable
                              key={table.id}
                              onPress={() =>
                                setTableLabel(tableLabel === table.label ? null : table.label)
                              }
                              style={[styles.chip, tableLabel === table.label && styles.chipActive]}
                            >
                              <Text
                                style={[
                                  styles.chipText,
                                  tableLabel === table.label && styles.chipTextActive,
                                ]}
                              >
                                {table.label}
                              </Text>
                            </Pressable>
                          ))}
                        </View>
                      )}
                    </View>
                  ) : null}
                </View>

                <View style={styles.customerCard}>
                  <Text style={styles.fieldLabel}>Nama pelanggan</Text>
                  <TextInput
                    value={customerName}
                    onChangeText={setCustomerName}
                    placeholder="Contoh: Budi"
                    placeholderTextColor={colors.slate400}
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
                        style={[styles.payOption, paymentMethod === method && styles.payOptionActive]}
                      >
                        <Text
                          style={[
                            styles.payOptionText,
                            paymentMethod === method && styles.payOptionTextActive,
                          ]}
                        >
                          {PAYMENT_LABEL[method]}
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
                        placeholderTextColor={colors.slate400}
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
          contentContainerStyle={[styles.cartContent, { paddingBottom: insets.bottom + spacing.xxl }]}
        >
          {onlineOrders.length === 0 ? (
            <View style={styles.receiptCard}>
              <View style={styles.emptyCart}>
                <Text style={styles.emptyCartIcon}>🛎️</Text>
                <Text style={styles.emptyCartTitle}>Belum ada pesanan online</Text>
                <Text style={styles.emptyCartHint}>
                  Pesanan dari layar "Pesan Online" akan muncul di sini untuk dikonfirmasi & dibayar.
                </Text>
              </View>
            </View>
          ) : (
            onlineOrders.map((order) => {
              const lines = onlineItems[order.id] ?? [];
              const isPaying = payingId === order.id;
              const isConfirmed = order.status === 'confirmed';

              return (
                <View key={order.id} style={styles.onlineCard}>
                  <View style={styles.onlineHead}>
                    <View style={styles.onlineHeadCopy}>
                      <Text style={styles.onlineNumber}>#{order.order_number}</Text>
                      <Text style={styles.onlineCustomer}>{order.customer_name ?? 'Tanpa nama'}</Text>
                    </View>
                    <View style={[styles.onlineTag, isConfirmed && styles.onlineTagConfirmed]}>
                      <Text style={[styles.onlineTagText, isConfirmed && styles.onlineTagTextConfirmed]}>
                        {isConfirmed ? 'SIAP BAYAR' : 'MENUNGGU'}
                      </Text>
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

                  {!isConfirmed ? (
                    <Pressable
                      onPress={() => handleConfirmOnline(order)}
                      style={({ pressed }) => [styles.confirmBtn, pressed && styles.pressed]}
                    >
                      <Text style={styles.payBtnText}>Konfirmasi Pesanan</Text>
                    </Pressable>
                  ) : isPaying ? (
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
                              {PAYMENT_LABEL[method]}
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
                            placeholderTextColor={colors.slate400}
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
    gap: spacing.sm,
    borderBottomWidth: 1,
    borderBottomColor: colors.slate800,
    backgroundColor: colors.slate900,
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing.md,
  },
  iconBtn: {
    width: 40,
    height: 40,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate700,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.slate800,
  },
  iconBtnText: { fontSize: 20, color: colors.white },
  toolbarCopy: { flex: 1, minWidth: 0 },
  toolbarTitle: { fontSize: 16, color: colors.white, ...font('700') },
  toolbarMeta: { fontSize: 11, color: colors.slate400, marginTop: 2 },
  navChip: {
    width: 40,
    height: 40,
    borderRadius: radius.md,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.slate800,
    borderWidth: 1,
    borderColor: colors.slate700,
  },
  navChipText: { fontSize: 18 },
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
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.slate50,
  },
  tabActive: { borderColor: colors.brand600, backgroundColor: colors.brand50 },
  tabText: { fontSize: 13, color: colors.slate600, ...font('600') },
  tabTextActive: { color: colors.brand700 },
  scroll: { flex: 1 },
  menuContent: { padding: spacing.lg, gap: spacing.lg },
  categoryBlock: { gap: spacing.sm },
  categoryTitle: {
    fontSize: 12,
    textTransform: 'uppercase',
    letterSpacing: 0.5,
    color: colors.slate500,
    ...font('700'),
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
  productName: { fontSize: 15, color: colors.slate900, ...font('700') },
  productPrice: { marginTop: 2, fontSize: 13, color: colors.brand600, ...font('600') },
  productAdd: {
    width: 36,
    height: 36,
    borderRadius: radius.full,
    backgroundColor: colors.brand600,
    color: colors.white,
    textAlign: 'center',
    lineHeight: 34,
    fontSize: 22,
    overflow: 'hidden',
    ...font('700'),
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
  receiptTitle: { fontSize: 18, color: colors.slate900, ...font('700') },
  receiptMeta: { fontSize: 12, color: colors.slate500 },
  emptyCart: { alignItems: 'center', padding: spacing.xxl, gap: spacing.sm },
  emptyCartIcon: { fontSize: 36 },
  emptyCartTitle: { fontSize: 16, color: colors.slate900, ...font('700') },
  emptyCartHint: { fontSize: 13, color: colors.slate500, textAlign: 'center' },
  cartLine: {
    borderBottomWidth: 1,
    borderBottomColor: colors.slate100,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
    gap: spacing.sm,
  },
  cartLineCopy: { flexDirection: 'row', justifyContent: 'space-between', gap: spacing.md },
  cartLineName: { flex: 1, fontSize: 14, color: colors.slate900, ...font('700') },
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
  qtyBtnText: { fontSize: 18, color: colors.slate900, ...font('700') },
  qtyValue: { minWidth: 24, textAlign: 'center', color: colors.slate900, ...font('700') },
  removeBtn: {
    marginLeft: 'auto',
    borderRadius: radius.md,
    backgroundColor: colors.red50,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
  },
  removeBtnText: { fontSize: 12, color: colors.red700, ...font('700') },
  cartLineTotal: { fontSize: 14, color: colors.brand600, ...font('700') },
  section: { borderTopWidth: 1, borderTopColor: colors.slate200, padding: spacing.lg, gap: spacing.md },
  customerCard: { paddingHorizontal: spacing.lg, paddingBottom: spacing.lg, gap: spacing.sm },
  payCard: { borderTopWidth: 1, borderTopColor: colors.slate200, padding: spacing.lg, gap: spacing.md },
  fieldLabel: { fontSize: 13, color: colors.slate600, ...font('600') },
  mutedText: { fontSize: 13, color: colors.slate500 },
  input: {
    minHeight: 46,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate300,
    backgroundColor: colors.white,
    paddingHorizontal: spacing.md,
    fontSize: 16,
    color: colors.slate900,
  },
  payGrid: { flexDirection: 'row', gap: spacing.sm },
  typeOption: {
    flex: 1,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    paddingVertical: spacing.md,
    paddingHorizontal: spacing.sm,
    gap: 2,
  },
  typeOptionActive: { borderColor: colors.brand600, backgroundColor: colors.brand50 },
  typeIcon: { fontSize: 20 },
  typeLabel: { fontSize: 14, color: colors.slate700, ...font('700') },
  typeLabelActive: { color: colors.brand700 },
  typeHint: { fontSize: 11, color: colors.slate500 },
  tablePickerWrap: { gap: spacing.sm },
  chipWrap: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  chip: {
    borderRadius: radius.full,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
  },
  chipActive: { borderColor: colors.brand600, backgroundColor: colors.brand50 },
  chipText: { fontSize: 13, color: colors.slate600 },
  chipTextActive: { color: colors.brand700, ...font('700') },
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
  payOptionActive: { borderColor: colors.brand600, backgroundColor: colors.brand50 },
  payOptionText: { fontSize: 13, color: colors.slate600, ...font('600') },
  payOptionTextActive: { color: colors.brand700 },
  cashPanel: { gap: spacing.sm },
  changeText: { fontSize: 13, color: colors.slate600 },
  changeStrong: { color: colors.slate900, ...font('700') },
  totalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    paddingTop: spacing.sm,
  },
  totalLabel: { fontSize: 14, color: colors.slate900, ...font('700') },
  totalValue: { fontSize: 20, color: colors.brand600, ...font('700') },
  payBtn: {
    minHeight: 48,
    borderRadius: radius.md,
    backgroundColor: colors.green600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  confirmBtn: {
    minHeight: 48,
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  payBtnText: { color: colors.white, fontSize: 16, ...font('700') },
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
  onlineNumber: { fontSize: 16, color: colors.slate900, ...font('700') },
  onlineCustomer: { fontSize: 13, color: colors.slate500, marginTop: 2 },
  onlineTag: {
    borderRadius: radius.full,
    borderWidth: 1,
    borderColor: colors.amber200,
    backgroundColor: colors.amber50,
    paddingHorizontal: 10,
    paddingVertical: 4,
  },
  onlineTagConfirmed: { borderColor: colors.brand200, backgroundColor: colors.brand50 },
  onlineTagText: { fontSize: 10, color: colors.amber800, ...font('700') },
  onlineTagTextConfirmed: { color: colors.brand700 },
  onlineLine: { flexDirection: 'row', justifyContent: 'space-between', gap: spacing.md, paddingVertical: 2 },
  onlineLineName: { flex: 1, fontSize: 14, color: colors.slate700 },
  onlineLineTotal: { fontSize: 14, color: colors.slate900, ...font('600') },
  onlineTotalRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    alignItems: 'center',
    borderTopWidth: 1,
    borderTopColor: colors.slate100,
    paddingTop: spacing.sm,
  },
  payCardInline: { borderTopWidth: 1, borderTopColor: colors.slate200, paddingTop: spacing.md, gap: spacing.md },
  onlineActions: { flexDirection: 'row', gap: spacing.sm },
  cancelBtn: {
    minHeight: 48,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate300,
    paddingHorizontal: spacing.lg,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.white,
  },
  cancelBtnText: { fontSize: 14, color: colors.slate600, ...font('700') },
  payBtnFlex: {
    flex: 1,
    minHeight: 48,
    borderRadius: radius.md,
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
    borderTopColor: colors.slate800,
    backgroundColor: colors.slate900,
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.md,
  },
  mobileCheckoutLabel: { fontSize: 11, color: colors.slate400 },
  mobileCheckoutTotal: { fontSize: 18, color: colors.white, ...font('700') },
  mobileCheckoutBtn: {
    minHeight: 44,
    borderRadius: radius.md,
    backgroundColor: colors.green600,
    paddingHorizontal: spacing.xl,
    alignItems: 'center',
    justifyContent: 'center',
  },
  mobileCheckoutBtnText: { color: colors.white, fontSize: 14, ...font('700') },
  loadingWrap: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  loadingText: { color: colors.slate600 },
  pressed: { opacity: 0.9, transform: [{ scale: 0.98 }] },
});
