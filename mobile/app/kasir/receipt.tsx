import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Linking,
  Pressable,
  ScrollView,
  Share,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { kasirApi } from '@/api/kasir';
import type { PosOrder } from '@/api/types';
import { colors, font, radius, spacing } from '@/theme';
import { formatRupiah } from '@/utils/rupiah';
import { useSafeAreaInsets } from 'react-native-safe-area-context';

export default function ReceiptScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [order, setOrder] = useState<PosOrder | null>(null);
  const [pdfUrl, setPdfUrl] = useState<string | null>(null);
  const [waMessage, setWaMessage] = useState('');
  const [shopName, setShopName] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      try {
        const res = await kasirApi.receipt(Number(id));
        setOrder(res.data.order);
        setPdfUrl(res.data.pdf_url);
        setWaMessage(res.data.wa_message);
        setShopName(res.data.shop_name);
      } catch {
        // PIN_LOCKED → redirect global
      } finally {
        setLoading(false);
      }
    })();
  }, [id]);

  if (loading || !order) {
    return (
      <View style={[styles.center, { paddingTop: insets.top }]}>
        <ActivityIndicator color={colors.brand600} />
      </View>
    );
  }

  return (
    <View style={[styles.root, { paddingTop: insets.top + spacing.md, paddingBottom: insets.bottom + spacing.lg }]}>
      <ScrollView contentContainerStyle={{ padding: spacing.lg, gap: spacing.md }}>
        <View style={styles.successBadge}>
          <Text style={styles.successText}>Pembayaran berhasil</Text>
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

        {pdfUrl ? (
          <Pressable onPress={() => Linking.openURL(pdfUrl)} style={styles.outlineBtn}>
            <Text style={styles.outlineText}>Buka PDF Struk</Text>
          </Pressable>
        ) : null}

        <Pressable
          onPress={() => Share.share({ message: waMessage || `Struk #${order.order_number}` })}
          style={styles.outlineBtn}
        >
          <Text style={styles.outlineText}>Bagikan / WhatsApp</Text>
        </Pressable>

        <Pressable onPress={() => router.replace('/kasir')} style={styles.primaryBtn}>
          <Text style={styles.primaryText}>Kembali ke POS</Text>
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
});
