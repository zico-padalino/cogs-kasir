import { useRouter } from 'expo-router';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { pinApi } from '@/api/kasir';
import { asApiError, useAuth } from '@/auth';
import { colors, font, radius, spacing } from '@/theme';

export default function PinUnlockScreen() {
  const { setPin, logout, user } = useAuth();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [pin, setPinValue] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [ttl, setTtl] = useState(10);
  const inputRef = useRef<TextInput>(null);
  const submittingRef = useRef(false);

  useEffect(() => {
    pinApi
      .show()
      .then((res) => {
        setTtl(res.data.ttl_minutes ?? 10);
        if (res.data.unlocked) {
          setPin(res.data);
          router.replace('/kasir');
        }
      })
      .catch((err) => {
        const apiErr = asApiError(err);
        if (apiErr.code === 'ATTENDANCE_REQUIRED' || apiErr.code === 'PROFILE_REQUIRED') {
          router.replace('/kasir/attendance' as never);
        }
      });
    const t = setTimeout(() => inputRef.current?.focus(), 300);
    return () => clearTimeout(t);
  }, [router, setPin]);

  const unlock = useCallback(
    async (value: string) => {
      if (submittingRef.current) return;
      if (value.length < 4) return;

      submittingRef.current = true;
      setSubmitting(true);
      setError(null);
      try {
        const res = await pinApi.unlock(value);
        setPin(res.data);
        router.replace('/kasir');
      } catch (err) {
        const apiErr = asApiError(err);
        setError(apiErr.message || 'PIN tidak dikenali.');
        setPinValue('');
      } finally {
        submittingRef.current = false;
        setSubmitting(false);
      }
    },
    [router, setPin],
  );

  const onChangePin = (raw: string) => {
    const digits = raw.replace(/\D/g, '').slice(0, 6);
    setPinValue(digits);
    setError(null);
    if (digits.length >= 4) {
      void unlock(digits);
    }
  };

  return (
    <View style={[styles.root, { paddingTop: insets.top + spacing.xxl, paddingBottom: insets.bottom + spacing.xl }]}>
      <View style={styles.card}>
        <View style={styles.logo}>
          <Text style={styles.logoText}>K</Text>
        </View>
        <Text style={styles.title}>Buka Kasir</Text>
        <Text style={styles.subtitle}>
          Masukkan PIN karyawan (4–6 digit). Sesi aktif {ttl} menit.
        </Text>
        {user ? <Text style={styles.station}>Stasiun: {user.name}</Text> : null}

        {error ? (
          <View style={styles.errorBox}>
            <Text style={styles.errorText}>{error}</Text>
          </View>
        ) : null}

        <TextInput
          ref={inputRef}
          value={pin}
          onChangeText={onChangePin}
          keyboardType="number-pad"
          secureTextEntry
          maxLength={6}
          editable={!submitting}
          style={styles.input}
          placeholder="••••"
          placeholderTextColor={colors.slate400}
        />

        {submitting ? <ActivityIndicator color={colors.brand600} style={{ marginTop: spacing.md }} /> : null}

        <Pressable
          onPress={() => {
            Alert.alert('Keluar', 'Logout dari stasiun kasir?', [
              { text: 'Batal', style: 'cancel' },
              { text: 'Keluar', style: 'destructive', onPress: () => logout() },
            ]);
          }}
          style={styles.logout}
        >
          <Text style={styles.logoutText}>Keluar akun</Text>
        </Pressable>
      </View>
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: colors.slate100,
    paddingHorizontal: spacing.lg,
    justifyContent: 'center',
  },
  card: {
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    padding: spacing.xl,
    alignItems: 'center',
    gap: spacing.sm,
  },
  logo: {
    width: 56,
    height: 56,
    borderRadius: radius.lg,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
    marginBottom: spacing.sm,
  },
  logoText: { color: colors.white, fontSize: 24, ...font('700') },
  title: { fontSize: 22, color: colors.slate900, ...font('700') },
  subtitle: { fontSize: 13, color: colors.slate500, textAlign: 'center', lineHeight: 18 },
  station: { fontSize: 12, color: colors.slate600, marginBottom: spacing.sm },
  errorBox: {
    alignSelf: 'stretch',
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.red200,
    backgroundColor: colors.red50,
    padding: spacing.md,
  },
  errorText: { color: colors.red700, fontSize: 13, textAlign: 'center', ...font('500') },
  input: {
    alignSelf: 'stretch',
    minHeight: 56,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate300,
    backgroundColor: colors.slate50,
    textAlign: 'center',
    fontSize: 28,
    letterSpacing: 8,
    color: colors.slate900,
    marginTop: spacing.md,
  },
  logout: { marginTop: spacing.lg, padding: spacing.md },
  logoutText: { color: colors.slate500, fontSize: 13, ...font('500') },
});
