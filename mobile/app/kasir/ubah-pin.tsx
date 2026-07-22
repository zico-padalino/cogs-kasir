import { useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { authApi } from '@/api/kasir';
import { asApiError } from '@/auth';
import { colors, font, fontDisplay, radius, spacing } from '@/theme';

/**
 * Samakan dengan web /pin (Ubah PIN): password akun + PIN baru + konfirmasi.
 */
export default function UbahPinScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [hasPin, setHasPin] = useState(true);
  const [canUseKasir, setCanUseKasir] = useState(true);
  const [loading, setLoading] = useState(true);
  const [currentPassword, setCurrentPassword] = useState('');
  const [pin, setPin] = useState('');
  const [pinConfirmation, setPinConfirmation] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [submitting, setSubmitting] = useState(false);
  const [success, setSuccess] = useState<string | null>(null);

  useEffect(() => {
    authApi
      .pinSetup()
      .then((res) => {
        setHasPin(!!res.data.has_pin);
        setCanUseKasir(!!res.data.can_use_kasir);
      })
      .catch(() => {
        // biarkan form tetap tampil; submit akan validasi server
      })
      .finally(() => setLoading(false));
  }, []);

  const onlyDigits = (value: string, max = 6) => value.replace(/\D/g, '').slice(0, max);

  const onSubmit = async () => {
    setError(null);
    setFieldErrors({});
    setSuccess(null);

    if (!currentPassword) {
      setFieldErrors({ current_password: 'Password akun wajib diisi untuk mengamankan PIN.' });
      return;
    }
    if (pin.length < 4 || pin.length > 6) {
      setFieldErrors({ pin: 'PIN harus 4–6 digit angka.' });
      return;
    }
    if (pin !== pinConfirmation) {
      setFieldErrors({ pin_confirmation: 'Konfirmasi PIN tidak cocok.' });
      return;
    }

    setSubmitting(true);
    try {
      await authApi.updatePinSetup({
        current_password: currentPassword,
        pin,
        pin_confirmation: pinConfirmation,
      });
      setSuccess('PIN berhasil disimpan. Masukkan PIN untuk membuka kasir.');
      setCurrentPassword('');
      setPin('');
      setPinConfirmation('');
      setTimeout(() => router.replace('/kasir/pin' as never), 700);
    } catch (err) {
      const apiErr = asApiError(err);
      const errors = (apiErr.payload as { errors?: Record<string, string[]> } | undefined)?.errors;
      if (errors) {
        const mapped: Record<string, string> = {};
        for (const [key, messages] of Object.entries(errors)) {
          if (messages?.[0]) mapped[key] = messages[0];
        }
        setFieldErrors(mapped);
      }
      setError(apiErr.message || 'Gagal menyimpan PIN.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <KeyboardAvoidingView
      style={styles.root}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <View style={styles.bgBase} />
      <View style={styles.glowA} pointerEvents="none" />
      <View style={styles.glowB} pointerEvents="none" />

      <ScrollView
        contentContainerStyle={[
          styles.scroll,
          {
            paddingTop: Math.max(insets.top + spacing.lg, 16),
            paddingBottom: Math.max(insets.bottom + spacing.xl, 24),
          },
        ]}
        keyboardShouldPersistTaps="handled"
      >
        <View style={styles.card}>
          <Text style={styles.title}>Ubah PIN</Text>
          <Text style={styles.subtitle}>
            PIN pribadi untuk membuka kasir dan menandai siapa yang bertugas
          </Text>

          <View style={styles.divider} />

          {loading ? (
            <ActivityIndicator color={colors.brand600} style={{ marginVertical: 24 }} />
          ) : !canUseKasir ? (
            <Text style={styles.mutedBox}>
              Akun ini tidak punya akses modul Kasir, jadi PIN kasir tidak diperlukan.
            </Text>
          ) : (
            <>
              <View style={styles.infoBanner}>
                <Text style={styles.infoBannerText}>
                  {hasPin
                    ? 'PIN sudah aktif. Isi form di bawah untuk mengganti PIN.'
                    : 'Belum ada PIN. Buat PIN 4–6 digit agar Anda bisa membuka kasir.'}
                </Text>
              </View>

              {success ? (
                <View style={styles.successBox}>
                  <Text style={styles.successText}>{success}</Text>
                </View>
              ) : null}

              {error ? (
                <View style={styles.errorBox}>
                  <Text style={styles.errorText}>{error}</Text>
                </View>
              ) : null}

              <Text style={styles.label}>Password akun</Text>
              <TextInput
                value={currentPassword}
                onChangeText={setCurrentPassword}
                secureTextEntry
                autoCapitalize="none"
                autoCorrect={false}
                editable={!submitting}
                style={styles.input}
                placeholder="Password login"
                placeholderTextColor={colors.slate400}
              />
              {fieldErrors.current_password ? (
                <Text style={styles.fieldError}>{fieldErrors.current_password}</Text>
              ) : null}

              <Text style={[styles.label, { marginTop: spacing.md }]}>PIN baru (4–6 digit)</Text>
              <TextInput
                value={pin}
                onChangeText={(v) => setPin(onlyDigits(v))}
                keyboardType="number-pad"
                secureTextEntry
                maxLength={6}
                editable={!submitting}
                style={[styles.input, styles.pinInput]}
                placeholder="••••"
                placeholderTextColor={colors.slate400}
              />
              {fieldErrors.pin ? <Text style={styles.fieldError}>{fieldErrors.pin}</Text> : null}

              <Text style={[styles.label, { marginTop: spacing.md }]}>Ulangi PIN</Text>
              <TextInput
                value={pinConfirmation}
                onChangeText={(v) => setPinConfirmation(onlyDigits(v))}
                keyboardType="number-pad"
                secureTextEntry
                maxLength={6}
                editable={!submitting}
                style={[styles.input, styles.pinInput]}
                placeholder="••••"
                placeholderTextColor={colors.slate400}
              />
              {fieldErrors.pin_confirmation ? (
                <Text style={styles.fieldError}>{fieldErrors.pin_confirmation}</Text>
              ) : null}

              <Pressable
                onPress={() => void onSubmit()}
                disabled={submitting}
                style={({ pressed }) => [
                  styles.submitBtn,
                  pressed && { opacity: 0.92 },
                  submitting && { opacity: 0.55 },
                ]}
              >
                {submitting ? (
                  <ActivityIndicator color={colors.white} />
                ) : (
                  <Text style={styles.submitText}>Simpan PIN</Text>
                )}
              </Pressable>
            </>
          )}

          <Pressable
            onPress={() => router.replace('/kasir/pin' as never)}
            style={({ pressed }) => [styles.backBtn, pressed && { opacity: 0.9 }]}
          >
            <Text style={styles.backText}>Kembali ke PIN</Text>
          </Pressable>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: '#f6f1ea' },
  bgBase: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: '#f7f1ea',
  },
  glowA: {
    position: 'absolute',
    top: -80,
    left: -40,
    width: 280,
    height: 280,
    borderRadius: 200,
    backgroundColor: 'rgba(92,64,51,0.14)',
  },
  glowB: {
    position: 'absolute',
    top: -40,
    right: -60,
    width: 240,
    height: 240,
    borderRadius: 200,
    backgroundColor: 'rgba(184,149,108,0.16)',
  },
  scroll: {
    flexGrow: 1,
    justifyContent: 'center',
    paddingHorizontal: spacing.md,
  },
  card: {
    width: '100%',
    maxWidth: 360,
    alignSelf: 'center',
    borderRadius: 20,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.7)',
    backgroundColor: 'rgba(255,255,255,0.96)',
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.lg,
    ...Platform.select({
      ios: {
        shadowColor: '#1c1410',
        shadowOpacity: 0.08,
        shadowRadius: 24,
        shadowOffset: { width: 0, height: 10 },
      },
      android: { elevation: 3 },
    }),
  },
  title: {
    fontSize: 18,
    color: colors.slate900,
    textAlign: 'center',
    ...fontDisplay('700'),
  },
  subtitle: {
    marginTop: 4,
    fontSize: 12,
    color: colors.slate500,
    textAlign: 'center',
    lineHeight: 17,
  },
  divider: {
    height: 1,
    backgroundColor: colors.slate200,
    marginVertical: spacing.md,
  },
  mutedBox: {
    fontSize: 13,
    color: colors.slate600,
    textAlign: 'center',
    lineHeight: 18,
  },
  infoBanner: {
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.brand100,
    backgroundColor: 'rgba(92,64,51,0.06)',
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    marginBottom: spacing.md,
  },
  infoBannerText: { fontSize: 12, color: colors.brand800, lineHeight: 17 },
  successBox: {
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: '#bbf7d0',
    backgroundColor: '#f0fdf4',
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    marginBottom: spacing.md,
  },
  successText: { color: '#166534', fontSize: 12, textAlign: 'center' },
  errorBox: {
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.red200,
    backgroundColor: colors.red50,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    marginBottom: spacing.md,
  },
  errorText: { color: colors.red700, fontSize: 13, textAlign: 'center', ...font('500') },
  label: {
    fontSize: 14,
    color: colors.slate700,
    ...font('500'),
    marginBottom: 6,
  },
  input: {
    minHeight: 46,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate300,
    backgroundColor: colors.white,
    paddingHorizontal: spacing.md,
    fontSize: 15,
    color: colors.slate900,
    ...font('500'),
  },
  pinInput: {
    textAlign: 'center',
    fontSize: 22,
    letterSpacing: 8,
    ...font('700'),
  },
  fieldError: {
    marginTop: 4,
    fontSize: 11,
    color: colors.red600,
  },
  submitBtn: {
    minHeight: 44,
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: spacing.lg,
  },
  submitText: { color: colors.white, fontSize: 15, ...font('700') },
  backBtn: {
    minHeight: 40,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate300,
    backgroundColor: colors.white,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: spacing.sm,
  },
  backText: { color: colors.slate700, fontSize: 14, ...font('500') },
});
