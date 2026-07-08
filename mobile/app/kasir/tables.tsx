import { useFocusEffect } from 'expo-router';
import * as Linking from 'expo-linking';
import { useCallback, useMemo, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import QRCode from 'react-native-qrcode-svg';
import { AppScaffold } from '@/components/AppScaffold';
import {
  Badge,
  Callout,
  Card,
  EmptyState,
  Field,
  Input,
  PrimaryButton,
  SectionTitle,
} from '@/components/cogs-ui';
import { createTable, deleteTable, listTables, toggleTable } from '@/local-db/repository';
import type { LocalTable } from '@/local-db/types';
import { colors, font, spacing } from '@/theme';

export default function KasirTablesScreen() {
  const insets = useSafeAreaInsets();
  const [tables, setTables] = useState<LocalTable[]>([]);
  const [tableNumber, setTableNumber] = useState('');
  const [label, setLabel] = useState('');

  const orderUrl = useMemo(() => Linking.createURL('/pesan-online'), []);

  const refresh = useCallback(async () => {
    setTables(await listTables());
  }, []);

  useFocusEffect(
    useCallback(() => {
      refresh();
    }, [refresh]),
  );

  const handleAdd = async () => {
    if (!tableNumber.trim()) {
      Alert.alert('Lengkapi', 'Nomor meja wajib diisi.');
      return;
    }

    await createTable({ table_number: tableNumber.trim(), label: label.trim() });
    setTableNumber('');
    setLabel('');
    await refresh();
  };

  const handleDelete = (table: LocalTable) => {
    Alert.alert('Hapus meja?', `"${table.label}" akan dihapus.`, [
      { text: 'Batal', style: 'cancel' },
      {
        text: 'Hapus',
        style: 'destructive',
        onPress: async () => {
          await deleteTable(table.id);
          await refresh();
        },
      },
    ]);
  };

  return (
    <AppScaffold moduleType="kasir" title="Meja QR" subtitle="Kelola meja & QR pesan online">
      <ScrollView
        contentContainerStyle={{
          padding: spacing.lg,
          paddingBottom: insets.bottom + spacing.xxl,
          gap: spacing.lg,
        }}
      >
        <Card>
          <SectionTitle>QR Pesan Online</SectionTitle>
          <View style={styles.qrWrap}>
            <View style={styles.qrBox}>
              <QRCode value={orderUrl} size={190} color={colors.slate900} backgroundColor={colors.white} />
            </View>
          </View>
          <Text style={styles.qrHint}>
            Tempel QR ini di meja atau kasir. Pelanggan scan untuk membuka layar Pesan Online.
          </Text>
          <View style={styles.urlBox}>
            <Text style={styles.urlText} numberOfLines={1}>
              {orderUrl}
            </Text>
          </View>
        </Card>

        <Card>
          <SectionTitle>Tambah Meja</SectionTitle>
          <View style={styles.row}>
            <View style={{ width: 110 }}>
              <Field label="No. Meja">
                <Input value={tableNumber} onChangeText={setTableNumber} placeholder="1" />
              </Field>
            </View>
            <View style={{ flex: 1 }}>
              <Field label="Label">
                <Input value={label} onChangeText={setLabel} placeholder="Meja 1" />
              </Field>
            </View>
          </View>
          <PrimaryButton label="Tambah Meja" onPress={handleAdd} />
        </Card>

        <Callout tone="info">
          Meja bersifat pencatatan internal. Saat transaksi Dine In di kasir, pilih meja untuk melacak
          pesanan aktif.
        </Callout>

        <View style={{ gap: spacing.sm }}>
          <SectionTitle>Daftar Meja ({tables.length})</SectionTitle>
          {tables.length === 0 ? (
            <Card>
              <EmptyState icon="🪑" title="Belum ada meja" hint="Tambahkan meja pertama di atas." />
            </Card>
          ) : (
            tables.map((table) => (
              <Card key={table.id} style={styles.tableCard}>
                <View style={styles.tableHead}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.tableLabel}>{table.label}</Text>
                    <Text style={styles.tableNumber}>No. {table.table_number}</Text>
                  </View>
                  <Badge
                    label={table.open_orders > 0 ? `${table.open_orders} pesanan aktif` : 'Kosong'}
                    tone={table.open_orders > 0 ? 'amber' : 'slate'}
                  />
                </View>
                <View style={styles.tableFoot}>
                  <View style={styles.switchRow}>
                    <Switch
                      value={table.is_active === 1}
                      onValueChange={(value) => toggleTable(table.id, value).then(refresh)}
                      trackColor={{ true: colors.brand600, false: colors.slate200 }}
                    />
                    <Text style={styles.switchLabel}>{table.is_active === 1 ? 'Aktif' : 'Nonaktif'}</Text>
                  </View>
                  <Pressable onPress={() => handleDelete(table)} hitSlop={8}>
                    <Text style={styles.deleteText}>Hapus</Text>
                  </Pressable>
                </View>
              </Card>
            ))
          )}
        </View>
      </ScrollView>
    </AppScaffold>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.slate100 },
  qrWrap: { alignItems: 'center' },
  qrBox: {
    padding: spacing.lg,
    borderRadius: 16,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
  },
  qrHint: { fontSize: 13, color: colors.slate600, textAlign: 'center', lineHeight: 18 },
  urlBox: {
    borderRadius: 8,
    backgroundColor: colors.slate50,
    borderWidth: 1,
    borderColor: colors.slate200,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
  },
  urlText: { fontSize: 12, color: colors.slate600, fontFamily: 'monospace' },
  row: { flexDirection: 'row', gap: spacing.md },
  tableCard: { gap: spacing.sm },
  tableHead: { flexDirection: 'row', alignItems: 'center', gap: spacing.md },
  tableLabel: { fontSize: 15, color: colors.slate900, ...font('700') },
  tableNumber: { fontSize: 12, color: colors.slate500, marginTop: 2 },
  tableFoot: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderTopWidth: 1,
    borderTopColor: colors.slate100,
    paddingTop: spacing.sm,
  },
  switchRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  switchLabel: { fontSize: 13, color: colors.slate600 },
  deleteText: { fontSize: 13, color: colors.red600, ...font('700') },
});
