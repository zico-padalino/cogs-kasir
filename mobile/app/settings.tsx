import { useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import {
  Alert,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import {
  clearAppBaseUrl,
  getAppBaseUrl,
  getDefaultAppUrl,
  setAppBaseUrl,
} from '@/config/appUrl';
import { colors, radius, spacing } from '@/theme';

export default function SettingsScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [url, setUrl] = useState('');
  const [savedUrl, setSavedUrl] = useState('');

  useEffect(() => {
    getAppBaseUrl().then((value) => {
      setUrl(value);
      setSavedUrl(value);
    });
  }, []);

  const handleSave = async () => {
    if (!/^https?:\/\//i.test(url.trim())) {
      Alert.alert('URL tidak valid', 'Gunakan format http:// atau https://');
      return;
    }

    await setAppBaseUrl(url);
    setSavedUrl(url.trim().replace(/\/+$/, ''));
    Alert.alert('Tersimpan', 'URL server diperbarui.');
  };

  const handleReset = async () => {
    await clearAppBaseUrl();
    const fallback = getDefaultAppUrl();
    setUrl(fallback);
    setSavedUrl(fallback);
    Alert.alert('Direset', 'Kembali ke URL default dari konfigurasi.');
  };

  return (
    <ScrollView
      style={styles.root}
      contentContainerStyle={{
        paddingTop: insets.top + spacing.lg,
        paddingBottom: insets.bottom + spacing.xxl,
        paddingHorizontal: spacing.lg,
        gap: spacing.lg,
      }}
    >
      <View style={styles.header}>
        <Pressable
          accessibilityRole="button"
          onPress={() => router.back()}
          style={({ pressed }) => [styles.backBtn, pressed && styles.pressed]}
        >
          <Text style={styles.backBtnText}>← Kembali</Text>
        </Pressable>
        <Text style={styles.title}>Pengaturan Server</Text>
        <Text style={styles.subtitle}>
          Arahkan aplikasi ke server Laravel Anda. Gunakan IP LAN untuk HP fisik, atau `10.0.2.2` untuk emulator Android.
        </Text>
      </View>

      <View style={styles.card}>
        <Text style={styles.label}>URL server Laravel</Text>
        <TextInput
          value={url}
          onChangeText={setUrl}
          autoCapitalize="none"
          autoCorrect={false}
          keyboardType="url"
          placeholder="https://toko-anda.com"
          placeholderTextColor={colors.slate500}
          style={styles.input}
        />
        <Text style={styles.hint}>Contoh: `http://192.168.1.10:8000` atau `https://domain-anda.com`</Text>

        <Pressable
          accessibilityRole="button"
          onPress={handleSave}
          style={({ pressed }) => [styles.saveBtn, pressed && styles.pressed]}
        >
          <Text style={styles.saveBtnText}>Simpan</Text>
        </Pressable>

        <Pressable
          accessibilityRole="button"
          onPress={handleReset}
          style={({ pressed }) => [styles.resetBtn, pressed && styles.pressed]}
        >
          <Text style={styles.resetBtnText}>Reset ke default</Text>
        </Pressable>
      </View>

      <View style={styles.card}>
        <Text style={styles.label}>URL tersimpan</Text>
        <Text style={styles.savedUrl}>{savedUrl || '—'}</Text>
      </View>
    </ScrollView>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: colors.slate100,
  },
  header: {
    gap: spacing.sm,
  },
  backBtn: {
    alignSelf: 'flex-start',
  },
  backBtnText: {
    fontSize: 14,
    fontWeight: '600',
    color: colors.brand600,
  },
  title: {
    fontSize: 24,
    fontWeight: '800',
    color: colors.slate900,
  },
  subtitle: {
    fontSize: 14,
    lineHeight: 20,
    color: colors.slate600,
  },
  card: {
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    padding: spacing.lg,
    gap: spacing.md,
  },
  label: {
    fontSize: 12,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.4,
    color: colors.slate500,
  },
  input: {
    minHeight: 48,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.slate50,
    paddingHorizontal: spacing.md,
    fontSize: 15,
    color: colors.slate900,
  },
  hint: {
    fontSize: 12,
    lineHeight: 18,
    color: colors.slate500,
  },
  saveBtn: {
    minHeight: 48,
    borderRadius: radius.lg,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  saveBtnText: {
    color: colors.white,
    fontSize: 15,
    fontWeight: '700',
  },
  resetBtn: {
    minHeight: 44,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.white,
  },
  resetBtnText: {
    color: colors.slate900,
    fontSize: 14,
    fontWeight: '600',
  },
  savedUrl: {
    fontSize: 13,
    color: colors.slate900,
    fontFamily: 'monospace',
  },
  pressed: {
    opacity: 0.85,
    transform: [{ scale: 0.98 }],
  },
});
