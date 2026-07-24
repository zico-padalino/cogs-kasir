import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
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
import QRCode from 'react-native-qrcode-svg';
import { kasirApi } from '@/api/kasir';
import { reportApiError } from '@/auth';
import { AppScaffold } from '@/components/AppScaffold';
import { colors, font, radius, spacing } from '@/theme';

export default function TablesScreen() {
  const router = useRouter();
  const [loading, setLoading] = useState(true);
  const [tables, setTables] = useState<
    { id: number; table_number: string; label: string; open_orders_count: number }[]
  >([]);
  const [orderUrl, setOrderUrl] = useState('');
  const [shopName, setShopName] = useState('');
  const [tableNumber, setTableNumber] = useState('');
  const [label, setLabel] = useState('');
  const [saving, setSaving] = useState(false);

  const refresh = useCallback(async () => {
    setLoading(true);
    try {
      const res = await kasirApi.tables();
      setTables(res.data.tables);
      setOrderUrl(res.data.order_url);
      setShopName(res.data.shop_name);
    } catch (err) {
      reportApiError(err);
    } finally {
      setLoading(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      void refresh();
    }, [refresh]),
  );

  const addTable = async () => {
    if (!tableNumber.trim() || !label.trim()) {
      Alert.alert('Lengkapi data', 'Nomor meja dan label wajib.');
      return;
    }
    setSaving(true);
    try {
      await kasirApi.createTable({ table_number: tableNumber.trim(), label: label.trim() });
      setTableNumber('');
      setLabel('');
      await refresh();
    } catch (err) {
      reportApiError(err);
    } finally {
      setSaving(false);
    }
  };

  const openCustomerMenu = async () => {
    if (!orderUrl) return;
    try {
      await Linking.openURL(orderUrl);
    } catch {
      Alert.alert('Gagal', 'Tidak bisa membuka menu pelanggan.');
    }
  };

  return (
    <AppScaffold moduleType="kasir" title="Meja & Barcode" subtitle={shopName || 'Pesanan online'}>
      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.brand600} />
        </View>
      ) : (
        <ScrollView contentContainerStyle={{ padding: spacing.lg, gap: spacing.lg, paddingBottom: spacing.xxl }}>
          <View style={styles.toolbar}>
            <Text style={styles.toolbarHint}>
              Satu barcode untuk seluruh toko. Pelanggan scan → isi nama → pesan → bayar di kasir.
            </Text>
            <Pressable
              onPress={() =>
                router.push(
                  `/kasir/barcode?url=${encodeURIComponent(orderUrl)}&shop=${encodeURIComponent(shopName)}` as never,
                )
              }
              style={styles.primaryBtn}
            >
              <Text style={styles.primaryBtnText}>Cetak Barcode</Text>
            </Pressable>
          </View>

          <View style={styles.card}>
            <Text style={styles.title}>Barcode Pesanan</Text>
            <Text style={styles.meta}>{shopName} · satu QR untuk semua meja</Text>
            <Text style={styles.url}>{orderUrl}</Text>
            {orderUrl ? (
              <View style={styles.qrWrap}>
                <QRCode value={orderUrl} size={180} />
              </View>
            ) : null}
            <Pressable onPress={() => void openCustomerMenu()} style={styles.outlineBtn}>
              <Text style={styles.outlineBtnText}>Buka Menu Pelanggan</Text>
            </Pressable>
            <Text style={styles.hint}>
              Tempel di kasir atau pintu masuk. Tiap HP mendapat nomor pesanan & nama pemesan sendiri.
            </Text>
          </View>

          <View style={styles.card}>
            <Text style={styles.title}>Tambah Meja</Text>
            <TextInput
              value={tableNumber}
              onChangeText={setTableNumber}
              placeholder="Nomor meja"
              placeholderTextColor={colors.slate400}
              style={styles.input}
            />
            <TextInput
              value={label}
              onChangeText={setLabel}
              placeholder="Label"
              placeholderTextColor={colors.slate400}
              style={styles.input}
            />
            <Pressable onPress={addTable} disabled={saving} style={styles.primaryBtn}>
              <Text style={styles.primaryBtnText}>{saving ? 'Menyimpan…' : 'Simpan Meja'}</Text>
            </Pressable>
          </View>

          <View style={styles.card}>
            <Text style={styles.title}>Daftar Meja</Text>
            {tables.length === 0 ? (
              <Text style={styles.meta}>Belum ada meja. Tambahkan untuk pelacakan internal kasir (opsional).</Text>
            ) : (
              tables.map((table) => (
                <View key={table.id} style={styles.tableRow}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.tableNo}>{table.label}</Text>
                    <Text style={styles.meta}>Meja #{table.table_number}</Text>
                  </View>
                  <View style={[styles.badge, table.open_orders_count > 0 ? styles.badgeAmber : styles.badgeGreen]}>
                    <Text style={styles.badgeText}>
                      {table.open_orders_count > 0 ? `${table.open_orders_count} pesanan aktif` : 'Kosong'}
                    </Text>
                  </View>
                </View>
              ))
            )}
          </View>
        </ScrollView>
      )}
    </AppScaffold>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  toolbar: { gap: spacing.sm },
  toolbarHint: { fontSize: 13, color: colors.slate500, lineHeight: 18 },
  card: {
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.md,
    gap: spacing.sm,
  },
  title: { fontSize: 16, color: colors.slate900, ...font('700') },
  meta: { fontSize: 12, color: colors.slate500 },
  url: { fontSize: 11, color: colors.slate500 },
  hint: { fontSize: 13, color: colors.slate600, lineHeight: 18 },
  qrWrap: { alignItems: 'center', paddingVertical: spacing.md },
  input: {
    borderWidth: 1,
    borderColor: colors.slate200,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    paddingVertical: 10,
    backgroundColor: colors.white,
    color: colors.slate900,
  },
  primaryBtn: {
    minHeight: 44,
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: spacing.md,
  },
  primaryBtnText: { color: colors.white, ...font('700') },
  outlineBtn: {
    minHeight: 42,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate300,
    alignItems: 'center',
    justifyContent: 'center',
  },
  outlineBtnText: { color: colors.brand700, ...font('700') },
  tableRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    paddingVertical: spacing.sm,
    borderTopWidth: 1,
    borderTopColor: colors.slate100,
  },
  tableNo: { fontSize: 14, color: colors.slate900, ...font('700') },
  badge: {
    borderRadius: radius.full,
    paddingHorizontal: spacing.sm,
    paddingVertical: 4,
  },
  badgeAmber: { backgroundColor: '#fef3c7' },
  badgeGreen: { backgroundColor: '#dcfce7' },
  badgeText: { fontSize: 11, color: colors.slate700, ...font('600') },
});
