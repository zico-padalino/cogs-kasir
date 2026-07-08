import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import { Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { getSetupProgress } from '@/cogs/repo';
import { colors, radius, spacing } from '@/theme';

type ModuleCardProps = {
  emoji: string;
  title: string;
  description: string;
  badge?: string;
  onPress: () => void;
};

function ModuleCard({ emoji, title, description, badge, onPress }: ModuleCardProps) {
  return (
    <Pressable
      accessibilityRole="button"
      onPress={onPress}
      style={({ pressed }) => [styles.card, pressed && styles.cardPressed]}
    >
      <View style={styles.cardIconWrap}>
        <Text style={styles.cardIcon}>{emoji}</Text>
      </View>
      <View style={styles.cardCopy}>
        <View style={styles.cardTitleRow}>
          <Text style={styles.cardTitle}>{title}</Text>
          {badge ? <Text style={styles.cardBadge}>{badge}</Text> : null}
        </View>
        <Text style={styles.cardDescription}>{description}</Text>
      </View>
      <Text style={styles.cardChevron}>›</Text>
    </Pressable>
  );
}

export default function HomeScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [cogs, setCogs] = useState({ percent: 0, currentStep: 1, fullyComplete: false });

  useFocusEffect(
    useCallback(() => {
      let active = true;

      getSetupProgress().then((progress) => {
        if (active) {
          setCogs({
            percent: progress.percent,
            currentStep: progress.currentStep,
            fullyComplete: progress.fullyComplete,
          });
        }
      });

      return () => {
        active = false;
      };
    }, []),
  );

  return (
    <ScrollView
      style={styles.root}
      contentContainerStyle={[
        styles.content,
        {
          paddingTop: insets.top + spacing.xl,
          paddingBottom: insets.bottom + spacing.xxl,
        },
      ]}
    >
      <View style={styles.hero}>
        <View style={styles.heroBadge}>
          <Text style={styles.heroBadgeText}>C</Text>
        </View>
        <Text style={styles.heroEyebrow}>COGS SEDERHANA</Text>
        <Text style={styles.heroTitle}>Hitung Biaya Produk</Text>
        <Text style={styles.heroLead}>
          Aplikasi COGS lokal untuk uji coba. Hitung Harga Pokok Produksi lewat 6 langkah — semua
          data tersimpan di perangkat, tanpa server.
        </Text>
      </View>

      <View style={styles.cogsHeroCard}>
        <Text style={styles.cogsHeroBadge}>Aplikasi utama · Offline</Text>
        <Text style={styles.cogsHeroTitle}>Aplikasi COGS</Text>
        <Text style={styles.cogsHeroText}>
          {cogs.fullyComplete
            ? 'Setup selesai. Buka untuk lihat hasil & hitung COGS.'
            : `Progress setup ${cogs.percent}% · lanjut ke langkah ${cogs.currentStep} dari 6`}
        </Text>
        <View style={styles.progressTrack}>
          <View style={[styles.progressFill, { width: `${cogs.percent}%` }]} />
        </View>
        <Pressable
          accessibilityRole="button"
          onPress={() => router.push('/cogs')}
          style={({ pressed }) => [styles.cogsPrimaryBtn, pressed && styles.pressed]}
        >
          <Text style={styles.cogsPrimaryBtnText}>Buka Aplikasi COGS</Text>
        </Pressable>
      </View>

      <Text style={styles.sectionTitle}>Modul Kasir (lokal, tanpa server)</Text>

      <ModuleCard
        emoji="🛒"
        title="Point of Sale"
        badge="Offline"
        description="Point of sale di perangkat: pilih menu, tipe pesanan, bayar, dan proses pesanan online."
        onPress={() => router.push('/kasir')}
      />

      <ModuleCard
        emoji="🧾"
        title="Riwayat Pesanan"
        badge="Lokal"
        description="Daftar pesanan kasir & online beserta status, detail, dan struk."
        onPress={() => router.push('/kasir/orders')}
      />

      <ModuleCard
        emoji="🪑"
        title="Meja QR"
        badge="Lokal"
        description="Kelola meja dan tampilkan QR untuk pelanggan memesan online."
        onPress={() => router.push('/kasir/tables')}
      />

      <ModuleCard
        emoji="🍽️"
        title="Kelola Menu"
        badge="Lokal"
        description="Tambah, ubah, aktif/nonaktifkan, dan hapus item menu kasir."
        onPress={() => router.push('/kasir/menu')}
      />

      <ModuleCard
        emoji="☕"
        title="Pesan Online"
        badge="Offline"
        description="Pelanggan pilih menu dan kirim pesanan langsung ke kasir di perangkat."
        onPress={() => router.push('/pesan-online')}
      />

      <View style={styles.noteCard}>
        <Text style={styles.noteTitle}>Untuk uji coba</Text>
        <Text style={styles.noteText}>
          Semua modul (COGS, Kasir POS, Pesan Online) berjalan penuh di perangkat tanpa Laravel.
          Data demo sudah terisi dan tersimpan lokal di perangkat.
        </Text>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: colors.slate100,
  },
  content: {
    paddingHorizontal: spacing.lg,
    gap: spacing.lg,
  },
  hero: {
    gap: spacing.sm,
  },
  heroBadge: {
    width: 56,
    height: 56,
    borderRadius: radius.lg,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  heroBadgeText: {
    color: colors.white,
    fontSize: 28,
    fontWeight: '700',
  },
  heroEyebrow: {
    fontSize: 11,
    fontWeight: '700',
    letterSpacing: 1,
    color: colors.brand600,
  },
  heroTitle: {
    fontSize: 28,
    fontWeight: '800',
    color: colors.slate900,
  },
  heroLead: {
    fontSize: 14,
    lineHeight: 20,
    color: colors.slate600,
  },
  cogsHeroCard: {
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.brand600,
    backgroundColor: colors.brand50,
    padding: spacing.lg,
    gap: spacing.sm,
  },
  cogsHeroBadge: {
    alignSelf: 'flex-start',
    borderRadius: 999,
    backgroundColor: colors.brand600,
    color: colors.white,
    overflow: 'hidden',
    paddingHorizontal: 10,
    paddingVertical: 4,
    fontSize: 10,
    fontWeight: '800',
  },
  cogsHeroTitle: {
    fontSize: 20,
    fontWeight: '800',
    color: colors.slate900,
  },
  cogsHeroText: {
    fontSize: 13,
    lineHeight: 18,
    color: colors.slate600,
  },
  progressTrack: {
    height: 8,
    borderRadius: 999,
    backgroundColor: colors.white,
    overflow: 'hidden',
    marginTop: spacing.xs,
  },
  progressFill: {
    height: '100%',
    borderRadius: 999,
    backgroundColor: colors.brand600,
  },
  cogsPrimaryBtn: {
    minHeight: 48,
    borderRadius: radius.lg,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: spacing.xs,
  },
  cogsPrimaryBtnText: {
    color: colors.white,
    fontSize: 15,
    fontWeight: '800',
  },
  sectionTitle: {
    fontSize: 13,
    fontWeight: '700',
    color: colors.slate500,
    textTransform: 'uppercase',
    letterSpacing: 0.4,
  },
  card: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    padding: spacing.lg,
    shadowColor: '#0f172a',
    shadowOpacity: 0.05,
    shadowRadius: 8,
    shadowOffset: { width: 0, height: 2 },
    elevation: 2,
  },
  cardPressed: {
    opacity: 0.92,
    transform: [{ scale: 0.99 }],
  },
  cardIconWrap: {
    width: 48,
    height: 48,
    borderRadius: radius.md,
    backgroundColor: colors.brand50,
    alignItems: 'center',
    justifyContent: 'center',
  },
  cardIcon: {
    fontSize: 24,
  },
  cardCopy: {
    flex: 1,
    minWidth: 0,
    gap: 4,
  },
  cardTitleRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    flexWrap: 'wrap',
  },
  cardTitle: {
    fontSize: 16,
    fontWeight: '700',
    color: colors.slate900,
  },
  cardBadge: {
    fontSize: 10,
    fontWeight: '700',
    color: colors.brand700,
    backgroundColor: colors.brand50,
    borderRadius: 999,
    overflow: 'hidden',
    paddingHorizontal: 8,
    paddingVertical: 3,
  },
  cardDescription: {
    fontSize: 13,
    lineHeight: 18,
    color: colors.slate600,
  },
  cardChevron: {
    fontSize: 28,
    color: colors.slate500,
    marginTop: -2,
  },
  noteCard: {
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: '#fde68a',
    backgroundColor: colors.amber50,
    padding: spacing.lg,
    gap: spacing.sm,
  },
  noteTitle: {
    fontSize: 14,
    fontWeight: '700',
    color: colors.amber800,
  },
  noteText: {
    fontSize: 13,
    lineHeight: 18,
    color: colors.amber800,
  },
  pressed: {
    opacity: 0.85,
  },
});
