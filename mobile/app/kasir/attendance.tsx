import { useRouter } from 'expo-router';
import { Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { useAuth } from '@/auth';
import { colors, font, radius, spacing } from '@/theme';

export default function AttendanceRequiredScreen() {
  const insets = useSafeAreaInsets();
  const { logout, refreshMe } = useAuth();
  const router = useRouter();

  return (
    <View style={[styles.root, { paddingTop: insets.top + spacing.xxl, paddingBottom: insets.bottom + spacing.xl }]}>
      <View style={styles.card}>
        <Text style={styles.icon}>📱</Text>
        <Text style={styles.title}>Absensi diperlukan</Text>
        <Text style={styles.body}>
          Silakan absen masuk melalui scan QR di toko (halaman Absensi web), lalu kembali ke aplikasi dan ketuk Coba lagi.
        </Text>
        <Pressable
          onPress={async () => {
            try {
              await refreshMe();
              router.replace('/kasir/pin' as never);
            } catch {
              // tetap di sini
            }
          }}
          style={styles.btn}
        >
          <Text style={styles.btnText}>Coba lagi</Text>
        </Pressable>
        <Pressable onPress={() => logout()} style={styles.link}>
          <Text style={styles.linkText}>Keluar akun</Text>
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.slate100, paddingHorizontal: spacing.lg, justifyContent: 'center' },
  card: {
    backgroundColor: colors.white,
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.xl,
    alignItems: 'center',
    gap: spacing.sm,
  },
  icon: { fontSize: 40, marginBottom: spacing.sm },
  title: { fontSize: 20, color: colors.slate900, ...font('700') },
  body: { fontSize: 13, color: colors.slate500, textAlign: 'center', lineHeight: 20 },
  btn: {
    alignSelf: 'stretch',
    minHeight: 48,
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: spacing.md,
  },
  btnText: { color: colors.white, ...font('700') },
  link: { padding: spacing.md },
  linkText: { color: colors.slate500, ...font('500') },
});
