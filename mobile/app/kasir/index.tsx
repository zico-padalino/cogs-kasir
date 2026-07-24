import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useEffect, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  FlatList,
  Image,
  KeyboardAvoidingView,
  Modal,
  Platform,
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
import type { MenuProduct, OrderItem, PosOrder, PosOrder as Order } from '@/api/types';
import { asApiError, useAuth } from '@/auth';
import {
  AppDrawer,
  PermanentSidebar,
  SIDEBAR_WIDTH,
  useSidebarLayout,
} from '@/components/AppScaffold';
import { consumePendingOpenOrderId, seedPendingIds } from '@/kasir/pendingOrderTracker';
import { colors, font, radius, spacing } from '@/theme';
import { formatRupiah, formatRupiahInput, parseRupiahInput } from '@/utils/rupiah';

type TabKey = 'menu' | 'cart';
type PayMethod = 'cash' | 'qris' | 'transfer';

const QRIS_IMAGE = require('../../assets/qris.jpeg');

const PRODUCT_GAP = spacing.sm;
const PRODUCT_PAD = spacing.md;

export default function KasirPosScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const { width: windowWidth, height: windowHeight } = useWindowDimensions();
  const { setPin, pin } = useAuth();
  const { isDesktop, showPermanent, setCollapsed, toggleCollapsed } = useSidebarLayout();
  // Tab-only (Menu | Pesanan). Rotasi hanya menyesuaikan kolom grid & spacing.
  const isLandscape = windowWidth > windowHeight;
  const contentWidth = showPermanent ? windowWidth - SIDEBAR_WIDTH : windowWidth;
  const productCols = isLandscape
    ? contentWidth >= 1000
      ? 5
      : contentWidth >= 800
        ? 4
        : 3
    : contentWidth >= 420
      ? 3
      : 2;
  const productCardWidth =
    (contentWidth - PRODUCT_PAD * 2 - PRODUCT_GAP * (productCols - 1)) / productCols;

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
  const [holding, setHolding] = useState(false);
  const [deliverOpen, setDeliverOpen] = useState(false);
  const [deliverTitle, setDeliverTitle] = useState('');
  const [deliverItems, setDeliverItems] = useState<OrderItem[]>([]);
  const [deliverTogglingId, setDeliverTogglingId] = useState<number | null>(null);

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

  const openDeliverModal = (title: string, items: OrderItem[]) => {
    setDeliverTitle(title);
    setDeliverItems(items);
    setDeliverOpen(true);
  };

  const toggleDeliverModalItem = async (item: OrderItem) => {
    if (deliverTogglingId) return;
    const next = !item.is_delivered;
    setDeliverTogglingId(item.id);
    try {
      const res = await kasirApi.setItemDelivered(item.id, next);
      const updatedItems = res.data?.items || [];
      setDeliverItems(updatedItems.length ? updatedItems : deliverItems.map((row) => (
        row.id === item.id ? { ...row, is_delivered: next } : row
      )));
      if (res.data && order?.id === res.data.id) {
        applyOrder(res.data);
      }
      if (res.data) {
        setPending((prev) =>
          prev.map((p) => (p.id === res.data?.id ? { ...p, ...res.data, items: res.data.items } : p)),
        );
      } else {
        await refresh();
      }
    } catch (err) {
      handleApiError(err);
    } finally {
      setDeliverTogglingId(null);
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
      if (res.stock_out_message) {
        Alert.alert('Stok habis', res.stock_out_message, [
          {
            text: 'OK',
            onPress: () => router.push(`/kasir/receipt?id=${res.data.id}` as never),
          },
        ]);
        return;
      }
      router.push(`/kasir/receipt?id=${res.data.id}` as never);
    } catch (err) {
      handleApiError(err);
    } finally {
      setPaying(false);
    }
  };

  const submitOpenBill = () => {
    if (!order || order.source !== 'kasir') return;
    const alreadyOpen = order.is_open_bill || order.status === 'unpaid';
    Alert.alert(
      'Open Bill',
      alreadyOpen
        ? 'Simpan perubahan Open Bill?'
        : 'Simpan sebagai Open Bill? Bisa dibuka lagi untuk tambah item. Stok belum dipotong.',
      [
        { text: 'Batal', style: 'cancel' },
        {
          text: 'Simpan',
          onPress: async () => {
            setHolding(true);
            try {
              const res = await kasirApi.openBill();
              applyOrder(res.data.active_order);
              await refresh();
              Alert.alert('Disimpan', res.message || 'Open Bill disimpan.');
            } catch (err) {
              handleApiError(err);
            } finally {
              setHolding(false);
            }
          },
        },
      ],
    );
  };

  const cashChange = useMemo(() => {
    if (payMethod !== 'cash') return 0;
    const received = parseRupiahInput(amountReceived);
    return Math.max(0, received - total);
  }, [amountReceived, payMethod, total]);

  const pendingSummary = useMemo(() => {
    const onlineWaiting = pending.filter((p) => p.status === 'submitted').length;
    const openBillCount = pending.filter((p) => p.is_open_bill || p.status === 'unpaid').length;
    const awaitingServeCount = pending.filter((p) => p.can_mark_served || p.status === 'paid').length;
    const pendingTotal = pending.reduce((sum, p) => sum + (p.total || 0), 0);
    return { onlineWaiting, openBillCount, awaitingServeCount, pendingTotal };
  }, [pending]);

  const cartEditable = order?.is_editable !== false;
  const isActiveOpenBill = Boolean(order?.is_open_bill || order?.status === 'unpaid');
  const canChecklistDelivered = Boolean(
    order?.can_checklist_delivered || isActiveOpenBill || order?.status === 'paid' || order?.status === 'served',
  );
  const cartDeliveredCount = (order?.items || []).filter((item) => item.is_delivered).length;

  if (loading && !order) {
    return (
      <View style={[styles.center, { paddingTop: insets.top }]}>
        <ActivityIndicator color={colors.brand600} size="large" />
        <Text style={styles.muted}>Memuat POS…</Text>
      </View>
    );
  }

  const newOrder = () => {
    Alert.alert('Pesanan baru', 'Buat order baru?', [
      { text: 'Batal', style: 'cancel' },
      {
        text: 'Ya',
        onPress: async () => {
          try {
            const res = await kasirApi.newOrder();
            applyOrder(res.data);
            setTab('menu');
          } catch (err) {
            handleApiError(err);
          }
        },
      },
    ]);
  };

  return (
    <View style={styles.root}>
      <KeyboardAvoidingView
        style={styles.root}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
      >
        <StatusBar barStyle={tab === 'cart' ? 'light-content' : 'dark-content'} />

        <View style={[styles.shell, showPermanent && styles.shellWithSidebar]}>
          {showPermanent ? (
            <PermanentSidebar moduleType="kasir" onCollapse={() => setCollapsed(true)} />
          ) : null}

          <View style={styles.posBody}>
            {/* MENU */}
            {tab === 'menu' ? (
              <View style={styles.mainCol}>
          <View style={[styles.topbar, { paddingTop: insets.top + spacing.sm }]}>
            <Pressable
              onPress={() => {
                if (isDesktop) {
                  toggleCollapsed();
                  return;
                }
                setDrawerOpen(true);
              }}
              style={styles.menuBtn}
              accessibilityLabel="Menu"
            >
              <View style={styles.menuLine} />
              <View style={styles.menuLine} />
              <View style={styles.menuLine} />
            </Pressable>

            <View style={styles.trxChip}>
              <Text style={styles.trxChipText} numberOfLines={1}>
                #{order?.order_number ?? '—'}
              </Text>
            </View>
            <View style={styles.metaChip}>
              <Text style={styles.metaChipText} numberOfLines={1}>
                {order?.order_type_icon ? `${order.order_type_icon} ` : ''}
                {order?.order_type_label || (orderType === 'dine_in' ? 'Dine In' : 'Take Away')}
              </Text>
            </View>
            <View style={styles.draftChip}>
              <Text style={styles.draftChipText}>{order?.status_label || 'Draft'}</Text>
            </View>

            <View style={{ flex: 1 }} />

            <Pressable onPress={newOrder} style={styles.newOrderBtn}>
              <Text style={styles.newOrderBtnText}>+ Pesanan Baru</Text>
            </Pressable>
          </View>

          <Pressable onPress={() => setOrderBarOpen((v) => !v)} style={styles.orderTypeBar}>
            <Text style={styles.orderTypeBarLabel}>Tipe pesanan</Text>
            <Text style={styles.orderTypeBarValue} numberOfLines={1}>
              {order?.order_type_icon ? `${order.order_type_icon} ` : ''}
              {order?.order_type_label || (orderType === 'dine_in' ? 'Dine In' : 'Take Away')}
              {customerNote ? ` · ${customerNote}` : ''}
            </Text>
            <Text style={styles.orderTypeBarCaret}>{orderBarOpen ? '▲' : '▼'}</Text>
          </Pressable>

          {orderBarOpen ? (
            <View style={styles.orderTypePanel}>
              <View style={styles.typeRow}>
                {[
                  { value: 'dine_in', label: 'Dine In', icon: '🪑', hint: 'Makan di tempat' },
                  { value: 'takeaway', label: 'Take Away', icon: '🥡', hint: 'Bungkus / dibawa pulang' },
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
                    <Text style={styles.typeHint}>{t.hint}</Text>
                  </Pressable>
                ))}
              </View>
              <Text style={styles.sectionLabel}>Nama pelanggan</Text>
              <TextInput
                value={customerNote}
                onChangeText={setCustomerNote}
                onEndEditing={() => saveOrderContext(orderType, customerNote)}
                placeholder="Contoh: Budi"
                placeholderTextColor={colors.slate400}
                style={styles.input}
              />
            </View>
          ) : null}

          {pending.length > 0 ? (
            <View style={styles.pendingBanner}>
              <Text style={styles.pendingTitle}>
                🔔 {pending.length} menunggu
                {pendingSummary.onlineWaiting > 0 ? ` · ${pendingSummary.onlineWaiting} online` : ''}
                {pendingSummary.openBillCount > 0 ? ` · ${pendingSummary.openBillCount} open bill` : ''}
                {pendingSummary.awaitingServeCount > 0 ? ` · ${pendingSummary.awaitingServeCount} siap antar` : ''}
                {` · ${formatRupiah(pendingSummary.pendingTotal)}`}
              </Text>
              <View style={styles.pendingList}>
                {pending.map((p) => {
                  const awaitingServe = p.can_mark_served || p.status === 'paid';
                  const isOpenBill = p.is_open_bill || p.status === 'unpaid';
                  const isCurrent = order?.id === p.id;
                  const openLabel = isOpenBill
                    ? 'Buka / Tambah'
                    : p.status === 'confirmed'
                      ? 'Bayar'
                      : 'Masuk kasir';
                  const deleteLabel = isOpenBill ? 'Hapus Open Bill' : 'Hapus';
                  const deleteConfirm = isOpenBill
                    ? `Hapus Open Bill ${p.customer_note || p.order_number}?`
                    : `Hapus pesanan ${p.customer_note || p.order_number}? Pesanan akan dibatalkan.`;
                  const serveConfirm = `Konfirmasi pesanan ${p.customer_note || p.order_number} sudah diantar / selesai?`;
                  const pendingItemCount = p.items?.length ?? p.item_count ?? 0;
                  const pendingDelivered = (p.items || []).filter((i) => i.is_delivered).length;

                  return (
                    <View
                      key={p.id}
                      style={[
                        styles.pendingCard,
                        isOpenBill && styles.pendingCardOpenBill,
                        awaitingServe && styles.pendingCardAwaitingServe,
                        isCurrent && styles.pendingCardCurrent,
                      ]}
                    >
                      <Pressable
                        disabled={isCurrent && !awaitingServe}
                        onPress={() => {
                          if (awaitingServe) {
                            router.push(`/kasir/order-detail?id=${p.id}` as never);
                            return;
                          }
                          if (isCurrent) {
                            setTab('cart');
                            return;
                          }
                          void (async () => {
                            try {
                              const res = await kasirApi.loadOrder(p.id);
                              applyOrder(res.data);
                              setTab('cart');
                            } catch (err) {
                              handleApiError(err);
                            }
                          })();
                        }}
                        style={({ pressed }) => [pressed && !(isCurrent && !awaitingServe) && styles.pendingCardPressed]}
                      >
                        <View style={styles.pendingCardTop}>
                          <Text style={styles.pendingNo} numberOfLines={1}>
                            {p.customer_note || 'Tanpa nama'}
                          </Text>
                          <Text style={styles.pendingAmount}>{formatRupiah(p.total)}</Text>
                        </View>
                        <Text style={styles.pendingMeta} numberOfLines={1}>
                          #{p.order_number}
                          {p.table?.label ? ` · ${p.table.label}` : ''}
                        </Text>
                        <Text style={styles.pendingMeta}>
                          {isCurrent && !awaitingServe
                            ? 'Sedang dibuka'
                            : awaitingServe
                              ? 'Sudah Bayar'
                              : isOpenBill
                                ? 'Open Bill'
                                : p.status_label || p.status}
                        </Text>
                        {(isOpenBill || awaitingServe) && pendingItemCount > 0 ? (
                          <Text style={styles.pendingDeliver}>
                            Diantar {pendingDelivered}/{pendingItemCount}
                          </Text>
                        ) : null}
                      </Pressable>
                      <View style={styles.pendingActions}>
                        {(isOpenBill || awaitingServe) && (p.items || []).length > 0 ? (
                          <Pressable
                            onPress={() =>
                              openDeliverModal(p.customer_note || p.order_number, p.items || [])
                            }
                            style={[styles.pendingDeliverBtn, styles.pendingActionHalf]}
                          >
                            <Text style={styles.pendingDeliverBtnText}>Ceklis antar</Text>
                          </Pressable>
                        ) : null}
                        {awaitingServe ? (
                          <Pressable
                            onPress={() => {
                              Alert.alert('Selesai antar', serveConfirm, [
                                { text: 'Batal', style: 'cancel' },
                                {
                                  text: 'Ya, selesai',
                                  onPress: async () => {
                                    try {
                                      await kasirApi.markServed(p.id);
                                      await refresh();
                                    } catch (err) {
                                      handleApiError(err);
                                    }
                                  },
                                },
                              ]);
                            }}
                            style={[styles.pendingLoad, styles.pendingServe, styles.pendingActionHalf]}
                          >
                            <Text style={styles.pendingLoadText}>Sudah diantar / selesai</Text>
                          </Pressable>
                        ) : isCurrent ? (
                          <Pressable
                            onPress={() => {
                              Alert.alert(deleteLabel, deleteConfirm, [
                                { text: 'Batal', style: 'cancel' },
                                {
                                  text: 'Hapus',
                                  style: 'destructive',
                                  onPress: async () => {
                                    try {
                                      await kasirApi.cancelPending(p.id);
                                      await refresh();
                                    } catch (err) {
                                      handleApiError(err);
                                    }
                                  },
                                },
                              ]);
                            }}
                            style={styles.pendingActionHalf}
                          >
                            <Text style={styles.pendingCancel}>{isOpenBill ? 'Hapus Open Bill' : 'Hapus pesanan'}</Text>
                          </Pressable>
                        ) : (
                          <>
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
                              style={[styles.pendingLoad, styles.pendingActionHalf]}
                            >
                              <Text style={styles.pendingLoadText}>{openLabel}</Text>
                            </Pressable>
                            <Pressable
                              onPress={() => {
                                Alert.alert(deleteLabel, deleteConfirm, [
                                  { text: 'Batal', style: 'cancel' },
                                  {
                                    text: 'Hapus',
                                    style: 'destructive',
                                    onPress: async () => {
                                      try {
                                        await kasirApi.cancelPending(p.id);
                                        await refresh();
                                      } catch (err) {
                                        handleApiError(err);
                                      }
                                    },
                                  },
                                ]);
                              }}
                              style={styles.pendingActionHalf}
                            >
                              <Text style={styles.pendingCancel}>{deleteLabel}</Text>
                            </Pressable>
                          </>
                        )}
                      </View>
                    </View>
                  );
                })}
              </View>
            </View>
          ) : null}

          <View style={styles.menuPane}>
            <Text style={styles.menuHeading}>Pilih Menu</Text>
            <TextInput
              value={search}
              onChangeText={setSearch}
              placeholder="Cari menu…"
              placeholderTextColor={colors.slate400}
              style={styles.search}
              returnKeyType="search"
            />
            <ScrollView
              horizontal
              showsHorizontalScrollIndicator={false}
              style={styles.catRow}
              contentContainerStyle={styles.catRowContent}
              keyboardShouldPersistTaps="handled"
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
              key={`cols-${productCols}`}
              numColumns={productCols}
              keyboardShouldPersistTaps="handled"
              keyboardDismissMode="on-drag"
              contentContainerStyle={[
                styles.productListContent,
                {
                  /* Dock Bayar di dalam mainCol (di atas tab) — cukup ruang dock saja */
                  paddingBottom: itemCount > 0 ? 100 : 24,
                },
              ]}
              columnWrapperStyle={productCols > 1 ? styles.productRow : undefined}
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

          {itemCount > 0 && order?.can_checkout !== false ? (
            <View style={[styles.checkoutDock, { paddingBottom: spacing.sm }]}>
              <View>
                <Text style={styles.dockMeta}>{itemCount} item</Text>
                <Text style={styles.dockTotal}>{formatRupiah(total)}</Text>
              </View>
              <View style={styles.payActions}>
                {order?.source === 'kasir' ? (
                  <Pressable onPress={submitOpenBill} disabled={holding} style={styles.holdBtn}>
                    <Text style={styles.holdBtnText}>
                      {holding ? '…' : isActiveOpenBill ? 'Simpan Open Bill' : 'Open Bill'}
                    </Text>
                  </Pressable>
                ) : null}
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
            </View>
          ) : null}
        </View>
      ) : null}

      {/* PANEL PESANAN — tab */}
      {tab === 'cart' ? (
        <View style={[styles.cartCol, styles.cartColMobile]}>
          <View style={[styles.cartHeader, { paddingTop: insets.top + spacing.sm }]}>
            <View style={{ flex: 1, minWidth: 0 }}>
              <Text style={styles.cartHeaderTitle}>Pesanan</Text>
              <Text style={styles.cartHeaderMeta} numberOfLines={1}>
                {customerNote || 'Umum'} · #{order?.order_number ?? '—'}
              </Text>
            </View>
            <View style={styles.cartHeaderBadge}>
              <Text style={styles.cartHeaderBadgeText}>
                {itemCount > 0 ? order?.status_label || 'Draft' : 'Draft'}
              </Text>
            </View>
          </View>

          <View style={styles.cartContext}>
            <View style={styles.cartContextChip}>
              <Text style={styles.cartContextText}>
                {order?.order_type_icon ? `${order.order_type_icon} ` : '🥡 '}
                {order?.order_type_label || (orderType === 'dine_in' ? 'Dine In' : 'Take Away')}
              </Text>
            </View>
            {customerNote ? (
              <View style={styles.cartContextChip}>
                <Text style={styles.cartContextText}>{customerNote}</Text>
              </View>
            ) : null}
          </View>

          {isActiveOpenBill ? (
            <View style={styles.openBillHint}>
              <Text style={styles.openBillHintText}>
                Open Bill aktif — boleh tambah item. Tekan Ceklis antar untuk tandai yang sudah diantar, lalu simpan atau Bayar.
              </Text>
            </View>
          ) : null}

          <ScrollView
            style={styles.cartScroll}
            keyboardShouldPersistTaps="handled"
            keyboardDismissMode="on-drag"
            contentContainerStyle={styles.cartScrollContent}
          >
            {(order?.items || []).length === 0 ? (
              <View style={styles.cartEmpty}>
                <Text style={{ fontSize: 40 }}>🛒</Text>
                <Text style={styles.cartEmptyTitle}>Belum ada item</Text>
                <Text style={styles.muted}>Pilih menu untuk mulai pesanan</Text>
              </View>
            ) : (
              <>
                {canChecklistDelivered ? (
                  <Pressable
                    onPress={() =>
                      openDeliverModal(
                        customerNote || order?.order_number || 'Pesanan',
                        order?.items || [],
                      )
                    }
                    style={styles.deliverOpenBtn}
                  >
                    <Text style={styles.deliverOpenLabel}>Ceklis antar</Text>
                    <Text style={styles.deliverOpenProgress}>
                      {cartDeliveredCount}/{(order?.items || []).length}
                    </Text>
                  </Pressable>
                ) : null}
                {(order?.items || []).map((item) => {
                  const delivered = Boolean(item.is_delivered);
                  return (
                    <View
                      key={item.id}
                      style={[styles.cartItemCard, delivered && styles.cartItemCardDelivered]}
                    >
                      {item.product_image_url ? (
                        <Image source={{ uri: item.product_image_url }} style={styles.cartItemThumb} />
                      ) : (
                        <View style={[styles.cartItemThumb, styles.cartItemThumbFallback]}>
                          <Text style={{ fontSize: 18 }}>☕</Text>
                        </View>
                      )}
                      <View style={{ flex: 1, minWidth: 0 }}>
                        <View style={styles.cartRow1}>
                          <Text
                            style={[styles.cartName, delivered && styles.cartNameDelivered]}
                            numberOfLines={2}
                          >
                            {item.product_name}
                          </Text>
                          <Text style={styles.cartPrice}>{formatRupiah(item.line_total)}</Text>
                        </View>
                        <Text style={styles.cartUnit}>{formatRupiah(item.unit_price)}</Text>
                        {delivered ? <Text style={styles.cartDeliveredTag}>✓ Sudah diantar</Text> : null}
                        <View style={styles.cartItemActions}>
                          {cartEditable ? (
                            <View style={styles.qtyRow}>
                              <Pressable onPress={() => changeQty(item.id, item.quantity - 1)} style={styles.qtyBtn}>
                                <Text style={styles.qtyBtnText}>−</Text>
                              </Pressable>
                              <Text style={styles.qtyVal}>{item.quantity}</Text>
                              <Pressable onPress={() => changeQty(item.id, item.quantity + 1)} style={styles.qtyBtn}>
                                <Text style={styles.qtyBtnText}>+</Text>
                              </Pressable>
                            </View>
                          ) : (
                            <Text style={styles.qtyVal}>×{item.quantity}</Text>
                          )}
                          {cartEditable ? (
                            <Pressable onPress={() => changeQty(item.id, 0)} style={styles.cartDeleteBtn}>
                              <Text style={styles.cartDeleteText}>×</Text>
                            </Pressable>
                          ) : null}
                        </View>
                        {item.notes ? <Text style={styles.cartNote}>{item.notes}</Text> : null}
                      </View>
                    </View>
                  );
                })}
              </>
            )}

            {(order?.items || []).length > 0 ? (
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
                  placeholder="Tambah diskon"
                  placeholderTextColor={colors.slate400}
                  style={styles.input}
                />
              </View>
            ) : null}
          </ScrollView>

          <View style={[styles.cartFooter, { paddingBottom: spacing.md }]}>
            <View style={styles.totalRow}>
              <Text style={styles.muted}>Subtotal</Text>
              <Text style={styles.cartFooterAmount}>{formatRupiah(order?.subtotal ?? 0)}</Text>
            </View>
            {(order?.discount_amount ?? 0) > 0 ? (
              <View style={styles.totalRow}>
                <Text style={styles.muted}>Diskon</Text>
                <Text style={styles.cartFooterAmount}>- {formatRupiah(order?.discount_amount ?? 0)}</Text>
              </View>
            ) : null}
            <View style={styles.totalRow}>
              <Text style={styles.totalLabel}>Total</Text>
              <Text style={styles.totalValue}>{formatRupiah(total)}</Text>
            </View>

            {itemCount > 0 && order?.can_checkout !== false ? (
              <View style={styles.cartFooterActions}>
                {order?.source === 'kasir' ? (
                  <Pressable onPress={submitOpenBill} disabled={holding} style={styles.holdBtn}>
                    <Text style={styles.holdBtnText}>
                      {holding ? '…' : isActiveOpenBill ? 'Simpan Open Bill' : 'Open Bill'}
                    </Text>
                  </Pressable>
                ) : null}
                <Pressable
                  onPress={() => {
                    setPayMethod('cash');
                    setAmountReceived(formatRupiahInput(Math.ceil(total)));
                    setProofUri(null);
                    setPayOpen(true);
                  }}
                  style={styles.payBtnGreen}
                >
                  <Text style={styles.payBtnText}>Bayar {formatRupiah(total)}</Text>
                </Pressable>
              </View>
            ) : (
              <Pressable onPress={newOrder} style={styles.outlineBtn}>
                <Text style={styles.outlineBtnText}>+ Pesanan Baru</Text>
              </Pressable>
            )}
          </View>
        </View>
      ) : null}

            {/* Tab Menu | Pesanan — selalu di bawah */}
            <View style={[styles.tabsBottom, { paddingBottom: insets.bottom + spacing.xs }]}>
              <Pressable
                onPress={() => setTab('menu')}
                style={[styles.tabBottom, tab === 'menu' && styles.tabBottomActive]}
              >
                <Text style={[styles.tabText, tab === 'menu' && styles.tabBottomTextActive]}>☕ Menu</Text>
              </Pressable>
              <Pressable
                onPress={() => setTab('cart')}
                style={[styles.tabBottom, tab === 'cart' && styles.tabBottomActive]}
              >
                <Text style={[styles.tabText, tab === 'cart' && styles.tabBottomTextActive]}>
                  🧾 Pesanan{itemCount ? ` (${itemCount})` : ''}
                </Text>
              </Pressable>
            </View>
          </View>
        </View>

      {/* Add item modal */}
      <Modal visible={!!addProduct} animationType="slide" transparent onRequestClose={() => setAddProduct(null)}>
        <KeyboardAvoidingView
          style={styles.modalOverlay}
          behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        >
          <ScrollView
            contentContainerStyle={styles.modalScroll}
            keyboardShouldPersistTaps="handled"
            bounces={false}
          >
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
          </ScrollView>
        </KeyboardAvoidingView>
      </Modal>

      {/* Deliver checklist modal */}
      <Modal visible={deliverOpen} animationType="slide" transparent onRequestClose={() => setDeliverOpen(false)}>
        <View style={styles.deliverOverlay}>
          <View style={[styles.deliverSheet, { paddingBottom: insets.bottom + spacing.lg }]}>
            <View style={styles.deliverHead}>
              <View style={{ flex: 1, minWidth: 0 }}>
                <Text style={styles.deliverEyebrow}>Ceklis antar</Text>
                <Text style={styles.deliverTitle} numberOfLines={1}>{deliverTitle}</Text>
                <Text style={styles.deliverProgress}>
                  Diantar {deliverItems.filter((i) => i.is_delivered).length}/{deliverItems.length}
                </Text>
              </View>
              <Pressable onPress={() => setDeliverOpen(false)} style={styles.deliverClose}>
                <Text style={styles.deliverCloseText}>×</Text>
              </Pressable>
            </View>
            <ScrollView style={styles.deliverList} contentContainerStyle={{ gap: spacing.sm, paddingBottom: spacing.md }}>
              {deliverItems.length === 0 ? (
                <Text style={styles.muted}>Tidak ada item.</Text>
              ) : (
                deliverItems.map((item) => {
                  const delivered = Boolean(item.is_delivered);
                  return (
                    <Pressable
                      key={item.id}
                      onPress={() => void toggleDeliverModalItem(item)}
                      disabled={deliverTogglingId === item.id}
                      style={[styles.deliverRow, delivered && styles.deliverRowOn]}
                    >
                      <View style={[styles.cartCheckBox, delivered && styles.cartCheckBoxOn]}>
                        <Text style={styles.cartCheckMark}>{delivered ? '✓' : ''}</Text>
                      </View>
                      <View style={{ flex: 1, minWidth: 0 }}>
                        <Text style={[styles.deliverRowName, delivered && styles.cartNameDelivered]} numberOfLines={2}>
                          {item.product_name || 'Item'}
                        </Text>
                        <Text style={styles.meta}>Qty {item.quantity}</Text>
                      </View>
                    </Pressable>
                  );
                })
              )}
            </ScrollView>
            <Pressable onPress={() => setDeliverOpen(false)} style={styles.payBtn}>
              <Text style={styles.payBtnText}>Selesai</Text>
            </Pressable>
          </View>
        </View>
      </Modal>

      {/* Pay modal */}
      <Modal visible={payOpen} animationType="slide" transparent onRequestClose={() => setPayOpen(false)}>
        <KeyboardAvoidingView
          style={styles.modalOverlay}
          behavior={Platform.OS === 'ios' ? 'padding' : 'height'}
        >
          <ScrollView contentContainerStyle={styles.modalScroll} keyboardShouldPersistTaps="handled" bounces={false}>
            <View style={[styles.modalSheet, { paddingBottom: insets.bottom + spacing.lg }]}>
              <Text style={styles.modalTitle}>Pembayaran</Text>
              <Text style={styles.totalValue}>{formatRupiah(total)}</Text>
              <View style={styles.typeRow}>
                {(
                  [
                    { value: 'cash', label: 'Tunai' },
                    { value: 'qris', label: 'QRIS' },
                    { value: 'transfer', label: 'Transfer' },
                  ] as const
                ).map((m) => (
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
                  {payMethod === 'qris' ? (
                    <>
                      <Text style={styles.sectionLabel}>Scan QRIS</Text>
                      <View style={styles.qrisFrame}>
                        <Image source={QRIS_IMAGE} style={styles.qrisImage} resizeMode="contain" />
                      </View>
                      <Text style={styles.muted}>
                        Minta pelanggan scan kode di atas, lalu unggah bukti pembayaran.
                      </Text>
                    </>
                  ) : null}
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
          </ScrollView>
        </KeyboardAvoidingView>
      </Modal>

      {!isDesktop ? (
        <AppDrawer moduleType="kasir" visible={drawerOpen} onClose={() => setDrawerOpen(false)} />
      ) : null}
      </KeyboardAvoidingView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: '#f6f1ea' },
  shell: { flex: 1, minWidth: 0 },
  shellWithSidebar: { flexDirection: 'row' },
  posBody: { flex: 1, minWidth: 0, flexDirection: 'column', backgroundColor: '#f6f1ea' },
  mainCol: { flex: 1, minWidth: 0, backgroundColor: '#f6f1ea', position: 'relative' },
  cartCol: {
    flex: 1,
    backgroundColor: colors.white,
  },
  cartColWide: {},
  cartColMobile: { flex: 1 },
  cartScroll: { flex: 1 },
  cartScrollContent: {
    padding: spacing.md,
    gap: spacing.sm,
    paddingBottom: spacing.xl,
    flexGrow: 1,
  },
  cartItemCard: {
    flexDirection: 'row',
    gap: spacing.sm,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    padding: spacing.sm,
  },
  cartItemThumb: {
    width: 56,
    height: 56,
    borderRadius: radius.md,
    backgroundColor: colors.slate100,
  },
  cartItemThumbFallback: { alignItems: 'center', justifyContent: 'center' },
  cartItemActions: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    marginTop: 6,
  },
  cartDeleteBtn: {
    width: 28,
    height: 28,
    borderRadius: radius.sm,
    backgroundColor: colors.red50,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cartDeleteText: { color: colors.red600, fontSize: 18, ...font('700'), lineHeight: 20 },
  cartNote: { marginTop: 4, fontSize: 11, color: colors.slate500 },
  cartFooter: {
    borderTopWidth: 1,
    borderTopColor: colors.slate200,
    backgroundColor: '#f6f1ea',
    paddingHorizontal: spacing.md,
    paddingTop: spacing.md,
    gap: 6,
  },
  cartFooterAmount: { color: colors.slate800, fontSize: 13, ...font('600') },
  cartFooterActions: { flexDirection: 'row', gap: 8, marginTop: spacing.sm },
  payBtnGreen: {
    flex: 1,
    minHeight: 48,
    borderRadius: radius.md,
    backgroundColor: colors.green600,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: spacing.md,
  },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center', gap: spacing.md },
  muted: { color: colors.slate500, fontSize: 13 },
  topbar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: colors.espresso,
    borderBottomWidth: 1,
    borderBottomColor: 'rgba(255,255,255,0.08)',
    paddingHorizontal: spacing.md,
    paddingBottom: spacing.sm,
    minHeight: 56,
  },
  menuBtn: {
    width: 40,
    height: 40,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.2)',
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
  },
  menuLine: { width: 16, height: 2, borderRadius: 2, backgroundColor: colors.white },
  trxChip: {
    maxWidth: 140,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: radius.md,
    backgroundColor: 'rgba(255,255,255,0.12)',
  },
  trxChipText: { color: colors.white, fontSize: 12, ...font('700') },
  metaChip: {
    maxWidth: 120,
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: radius.full,
    backgroundColor: 'rgba(255,255,255,0.1)',
  },
  metaChipText: { color: 'rgba(255,255,255,0.92)', fontSize: 11, ...font('600') },
  draftChip: {
    paddingHorizontal: 10,
    paddingVertical: 6,
    borderRadius: radius.full,
    backgroundColor: colors.white,
  },
  draftChipText: { color: colors.espresso, fontSize: 11, ...font('700') },
  newOrderBtn: {
    paddingHorizontal: spacing.md,
    paddingVertical: 8,
    borderRadius: radius.md,
    backgroundColor: colors.white,
  },
  newOrderBtnText: { color: colors.espresso, fontSize: 12, ...font('700') },
  orderTypeBar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    backgroundColor: '#efe6da',
    borderBottomWidth: 1,
    borderBottomColor: colors.brand200,
    paddingHorizontal: spacing.md,
    paddingVertical: 10,
  },
  orderTypeBarLabel: {
    fontSize: 10,
    color: colors.slate500,
    textTransform: 'uppercase',
    letterSpacing: 0.4,
    ...font('700'),
  },
  orderTypeBarValue: { flex: 1, fontSize: 13, color: colors.slate800, ...font('600') },
  orderTypeBarCaret: { fontSize: 10, color: colors.slate500 },
  orderTypePanel: {
    backgroundColor: colors.white,
    borderBottomWidth: 1,
    borderBottomColor: colors.slate200,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.md,
    gap: 8,
  },
  typeHint: { fontSize: 10, color: colors.slate500, marginTop: 2 },
  menuHeading: {
    fontSize: 13,
    color: colors.slate700,
    ...font('700'),
    marginBottom: 6,
    letterSpacing: 0.3,
    textTransform: 'uppercase',
  },
  modalScroll: {
    flexGrow: 1,
    justifyContent: 'flex-end',
  },
  pendingBanner: {
    backgroundColor: colors.amber50,
    borderBottomWidth: 1,
    borderBottomColor: colors.amber200,
    paddingVertical: spacing.sm,
    paddingHorizontal: spacing.md,
    gap: 8,
  },
  pendingTitle: { fontSize: 12, color: colors.amber800, ...font('700') },
  pendingList: { gap: 8 },
  pendingCard: {
    backgroundColor: colors.white,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.amber200,
    padding: spacing.sm,
    width: '100%',
  },
  pendingCardOpenBill: {
    borderColor: '#93c5fd',
    backgroundColor: '#eff6ff',
  },
  pendingCardAwaitingServe: {
    borderColor: colors.green200,
    backgroundColor: colors.green50,
  },
  pendingCardCurrent: {
    borderColor: colors.brand600,
  },
  pendingCardPressed: {
    opacity: 0.92,
    borderColor: colors.brand400,
  },
  pendingCardTop: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    justifyContent: 'space-between',
    gap: spacing.sm,
  },
  pendingNo: { flex: 1, fontSize: 14, ...font('700'), color: colors.slate900 },
  pendingAmount: { fontSize: 14, ...font('700'), color: colors.brand700 },
  pendingMeta: { fontSize: 12, color: colors.slate600, marginTop: 2 },
  pendingDeliver: { fontSize: 11, color: colors.slate500, marginTop: 4, ...font('500') },
  pendingActions: { flexDirection: 'row', flexWrap: 'wrap', gap: 8, marginTop: 8 },
  pendingActionFull: { flexGrow: 1, flexBasis: '100%' },
  pendingActionHalf: { flexGrow: 1, flexBasis: '45%' },
  pendingLoad: {
    backgroundColor: colors.brand600,
    borderRadius: radius.sm,
    paddingHorizontal: 10,
    paddingVertical: 8,
    alignItems: 'center',
  },
  pendingServe: { backgroundColor: colors.green600 },
  pendingLoadText: { color: colors.white, fontSize: 12, ...font('600'), textAlign: 'center' },
  pendingCancel: {
    color: colors.red600,
    fontSize: 12,
    ...font('600'),
    paddingVertical: 8,
    textAlign: 'center',
    backgroundColor: '#fee2e2',
    borderRadius: radius.sm,
    overflow: 'hidden',
  },
  cartDeliverProgress: { fontSize: 12, color: colors.slate500, marginBottom: 4 },
  deliverOpenBtn: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    gap: spacing.sm,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.green200,
    backgroundColor: colors.green50,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    marginBottom: spacing.sm,
  },
  deliverOpenLabel: { fontSize: 14, color: colors.green700, ...font('700') },
  deliverOpenProgress: {
    borderRadius: radius.full,
    backgroundColor: colors.white,
    paddingHorizontal: 10,
    paddingVertical: 4,
    fontSize: 12,
    color: colors.green700,
    ...font('700'),
    overflow: 'hidden',
  },
  cartDeliveredTag: { fontSize: 11, color: colors.green700, ...font('600'), marginTop: 2 },
  cartCheckBox: {
    width: 22,
    height: 22,
    borderRadius: 6,
    borderWidth: 1.5,
    borderColor: colors.slate300,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: 2,
    backgroundColor: colors.white,
  },
  cartCheckBoxOn: {
    borderColor: colors.brand600,
    backgroundColor: colors.brand600,
  },
  cartCheckMark: { color: colors.white, fontSize: 13, ...font('700'), lineHeight: 15 },
  cartItemCardDelivered: { backgroundColor: '#ecfdf5' },
  cartNameDelivered: { color: colors.slate500, textDecorationLine: 'line-through' },
  pendingDeliverBtn: {
    backgroundColor: colors.green50,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.green200,
    paddingHorizontal: 10,
    paddingVertical: 8,
    alignItems: 'center',
  },
  pendingDeliverBtnText: { color: colors.green700, fontSize: 12, ...font('600'), textAlign: 'center' },
  deliverOverlay: {
    flex: 1,
    justifyContent: 'flex-end',
    backgroundColor: 'rgba(28,20,16,0.45)',
  },
  deliverSheet: {
    backgroundColor: colors.white,
    borderTopLeftRadius: radius['3xl'],
    borderTopRightRadius: radius['3xl'],
    padding: spacing.lg,
    maxHeight: '88%',
  },
  deliverHead: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.md, marginBottom: spacing.md },
  deliverEyebrow: { fontSize: 11, color: colors.green700, ...font('700'), textTransform: 'uppercase' },
  deliverTitle: { fontSize: 18, color: colors.slate900, ...font('700'), marginTop: 2 },
  deliverProgress: { fontSize: 12, color: colors.slate500, marginTop: 4 },
  deliverClose: {
    width: 36,
    height: 36,
    borderRadius: 18,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.slate100,
  },
  deliverCloseText: { fontSize: 22, color: colors.slate500, lineHeight: 24 },
  deliverList: { flexGrow: 0, marginBottom: spacing.md },
  deliverRow: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: spacing.sm,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.slate50,
    borderRadius: radius.lg,
    padding: spacing.md,
  },
  deliverRowOn: { borderColor: colors.green200, backgroundColor: colors.green50 },
  deliverRowName: { fontSize: 14, color: colors.slate900, ...font('600') },
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
  openBillHint: {
    marginHorizontal: spacing.lg,
    marginTop: spacing.sm,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.amber200,
    backgroundColor: colors.amber50,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
  },
  openBillHintText: { fontSize: 12, color: colors.amber800, lineHeight: 17, ...font('500') },
  cartContext: {
    flexDirection: 'row',
    flexWrap: 'wrap',
    gap: 8,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    backgroundColor: colors.white,
    borderBottomWidth: 1,
    borderBottomColor: colors.slate100,
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
  payActions: {
    flexDirection: 'column',
    alignItems: 'stretch',
    gap: 8,
    minWidth: 140,
  },
  holdBtn: {
    minHeight: 40,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.amber500,
    backgroundColor: colors.amber50,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: spacing.md,
  },
  holdBtnText: { color: colors.amber800, fontSize: 12, ...font('700'), textAlign: 'center' },
  search: {
    minHeight: 44,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    paddingHorizontal: spacing.md,
    color: colors.slate900,
  },
  menuPane: { flex: 1, paddingHorizontal: spacing.md, paddingTop: spacing.sm },
  catRow: { flexGrow: 0, marginTop: spacing.sm },
  catRowContent: {
    gap: 8,
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
  qrisFrame: {
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    overflow: 'hidden',
    alignItems: 'center',
  },
  qrisImage: {
    width: '100%',
    height: 320,
  },
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
