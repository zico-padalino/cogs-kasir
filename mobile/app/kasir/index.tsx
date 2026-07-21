import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Image,
  Modal,
  Pressable,
  ScrollView,
  StatusBar,
  StyleSheet,
  Text,
  TextInput,
  useWindowDimensions,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import * as ImagePicker from 'expo-image-picker';
import { kasirApi, pinApi } from '@/api/kasir';
import type { MenuProduct, PosOrder, PosOrder as Order } from '@/api/types';
import { asApiError, useAuth } from '@/auth';
import { AppDrawer } from '@/components/AppScaffold';
import { consumePendingOpenOrderId, seedPendingIds } from '@/kasir/pendingOrderTracker';
import { colors, font, radius, spacing } from '@/theme';
import { formatRupiah, formatRupiahInput, parseRupiahInput } from '@/utils/rupiah';

type TabKey = 'menu' | 'cart';
type PayMethod = 'cash' | 'qris' | 'transfer';

const PRODUCT_GAP = spacing.sm;
const PRODUCT_PAD = spacing.md;
const WIDE_BREAKPOINT = 900;
const CART_COL_WIDTH = 300;

export default function KasirPosScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { width: windowWidth } = useWindowDimensions();
  const { setPin, pin } = useAuth();
  const isWide = windowWidth >= WIDE_BREAKPOINT;
  const menuColWidth = isWide ? Math.max(320, windowWidth - CART_COL_WIDTH) : windowWidth;
  const productCardWidth = (menuColWidth - PRODUCT_PAD * 2 - PRODUCT_GAP) / 2;

  const [tab, setTab] = useState<TabKey>('menu');
  const [loading, setLoading] = useState(true);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [products, setProducts] = useState<MenuProduct[]>([]);
  const [categoryLabels, setCategoryLabels] = useState<Record<string, string>>({});
  const [categories, setCategories] = useState<string[]>([]);
  const [category, setCategory] = useState<string | 'all'>('all');
  const [search, setSearch] = useState('');
  const [order, setOrder] = useState<Order | null>(null);
  const [pending, setPending] = useState<PosOrder[]>([]);
  const [shopName, setShopName] = useState('Kasir');
  const [pollMs, setPollMs] = useState(5000);

  const [addProduct, setAddProduct] = useState<MenuProduct | null>(null);
  const [qty, setQty] = useState(1);
  const [notes, setNotes] = useState('');
  const [addonIds, setAddonIds] = useState<number[]>([]);
  const [savingItem, setSavingItem] = useState(false);

  const [payOpen, setPayOpen] = useState(false);
  const [payMethod, setPayMethod] = useState<PayMethod>('cash');
  const [amountReceived, setAmountReceived] = useState('');
  const [proofUri, setProofUri] = useState<string | null>(null);
  const [paying, setPaying] = useState(false);

  const [discountType, setDiscountType] = useState<'amount' | 'percent' | null>(null);
  const [discountValue, setDiscountValue] = useState('');
  const [customerNote, setCustomerNote] = useState('');
  const [orderType, setOrderType] = useState('takeaway');
  const [orderBarOpen, setOrderBarOpen] = useState(false);

  const applyOrder = useCallback((next: Order) => {
    setOrder(next);
    setCustomerNote(next.customer_note ?? '');
    setOrderType(next.order_type || 'takeaway');
    setDiscountType((next.discount_type as 'amount' | 'percent' | null) ?? null);
    setDiscountValue(next.discount_value ? String(next.discount_value) : '');
  }, []);

  const handleApiError = useCallback((err: unknown) => {
    const apiErr = asApiError(err);
    if (apiErr.status === 423 || apiErr.code === 'PIN_LOCKED') {
      // redirect global via PinLockedListener + KasirPinSessionGuard
      return;
    }
    if (apiErr.code === 'ATTENDANCE_REQUIRED') {
      Alert.alert('Absensi diperlukan', apiErr.message);
      return;
    }
    Alert.alert('Gagal', apiErr.message || 'Terjadi kesalahan.');
  }, []);

  const refresh = useCallback(async () => {
    setLoading(true);
    try {
      const res = await kasirApi.pos();
      const data = res.data;
      setProducts(data.products);
      setCategories(data.menu_categories);
      setCategoryLabels(data.menu_category_labels || {});
      applyOrder(data.order);
      setPending(data.pending_orders || []);
      setShopName(data.shop_name);
      setPollMs((data.poll_interval_seconds || 5) * 1000);
      setPin(data.pin);
      // Seed agar pesanan lama tidak dibunyikan saat buka POS.
      seedPendingIds((data.pending_orders || []).map((o) => o.id));
    } catch (err) {
      handleApiError(err);
    } finally {
      setLoading(false);
    }
  }, [applyOrder, handleApiError, setPin]);

  useFocusEffect(
    useCallback(() => {
      void (async () => {
        await refresh();
        const pendingId = consumePendingOpenOrderId();
        if (!pendingId) {
          return;
        }
        try {
          const res = await kasirApi.loadOrder(pendingId);
          applyOrder(res.data);
          setTab('cart');
        } catch (err) {
          handleApiError(err);
        }
      })();
    }, [refresh, applyOrder, handleApiError]),
  );

  useEffect(() => {
    const timer = setInterval(async () => {
      try {
        const res = await kasirApi.poll();
        const data = res.data;
        setPin({
          unlocked: data.unlocked,
          expires_at: data.expires_at,
          server_now: data.server_now,
          remaining_seconds: data.remaining_seconds,
          operator_name: data.operator_name,
        });
        if (!data.unlocked) {
          router.replace('/kasir/pin' as never);
          return;
        }

        // TTS/toast di KasirOrderAlertGuard (termasuk saat layar PIN).
        setPending(data.orders || []);
      } catch (err) {
        const apiErr = asApiError(err);
        if (apiErr.status === 423) {
          router.replace('/kasir/pin' as never);
        }
      }
    }, pollMs);

    return () => clearInterval(timer);
  }, [pollMs, router, setPin]);

  useEffect(() => {
    const statusTimer = setInterval(async () => {
      try {
        const res = await pinApi.status();
        setPin(res.data);
        if (!res.data.unlocked) {
          router.replace('/kasir/pin' as never);
        }
      } catch {
        // ignore
      }
    }, 20000);
    return () => clearInterval(statusTimer);
  }, [router, setPin]);

  const filteredProducts = useMemo(() => {
    const q = search.trim().toLowerCase();
    return products.filter((p) => {
      if (category !== 'all' && p.menu_category !== category) return false;
      if (!q) return true;
      return p.name.toLowerCase().includes(q) || (p.description || '').toLowerCase().includes(q);
    });
  }, [products, category, search]);

  const itemCount = order?.items?.length ?? 0;
  const total = order?.total ?? 0;

  const openAdd = (product: MenuProduct) => {
    setAddProduct(product);
    setQty(1);
    setNotes('');
    setAddonIds([]);
  };

  const submitAdd = async () => {
    if (!addProduct) return;
    setSavingItem(true);
    try {
      const res = await kasirApi.addItem({
        product_id: addProduct.id,
        quantity: qty,
        notes: notes.trim() || undefined,
        addon_ids: addonIds,
      });
      applyOrder(res.data);
      setAddProduct(null);
      setTab('cart');
    } catch (err) {
      handleApiError(err);
    } finally {
      setSavingItem(false);
    }
  };

  const saveOrderContext = async (nextType?: string, nextNote?: string) => {
    try {
      const res = await kasirApi.updateOrder({
        order_type: nextType ?? orderType,
        customer_note: nextNote ?? customerNote,
      });
      applyOrder(res.data);
    } catch (err) {
      handleApiError(err);
    }
  };

  const saveDiscount = async (type: 'amount' | 'percent' | null, value: string) => {
    try {
      const res = await kasirApi.updateDiscount({
        discount_type: type,
        discount_value: value ? Number(value) : 0,
      });
      applyOrder(res.data);
    } catch (err) {
      handleApiError(err);
    }
  };

  const changeQty = async (itemId: number, nextQty: number) => {
    try {
      if (nextQty <= 0) {
        const res = await kasirApi.removeItem(itemId);
        if (res.data) applyOrder(res.data);
        return;
      }
      const res = await kasirApi.updateItem(itemId, { quantity: nextQty });
      if (res.data) applyOrder(res.data);
    } catch (err) {
      handleApiError(err);
    }
  };

  const pickProof = async () => {
    const result = await ImagePicker.launchCameraAsync({
      mediaTypes: ['images'],
      quality: 0.7,
    });
    if (!result.canceled && result.assets[0]) {
      setProofUri(result.assets[0].uri);
    }
  };

  const submitPay = async () => {
    if (!order) return;
    if ((payMethod === 'qris' || payMethod === 'transfer') && !proofUri) {
      Alert.alert('Bukti wajib', 'Upload foto bukti pembayaran untuk QRIS / Transfer.');
      return;
    }
    if (payMethod === 'cash') {
      const received = parseRupiahInput(amountReceived);
      if (received < total) {
        Alert.alert('Nominal kurang', 'Uang diterima harus minimal total belanja.');
        return;
      }
    }

    setPaying(true);
    try {
      const form = new FormData();
      form.append('payment_method', payMethod);
      if (payMethod === 'cash') {
        form.append('amount_received', String(parseRupiahInput(amountReceived)));
      }
      if (proofUri) {
        form.append('payment_proof', {
          uri: proofUri,
          name: 'proof.jpg',
          type: 'image/jpeg',
        } as unknown as Blob);
      }
      const res = await kasirApi.pay(form);
      setPayOpen(false);
      setProofUri(null);
      setAmountReceived('');
      router.push(`/kasir/receipt?id=${res.data.id}` as never);
    } catch (err) {
      handleApiError(err);
    } finally {
      setPaying(false);
    }
  };

  const cashChange = useMemo(() => {
    if (payMethod !== 'cash') return 0;
    const received = parseRupiahInput(amountReceived);
    return Math.max(0, received - total);
  }, [amountReceived, payMethod, total]);

  if (loading && !order) {
    return (
      <View style={[styles.center, { paddingTop: insets.top }]}>
        <ActivityIndicator color={colors.brand600} size="large" />
        <Text style={styles.muted}>Memuat POS…</Text>
      </View>
    );
  }

  return (
    <View style={[styles.root, isWide && styles.rootWide]}>
      <StatusBar barStyle={!isWide && tab === 'cart' ? 'light-content' : 'dark-content'} />

      {isWide || tab === 'menu' ? (
        <View style={[styles.mainCol, isWide && styles.mainColWide]}>
          <View style={[styles.topbar, { paddingTop: insets.top + spacing.sm }]}>
            <Pressable onPress={() => setDrawerOpen(true)} style={styles.menuBtn}>
              <View style={styles.menuLine} />
              <View style={styles.menuLine} />
              <View style={styles.menuLine} />
            </Pressable>
            <View style={{ flex: 1 }}>
              <Text style={styles.topTitle}>{shopName}</Text>
              <Text style={styles.topSub}>
                #{order?.order_number ?? '-'} · {order?.order_type_icon} {order?.order_type_label || orderType}
                {pin?.operator_name ? ` · ${pin.operator_name}` : ''}
              </Text>
            </View>
            <Pressable onPress={() => setOrderBarOpen(true)} style={styles.chipBtn}>
              <Text style={styles.chipText}>Info</Text>
            </Pressable>
          </View>

          {pending.length > 0 ? (
            <View style={styles.pendingBanner}>
              <Text style={styles.pendingTitle}>🔔 Pesanan online ({pending.length})</Text>
              <ScrollView horizontal showsHorizontalScrollIndicator={false} contentContainerStyle={{ gap: 8 }}>
                {pending.map((p) => (
                  <View key={p.id} style={styles.pendingCard}>
                    <Text style={styles.pendingNo}>#{p.order_number}</Text>
                    <Text style={styles.pendingMeta} numberOfLines={1}>
                      {p.customer_note || p.table?.label || 'Tanpa nama'}
                    </Text>
                    <Text style={styles.pendingMeta}>{formatRupiah(p.total)}</Text>
                    <View style={styles.pendingActions}>
                      <Pressable
                        onPress={async () => {
                          try {
                            const res = await kasirApi.loadOrder(p.id);
                            applyOrder(res.data);
                            setTab('cart');
                          } catch (err) {
                            handleApiError(err);
                          }
                        }}
                        style={styles.pendingLoad}
                      >
                        <Text style={styles.pendingLoadText}>Buka</Text>
                      </Pressable>
                      <Pressable
                        onPress={async () => {
                          try {
                            await kasirApi.cancelPending(p.id);
                            await refresh();
                          } catch (err) {
                            handleApiError(err);
                          }
                        }}
                      >
                        <Text style={styles.pendingCancel}>Hapus</Text>
                      </Pressable>
                    </View>
                  </View>
                ))}
              </ScrollView>
            </View>
          ) : null}

          {!isWide && tab === 'menu' ? (
            <View style={styles.tabs}>
              <Pressable onPress={() => setTab('menu')} style={[styles.tab, styles.tabActive]}>
                <Text style={[styles.tabText, styles.tabTextActive]}>☕ Menu</Text>
              </Pressable>
              <Pressable onPress={() => setTab('cart')} style={styles.tab}>
                <Text style={styles.tabText}>🧾 Pesanan{itemCount ? ` (${itemCount})` : ''}</Text>
              </Pressable>
            </View>
          ) : null}

          <View style={styles.menuPane}>
            <TextInput
              value={search}
              onChangeText={setSearch}
              placeholder="Cari menu…"
              placeholderTextColor={colors.slate400}
              style={styles.search}
            />
            <ScrollView
              horizontal
              showsHorizontalScrollIndicator={false}
              style={styles.catRow}
              contentContainerStyle={styles.catRowContent}
            >
              <Pressable onPress={() => setCategory('all')} style={[styles.catChip, category === 'all' && styles.catChipOn]}>
                <Text style={[styles.catChipText, category === 'all' && styles.catChipTextOn]}>Semua</Text>
              </Pressable>
              {categories.map((slug) => (
                <Pressable key={slug} onPress={() => setCategory(slug)} style={[styles.catChip, category === slug && styles.catChipOn]}>
                  <Text style={[styles.catChipText, category === slug && styles.catChipTextOn]} numberOfLines={1}>
                    {categoryLabels[slug] || slug}
                  </Text>
                </Pressable>
              ))}
            </ScrollView>
            <FlatList
              style={styles.productList}
              data={filteredProducts}
              keyExtractor={(item) => String(item.id)}
              numColumns={2}
              contentContainerStyle={[
                styles.productListContent,
                { paddingBottom: itemCount > 0 ? 120 + insets.bottom : 24 + insets.bottom },
              ]}
              columnWrapperStyle={styles.productRow}
              renderItem={({ item }) => {
                const soldOut = item.is_sold_out === true || (item.stock_tracked === true && item.in_stock === false);
                const noPrice = !(item.selling_price > 0);

                return (
                  <Pressable
                    onPress={() => {
                      if (soldOut || noPrice) {
                        Alert.alert(soldOut ? 'Habis' : 'Atur harga', soldOut ? 'Stok menu ini habis.' : 'Harga jual belum diatur.');
                        return;
                      }
                      openAdd(item);
                    }}
                    style={[styles.productCard, { width: productCardWidth }, (soldOut || noPrice) && { opacity: 0.55 }]}
                  >
                    <View style={[styles.productMedia, { height: productCardWidth * 0.85 }]}>
                      <Image source={{ uri: item.image_url }} style={styles.productImage} />
                      {soldOut ? (
                        <View style={[styles.productFab, { backgroundColor: colors.rose600 }]}>
                          <Text style={styles.productFabText}>∅</Text>
                        </View>
                      ) : (
                        <View style={styles.productFab}>
                          <Text style={styles.productFabText}>+</Text>
                        </View>
                      )}
                    </View>
                    <View style={styles.productBody}>
                      <Text style={styles.productCategory} numberOfLines={1}>
                        {categoryLabels[item.menu_category || ''] || item.menu_category || 'Lainnya'}
                      </Text>
                      <Text style={styles.productName} numberOfLines={2}>
                        {item.name}
                      </Text>
                      <Text style={styles.productPrice} numberOfLines={1}>
                        {soldOut ? 'Habis' : formatRupiah(item.selling_price)}
                      </Text>
                    </View>
                  </Pressable>
                );
              }}
              ListEmptyComponent={<Text style={[styles.muted, { padding: spacing.lg }]}>Tidak ada menu.</Text>}
            />
          </View>

          {!isWide && itemCount > 0 && order?.can_checkout !== false ? (
            <View style={[styles.checkoutDock, { paddingBottom: insets.bottom + spacing.sm }]}>
              <View>
                <Text style={styles.dockMeta}>{itemCount} item</Text>
                <Text style={styles.dockTotal}>{formatRupiah(total)}</Text>
              </View>
              <Pressable
                onPress={() => {
                  setPayMethod('cash');
                  setAmountReceived(formatRupiahInput(Math.ceil(total)));
                  setProofUri(null);
                  setPayOpen(true);
                }}
                style={styles.payBtn}
              >
                <Text style={styles.payBtnText}>Bayar</Text>
              </Pressable>
            </View>
          ) : null}
        </View>
      ) : null}

      {isWide || tab === 'cart' ? (
        <View style={[styles.cartCol, isWide ? styles.cartColWide : styles.cartColMobile]}>
          <View style={[styles.cartHeader, { paddingTop: insets.top + spacing.sm }]}>
            <View style={{ flex: 1 }}>
              <Text style={styles.cartHeaderTitle}>Pesanan</Text>
              <Text style={styles.cartHeaderMeta}>
                {itemCount} item · {order?.order_number ?? '-'}
              </Text>
            </View>
            <View style={styles.cartHeaderBadge}>
              <Text style={styles.cartHeaderBadgeText}>{order?.status_label || 'Draft'}</Text>
            </View>
          </View>

          <View style={styles.cartPane}>
            {(order?.order_type_label || order?.customer_note) ? (
              <View style={styles.cartContext}>
                {order?.order_type_label ? (
                  <View style={styles.cartContextChip}>
                    <Text style={styles.cartContextText}>
                      {order.order_type_icon ? `${order.order_type_icon} ` : ''}
                      {order.order_type_label}
                    </Text>
                  </View>
                ) : null}
                {order?.customer_note ? (
                  <View style={styles.cartContextChip}>
                    <Text style={styles.cartContextText}>{order.customer_note}</Text>
                  </View>
                ) : null}
              </View>
            ) : null}

            <ScrollView
              style={{ flex: 1 }}
              contentContainerStyle={{
                padding: spacing.lg,
                paddingBottom: (itemCount > 0 && order?.can_checkout !== false ? 96 : 24) + spacing.lg,
                gap: spacing.md,
              }}
            >
              {(order?.items || []).length === 0 ? (
                <View style={styles.cartEmpty}>
                  <Text style={{ fontSize: 36 }}>☕</Text>
                  <Text style={styles.cartEmptyTitle}>Belum ada item</Text>
                  <Text style={styles.muted}>Pilih menu untuk mulai pesanan</Text>
                </View>
              ) : (
                (order?.items || []).map((item) => (
                  <View key={item.id} style={styles.cartItem}>
                    <View style={styles.cartRow1}>
                      <Text style={styles.cartName} numberOfLines={1}>
                        {item.product_name}
                      </Text>
                      <Text style={styles.cartPrice}>{formatRupiah(item.line_total)}</Text>
                    </View>
                    <View style={styles.cartRow2}>
                      <Text style={styles.cartUnit}>
                        {formatRupiah(item.unit_price)}
                        {item.notes ? ` · ${item.notes}` : ''}
                      </Text>
                      <View style={styles.qtyRow}>
                        <Pressable onPress={() => changeQty(item.id, item.quantity - 1)} style={styles.qtyBtn}>
                          <Text style={styles.qtyBtnText}>−</Text>
                        </Pressable>
                        <Text style={styles.qtyVal}>{item.quantity}</Text>
                        <Pressable onPress={() => changeQty(item.id, item.quantity + 1)} style={styles.qtyBtn}>
                          <Text style={styles.qtyBtnText}>+</Text>
                        </Pressable>
                      </View>
                    </View>
                  </View>
                ))
              )}

              {(order?.items || []).length > 0 ? (
                <>
                  <View style={styles.discountBox}>
                    <Text style={styles.sectionLabel}>Diskon</Text>
                    <View style={styles.discountTabs}>
                      {(['amount', 'percent'] as const).map((t) => (
                        <Pressable
                          key={t}
                          onPress={() => {
                            setDiscountType(t);
                            void saveDiscount(t, discountValue);
                          }}
                          style={[styles.discountTab, discountType === t && styles.discountTabOn]}
                        >
                          <Text style={styles.discountTabText}>{t === 'amount' ? 'Rp' : '%'}</Text>
                        </Pressable>
                      ))}
                    </View>
                    <TextInput
                      value={discountValue}
                      onChangeText={setDiscountValue}
                      onEndEditing={() => saveDiscount(discountType, discountValue)}
                      keyboardType="numeric"
                      placeholder="0"
                      style={styles.input}
                    />
                  </View>

                  <View style={styles.totals}>
                    <View style={styles.totalRow}>
                      <Text style={styles.muted}>Subtotal</Text>
                      <Text>{formatRupiah(order?.subtotal ?? 0)}</Text>
                    </View>
                    {(order?.discount_amount ?? 0) > 0 ? (
                      <View style={styles.totalRow}>
                        <Text style={styles.muted}>Diskon</Text>
                        <Text>- {formatRupiah(order?.discount_amount ?? 0)}</Text>
                      </View>
                    ) : null}
                    <View style={styles.totalRow}>
                      <Text style={styles.totalLabel}>Total</Text>
                      <Text style={styles.totalValue}>{formatRupiah(total)}</Text>
                    </View>
                  </View>

                  <View style={styles.rowActions}>
                    <Pressable
                      onPress={() => {
                        Alert.alert('Pesanan baru', 'Buat order baru?', [
                          { text: 'Batal', style: 'cancel' },
                          {
                            text: 'Ya',
                            onPress: async () => {
                              try {
                                const res = await kasirApi.newOrder();
                                applyOrder(res.data);
                              } catch (err) {
                                handleApiError(err);
                              }
                            },
                          },
                        ]);
                      }}
                      style={styles.outlineBtn}
                    >
                      <Text style={styles.outlineBtnText}>Order Baru</Text>
                    </Pressable>
                    <Pressable
                      onPress={() => {
                        Alert.alert('Batalkan', 'Batalkan pesanan aktif?', [
                          { text: 'Tidak', style: 'cancel' },
                          {
                            text: 'Batalkan',
                            style: 'destructive',
                            onPress: async () => {
                              try {
                                const res = await kasirApi.cancelOrder();
                                applyOrder(res.data);
                              } catch (err) {
                                handleApiError(err);
                              }
                            },
                          },
                        ]);
                      }}
                      style={styles.dangerBtn}
                    >
                      <Text style={styles.dangerBtnText}>Batal</Text>
                    </Pressable>
                  </View>
                </>
              ) : null}
            </ScrollView>
          </View>

          {itemCount > 0 && order?.can_checkout !== false ? (
            <View style={styles.checkoutDockInline}>
              <View>
                <Text style={styles.dockMeta}>{itemCount} item</Text>
                <Text style={styles.dockTotal}>{formatRupiah(total)}</Text>
              </View>
              <Pressable
                onPress={() => {
                  setPayMethod('cash');
                  setAmountReceived(formatRupiahInput(Math.ceil(total)));
                  setProofUri(null);
                  setPayOpen(true);
                }}
                style={styles.payBtn}
              >
                <Text style={styles.payBtnText}>Bayar</Text>
              </Pressable>
            </View>
          ) : null}

          {!isWide ? (
            <View style={[styles.tabsBottom, { paddingBottom: insets.bottom + spacing.xs }]}>
              <Pressable onPress={() => setTab('menu')} style={styles.tabBottom}>
                <Text style={styles.tabText}>☕ Menu</Text>
              </Pressable>
              <Pressable onPress={() => setTab('cart')} style={[styles.tabBottom, styles.tabBottomActive]}>
                <Text style={[styles.tabText, styles.tabBottomTextActive]}>
                  🧾 Pesanan{itemCount ? ` (${itemCount})` : ''}
                </Text>
              </Pressable>
            </View>
          ) : null}
        </View>
      ) : null}

      {/* Add item modal */}
      <Modal visible={!!addProduct} animationType="slide" transparent onRequestClose={() => setAddProduct(null)}>
        <View style={styles.modalOverlay}>
          <View style={[styles.modalSheet, { paddingBottom: insets.bottom + spacing.lg }]}>
            <Text style={styles.modalTitle}>{addProduct?.name}</Text>
            <Text style={styles.productPrice}>{formatRupiah(addProduct?.selling_price ?? 0)}</Text>
            {(addProduct?.addons || []).length > 0 ? (
              <View style={{ gap: 8, marginTop: spacing.md }}>
                <Text style={styles.sectionLabel}>Add-on</Text>
                {addProduct?.addons?.map((addon) => {
                  const on = addonIds.includes(addon.id);
                  return (
                  <Pressable
                      key={addon.id}
                      onPress={() =>
                        setAddonIds((prev) => (on ? prev.filter((id) => id !== addon.id) : [...prev, addon.id]))
                      }
                      style={[styles.addonRow, on && styles.addonRowOn]}
                    >
                      <Text style={styles.addonName}>{addon.name}</Text>
                      <Text style={styles.addonPrice}>+{formatRupiah(addon.price)}</Text>
                  </Pressable>
                  );
                })}
                </View>
            ) : null}
            <Text style={[styles.sectionLabel, { marginTop: spacing.md }]}>Catatan</Text>
            <TextInput value={notes} onChangeText={setNotes} placeholder="Opsional" style={styles.input} />
            <View style={styles.qtyRowLarge}>
              <Pressable onPress={() => setQty((q) => Math.max(1, q - 1))} style={styles.qtyBtn}>
                <Text style={styles.qtyBtnText}>−</Text>
              </Pressable>
              <Text style={styles.qtyValLarge}>{qty}</Text>
              <Pressable onPress={() => setQty((q) => q + 1)} style={styles.qtyBtn}>
                <Text style={styles.qtyBtnText}>+</Text>
              </Pressable>
          </View>
            <Pressable onPress={submitAdd} disabled={savingItem} style={styles.payBtn}>
              <Text style={styles.payBtnText}>{savingItem ? 'Menyimpan…' : 'Tambah ke Pesanan'}</Text>
            </Pressable>
            <Pressable onPress={() => setAddProduct(null)} style={{ alignItems: 'center', padding: spacing.md }}>
              <Text style={styles.muted}>Tutup</Text>
            </Pressable>
              </View>
            </View>
      </Modal>

      {/* Order bar modal */}
      <Modal visible={orderBarOpen} animationType="fade" transparent onRequestClose={() => setOrderBarOpen(false)}>
        <Pressable style={styles.modalOverlay} onPress={() => setOrderBarOpen(false)}>
          <Pressable style={styles.modalSheet} onPress={(e) => e.stopPropagation()}>
            <Text style={styles.modalTitle}>Tipe pesanan</Text>
            <View style={styles.typeRow}>
              {[
                { value: 'dine_in', label: 'Dine In', icon: '🪑' },
                { value: 'takeaway', label: 'Take Away', icon: '🥡' },
              ].map((t) => (
                    <Pressable
                  key={t.value}
                  onPress={() => {
                    setOrderType(t.value);
                    void saveOrderContext(t.value, customerNote);
                  }}
                  style={[styles.typeCard, orderType === t.value && styles.typeCardOn]}
                >
                  <Text style={{ fontSize: 22 }}>{t.icon}</Text>
                  <Text style={styles.typeLabel}>{t.label}</Text>
                          </Pressable>
                        ))}
                      </View>
            <Text style={styles.sectionLabel}>Nama pelanggan</Text>
                          <TextInput
              value={customerNote}
              onChangeText={setCustomerNote}
              onEndEditing={() => saveOrderContext(orderType, customerNote)}
              placeholder="Opsional"
                            style={styles.input}
                          />
            <Pressable onPress={() => setOrderBarOpen(false)} style={[styles.payBtn, { marginTop: spacing.md }]}>
              <Text style={styles.payBtnText}>Simpan</Text>
                        </Pressable>
          </Pressable>
        </Pressable>
      </Modal>

      {/* Pay modal */}
      <Modal visible={payOpen} animationType="slide" transparent onRequestClose={() => setPayOpen(false)}>
        <View style={styles.modalOverlay}>
          <View style={[styles.modalSheet, { paddingBottom: insets.bottom + spacing.lg }]}>
            <Text style={styles.modalTitle}>Pembayaran</Text>
            <Text style={styles.totalValue}>{formatRupiah(total)}</Text>
            <View style={styles.typeRow}>
              {([
                { value: 'cash', label: 'Tunai' },
                { value: 'qris', label: 'QRIS' },
                { value: 'transfer', label: 'Transfer' },
              ] as const).map((m) => (
                        <Pressable
                  key={m.value}
                  onPress={() => setPayMethod(m.value)}
                  style={[styles.typeCard, payMethod === m.value && styles.typeCardOn]}
                        >
                  <Text style={styles.typeLabel}>{m.label}</Text>
                        </Pressable>
              ))}
                      </View>
            {payMethod === 'cash' ? (
              <>
                <Text style={styles.sectionLabel}>Uang diterima</Text>
                <TextInput
                  value={amountReceived}
                  onChangeText={(text) => setAmountReceived(formatRupiahInput(text))}
                  keyboardType="number-pad"
                  placeholder="0"
                  placeholderTextColor={colors.slate400}
                  style={styles.input}
                />
                <Text style={styles.muted}>Kembalian: {formatRupiah(cashChange)}</Text>
              </>
            ) : (
              <>
                <Text style={styles.sectionLabel}>Bukti pembayaran</Text>
                <Pressable onPress={pickProof} style={styles.outlineBtn}>
                  <Text style={styles.outlineBtnText}>{proofUri ? 'Ganti foto' : 'Ambil foto'}</Text>
                    </Pressable>
                {proofUri ? <Text style={styles.muted}>Foto terpilih</Text> : null}
              </>
            )}
            <Pressable onPress={submitPay} disabled={paying} style={[styles.payBtn, { marginTop: spacing.lg }]}>
              <Text style={styles.payBtnText}>{paying ? 'Memproses…' : 'Konfirmasi Bayar'}</Text>
            </Pressable>
            <Pressable onPress={() => setPayOpen(false)} style={{ alignItems: 'center', padding: spacing.md }}>
              <Text style={styles.muted}>Batal</Text>
          </Pressable>
        </View>
        </View>
      </Modal>

      <AppDrawer moduleType="kasir" visible={drawerOpen} onClose={() => setDrawerOpen(false)} />
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.slate100 },
  rootWide: { flexDirection: 'row' },
  mainCol: { flex: 1 },
  mainColWide: { borderRightWidth: 1, borderRightColor: colors.slate200 },
  cartCol: { flex: 1, backgroundColor: colors.slate100 },
  cartColWide: { flexGrow: 0, flexShrink: 0, width: CART_COL_WIDTH, borderLeftWidth: 1, borderLeftColor: colors.slate200 },
  cartColMobile: {},
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: spacing.md },
  muted: { color: colors.slate500, fontSize: 13 },
  topbar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    backgroundColor: colors.white,
    borderBottomWidth: 1,
    borderBottomColor: colors.slate200,
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing.md,
  },
  menuBtn: {
    width: 44,
    height: 44,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
  },
  menuLine: { width: 18, height: 2, borderRadius: 2, backgroundColor: colors.slate700 },
  topTitle: { fontSize: 16, color: colors.slate900, ...font('700') },
  topSub: { fontSize: 12, color: colors.slate500, marginTop: 2 },
  chipBtn: {
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    borderRadius: radius.md,
    backgroundColor: colors.brand50,
  },
  chipText: { color: colors.brand700, fontSize: 12, ...font('600') },
  pendingBanner: {
    backgroundColor: colors.amber50,
    borderBottomWidth: 1,
    borderBottomColor: colors.amber200,
    paddingVertical: spacing.sm,
    paddingLeft: spacing.lg,
    gap: 6,
  },
  pendingTitle: { fontSize: 12, color: colors.amber800, ...font('700') },
  pendingCard: {
    backgroundColor: colors.white,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.amber200,
    padding: spacing.sm,
    minWidth: 140,
  },
  pendingNo: { fontSize: 13, ...font('700'), color: colors.slate900 },
  pendingMeta: { fontSize: 12, color: colors.slate600 },
  pendingActions: { flexDirection: 'row', gap: 10, marginTop: 6 },
  pendingLoad: { backgroundColor: colors.brand600, borderRadius: radius.sm, paddingHorizontal: 8, paddingVertical: 4 },
  pendingLoadText: { color: colors.white, fontSize: 11, ...font('600') },
  pendingCancel: { color: colors.red600, fontSize: 11, ...font('600'), paddingVertical: 4 },
  tabs: { flexDirection: 'row', backgroundColor: colors.white, borderBottomWidth: 1, borderBottomColor: colors.slate200 },
  tabsBottom: {
    flexDirection: 'row',
    backgroundColor: colors.white,
    borderTopWidth: 1,
    borderTopColor: colors.slate200,
    paddingTop: spacing.xs,
    paddingHorizontal: spacing.xs,
    gap: spacing.xs,
    shadowColor: '#1C1410',
    shadowOpacity: 0.08,
    shadowRadius: 12,
    shadowOffset: { width: 0, height: -4 },
    elevation: 8,
  },
  tab: { flex: 1, alignItems: 'center', paddingVertical: spacing.md },
  tabActive: { borderBottomWidth: 2, borderBottomColor: colors.brand600 },
  tabBottom: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    minHeight: 48,
    borderRadius: radius.lg,
    paddingVertical: spacing.sm,
  },
  tabBottomActive: {
    backgroundColor: colors.brand600,
  },
  tabBottomTextActive: { color: colors.white, ...font('700') },
  tabText: { color: colors.slate500, fontSize: 14, ...font('500') },
  tabTextActive: { color: colors.brand700, ...font('700') },
  cartHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    backgroundColor: colors.slate900,
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing.md,
  },
  cartHeaderTitle: { fontSize: 18, color: colors.white, ...font('700') },
  cartHeaderMeta: { fontSize: 12, color: colors.slate400, marginTop: 2 },
  cartHeaderBadge: {
    borderRadius: radius.full,
    backgroundColor: colors.white,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
  },
  cartHeaderBadgeText: { fontSize: 11, color: colors.slate900, ...font('700') },
  cartPane: { flex: 1, backgroundColor: colors.slate100 },
  cartContext: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.sm,
    backgroundColor: colors.white,
    borderBottomWidth: 1,
    borderBottomColor: colors.slate200,
  },
  cartContextChip: {
    borderRadius: radius.full,
    backgroundColor: colors.brand50,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.xs,
  },
  cartContextText: { fontSize: 12, color: colors.brand700, ...font('600') },
  cartEmpty: { alignItems: 'center', justifyContent: 'center', paddingVertical: spacing.xxl, gap: spacing.sm },
  cartEmptyTitle: { fontSize: 15, color: colors.slate700, ...font('600') },
  checkoutDockInline: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: colors.slate900,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
    gap: spacing.md,
  },
  search: {
    marginHorizontal: spacing.lg,
    marginTop: spacing.md,
    minHeight: 44,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    paddingHorizontal: spacing.md,
    color: colors.slate900,
  },
  menuPane: { flex: 1 },
  catRow: { flexGrow: 0, marginTop: spacing.sm },
  catRowContent: {
    gap: 8,
    paddingHorizontal: spacing.lg,
    paddingVertical: 4,
    alignItems: 'center',
  },
  catChip: {
    borderRadius: radius.full,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    justifyContent: 'center',
  },
  catChipOn: { backgroundColor: colors.brand600, borderColor: colors.brand600 },
  catChipText: { fontSize: 12, color: colors.slate600, ...font('500') },
  catChipTextOn: { color: colors.white },
  productList: { flex: 1 },
  productListContent: {
    paddingHorizontal: spacing.md,
    paddingTop: spacing.sm,
  },
  productRow: {
    gap: PRODUCT_GAP,
    marginBottom: PRODUCT_GAP,
  },
  productCard: {
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    overflow: 'hidden',
  },
  productMedia: {
    width: '100%',
    backgroundColor: colors.slate100,
    position: 'relative',
  },
  productImage: {
    width: '100%',
    height: '100%',
    resizeMode: 'cover',
  },
  productFab: {
    position: 'absolute',
    right: 8,
    bottom: 8,
    width: 28,
    height: 28,
    borderRadius: 14,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  productFabText: { color: colors.white, fontSize: 18, lineHeight: 20, ...font('700') },
  productBody: {
    paddingHorizontal: spacing.sm,
    paddingTop: spacing.sm,
    paddingBottom: spacing.md,
    gap: 2,
    minHeight: 78,
  },
  productCategory: { fontSize: 10, color: colors.slate500, ...font('500'), textTransform: 'uppercase' },
  productName: { fontSize: 13, color: colors.slate900, ...font('600'), lineHeight: 17 },
  productPrice: { fontSize: 13, color: colors.brand700, ...font('700'), marginTop: 4 },
  cartItem: {
    flexDirection: 'column',
    gap: 4,
    backgroundColor: colors.white,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    paddingVertical: 8,
    paddingHorizontal: 10,
  },
  cartRow1: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  cartRow2: { flexDirection: 'row', alignItems: 'center', gap: 8 },
  cartName: { fontSize: 12, color: colors.slate900, ...font('600'), flex: 1 },
  cartNotes: { fontSize: 10, color: colors.slate500 },
  cartUnit: { flex: 1, fontSize: 10, color: colors.slate500 },
  cartPrice: { fontSize: 12, color: colors.slate900, ...font('700') },
  qtyRow: { flexDirection: 'row', alignItems: 'center', gap: 6 },
  qtyRowLarge: { flexDirection: 'row', alignItems: 'center', justifyContent: 'center', gap: 16, marginVertical: spacing.lg },
  qtyBtn: {
    width: 28,
    height: 28,
    borderRadius: radius.sm,
    backgroundColor: colors.slate100,
    alignItems: 'center',
    justifyContent: 'center',
  },
  qtyBtnText: { fontSize: 14, color: colors.slate800, ...font('700') },
  qtyVal: { minWidth: 20, textAlign: 'center', fontSize: 12, ...font('600') },
  qtyValLarge: { fontSize: 22, minWidth: 40, textAlign: 'center', ...font('700') },
  discountBox: { backgroundColor: colors.white, borderRadius: radius.lg, borderWidth: 1, borderColor: colors.slate200, padding: spacing.md, gap: spacing.sm },
  discountTabs: { flexDirection: 'row', gap: 8 },
  discountTab: { paddingHorizontal: 14, paddingVertical: 8, borderRadius: radius.md, backgroundColor: colors.slate100 },
  discountTabOn: { backgroundColor: colors.brand100 },
  discountTabText: { ...font('600'), color: colors.slate700 },
  sectionLabel: { fontSize: 13, color: colors.slate700, ...font('600') },
  input: {
    minHeight: 44,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate300,
    backgroundColor: colors.white,
    paddingHorizontal: spacing.md,
    color: colors.slate900,
  },
  totals: { backgroundColor: colors.white, borderRadius: radius.lg, borderWidth: 1, borderColor: colors.slate200, padding: spacing.md, gap: 8 },
  totalRow: { flexDirection: 'row', justifyContent: 'space-between' },
  totalLabel: { fontSize: 15, ...font('700'), color: colors.slate900 },
  totalValue: { fontSize: 18, ...font('700'), color: colors.brand700 },
  rowActions: { flexDirection: 'row', gap: spacing.sm },
  outlineBtn: {
    flex: 1,
    minHeight: 44,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate300,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.white,
  },
  outlineBtnText: { color: colors.slate700, ...font('600') },
  dangerBtn: {
    minHeight: 44,
    paddingHorizontal: spacing.lg,
    borderRadius: radius.md,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.red50,
    borderWidth: 1,
    borderColor: colors.red200,
  },
  dangerBtnText: { color: colors.red700, ...font('600') },
  checkoutDock: {
    position: 'absolute',
    left: 0,
    right: 0,
    bottom: 0,
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    backgroundColor: colors.slate900,
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.md,
    gap: spacing.md,
  },
  dockMeta: { color: colors.slate400, fontSize: 12 },
  dockTotal: { color: colors.white, fontSize: 18, ...font('700') },
  payBtn: {
    minHeight: 48,
    minWidth: 120,
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: spacing.xl,
  },
  payBtnText: { color: colors.white, fontSize: 15, ...font('700') },
  modalOverlay: { flex: 1, backgroundColor: 'rgba(28,20,16,0.5)', justifyContent: 'flex-end' },
  modalSheet: {
    backgroundColor: colors.white,
    borderTopLeftRadius: radius['3xl'],
    borderTopRightRadius: radius['3xl'],
    padding: spacing.xl,
    gap: spacing.sm,
  },
  modalTitle: { fontSize: 18, color: colors.slate900, ...font('700') },
  addonRow: {
    flexDirection: 'row',
    justifyContent: 'space-between',
    borderWidth: 1,
    borderColor: colors.slate200,
    borderRadius: radius.md,
    padding: spacing.md,
  },
  addonRowOn: { borderColor: colors.brand500, backgroundColor: colors.brand50 },
  addonName: { color: colors.slate800, ...font('500') },
  addonPrice: { color: colors.brand700, ...font('600') },
  typeRow: { flexDirection: 'row', gap: spacing.sm, marginVertical: spacing.sm },
  typeCard: {
    flex: 1,
    alignItems: 'center',
    gap: 4,
    borderWidth: 1,
    borderColor: colors.slate200,
    borderRadius: radius.lg,
    paddingVertical: spacing.md,
    backgroundColor: colors.slate50,
  },
  typeCardOn: { borderColor: colors.brand500, backgroundColor: colors.brand50 },
  typeLabel: { fontSize: 13, color: colors.slate800, ...font('600') },
});
