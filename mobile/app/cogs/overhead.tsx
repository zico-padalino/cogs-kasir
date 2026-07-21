import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { AppScaffold } from '@/components/AppScaffold';
import {
  Card,
  EmptyState,
  Field,
  Input,
  PrimaryButton,
  SectionTitle,
  Segmented,
  StepHeader,
} from '@/components/cogs-ui';
import { formatQty, formatRupiah, parseNumber } from '@/cogs/format';
import {
  createOverheadRate,
  deleteOverheadRate,
  listOverheadRates,
  toggleOverheadRate,
} from '@/cogs/repo';
import { ALLOCATION_BASE_LABEL, type AllocationBase, type OverheadRate } from '@/cogs/types';
import { colors, spacing } from '@/theme';

const BASE_OPTIONS: { value: AllocationBase; label: string }[] = [
  { value: 'direct_material', label: 'Bahan Baku' },
  { value: 'labor_hours', label: 'Jam Kerja' },
  { value: 'machine_hours', label: 'Jam Mesin' },
  { value: 'direct_labor', label: 'Upah' },
  { value: 'units_produced', label: 'Unit' },
];

export default function OverheadScreen() {
  const insets = useSafeAreaInsets();
  const [rates, setRates] = useState<OverheadRate[]>([]);
  const [name, setName] = useState('');
  const [base, setBase] = useState<AllocationBase>('direct_material');
  const [rate, setRate] = useState('');
  const [description, setDescription] = useState('');

  const refresh = useCallback(async () => {
    setRates(await listOverheadRates());
  }, []);

  useFocusEffect(
    useCallback(() => {
      refresh();
    }, [refresh]),
  );

  const handleAdd = async () => {
    if (!name.trim()) {
      Alert.alert('Lengkapi', 'Nama tarif overhead wajib diisi.');
      return;
    }

    await createOverheadRate({
      name: name.trim(),
      allocation_base: base,
      rate: parseNumber(rate),
      description: description.trim() || null,
    });

    setName('');
    setRate('');
    setDescription('');
    setBase('direct_material');
    await refresh();
  };

  const handleDelete = (id: number) => {
    Alert.alert('Hapus tarif?', 'Tarif overhead ini akan dihapus.', [
      { text: 'Batal', style: 'cancel' },
      {
        text: 'Hapus',
        style: 'destructive',
        onPress: async () => {
          await deleteOverheadRate(id);
          await refresh();
        },
      },
    ]);
  };

  return (
    <AppScaffold moduleType="cogs" title="Biaya Overhead" subtitle="Langkah 1 dari 6">
      <ScrollView
        contentContainerStyle={{
          padding: spacing.lg,
          paddingBottom: insets.bottom + spacing.xxl,
          gap: spacing.lg,
        }}
      >
        <StepHeader
          number={1}
          title="Biaya Overhead"
          description="Tarif biaya tidak langsung produksi, mis. 15% dari bahan atau Rp per jam."
        />
        <Card>
          <SectionTitle>Tambah Tarif</SectionTitle>
          <Field label="Nama tarif">
            <Input value={name} onChangeText={setName} placeholder="Contoh: Listrik & sewa" />
          </Field>
          <Field label="Dihitung dari">
            <Segmented options={BASE_OPTIONS} value={base} onChange={setBase} />
          </Field>
          <Field
            label="Nilai"
            hint="Untuk % dari bahan isi desimal (0.15 = 15%). Untuk per jam isi Rupiah (25000)."
          >
            <Input value={rate} onChangeText={setRate} keyboardType="numeric" placeholder="0.15 atau 25000" />
          </Field>
          <Field label="Keterangan (opsional)">
            <Input value={description} onChangeText={setDescription} placeholder="Catatan singkat" />
          </Field>
          <PrimaryButton label="Simpan Tarif" onPress={handleAdd} />
        </Card>

        <View style={{ gap: spacing.sm }}>
          <SectionTitle>Daftar Tarif ({rates.length})</SectionTitle>
          {rates.length === 0 ? (
            <Card>
              <EmptyState icon="⚙️" title="Belum ada tarif" hint="Tambahkan minimal satu tarif overhead." />
            </Card>
          ) : (
            rates.map((item) => (
              <Card key={item.id} style={styles.rateCard}>
                <View style={styles.rateHead}>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.rateName}>{item.name}</Text>
                    <Text style={styles.rateBase}>{ALLOCATION_BASE_LABEL[item.allocation_base]}</Text>
                    {item.description ? <Text style={styles.rateDesc}>{item.description}</Text> : null}
                  </View>
                  <Text style={styles.rateValue}>
                    {item.allocation_base === 'direct_material' || item.allocation_base === 'direct_labor'
                      ? `${formatQty(item.rate)}×`
                      : formatRupiah(item.rate)}
                  </Text>
                </View>
                <View style={styles.rateFoot}>
                  <View style={styles.switchRow}>
                    <Switch
                      value={item.is_active === 1}
                      onValueChange={(value) => toggleOverheadRate(item.id, value).then(refresh)}
                      trackColor={{ true: colors.brand600, false: colors.slate200 }}
                    />
                    <Text style={styles.switchLabel}>{item.is_active === 1 ? 'Aktif' : 'Nonaktif'}</Text>
                  </View>
                  <Pressable onPress={() => handleDelete(item.id)}>
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
  rateCard: { gap: spacing.sm },
  rateHead: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.md },
  rateName: { fontSize: 15, fontWeight: '700', color: colors.slate900 },
  rateBase: { fontSize: 12, color: colors.slate500, marginTop: 2 },
  rateDesc: { fontSize: 12, color: colors.slate500, marginTop: 4 },
  rateValue: { fontSize: 15, fontWeight: '800', color: colors.brand600 },
  rateFoot: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderTopWidth: 1,
    borderTopColor: colors.slate100,
    paddingTop: spacing.sm,
  },
  switchRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  switchLabel: { fontSize: 13, color: colors.slate600 },
  deleteText: { fontSize: 13, fontWeight: '700', color: '#dc2626' },
});
