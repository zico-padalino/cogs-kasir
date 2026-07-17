import { useEffect, useState } from 'react';
import {
  Image,
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
import { asApiError, useAuth } from '@/auth';
import { colors, font, radius, spacing } from '@/theme';

type ShopInfo = {
  name: string;
  title: string;
  logo_url?: string | null;
  initial: string;
};

export default function LoginScreen() {
  const { login } = useAuth();
  const insets = useSafeAreaInsets();

  const [shop, setShop] = useState<ShopInfo>({
    name: 'Coffee & Kitchen',
    title: 'Masuk untuk mengelola toko Anda',
    logo_url: null,
    initial: 'C',
  });
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [remember, setRemember] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    authApi
      .shop()
      .then((res) => {
        setShop({
          name: res.data.name || 'Coffee & Kitchen',
          title: res.data.title || 'Masuk untuk mengelola toko Anda',
          logo_url: res.data.logo_url,
          initial: res.data.initial || (res.data.name?.[0] || 'C').toUpperCase(),
        });
      })
      .catch(() => {
        // default lokal jika endpoint shop belum ada di server
      });
  }, []);

  const handleSubmit = async () => {
    setError(null);

    if (!email.trim()) {
      setError('Email wajib diisi.');
      return;
    }
    if (!password) {
      setError('Password wajib diisi.');
      return;
    }

    setSubmitting(true);
    try {
      await login({ email, password });
    } catch (err) {
      const apiErr = asApiError(err);
      setError(apiErr.message || 'Gagal masuk.');
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
      <View style={styles.glowTop} pointerEvents="none" />

      <ScrollView
        contentContainerStyle={[
          styles.scroll,
          {
            paddingTop: Math.max(insets.top + spacing.xl, 24),
            paddingBottom: Math.max(insets.bottom + spacing.xxl, 32),
          },
        ]}
        keyboardShouldPersistTaps="handled"
      >
        <View style={styles.card}>
          <View style={styles.brand}>
            {shop.logo_url ? (
              <Image source={{ uri: shop.logo_url }} style={styles.logoImg} />
            ) : (
              <View style={styles.logoFallback}>
                <Text style={styles.logoFallbackText}>{shop.initial}</Text>
              </View>
            )}
            <Text style={styles.shopName}>{shop.name}</Text>
            <Text style={styles.shopTitle}>{shop.title}</Text>
          </View>

          <View style={styles.divider} />

          <Text style={styles.formTitle}>Masuk</Text>
          <Text style={styles.formSub}>Gunakan email dan password akun Anda</Text>

          {error ? (
            <View style={styles.errorBox}>
              <Text style={styles.errorText}>{error}</Text>
            </View>
          ) : null}

          <Text style={styles.label}>Email</Text>
          <TextInput
            value={email}
            onChangeText={setEmail}
            placeholder="nama@email.com"
            placeholderTextColor={colors.slate400}
            autoCapitalize="none"
            keyboardType="email-address"
            autoComplete="username"
            autoCorrect={false}
            style={styles.input}
          />

          <Text style={styles.label}>Password</Text>
          <View style={styles.passwordWrap}>
            <TextInput
              value={password}
              onChangeText={setPassword}
              placeholder="••••••••"
              placeholderTextColor={colors.slate400}
              secureTextEntry={!showPassword}
              autoCapitalize="none"
              autoComplete="current-password"
              style={[styles.input, styles.passwordInput]}
            />
            <Pressable
              onPress={() => setShowPassword((v) => !v)}
              style={styles.eyeBtn}
              accessibilityLabel={showPassword ? 'Sembunyikan password' : 'Tampilkan password'}
            >
              <Text style={styles.eyeText}>{showPassword ? '🙈' : '👁'}</Text>
            </Pressable>
          </View>

          <Pressable onPress={() => setRemember((v) => !v)} style={styles.rememberRow}>
            <View style={[styles.checkbox, remember && styles.checkboxOn]}>
              {remember ? <Text style={styles.checkboxTick}>✓</Text> : null}
            </View>
            <Text style={styles.rememberText}>Ingat saya di perangkat ini</Text>
          </Pressable>

          <Pressable
            onPress={handleSubmit}
            disabled={submitting}
            style={({ pressed }) => [
              styles.submitBtn,
              pressed && { opacity: 0.92 },
              submitting && { opacity: 0.6 },
            ]}
          >
            <Text style={styles.submitText}>{submitting ? 'Memproses…' : 'Masuk'}</Text>
          </Pressable>
        </View>
      </ScrollView>
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: '#f1f5f9' },
  bgBase: {
    ...StyleSheet.absoluteFillObject,
    backgroundColor: '#f8fafc',
  },
  glowA: {
    position: 'absolute',
    top: -80,
    left: -40,
    width: 280,
    height: 280,
    borderRadius: 200,
    backgroundColor: 'rgba(79,70,229,0.14)',
  },
  glowB: {
    position: 'absolute',
    top: -40,
    right: -60,
    width: 240,
    height: 240,
    borderRadius: 200,
    backgroundColor: 'rgba(99,102,241,0.12)',
  },
  glowTop: {
    position: 'absolute',
    top: 0,
    left: 0,
    right: 0,
    height: 140,
    backgroundColor: 'rgba(255,255,255,0.55)',
  },
  scroll: {
    flexGrow: 1,
    justifyContent: 'center',
    paddingHorizontal: spacing.lg,
  },
  card: {
    width: '100%',
    maxWidth: 420,
    alignSelf: 'center',
    borderRadius: 28,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.7)',
    backgroundColor: 'rgba(255,255,255,0.96)',
    paddingHorizontal: spacing.xl,
    paddingVertical: spacing.xl,
    ...Platform.select({
      ios: {
        shadowColor: '#0f172a',
        shadowOpacity: 0.08,
        shadowRadius: 30,
        shadowOffset: { width: 0, height: 12 },
      },
      android: { elevation: 4 },
    }),
  },
  brand: { alignItems: 'center' },
  logoImg: {
    width: 80,
    height: 80,
    borderRadius: 24,
    backgroundColor: colors.brand50,
    borderWidth: 4,
    borderColor: colors.brand50,
  },
  logoFallback: {
    width: 80,
    height: 80,
    borderRadius: 24,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.brand600,
    borderWidth: 4,
    borderColor: colors.brand50,
  },
  logoFallbackText: { color: colors.white, fontSize: 32, ...font('700') },
  shopName: {
    marginTop: spacing.md,
    fontSize: 24,
    color: colors.slate900,
    textAlign: 'center',
    ...font('700'),
  },
  shopTitle: {
    marginTop: 4,
    fontSize: 14,
    lineHeight: 20,
    color: colors.slate500,
    textAlign: 'center',
  },
  divider: {
    height: 1,
    backgroundColor: colors.slate200,
    marginVertical: spacing.xl,
  },
  formTitle: { fontSize: 20, color: colors.slate900, ...font('700') },
  formSub: { fontSize: 13, color: colors.slate500, marginTop: 4, marginBottom: spacing.md },
  errorBox: {
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.red200,
    backgroundColor: colors.red50,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    marginBottom: spacing.md,
  },
  errorText: { color: colors.red700, fontSize: 13, ...font('500') },
  label: {
    fontSize: 14,
    color: colors.slate700,
    ...font('500'),
    marginBottom: 6,
    marginTop: spacing.sm,
  },
  input: {
    minHeight: 48,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate300,
    backgroundColor: colors.white,
    paddingHorizontal: spacing.md,
    fontSize: 16,
    color: colors.slate900,
  },
  passwordWrap: { position: 'relative', justifyContent: 'center' },
  passwordInput: { paddingRight: 48 },
  eyeBtn: {
    position: 'absolute',
    right: 4,
    width: 44,
    height: 44,
    alignItems: 'center',
    justifyContent: 'center',
  },
  eyeText: { fontSize: 18 },
  rememberRow: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    marginTop: spacing.md,
  },
  checkbox: {
    width: 20,
    height: 20,
    borderRadius: 4,
    borderWidth: 1,
    borderColor: colors.slate300,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.white,
  },
  checkboxOn: { backgroundColor: colors.brand600, borderColor: colors.brand600 },
  checkboxTick: { color: colors.white, fontSize: 12, ...font('700') },
  rememberText: { fontSize: 13, color: colors.slate600 },
  submitBtn: {
    minHeight: 52,
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: spacing.lg,
  },
  submitText: { color: colors.white, fontSize: 16, ...font('700') },
});
