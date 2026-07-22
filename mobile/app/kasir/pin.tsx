import { useRouter } from 'expo-router';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
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
import { authApi, pinApi } from '@/api/kasir';
import { asApiError, useAuth } from '@/auth';
import {
  isKasirListenModeRunning,
  startKasirListenMode,
} from '@/kasir/kasirListenMode';
import { registerKasirPushToken } from '@/kasir/pushNotifications';
import { colors, font, fontDisplay, radius, spacing } from '@/theme';
import { resolveMediaUrl } from '@/utils/mediaUrl';

type ShopInfo = {
  name: string;
  logo_url?: string | null;
  initial: string;
};

/** Samakan UI/UX dengan web kasir/pin-unlock (tanpa pindah modul → Ubah PIN). */
export default function PinUnlockScreen() {
  const { setPin, logout, user } = useAuth();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [shop, setShop] = useState<ShopInfo>({
    name: 'Coffee & Kitchen',
    logo_url: null,
    initial: 'C',
  });
  const [pin, setPinValue] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const inputRef = useRef<TextInput>(null);
  const submittingRef = useRef(false);
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

  useEffect(() => {
    // Push + mode suara tetap aktif di belakang layar (tanpa status debug di UI).
    void registerKasirPushToken();
    if (Platform.OS === 'android') {
      void startKasirListenMode().then(async (ok) => {
        if (!ok) await isKasirListenModeRunning();
      });
    }

    authApi
      .shop()
      .then((res) => {
        setShop({
          name: res.data.name || 'Coffee & Kitchen',
          logo_url: resolveMediaUrl(res.data.logo_url),
          initial: res.data.initial || (res.data.name?.[0] || 'C').toUpperCase(),
        });
      })
      .catch(() => {
        // pakai default lokal
      });

    pinApi
      .show()
      .then((res) => {
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
    return () => {
      clearTimeout(t);
      if (debounceRef.current) clearTimeout(debounceRef.current);
    };
  }, [router, setPin]);

  const unlock = useCallback(
    async (value: string) => {
      if (submittingRef.current) return;
      const digits = value.replace(/\D/g, '').slice(0, 6);
      if (digits.length < 4) return;

      submittingRef.current = true;
      setSubmitting(true);
      setError(null);
      try {
        const res = await pinApi.unlock(digits);
        setPin(res.data);
        router.replace('/kasir');
      } catch (err) {
        const apiErr = asApiError(err);
        const fieldError = (apiErr.payload as { errors?: { pin?: string[] } } | undefined)?.errors
          ?.pin?.[0];
        setError(fieldError || apiErr.message || 'PIN tidak dikenali. Coba lagi.');
        setPinValue('');
        setTimeout(() => inputRef.current?.focus(), 100);
      } finally {
        submittingRef.current = false;
        setSubmitting(false);
      }
    },
    [router, setPin],
  );

  const scheduleUnlock = useCallback(
    (digits: string) => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
        debounceRef.current = null;
      }

      // sama seperti web: 6 digit langsung submit, 4–5 digit tunggu 450ms
      if (digits.length === 6) {
        void unlock(digits);
        return;
      }

      if (digits.length >= 4) {
        debounceRef.current = setTimeout(() => {
          void unlock(digits);
        }, 450);
      }
    },
    [unlock],
  );

  const onChangePin = (raw: string) => {
    const digits = raw.replace(/\D/g, '').slice(0, 6);
    setPinValue(digits);
    setError(null);
    scheduleUnlock(digits);
  };

  const onSubmit = () => {
    if (debounceRef.current) {
      clearTimeout(debounceRef.current);
      debounceRef.current = null;
    }
    void unlock(pin);
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
            paddingTop: Math.max(insets.top + spacing.lg, 16),
            paddingBottom: Math.max(insets.bottom + spacing.xl, 24),
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
            <Text style={styles.shopTitle}>PIN menentukan siapa yang melayani</Text>
          </View>

          <View style={styles.divider} />

          {error ? (
            <View style={styles.errorBox}>
              <Text style={styles.errorText}>{error}</Text>
            </View>
          ) : null}

          <Text style={styles.label}>PIN pegawai (4–6 digit)</Text>
          <TextInput
            ref={inputRef}
            value={pin}
            onChangeText={onChangePin}
            onSubmitEditing={onSubmit}
            keyboardType="number-pad"
            secureTextEntry
            maxLength={6}
            editable={!submitting}
            style={styles.input}
            placeholder="••••"
            placeholderTextColor={colors.slate400}
            returnKeyType="done"
            textContentType="oneTimeCode"
          />
          <Text style={styles.hint}>Isi PIN — otomatis masuk tanpa klik tombol</Text>

          <Pressable
            onPress={onSubmit}
            disabled={submitting || pin.length < 4}
            style={({ pressed }) => [
              styles.submitBtn,
              pressed && { opacity: 0.92 },
              (submitting || pin.length < 4) && { opacity: 0.55 },
            ]}
          >
            {submitting ? (
              <ActivityIndicator color={colors.white} />
            ) : (
              <Text style={styles.submitText}>Buka Kasir</Text>
            )}
          </Pressable>

          <View style={styles.meta}>
            {user ? (
              <Text style={styles.metaText}>
                Stasiun aktif: <Text style={styles.metaStrong}>{user.name}</Text>
              </Text>
            ) : null}
            <Text style={styles.metaMuted}>PIN dibuat di Admin → Data Karyawan</Text>
          </View>

          <View style={styles.actionsWrap}>
            <Pressable
              onPress={() => router.push('/kasir/ubah-pin' as never)}
              style={({ pressed }) => [styles.outlineBtn, pressed && { opacity: 0.9 }]}
            >
              <Text style={styles.outlineText}>Ubah PIN</Text>
            </Pressable>

            <Pressable
              onPress={() => logout()}
              style={({ pressed }) => [
                styles.outlineBtn,
                styles.logoutBtn,
                pressed && { opacity: 0.9 },
              ]}
            >
              <Text style={[styles.outlineText, styles.logoutText]}>Keluar / Logout</Text>
            </Pressable>
            <Text style={styles.logoutHint}>Keluar dari akun login stasiun ini</Text>
          </View>
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
  brand: { alignItems: 'center' },
  logoImg: {
    width: 48,
    height: 48,
    borderRadius: 16,
    backgroundColor: colors.brand50,
    borderWidth: 2,
    borderColor: colors.brand50,
  },
  logoFallback: {
    width: 48,
    height: 48,
    borderRadius: 16,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.brand600,
    borderWidth: 2,
    borderColor: colors.brand50,
  },
  logoFallbackText: { color: colors.white, fontSize: 20, ...font('700') },
  shopName: {
    marginTop: spacing.sm,
    fontSize: 18,
    color: colors.slate900,
    textAlign: 'center',
    ...fontDisplay('700'),
  },
  shopTitle: {
    marginTop: 2,
    fontSize: 12,
    color: colors.slate500,
    textAlign: 'center',
  },
  divider: {
    height: 1,
    backgroundColor: colors.slate200,
    marginVertical: spacing.md,
  },
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
    minHeight: 52,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate300,
    backgroundColor: colors.white,
    textAlign: 'center',
    fontSize: 24,
    letterSpacing: 10,
    color: colors.slate900,
    ...font('700'),
  },
  hint: {
    marginTop: 6,
    fontSize: 11,
    color: colors.slate500,
    textAlign: 'center',
  },
  submitBtn: {
    minHeight: 44,
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
    marginTop: spacing.md,
  },
  submitText: { color: colors.white, fontSize: 15, ...font('700') },
  meta: {
    marginTop: spacing.md,
    alignItems: 'center',
    gap: 2,
  },
  metaText: { fontSize: 11, color: colors.slate500 },
  metaStrong: { ...font('600'), color: colors.slate700 },
  metaMuted: { fontSize: 11, color: colors.slate400 },
  actionsWrap: {
    marginTop: spacing.md,
    borderTopWidth: 1,
    borderTopColor: colors.slate200,
    paddingTop: spacing.md,
    gap: 8,
  },
  outlineBtn: {
    minHeight: 40,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate300,
    backgroundColor: colors.white,
    alignItems: 'center',
    justifyContent: 'center',
  },
  outlineText: { color: colors.slate700, fontSize: 14, ...font('500') },
  logoutBtn: {},
  logoutText: {},
  logoutHint: {
    marginTop: 2,
    fontSize: 10,
    color: colors.slate400,
    textAlign: 'center',
  },
});
