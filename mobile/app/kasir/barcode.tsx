import { useLocalSearchParams, useRouter } from 'expo-router';
import { Alert, Pressable, Share, StyleSheet, Text, View } from 'react-native';
import QRCode from 'react-native-qrcode-svg';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors, font, fontDisplay, radius, spacing } from '@/theme';

/**
 * Samakan web /kasir/barcode — kartu stiker QR untuk discan/dibagikan.
 */
export default function BarcodeScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const params = useLocalSearchParams<{ url?: string; shop?: string }>();
  const orderUrl = typeof params.url === 'string' ? decodeURIComponent(params.url) : '';
  const shopName = typeof params.shop === 'string' ? decodeURIComponent(params.shop) : 'Coffee & Kitchen';

  const shareCard = async () => {
    if (!orderUrl) return;
    try {
      await Share.share({
        title: `Barcode ${shopName}`,
        message: `${shopName}\nScan untuk pesan:\n${orderUrl}`,
        url: orderUrl,
      });
    } catch {
      Alert.alert('Gagal', 'Tidak bisa membagikan barcode.');
    }
  };

  return (
    <View style={[styles.root, { paddingTop: insets.top + spacing.md, paddingBottom: insets.bottom + spacing.lg }]}>
      <Pressable onPress={() => router.back()} style={styles.back}>
        <Text style={styles.backText}>← Kembali</Text>
      </Pressable>
      <Text style={styles.hint}>
        Ukuran stiker meja — screenshot kartu di bawah lalu cetak kecil untuk ditempel, atau bagikan link.
      </Text>

      <View style={styles.card}>
        <Text style={styles.mark}>QR</Text>
        <Text style={styles.eyebrow}>Scan untuk pesan</Text>
        <Text style={styles.shop}>{shopName}</Text>
        <Text style={styles.tagline}>Menu & pesanan dari HP</Text>
        <View style={styles.qrFrame}>{orderUrl ? <QRCode value={orderUrl} size={180} /> : null}</View>
        <Text style={styles.cta}>Arahkan kamera ke kode ini</Text>
      </View>

      <Pressable onPress={() => void shareCard()} style={styles.btn} disabled={!orderUrl}>
        <Text style={styles.btnText}>Bagikan Link Barcode</Text>
      </Pressable>
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: '#f6f1ea',
    paddingHorizontal: spacing.lg,
    gap: spacing.md,
  },
  back: { alignSelf: 'flex-start' },
  backText: { color: colors.brand700, ...font('600'), fontSize: 14 },
  hint: { fontSize: 13, color: colors.slate500, lineHeight: 18 },
  card: {
    backgroundColor: colors.white,
    borderRadius: radius['3xl'],
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.xl,
    alignItems: 'center',
    gap: spacing.sm,
  },
  mark: {
    alignSelf: 'flex-start',
    fontSize: 11,
    color: colors.brand600,
    ...font('700'),
    letterSpacing: 1,
  },
  eyebrow: { fontSize: 12, color: colors.slate500, ...font('600') },
  shop: { fontSize: 24, color: colors.espresso, ...fontDisplay('700'), textAlign: 'center' },
  tagline: { fontSize: 13, color: colors.copper, textAlign: 'center' },
  qrFrame: {
    marginTop: spacing.md,
    padding: spacing.md,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
  },
  cta: { marginTop: spacing.sm, fontSize: 13, color: colors.slate600, ...font('600') },
  btn: {
    minHeight: 48,
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  btnText: { color: colors.white, ...font('700') },
});
