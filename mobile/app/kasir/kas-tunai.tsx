import { useFocusEffect, useRouter } from 'expo-router';
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
import { asApiError } from '@/auth';
import { AppScaffold } from '@/components/AppScaffold';
import { colors, font, radius, spacing } from '@/theme';
import { formatRupiah, parseRupiahInput } from '@/utils/rupiah';

export default function KasTunaiScreen() {
  const router = useRouter();
  const [loading, setLoading] = useState(true);
  const [report, setReport] = useState<Record<string, unknown> | null>(null);
  const [amount, setAmount] = useState('');
  const [note, setNote] = useState('');
  const [mode, setMode] = useState<'float' | 'expense'>('float');
  const [saving, setSaving] = useState(false);

  const refresh = useCallback(async () => {
    setLoading(true);
    try {
      const res = await kasirApi.kasTunai();
      setReport(res.data);
    } catch (err) {
      if (asApiError(err).status === 423) router.replace('/kasir/pin' as never);
    } finally {
      setLoading(false);
    }
  }, [router]);

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
      Alert.alert('Gagal', asApiError(err).message);
    } finally {
      setSaving(false);
    }
  };

  const entries = (report?.entries as { id: number; type: string; amount: number; note: string }[]) || [];

  return (
    <AppScaffold moduleType="kasir" title="Kas Tunai" subtitle="Setoran & pengeluaran">
      {loading || !report ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.brand600} />
        </View>
      ) : (
        <ScrollView contentContainerStyle={{ padding: spacing.lg, gap: spacing.md }}>
          <View style={styles.stats}>
            <View style={styles.stat}>
              <Text style={styles.statLabel}>Saldo</Text>
              <Text style={styles.statValue}>{formatRupiah(Number(report.balance || 0))}</Text>
            </View>
            <View style={styles.stat}>
              <Text style={styles.statLabel}>Opening</Text>
              <Text style={styles.statValue}>{formatRupiah(Number(report.opening || 0))}</Text>
            </View>
            <View style={styles.stat}>
              <Text style={styles.statLabel}>Closing</Text>
              <Text style={styles.statValue}>{formatRupiah(Number(report.closing || 0))}</Text>
            </View>
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
            <TextInput
              value={amount}
              onChangeText={setAmount}
              keyboardType="numeric"
              placeholder="Nominal"
              style={styles.input}
            />
            <TextInput value={note} onChangeText={setNote} placeholder="Keterangan" style={styles.input} />
            <Pressable onPress={submit} disabled={saving} style={styles.btn}>
              <Text style={styles.btnText}>{saving ? 'Menyimpan…' : 'Catat'}</Text>
            </Pressable>
          </View>

          {entries.map((entry) => (
            <View key={entry.id} style={styles.card}>
              <Text style={styles.entryType}>{entry.type}</Text>
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
  stats: { flexDirection: 'row', gap: spacing.sm },
  stat: {
    flex: 1,
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.md,
  },
  statLabel: { fontSize: 11, color: colors.slate500, ...font('600') },
  statValue: { fontSize: 12, color: colors.slate900, ...font('700'), marginTop: 4 },
  card: {
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.lg,
    gap: spacing.sm,
  },
  modeRow: { flexDirection: 'row', gap: 8 },
  modeChip: {
    flex: 1,
    alignItems: 'center',
    paddingVertical: spacing.sm,
    borderRadius: radius.md,
    backgroundColor: colors.slate100,
  },
  modeChipOn: { backgroundColor: colors.brand600 },
  modeText: { fontSize: 13, color: colors.slate600, ...font('600') },
  modeTextOn: { color: colors.white },
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
  entryType: { fontSize: 12, color: colors.slate500, textTransform: 'uppercase', ...font('600') },
  entryAmount: { fontSize: 16, color: colors.slate900, ...font('700') },
  meta: { fontSize: 12, color: colors.slate500 },
});
