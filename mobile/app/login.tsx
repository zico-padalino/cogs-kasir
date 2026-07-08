import { useState } from 'react';
import {
  KeyboardAvoidingView,
  Platform,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  useWindowDimensions,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { ROLE_META, useAuth, type Role } from '@/auth';
import { colors, font, radius, spacing } from '@/theme';

const MODULES: { value: Role; label: string; description: string; icon: string }[] = [
  { value: 'cogs', label: 'COGS', description: 'Perhitungan biaya produk & produksi', icon: '📊' },
  { value: 'kasir', label: 'Kasir', description: 'Penjualan & transaksi kasir', icon: '🧾' },
];

const DEMO = [
  { role: 'COGS', email: 'cogs@local.test', password: 'password' },
  { role: 'Kasir', email: 'kasir@local.test', password: 'password' },
];

export default function LoginScreen() {
  const { login } = useAuth();
  const insets = useSafeAreaInsets();
  const { width } = useWindowDimensions();
  const wide = width >= 900;

  const [module, setModule] = useState<Role>('cogs');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [showPassword, setShowPassword] = useState(false);
  const [remember, setRemember] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

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
      await login({ email, password, module });
      // Redirect ditangani oleh RootNavigator berdasarkan role.
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Gagal masuk.');
    } finally {
      setSubmitting(false);
    }
  };

  const fillDemo = (item: (typeof DEMO)[number]) => {
    setEmail(item.email);
    setPassword(item.password);
    setModule(item.role === 'COGS' ? 'cogs' : 'kasir');
    setError(null);
  };

  const brandPanel = (
    <View style={styles.brandPanel}>
      <View style={styles.brandLogo}>
        <Text style={styles.brandLogoText}>C</Text>
      </View>
      <Text style={styles.brandHeadline}>COGS Sederhana</Text>
      <Text style={styles.brandTagline}>
        Satu aplikasi untuk perhitungan biaya produk dan operasional kasir.
      </Text>
      <View style={styles.previewList}>
        {MODULES.map((item) => {
          const active = module === item.value;
          return (
            <View key={item.value} style={[styles.previewCard, active && styles.previewCardActive]}>
              <Text style={styles.previewIcon}>{item.icon}</Text>
              <View style={{ flex: 1 }}>
                <Text style={styles.previewTitle}>Modul {item.label}</Text>
                <Text style={styles.previewDesc}>{item.description}</Text>
              </View>
            </View>
          );
        })}
      </View>
    </View>
  );

  const formPanel = (
    <ScrollView
      contentContainerStyle={[
        styles.formScroll,
        { paddingTop: insets.top + spacing.xl, paddingBottom: insets.bottom + spacing.xxl },
      ]}
      keyboardShouldPersistTaps="handled"
    >
      <View style={styles.formCard}>
        <View style={styles.miniLogoRow}>
          <View style={styles.miniLogo}>
            <Text style={styles.miniLogoText}>C</Text>
          </View>
          <View>
            <Text style={styles.miniTitle}>COGS Sederhana</Text>
            <Text style={styles.miniSubtitle}>Pilih modul lalu masuk</Text>
          </View>
        </View>

        <Text style={styles.heading}>Masuk ke sistem</Text>
        <Text style={styles.subheading}>Pilih modul, lalu masukkan email dan password Anda.</Text>

        {error ? (
          <View style={styles.errorBox}>
            <Text style={styles.errorText}>{error}</Text>
          </View>
        ) : null}

        <Text style={styles.label}>Pilih modul</Text>
        <View style={styles.moduleTabs}>
          {MODULES.map((item) => {
            const active = module === item.value;
            return (
              <Pressable
                key={item.value}
                onPress={() => setModule(item.value)}
                style={[styles.moduleTab, active && styles.moduleTabActive]}
              >
                <Text style={[styles.moduleTabLabel, active && styles.moduleTabLabelActive]}>
                  {item.label}
                </Text>
                <Text style={styles.moduleTabDesc}>{item.description}</Text>
              </Pressable>
            );
          })}
        </View>

        <Text style={styles.label}>Email</Text>
        <TextInput
          value={email}
          onChangeText={setEmail}
          placeholder="nama@perusahaan.com"
          placeholderTextColor={colors.slate400}
          autoCapitalize="none"
          keyboardType="email-address"
          autoComplete="username"
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
          <Pressable onPress={() => setShowPassword((prev) => !prev)} style={styles.eyeBtn} hitSlop={8}>
            <Text style={styles.eyeText}>{showPassword ? '🙈' : '👁'}</Text>
          </Pressable>
        </View>

        <Pressable onPress={() => setRemember((prev) => !prev)} style={styles.rememberRow}>
          <View style={[styles.checkbox, remember && styles.checkboxOn]}>
            {remember ? <Text style={styles.checkboxTick}>✓</Text> : null}
          </View>
          <Text style={styles.rememberText}>Ingat saya di perangkat ini</Text>
        </Pressable>

        <Pressable
          onPress={handleSubmit}
          disabled={submitting}
          style={({ pressed }) => [styles.submitBtn, pressed && styles.pressed, submitting && styles.btnDisabled]}
        >
          <Text style={styles.submitText}>
            {submitting ? 'Memproses…' : `Masuk ke ${ROLE_META[module].label}`}
          </Text>
        </Pressable>

        <View style={styles.demoBox}>
          <Text style={styles.demoTitle}>Akun demo</Text>
          {DEMO.map((item) => (
            <Pressable key={item.role} onPress={() => fillDemo(item)} style={styles.demoRow}>
              <Text style={styles.demoRole}>{item.role}</Text>
              <Text style={styles.demoCred}>
                {item.email} / {item.password}
              </Text>
            </Pressable>
          ))}
          <Text style={styles.demoHint}>Ketuk salah satu untuk mengisi otomatis.</Text>
        </View>
      </View>
    </ScrollView>
  );

  return (
    <KeyboardAvoidingView
      style={styles.root}
      behavior={Platform.OS === 'ios' ? 'padding' : undefined}
    >
      {wide ? (
        <View style={styles.splitRow}>
          <View style={styles.splitBrand}>{brandPanel}</View>
          <View style={styles.splitForm}>{formPanel}</View>
        </View>
      ) : (
        formPanel
      )}
    </KeyboardAvoidingView>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.slate100 },
  splitRow: { flex: 1, flexDirection: 'row' },
  splitBrand: { flex: 1 },
  splitForm: { flex: 1 },
  brandPanel: {
    flex: 1,
    backgroundColor: colors.brand700,
    paddingHorizontal: spacing.xxl,
    justifyContent: 'center',
    gap: spacing.md,
  },
  brandLogo: {
    width: 56,
    height: 56,
    borderRadius: radius.lg,
    backgroundColor: 'rgba(255,255,255,0.15)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  brandLogoText: { color: colors.white, fontSize: 28, ...font('700') },
  brandHeadline: { color: colors.white, fontSize: 26, ...font('700'), marginTop: spacing.sm },
  brandTagline: { color: colors.brand100, fontSize: 14, lineHeight: 20, maxWidth: 360 },
  previewList: { gap: spacing.sm, marginTop: spacing.lg, maxWidth: 400 },
  previewCard: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: spacing.md,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: 'rgba(255,255,255,0.1)',
    backgroundColor: 'rgba(255,255,255,0.1)',
    padding: spacing.lg,
    opacity: 0.6,
  },
  previewCardActive: {
    opacity: 1,
    borderColor: 'rgba(255,255,255,0.3)',
    backgroundColor: 'rgba(255,255,255,0.15)',
  },
  previewIcon: { fontSize: 22 },
  previewTitle: { color: colors.white, fontSize: 15, ...font('600') },
  previewDesc: { color: colors.brand100, fontSize: 12, marginTop: 2 },
  formScroll: { flexGrow: 1, justifyContent: 'center', paddingHorizontal: spacing.lg },
  formCard: {
    width: '100%',
    maxWidth: 440,
    alignSelf: 'center',
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    padding: spacing.xl,
    gap: spacing.md,
  },
  miniLogoRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.md, marginBottom: spacing.xs },
  miniLogo: {
    width: 44,
    height: 44,
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  miniLogoText: { color: colors.white, fontSize: 20, ...font('700') },
  miniTitle: { fontSize: 16, color: colors.slate900, ...font('700') },
  miniSubtitle: { fontSize: 12, color: colors.slate500, marginTop: 1 },
  heading: { fontSize: 22, color: colors.slate900, ...font('700') },
  subheading: { fontSize: 13, color: colors.slate500, marginTop: -spacing.xs },
  errorBox: {
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.red200,
    backgroundColor: colors.red50,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
  },
  errorText: { color: colors.red700, fontSize: 13, ...font('500') },
  label: { fontSize: 14, color: colors.slate700, ...font('500'), marginTop: spacing.xs },
  moduleTabs: { flexDirection: 'row', gap: spacing.sm },
  moduleTab: {
    flex: 1,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.slate50,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.md,
    gap: 2,
  },
  moduleTabActive: { borderColor: colors.brand500, backgroundColor: colors.brand50 },
  moduleTabLabel: { fontSize: 14, color: colors.slate900, ...font('700') },
  moduleTabLabelActive: { color: colors.brand800 },
  moduleTabDesc: { fontSize: 11, color: colors.slate500, lineHeight: 15 },
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
  eyeBtn: { position: 'absolute', right: 4, width: 44, height: 44, alignItems: 'center', justifyContent: 'center' },
  eyeText: { fontSize: 18 },
  rememberRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm, marginTop: spacing.xs },
  checkbox: {
    width: 20,
    height: 20,
    borderRadius: radius.sm,
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
    marginTop: spacing.sm,
  },
  submitText: { color: colors.white, fontSize: 16, ...font('700') },
  btnDisabled: { opacity: 0.6 },
  demoBox: {
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.slate50,
    padding: spacing.md,
    gap: spacing.xs,
    marginTop: spacing.sm,
  },
  demoTitle: { fontSize: 12, color: colors.slate600, ...font('700'), textTransform: 'uppercase', letterSpacing: 0.4 },
  demoRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm, paddingVertical: 4 },
  demoRole: {
    width: 52,
    fontSize: 11,
    color: colors.brand700,
    ...font('700'),
  },
  demoCred: { flex: 1, fontSize: 12, color: colors.slate600, fontFamily: 'monospace' },
  demoHint: { fontSize: 11, color: colors.slate500, marginTop: 2 },
  pressed: { opacity: 0.9 },
});
