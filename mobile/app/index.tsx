import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useState } from 'react';
import {
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { getAppBaseUrl } from '@/config/appUrl';
import { getLocalStorageStats } from '@/local-db/repository';
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
  const [serverUrl, setServerUrl] = useState('');
  const [localStats, setLocalStats] = useState({ products: 0, orders: 0 });
  const isAndroid = Platform.OS === 'android';

  useFocusEffect(
    useCallback(() => {
      let active = true;

      getAppBaseUrl().then((url) => {
        if (active) {
          setServerUrl(url);
        }
      });

      if (Platform.OS === 'android') {
        getLocalStorageStats().then((stats) => {
          if (active) {
            setLocalStats(stats);
          }
        });
      }

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
          <Text style={styles.heroBadgeText}>K</Text>
        </View>
        <Text style={styles.heroEyebrow}>COGS PERHITUNGAN</Text>
        <Text style={styles.heroTitle}>Kasir POS Mobile</Text>
        <Text style={styles.heroLead}>
          {isAndroid
            ? 'Android: coba Kasir Lokal tanpa server. Data menu & transaksi tersimpan langsung di HP.'
            : 'UI sama seperti versi web Laravel — drawer, tab menu, dan checkout mobile.'}
        </Text>
      </View>

      {isAndroid ? (
        <View style={styles.androidHeroCard}>
          <Text style={styles.androidHeroBadge}>Disarankan untuk uji coba</Text>
          <Text style={styles.androidHeroTitle}>Kasir Lokal Android</Text>
          <Text style={styles.androidHeroText}>
            {localStats.products} menu demo · {localStats.orders} transaksi tersimpan di perangkat
          </Text>
          <View style={styles.androidHeroActions}>
            <Pressable
              accessibilityRole="button"
              onPress={() => router.push('/local-kasir')}
              style={({ pressed }) => [styles.androidPrimaryBtn, pressed && styles.pressed]}
            >
              <Text style={styles.androidPrimaryBtnText}>Buka Kasir Lokal</Text>
            </Pressable>
            <Pressable
              accessibilityRole="button"
              onPress={() => router.push('/local-orders')}
              style={({ pressed }) => [styles.androidSecondaryBtn, pressed && styles.pressed]}
            >
              <Text style={styles.androidSecondaryBtnText}>Riwayat</Text>
            </Pressable>
          </View>
        </View>
      ) : null}

      <View style={styles.serverCard}>
        <Text style={styles.serverLabel}>Server aktif</Text>
        <Text style={styles.serverUrl} numberOfLines={2}>
          {serverUrl || 'Memuat…'}
        </Text>
        <Pressable
          accessibilityRole="button"
          onPress={() => router.push('/settings')}
          style={({ pressed }) => [styles.serverBtn, pressed && styles.pressed]}
        >
          <Text style={styles.serverBtnText}>Ubah URL server</Text>
        </Pressable>
      </View>

      <Text style={styles.sectionTitle}>
        {isAndroid ? 'Mode server Laravel' : 'Pilih modul'}
      </Text>

      <ModuleCard
        emoji="🛒"
        title="Kasir POS"
        badge="Kasir"
        description="Point of sale, konfirmasi pesanan online, dan pembayaran."
        onPress={() => router.push('/kasir')}
      />

      <ModuleCard
        emoji="☕"
        title="Pesan Online"
        badge="Pelanggan"
        description="Menu QR untuk pelanggan memesan dari ponsel."
        onPress={() => router.push('/pesan')}
      />

      <View style={styles.noteCard}>
        <Text style={styles.noteTitle}>
          {isAndroid ? 'Cara coba di Android' : 'Tips instalasi APK'}
        </Text>
        <Text style={styles.noteText}>
          {isAndroid
            ? 'Untuk uji cepat tanpa Laravel: pakai Kasir Lokal. Untuk sinkron server asli: build APK lalu set URL Laravel di Pengaturan.'
            : 'Build APK dengan perintah `npm run build:apk` di folder mobile, lalu unduh dan install di Android.'}
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
  androidHeroCard: {
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.brand600,
    backgroundColor: colors.brand50,
    padding: spacing.lg,
    gap: spacing.sm,
  },
  androidHeroBadge: {
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
  androidHeroTitle: {
    fontSize: 20,
    fontWeight: '800',
    color: colors.slate900,
  },
  androidHeroText: {
    fontSize: 13,
    lineHeight: 18,
    color: colors.slate600,
  },
  androidHeroActions: {
    flexDirection: 'row',
    gap: spacing.sm,
    marginTop: spacing.xs,
  },
  androidPrimaryBtn: {
    flex: 1,
    minHeight: 44,
    borderRadius: radius.lg,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  androidPrimaryBtnText: {
    color: colors.white,
    fontSize: 14,
    fontWeight: '800',
  },
  androidSecondaryBtn: {
    minHeight: 44,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.brand600,
    backgroundColor: colors.white,
    paddingHorizontal: spacing.lg,
    alignItems: 'center',
    justifyContent: 'center',
  },
  androidSecondaryBtnText: {
    color: colors.brand700,
    fontSize: 14,
    fontWeight: '700',
  },
  serverCard: {
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    padding: spacing.lg,
    gap: spacing.sm,
  },
  serverLabel: {
    fontSize: 11,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.5,
    color: colors.slate500,
  },
  serverUrl: {
    fontSize: 13,
    color: colors.slate900,
    fontFamily: 'monospace',
  },
  serverBtn: {
    alignSelf: 'flex-start',
    marginTop: spacing.xs,
    minHeight: 40,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    paddingHorizontal: spacing.lg,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.slate50,
  },
  serverBtnText: {
    fontSize: 13,
    fontWeight: '600',
    color: colors.slate900,
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
