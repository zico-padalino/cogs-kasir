import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import QRCode from 'react-native-qrcode-svg';
import { kasirApi } from '@/api/kasir';
import { asApiError, useAuth } from '@/auth';
import { AppScaffold } from '@/components/AppScaffold';
import { colors, font, radius, spacing } from '@/theme';
import { useRouter } from 'expo-router';

export default function TablesScreen() {
  const router = useRouter();
  const { setPin } = useAuth();
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
      const apiErr = asApiError(err);
      if (apiErr.status === 423) {
        setPin({ unlocked: false, expires_at: null, server_now: 0, remaining_seconds: 0 });
        router.replace('/kasir/pin' as never);
      }
    } finally {
      setLoading(false);
    }
  }, [router, setPin]);

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
      Alert.alert('Gagal', asApiError(err).message);
    } finally {
      setSaving(false);
    }
  };

  return (
    <AppScaffold moduleType="kasir" title="Meja QR" subtitle={shopName || 'Pesanan online'}>
      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.brand600} />
        </View>
      ) : (
        <ScrollView contentContainerStyle={{ padding: spacing.lg, gap: spacing.lg }}>
          <View style={styles.card}>
            <Text style={styles.title}>QR Pesan Online</Text>
            <Text style={styles.meta}>{orderUrl}</Text>
            {orderUrl ? (
              <View style={styles.qrWrap}>
                <QRCode value={orderUrl} size={180} />
              </View>
            ) : null}
          </View>

          <View style={styles.card}>
            <Text style={styles.title}>Tambah Meja</Text>
            <TextInput
              value={tableNumber}
              onChangeText={setTableNumber}
              placeholder="Nomor meja"
              style={styles.input}
            />
            <TextInput value={label} onChangeText={setLabel} placeholder="Label" style={styles.input} />
            <Pressable onPress={addTable} disabled={saving} style={styles.btn}>
              <Text style={styles.btnText}>{saving ? 'Menyimpan…' : 'Tambah'}</Text>
            </Pressable>
          </View>

          {tables.map((table) => (
            <View key={table.id} style={styles.card}>
              <Text style={styles.tableNo}>
                Meja {table.table_number} — {table.label}
              </Text>
              <Text style={styles.meta}>Order terbuka: {table.open_orders_count}</Text>
            </View>
          ))}
        </ScrollView>
      )}
    </AppScaffold>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  card: {
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.lg,
    gap: spacing.sm,
  },
  title: { fontSize: 16, color: colors.slate900, ...font('700') },
  meta: { fontSize: 12, color: colors.slate500 },
  qrWrap: { alignItems: 'center', paddingVertical: spacing.md },
  input: {
    minHeight: 44,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate300,
    paddingHorizontal: spacing.md,
    color: colors.slate900,
  },
  btn: {
    minHeight: 44,
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  btnText: { color: colors.white, ...font('700') },
  tableNo: { fontSize: 15, ...font('600'), color: colors.slate900 },
});
