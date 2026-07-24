import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Linking,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { kasirApi } from '@/api/kasir';
import type { PosOrder } from '@/api/types';
import {
  getThermalPaper,
  printThermalViaRawBt,
  setThermalPaper,
  type ThermalPaper,
  type ThermalPayload,
} from '@/kasir/thermalPrint';
import { colors, font, radius, spacing } from '@/theme';
import { formatRupiah } from '@/utils/rupiah';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

function normalizePhone(raw: string): string {
  let digits = String(raw || '').replace(/\D+/g, '');
  if (digits.startsWith('0')) {
    digits = `62${digits.slice(1)}`;
  } else if (digits.startsWith('8') && digits.length >= 9) {
    digits = `62${digits}`;
  }
  return digits;
}

function isValidWaPhone(phone: string): boolean {
  return /^62\d{8,15}$/.test(phone);
}

export default function ReceiptScreen() {
  const { id, from } = useLocalSearchParams<{ id: string; from?: string }>();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const fromHistory = from === 'history';
  const [order, setOrder] = useState<PosOrder | null>(null);
  const [pdfUrl, setPdfUrl] = useState<string | null>(null);
  const [waMessage, setWaMessage] = useState('');
  const [shopName, setShopName] = useState('');
  const [thermal, setThermal] = useState<ThermalPayload | null>(null);
  const [paper, setPaper] = useState<ThermalPaper>('58mm');
  const [printing, setPrinting] = useState(false);
  const [loading, setLoading] = useState(true);
  const [waOpen, setWaOpen] = useState(false);
  const [waPhone, setWaPhone] = useState('');
  const [waError, setWaError] = useState('');
  const [waSending, setWaSending] = useState(false);

  const loadReceipt = async (paperSize: ThermalPaper) => {
    const res = await kasirApi.receipt(Number(id), paperSize);
    setOrder(res.data.order);
    setPdfUrl(res.data.pdf_url);
    setWaMessage(res.data.wa_message);
    setShopName(res.data.shop_name);
    setThermal(res.data.thermal ?? null);
  };

  useEffect(() => {
    (async () => {
      try {
        const saved = await getThermalPaper();
        setPaper(saved);
        await loadReceipt(saved);
      } catch {
        // PIN_LOCKED → redirect global
      } finally {
        setLoading(false);
      }
    })();
  }, [id]);

  const onChangePaper = async (next: ThermalPaper) => {
    setPaper(next);
    await setThermalPaper(next);
    try {
      await loadReceipt(next);
    } catch {
      Alert.alert('Gagal', 'Tidak bisa memuat data thermal untuk ukuran kertas ini.');
    }
  };

  const onPrintThermal = async () => {
    if (!thermal?.base64) {
      Alert.alert('Belum siap', 'Data cetak thermal belum tersedia.');
      return;
    }
    setPrinting(true);
    try {
      const result = await printThermalViaRawBt(thermal);
      if (result === 'store') {
        Alert.alert(
          'Pasang RawBT',
          'Untuk cetak ke printer Ainuo, pasang aplikasi RawBT, pair printer di Bluetooth, lalu coba lagi.',
        );
      } else if (result === 'failed') {
        Alert.alert('Gagal cetak', 'Tidak bisa membuka RawBT. Pastikan aplikasi RawBT terpasang.');
      }
    } finally {
      setPrinting(false);
    }
  };

  const onSendWhatsApp = async () => {
    const phone = normalizePhone(waPhone);
    if (!isValidWaPhone(phone)) {
      setWaError('Nomor WhatsApp tidak valid. Pakai format 08xxxxxxxxxx.');
      return;
    }

    const message = waMessage || `Struk #${order?.order_number ?? ''}`;
    if (!message.trim()) {
      setWaError('Pesan WhatsApp belum siap.');
      return;
    }

    setWaError('');
    setWaSending(true);
    try {
      const url = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
      const canOpen = await Linking.canOpenURL(url);
      if (!canOpen) {
        setWaError('Tidak bisa membuka WhatsApp di perangkat ini.');
        return;
      }
      await Linking.openURL(url);
    } catch {
      setWaError('Gagal membuka WhatsApp. Pastikan aplikasi WA terpasang.');
    } finally {
      setWaSending(false);
    }
  };

  if (loading || !order) {
    return (
      <View style={[styles.center, { paddingTop: insets.top }]}>
        <ActivityIndicator color={colors.brand600} />
      </View>
    );
  }

  return (
    <View style={[styles.root, { paddingTop: insets.top + spacing.md, paddingBottom: insets.bottom + spacing.lg }]}>
      <ScrollView contentContainerStyle={{ padding: spacing.lg, gap: spacing.md }} keyboardShouldPersistTaps="handled">
        <View style={styles.successBadge}>
          <Text style={styles.successText}>{fromHistory ? 'Struk Pesanan' : 'Pembayaran berhasil'}</Text>
        </View>
        <Text style={styles.shop}>{shopName}</Text>
        <Text style={styles.orderNo}>#{order.order_number}</Text>
        <Text style={styles.meta}>
          {order.cashier_name} · {order.payment_method_label}
        </Text>

        <View style={styles.card}>
          {(order.items || []).map((item) => (
            <View key={item.id} style={styles.row}>
              <View style={{ flex: 1 }}>
                <Text style={styles.itemName}>
                  {item.quantity}× {item.product_name}
                </Text>
                {item.notes ? <Text style={styles.meta}>{item.notes}</Text> : null}
              </View>
              <Text style={styles.itemTotal}>{formatRupiah(item.line_total)}</Text>
            </View>
          ))}
          <View style={styles.divider} />
          <View style={styles.row}>
            <Text style={styles.meta}>Subtotal</Text>
            <Text>{formatRupiah(order.subtotal)}</Text>
          </View>
          {order.discount_amount > 0 ? (
            <View style={styles.row}>
              <Text style={styles.meta}>Diskon</Text>
              <Text>- {formatRupiah(order.discount_amount)}</Text>
            </View>
          ) : null}
          <View style={styles.row}>
            <Text style={styles.totalLabel}>Total</Text>
            <Text style={styles.total}>{formatRupiah(order.total)}</Text>
          </View>
          {order.change_amount != null && order.change_amount > 0 ? (
            <View style={styles.row}>
              <Text style={styles.meta}>Kembalian</Text>
              <Text>{formatRupiah(order.change_amount)}</Text>
            </View>
          ) : null}
        </View>

        <View style={styles.paperRow}>
          <Pressable
            onPress={() => onChangePaper('58mm')}
            style={[styles.paperChip, paper === '58mm' && styles.paperChipActive]}
          >
            <Text style={[styles.paperChipText, paper === '58mm' && styles.paperChipTextActive]}>58mm</Text>
          </Pressable>
          <Pressable
            onPress={() => onChangePaper('80mm')}
            style={[styles.paperChip, paper === '80mm' && styles.paperChipActive]}
          >
            <Text style={[styles.paperChipText, paper === '80mm' && styles.paperChipTextActive]}>80mm</Text>
          </Pressable>
        </View>

        <Pressable onPress={onPrintThermal} disabled={printing} style={styles.primaryBtn}>
          <Text style={styles.primaryText}>{printing ? 'Membuka printer…' : 'Cetak Thermal (Ainuo)'}</Text>
        </Pressable>
        <Text style={styles.hint}>Pair printer Ainuo di Bluetooth, lalu pasang RawBT sebagai jembatan cetak.</Text>

        {pdfUrl ? (
          <Pressable onPress={() => Linking.openURL(pdfUrl)} style={styles.outlineBtn}>
            <Text style={styles.outlineText}>Cetak PDF</Text>
          </Pressable>
        ) : null}

        {!waOpen ? (
          <Pressable
            onPress={() => {
              setWaOpen(true);
              setWaError('');
            }}
            style={styles.outlineBtn}
          >
            <Text style={styles.outlineText}>Kirim WhatsApp</Text>
          </Pressable>
        ) : (
          <View style={styles.waPanel}>
            <Text style={styles.waLabel}>Nomor WhatsApp pelanggan</Text>
            <TextInput
              value={waPhone}
              onChangeText={(value) => {
                setWaPhone(value);
                if (waError) setWaError('');
              }}
              placeholder="08xxxxxxxxxx"
              placeholderTextColor={colors.slate400}
              keyboardType="phone-pad"
              autoComplete="tel"
              textContentType="telephoneNumber"
              style={styles.waInput}
              returnKeyType="send"
              onSubmitEditing={onSendWhatsApp}
            />
            <Text style={styles.waHint}>Chat WhatsApp langsung dibuka ke nomor ini dengan tautan PDF struk.</Text>
            {waError ? <Text style={styles.waError}>{waError}</Text> : null}
            <View style={styles.waActions}>
              <Pressable
                onPress={onSendWhatsApp}
                disabled={waSending}
                style={[styles.primaryBtn, styles.waActionBtn, waSending && styles.btnDisabled]}
              >
                <Text style={styles.primaryText}>{waSending ? 'Membuka WA…' : 'Kirim Sekarang'}</Text>
              </Pressable>
              <Pressable
                onPress={() => {
                  setWaOpen(false);
                  setWaError('');
                }}
                style={[styles.outlineBtn, styles.waActionBtn]}
              >
                <Text style={styles.outlineText}>Batal</Text>
              </Pressable>
            </View>
          </View>
        )}

        <Pressable
          onPress={() => router.replace((fromHistory ? '/kasir/orders' : '/kasir') as never)}
          style={styles.outlineBtn}
        >
          <Text style={styles.outlineText}>{fromHistory ? 'Kembali ke Riwayat' : 'POS Baru'}</Text>
        </Pressable>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.slate100 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  successBadge: {
    alignSelf: 'center',
    backgroundColor: colors.green50,
    borderColor: colors.green200,
    borderWidth: 1,
    borderRadius: radius.full,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
  },
  successText: { color: colors.green700, ...font('700'), fontSize: 13 },
  shop: { textAlign: 'center', fontSize: 18, color: colors.slate900, ...font('700') },
  orderNo: { textAlign: 'center', fontSize: 22, color: colors.brand700, ...font('700') },
  meta: { fontSize: 12, color: colors.slate500, textAlign: 'center' },
  card: {
    backgroundColor: colors.white,
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.lg,
    gap: spacing.sm,
  },
  row: { flexDirection: 'row', justifyContent: 'space-between', gap: spacing.md },
  itemName: { fontSize: 14, color: colors.slate900, ...font('500') },
  itemTotal: { fontSize: 13, ...font('600'), color: colors.slate800 },
  divider: { height: 1, backgroundColor: colors.slate200, marginVertical: spacing.sm },
  totalLabel: { fontSize: 15, ...font('700') },
  total: { fontSize: 16, color: colors.brand700, ...font('700') },
  paperRow: { flexDirection: 'row', gap: spacing.sm },
  paperChip: {
    flex: 1,
    minHeight: 44,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate300,
    backgroundColor: colors.white,
    alignItems: 'center',
    justifyContent: 'center',
  },
  paperChipActive: { borderColor: colors.brand600, backgroundColor: colors.brand50 },
  paperChipText: { color: colors.slate700, ...font('600') },
  paperChipTextActive: { color: colors.brand700 },
  hint: { fontSize: 12, color: colors.slate500, textAlign: 'center' },
  outlineBtn: {
    minHeight: 48,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate300,
    backgroundColor: colors.white,
    alignItems: 'center',
    justifyContent: 'center',
  },
  outlineText: { color: colors.slate700, ...font('600') },
  primaryBtn: {
    minHeight: 52,
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  primaryText: { color: colors.white, ...font('700') },
  btnDisabled: { opacity: 0.7 },
  waPanel: {
    backgroundColor: colors.white,
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.lg,
    gap: spacing.sm,
  },
  waLabel: { fontSize: 13, color: colors.slate700, ...font('600') },
  waInput: {
    minHeight: 48,
    borderWidth: 1,
    borderColor: colors.slate300,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    fontSize: 16,
    color: colors.slate900,
    backgroundColor: colors.cream,
  },
  waHint: { fontSize: 12, color: colors.slate500 },
  waError: { fontSize: 13, color: colors.red600 },
  waActions: { gap: spacing.sm, marginTop: spacing.xs },
  waActionBtn: { width: '100%' },
});
