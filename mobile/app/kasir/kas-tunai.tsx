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
import { kasirApi } from '@/api/kasir';
import { reportApiError } from '@/auth';
import { AppScaffold } from '@/components/AppScaffold';
import { colors, font, radius, spacing } from '@/theme';
import { formatRupiah, formatRupiahInput, parseRupiahInput } from '@/utils/rupiah';

function todayIso(): string {
  const d = new Date();
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

const TYPE_LABEL: Record<string, string> = {
  float_in: 'Setoran',
  sale_in: 'Penjualan tunai',
  change_out: 'Kembalian',
  expense: 'Pengeluaran',
};

export default function KasTunaiScreen() {
  const [loading, setLoading] = useState(true);
  const [date, setDate] = useState(todayIso());
  const [report, setReport] = useState<Record<string, unknown> | null>(null);
  const [amount, setAmount] = useState('');
  const [note, setNote] = useState('');
  const [mode, setMode] = useState<'float' | 'expense'>('float');
  const [saving, setSaving] = useState(false);

  const refresh = useCallback(async (forDate?: string) => {
    setLoading(true);
    try {
      const res = await kasirApi.kasTunai(forDate || date);
      setReport(res.data);
      if (typeof res.data.date === 'string') {
        setDate(res.data.date);
      }
    } catch (err) {
      reportApiError(err);
    } finally {
      setLoading(false);
    }
  }, [date]);

  useFocusEffect(
    useCallback(() => {
      void refresh();
    }, [refresh]),
  );

  const submit = async () => {
    const value = parseRupiahInput(amount);
    if (value <= 0 || !note.trim()) {
      Alert.alert('Lengkapi data', 'Nominal dan keterangan wajib.');
      return;
    }
    setSaving(true);
    try {
      if (mode === 'float') {
        await kasirApi.kasFloat({ amount: value, note: note.trim() });
      } else {
        await kasirApi.kasExpense({ amount: value, note: note.trim() });
      }
      setAmount('');
      setNote('');
      await refresh();
    } catch (err) {
      reportApiError(err);
    } finally {
      setSaving(false);
    }
  };

  const entries =
    (report?.entries as { id: number; type: string; amount: number; note: string; occurred_at?: string }[]) ||
    [];
  const isToday = date === todayIso();

  return (
    <AppScaffold moduleType="kasir" title="Kas Tunai" subtitle="Setoran & pengeluaran">
      <View style={styles.filterCard}>
        <Text style={styles.filterLabel}>Tanggal (YYYY-MM-DD)</Text>
        <TextInput value={date} onChangeText={setDate} style={styles.input} placeholder={todayIso()} />
        <View style={styles.filterActions}>
          <Pressable onPress={() => void refresh(date)} style={styles.primaryBtn}>
            <Text style={styles.primaryBtnText}>Tampilkan</Text>
          </Pressable>
          {!isToday ? (
            <Pressable
              onPress={() => {
                const t = todayIso();
                setDate(t);
                void refresh(t);
              }}
              style={styles.outlineBtn}
            >
              <Text style={styles.outlineBtnText}>Hari ini</Text>
            </Pressable>
          ) : null}
        </View>
        <Text style={styles.balanceHint}>
          Saldo sekarang:{' '}
          <Text style={styles.balanceValue}>{formatRupiah(Number(report?.balance || 0))}</Text>
        </Text>
      </View>

      {loading || !report ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.brand600} />
        </View>
      ) : (
        <ScrollView contentContainerStyle={{ padding: spacing.lg, gap: spacing.md, paddingBottom: spacing.xxl }}>
          <View style={styles.statsGrid}>
            {[
              { label: 'Saldo awal', value: report.opening },
              { label: 'Setoran', value: report.float_in },
              { label: 'Penjualan tunai', value: report.sale_in },
              { label: 'Kembalian', value: report.change_out },
              { label: 'Pengeluaran', value: report.expense },
              { label: 'Saldo akhir', value: report.closing },
            ].map((stat) => (
              <View key={stat.label} style={styles.stat}>
                <Text style={styles.statLabel}>{stat.label}</Text>
                <Text style={styles.statValue}>{formatRupiah(Number(stat.value || 0))}</Text>
              </View>
            ))}
          </View>

          <View style={styles.card}>
            <View style={styles.modeRow}>
              <Pressable
                onPress={() => setMode('float')}
                style={[styles.modeChip, mode === 'float' && styles.modeChipOn]}
              >
                <Text style={[styles.modeText, mode === 'float' && styles.modeTextOn]}>Setoran</Text>
              </Pressable>
              <Pressable
                onPress={() => setMode('expense')}
                style={[styles.modeChip, mode === 'expense' && styles.modeChipOn]}
              >
                <Text style={[styles.modeText, mode === 'expense' && styles.modeTextOn]}>Pengeluaran</Text>
              </Pressable>
            </View>
            <Text style={styles.modeHint}>
              {mode === 'float'
                ? 'Modal / setoran kas yang disediakan di laci.'
                : 'Pembelian mendadak / pemakaian uang kas.'}
            </Text>
            <TextInput
              value={amount}
              onChangeText={(text) => setAmount(formatRupiahInput(text))}
              keyboardType="number-pad"
              placeholder="0"
              placeholderTextColor={colors.slate400}
              style={styles.input}
            />
            <TextInput
              value={note}
              onChangeText={setNote}
              placeholder={mode === 'float' ? 'Contoh: Modal pagi' : 'Contoh: Beli gula darurat'}
              placeholderTextColor={colors.slate400}
              style={styles.input}
            />
            <Pressable onPress={submit} disabled={saving} style={styles.btn}>
              <Text style={styles.btnText}>
                {saving ? 'Menyimpan…' : mode === 'float' ? 'Catat Setoran' : 'Catat Pengeluaran'}
              </Text>
            </Pressable>
          </View>

          {entries.map((entry) => (
            <View key={entry.id} style={styles.card}>
              <Text style={styles.entryType}>{TYPE_LABEL[entry.type] || entry.type}</Text>
              <Text style={styles.entryAmount}>{formatRupiah(entry.amount)}</Text>
              <Text style={styles.meta}>{entry.note}</Text>
            </View>
          ))}
        </ScrollView>
      )}
    </AppScaffold>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  filterCard: {
    marginHorizontal: spacing.lg,
    marginTop: spacing.md,
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.md,
    gap: spacing.sm,
  },
  filterLabel: { fontSize: 12, color: colors.slate500, ...font('600') },
  filterActions: { flexDirection: 'row', gap: spacing.sm },
  primaryBtn: {
    flex: 1,
    minHeight: 42,
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  primaryBtnText: { color: colors.white, ...font('700') },
  outlineBtn: {
    minHeight: 42,
    paddingHorizontal: spacing.md,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate300,
    alignItems: 'center',
    justifyContent: 'center',
  },
  outlineBtnText: { color: colors.brand700, ...font('700') },
  balanceHint: { fontSize: 13, color: colors.slate500 },
  balanceValue: { color: colors.slate900, ...font('700') },
  statsGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  stat: {
    width: '48%',
    flexGrow: 1,
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.md,
  },
  statLabel: { fontSize: 11, color: colors.slate500, ...font('600') },
  statValue: { fontSize: 13, color: colors.slate900, ...font('700'), marginTop: 4 },
  card: {
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.md,
    gap: spacing.sm,
  },
  modeRow: { flexDirection: 'row', gap: spacing.sm },
  modeChip: {
    flex: 1,
    minHeight: 40,
    borderRadius: radius.md,
    backgroundColor: colors.slate100,
    alignItems: 'center',
    justifyContent: 'center',
  },
  modeChipOn: { backgroundColor: colors.brand600 },
  modeText: { color: colors.slate600, ...font('600') },
  modeTextOn: { color: colors.white },
  modeHint: { fontSize: 12, color: colors.slate500 },
  input: {
    borderWidth: 1,
    borderColor: colors.slate200,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    paddingVertical: 10,
    backgroundColor: colors.white,
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
  entryType: { fontSize: 12, color: colors.brand700, ...font('700') },
  entryAmount: { fontSize: 15, color: colors.slate900, ...font('700') },
  meta: { fontSize: 12, color: colors.slate500 },
});
