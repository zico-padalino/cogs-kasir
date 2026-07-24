import { useRouter } from 'expo-router';
import { useState } from 'react';
import {
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

/** Samakan web Ubah Password. */
export default function UbahPasswordScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [currentPassword, setCurrentPassword] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirmation, setPasswordConfirmation] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
  const [submitting, setSubmitting] = useState(false);
  const [success, setSuccess] = useState<string | null>(null);

  const onSubmit = async () => {
    setError(null);
    setFieldErrors({});
    setSuccess(null);

    if (!currentPassword) {
      setFieldErrors({ current_password: 'Password saat ini wajib diisi.' });
      return;
    }
    if (password.length < 8) {
      setFieldErrors({ password: 'Password baru minimal 8 karakter.' });
      return;
    }
    if (password !== passwordConfirmation) {
      setFieldErrors({ password_confirmation: 'Konfirmasi password tidak cocok.' });
      return;
    }

    setSubmitting(true);
    try {
      await authApi.changePassword({
        current_password: currentPassword,
        password,
        password_confirmation: passwordConfirmation,
      });
      setSuccess('Password berhasil diubah.');
      setCurrentPassword('');
      setPassword('');
      setPasswordConfirmation('');
      setTimeout(() => router.back(), 800);
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
      setError(apiErr.message || 'Gagal mengubah password.');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <KeyboardAvoidingView
      style={styles.root}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      <ScrollView
        contentContainerStyle={{
          padding: spacing.lg,
          paddingTop: insets.top + spacing.lg,
          paddingBottom: insets.bottom + spacing.xl,
          gap: spacing.md,
        }}
        keyboardShouldPersistTaps="handled"
      >
        <Pressable onPress={() => router.back()}>
          <Text style={styles.back}>← Kembali</Text>
        </Pressable>
        <Text style={styles.title}>Ubah Password</Text>
        <Text style={styles.sub}>Ganti password akun login (bukan PIN kasir).</Text>

        {error ? <Text style={styles.error}>{error}</Text> : null}
        {success ? <Text style={styles.success}>{success}</Text> : null}

        <Text style={styles.label}>Password saat ini</Text>
        <TextInput
          value={currentPassword}
          onChangeText={setCurrentPassword}
          secureTextEntry
          style={styles.input}
          placeholderTextColor={colors.slate400}
        />
        {fieldErrors.current_password ? <Text style={styles.fieldError}>{fieldErrors.current_password}</Text> : null}

        <Text style={styles.label}>Password baru</Text>
        <TextInput
          value={password}
          onChangeText={setPassword}
          secureTextEntry
          style={styles.input}
          placeholderTextColor={colors.slate400}
        />
        {fieldErrors.password ? <Text style={styles.fieldError}>{fieldErrors.password}</Text> : null}

        <Text style={styles.label}>Konfirmasi password baru</Text>
        <TextInput
          value={passwordConfirmation}
          onChangeText={setPasswordConfirmation}
          secureTextEntry
          style={styles.input}
          placeholderTextColor={colors.slate400}
        />
        {fieldErrors.password_confirmation ? (
          <Text style={styles.fieldError}>{fieldErrors.password_confirmation}</Text>
        ) : null}

        <Pressable onPress={() => void onSubmit()} disabled={submitting} style={styles.btn}>
          <Text style={styles.btnText}>{submitting ? 'Menyimpan…' : 'Simpan Password'}</Text>
        </Pressable>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: '#f6f1ea' },
  back: { color: colors.brand700, ...font('600'), marginBottom: spacing.sm },
  title: { fontSize: 24, color: colors.espresso, ...fontDisplay('700') },
  sub: { fontSize: 13, color: colors.slate500, marginBottom: spacing.sm },
  label: { fontSize: 12, color: colors.slate500, ...font('600') },
  input: {
    borderWidth: 1,
    borderColor: colors.slate200,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    paddingVertical: 12,
    backgroundColor: colors.white,
    color: colors.slate900,
  },
  error: { color: colors.red600, ...font('600') },
  success: { color: colors.green700, ...font('600') },
  fieldError: { color: colors.red600, fontSize: 12 },
  btn: {
    marginTop: spacing.md,
    minHeight: 48,
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  btnText: { color: colors.white, ...font('700') },
});
