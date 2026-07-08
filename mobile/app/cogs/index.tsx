import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { AppScaffold } from '@/components/AppScaffold';
import { Card, PrimaryButton, StatCard } from '@/components/cogs-ui';
import { resetCogsData } from '@/cogs/db';
import { formatQty, formatRupiah } from '@/cogs/format';
import {
  getCogsSummary,
  getSetupProgress,
  type CogsSummary,
  type SetupProgress,
} from '@/cogs/repo';
import { colors, radius, spacing } from '@/theme';

export default function CogsDashboard() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [progress, setProgress] = useState<SetupProgress | null>(null);
  const [summary, setSummary] = useState<CogsSummary | null>(null);

  const refresh = useCallback(async () => {
    const [nextProgress, nextSummary] = await Promise.all([getSetupProgress(), getCogsSummary()]);
    setProgress(nextProgress);
    setSummary(nextSummary);
  }, []);

  useFocusEffect(
    useCallback(() => {
      refresh();
    }, [refresh]),
  );

  const handleReset = () => {
    Alert.alert(
      'Hapus semua data COGS?',
      'Semua produk, stok, produksi, dan hasil COGS lokal akan dihapus permanen. Tindakan ini tidak bisa dibatalkan.',
      [
        { text: 'Batal', style: 'cancel' },
        {
          text: 'Hapus Semua',
          style: 'destructive',
          onPress: async () => {
            await resetCogsData();
            await refresh();
            Alert.alert('Selesai', 'Semua data COGS lokal sudah dihapus.');
          },
        },
      ],
    );
  };

  const currentStep = progress?.steps.find((step) => step.number === progress.currentStep);

  return (
    <AppScaffold moduleType="cogs" title="Beranda" subtitle="Kelola data & hasil COGS">
    <ScrollView
      style={styles.root}
      contentContainerStyle={{
        paddingTop: spacing.lg,
        paddingBottom: insets.bottom + spacing.xxl,
        paddingHorizontal: spacing.lg,
        gap: spacing.lg,
      }}
    >
      <View style={styles.hero}>
        <View style={styles.heroBadge}>
          <Text style={styles.heroBadgeText}>C</Text>
        </View>
        <Text style={styles.heroEyebrow}>COGS SEDERHANA</Text>
        <Text style={styles.heroTitle}>Hitung Biaya Produk</Text>
        <Text style={styles.heroLead}>
          Bahan + Tenaga Kerja + Overhead = COGS. Ikuti 6 langkah, data tersimpan di perangkat.
        </Text>
      </View>

      {progress ? (
        <Card>
          <View style={styles.progressHead}>
            <Text style={styles.progressTitle}>
              {progress.fullyComplete ? 'Setup selesai' : `Langkah ${progress.currentStep} dari 6`}
            </Text>
            <Text style={styles.progressPercent}>{progress.percent}%</Text>
          </View>
          <View style={styles.progressTrack}>
            <View style={[styles.progressFill, { width: `${progress.percent}%` }]} />
          </View>
          {!progress.fullyComplete && currentStep ? (
            <PrimaryButton
              label={`Lanjut: ${currentStep.short}`}
              onPress={() => router.push(currentStep.route as never)}
            />
          ) : null}
        </Card>
      ) : null}

      <View style={{ gap: spacing.sm }}>
        {progress?.steps.map((step) => {
          const isActive = step.number === progress.currentStep && !progress.fullyComplete;

          return (
            <Pressable
              key={step.key}
              onPress={() => router.push(step.route as never)}
              style={({ pressed }) => [
                styles.stepCard,
                isActive && styles.stepCardActive,
                pressed && styles.pressed,
              ]}
            >
              <View
                style={[
                  styles.stepBadge,
                  step.done ? styles.stepBadgeDone : isActive ? styles.stepBadgeActive : null,
                ]}
              >
                <Text style={[styles.stepBadgeText, step.done && styles.stepBadgeTextDone]}>
                  {step.done ? '✓' : step.number}
                </Text>
              </View>
              <View style={styles.stepCopy}>
                <Text style={styles.stepTitle}>{step.title}</Text>
                <Text style={styles.stepDesc}>{step.description}</Text>
                <Text style={styles.stepHint}>💡 {step.hint}</Text>
              </View>
              <Text style={styles.stepChevron}>›</Text>
            </Pressable>
          );
        })}
      </View>

      {summary && summary.total_records > 0 ? (
        <View style={{ gap: spacing.md }}>
          <Text style={styles.summaryTitle}>Ringkasan Hasil</Text>
          <View style={styles.statGrid}>
            <StatCard label="Total COGS" value={formatRupiah(summary.total_cogs)} color="brand" />
            <StatCard label="Bahan Baku" value={formatRupiah(summary.total_direct_material)} color="green" />
            <StatCard label="Tenaga Kerja" value={formatRupiah(summary.total_direct_labor)} color="amber" />
            <StatCard label="Overhead" value={formatRupiah(summary.total_overhead)} color="rose" />
          </View>

          <Card>
            <Text style={styles.tableTitle}>Biaya per Produk</Text>
            {summary.by_product.map((item) => (
              <View key={item.product_id} style={styles.productRow}>
                <View style={{ flex: 1 }}>
                  <Text style={styles.productName}>{item.name}</Text>
                  <Text style={styles.productMeta}>
                    {formatQty(item.total_quantity)} unit · {formatRupiah(item.total_cogs)}
                  </Text>
                </View>
                <Text style={styles.productUnit}>{formatRupiah(item.average_unit_cogs)}/unit</Text>
              </View>
            ))}
          </Card>
        </View>
      ) : null}

      <Card style={styles.resetCard}>
        <Text style={styles.resetTitle}>Hapus semua data</Text>
        <Text style={styles.resetText}>
          Hapus seluruh data COGS lokal (produk, stok, resep, produksi, hasil) hingga kosong. Tidak
          bisa dibatalkan.
        </Text>
        <PrimaryButton label="Hapus Semua Data COGS" onPress={handleReset} tone="danger" />
      </Card>
    </ScrollView>
    </AppScaffold>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.slate100 },
  topRow: { flexDirection: 'row', alignItems: 'center', justifyContent: 'space-between' },
  homeBtn: { paddingVertical: spacing.xs },
  homeBtnText: { fontSize: 14, fontWeight: '600', color: colors.brand600 },
  localBadge: { borderRadius: 999, backgroundColor: colors.brand50, paddingHorizontal: 10, paddingVertical: 4 },
  localBadgeText: { fontSize: 10, fontWeight: '800', color: colors.brand700 },
  hero: { gap: spacing.sm },
  heroBadge: {
    width: 52,
    height: 52,
    borderRadius: radius.lg,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  heroBadgeText: { color: colors.white, fontSize: 26, fontWeight: '800' },
  heroEyebrow: { fontSize: 11, fontWeight: '700', letterSpacing: 1, color: colors.brand600 },
  heroTitle: { fontSize: 26, fontWeight: '800', color: colors.slate900 },
  heroLead: { fontSize: 14, lineHeight: 20, color: colors.slate600 },
  progressHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  progressTitle: { fontSize: 15, fontWeight: '700', color: colors.slate900 },
  progressPercent: { fontSize: 15, fontWeight: '800', color: colors.brand600 },
  progressTrack: { height: 8, borderRadius: 999, backgroundColor: colors.slate100, overflow: 'hidden' },
  progressFill: { height: '100%', borderRadius: 999, backgroundColor: colors.brand600 },
  stepCard: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    padding: spacing.md,
  },
  stepCardActive: { borderColor: colors.brand600, borderWidth: 2 },
  stepBadge: {
    width: 34,
    height: 34,
    borderRadius: 999,
    backgroundColor: colors.slate100,
    alignItems: 'center',
    justifyContent: 'center',
  },
  stepBadgeDone: { backgroundColor: colors.green600 },
  stepBadgeActive: { backgroundColor: colors.brand600 },
  stepBadgeText: { fontSize: 14, fontWeight: '800', color: colors.slate600 },
  stepBadgeTextDone: { color: colors.white },
  stepCopy: { flex: 1, gap: 2 },
  stepTitle: { fontSize: 15, fontWeight: '700', color: colors.slate900 },
  stepDesc: { fontSize: 12, lineHeight: 16, color: colors.slate600 },
  stepHint: { fontSize: 11, lineHeight: 15, color: colors.slate500, marginTop: 2 },
  stepChevron: { fontSize: 26, color: colors.slate500 },
  summaryTitle: { fontSize: 16, fontWeight: '800', color: colors.slate900 },
  statGrid: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.sm },
  tableTitle: { fontSize: 14, fontWeight: '700', color: colors.slate900 },
  productRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderTopWidth: 1,
    borderTopColor: colors.slate100,
    paddingTop: spacing.sm,
  },
  productName: { fontSize: 14, fontWeight: '600', color: colors.slate900 },
  productMeta: { fontSize: 12, color: colors.slate500 },
  productUnit: { fontSize: 13, fontWeight: '700', color: colors.brand600 },
  resetCard: { borderColor: '#fecaca', backgroundColor: '#fef2f2' },
  resetTitle: { fontSize: 14, fontWeight: '700', color: '#b91c1c' },
  resetText: { fontSize: 13, lineHeight: 18, color: '#b91c1c' },
  pressed: { opacity: 0.9, transform: [{ scale: 0.99 }] },
});
