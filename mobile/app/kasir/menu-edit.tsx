import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
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
import * as ImagePicker from 'expo-image-picker';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { kasirApi } from '@/api/kasir';
import type { MenuProduct } from '@/api/types';
import { isPinSessionError, reportApiError } from '@/auth';
import { AppScaffold } from '@/components/AppScaffold';
import { colors, font, radius, spacing } from '@/theme';
import { formatRupiah } from '@/utils/rupiah';

export default function MenuEditScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [product, setProduct] = useState<(MenuProduct & { presets?: Record<string, string> }) | null>(null);
  const [description, setDescription] = useState('');
  const [category, setCategory] = useState('');
  const [categories, setCategories] = useState<{ slug: string; name: string }[]>([]);
  const [imageUri, setImageUri] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    (async () => {
      try {
        const [prodRes, catRes] = await Promise.all([kasirApi.product(Number(id)), kasirApi.categories()]);
        setProduct(prodRes.data);
        setDescription(prodRes.data.description || '');
        setCategory(prodRes.data.menu_category || '');
        setCategories((catRes.data || []).map((c) => ({ slug: c.slug, name: c.name })));
      } catch (err) {
        reportApiError(err);
        if (!isPinSessionError(err)) {
          router.back();
        }
      } finally {
        setLoading(false);
      }
    })();
  }, [id, router]);

  const pickImage = async () => {
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ImagePicker.MediaTypeOptions.Images,
      quality: 0.8,
    });
    if (!result.canceled && result.assets[0]) {
      setImageUri(result.assets[0].uri);
    }
  };

  const save = async () => {
    setSaving(true);
    try {
      const form = new FormData();
      form.append('description', description);
      form.append('menu_category', category);
      if (imageUri) {
        form.append('image', {
          uri: imageUri,
          name: 'menu.jpg',
          type: 'image/jpeg',
        } as unknown as Blob);
      }
      await kasirApi.updateProduct(Number(id), form);
      Alert.alert('Berhasil', 'Menu diperbarui.');
      router.back();
    } catch (err) {
      reportApiError(err);
    } finally {
      setSaving(false);
    }
  };

  if (loading || !product) {
    return (
      <AppScaffold moduleType="kasir" title="Edit Menu">
        <View style={styles.center}>
          <ActivityIndicator color={colors.brand600} />
        </View>
      </AppScaffold>
    );
  }

  return (
    <AppScaffold moduleType="kasir" title="Edit Menu" subtitle={product.name}>
      <KeyboardAvoidingView
        style={styles.flex}
        behavior={Platform.OS === 'ios' ? 'padding' : undefined}
        keyboardVerticalOffset={Platform.OS === 'ios' ? 8 : 0}
      >
        <ScrollView
          style={styles.flex}
          keyboardShouldPersistTaps="handled"
          contentContainerStyle={[
            styles.content,
            { paddingBottom: Math.max(insets.bottom, 16) + 88 },
          ]}
        >
          <View style={styles.preview}>
            <Image source={{ uri: imageUri || product.image_url }} style={styles.image} />
            <View style={styles.previewBody}>
              <Text style={styles.productName} numberOfLines={2}>
                {product.name}
              </Text>
              {product.sku ? <Text style={styles.sku}>{product.sku}</Text> : null}
              <Text style={styles.price}>
                {(product.selling_price || 0) > 0
                  ? formatRupiah(product.selling_price)
                  : 'Harga belum diatur'}
              </Text>
            </View>
          </View>

          <Text style={styles.label}>Gambar Menu</Text>
          <Pressable onPress={pickImage} style={styles.outline}>
            <Text style={styles.outlineText}>Ganti gambar</Text>
          </Pressable>

          <Text style={styles.label}>Kategori Menu</Text>
          <View style={styles.chips}>
            {categories.map((c) => {
              const on = category === c.slug;
              return (
                <Pressable
                  key={c.slug}
                  onPress={() => setCategory(c.slug)}
                  style={[styles.chip, on && styles.chipOn]}
                >
                  <Text style={[styles.chipText, on && styles.chipTextOn]} numberOfLines={1}>
                    {c.name}
                  </Text>
                </Pressable>
              );
            })}
          </View>

          <Text style={styles.label}>Deskripsi</Text>
          <TextInput
            value={description}
            onChangeText={setDescription}
            multiline
            placeholder="Tulis deskripsi singkat untuk pelanggan…"
            placeholderTextColor={colors.slate400}
            style={[styles.input, styles.textarea]}
          />
        </ScrollView>

        <View style={[styles.footer, { paddingBottom: Math.max(insets.bottom, 12) }]}>
          <Pressable
            onPress={() => router.back()}
            style={styles.secondaryBtn}
            disabled={saving}
          >
            <Text style={styles.secondaryBtnText}>Batal</Text>
          </Pressable>
          <Pressable
            onPress={save}
            disabled={saving}
            style={[styles.primaryBtn, saving && { opacity: 0.65 }]}
          >
            {saving ? (
              <ActivityIndicator color={colors.white} />
            ) : (
              <Text style={styles.primaryBtnText}>Simpan</Text>
            )}
          </Pressable>
        </View>
      </KeyboardAvoidingView>
    </AppScaffold>
  );
}

const styles = StyleSheet.create({
  flex: { flex: 1 },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  content: {
    padding: spacing.lg,
    gap: spacing.sm,
  },
  preview: {
    flexDirection: 'row',
    gap: spacing.md,
    backgroundColor: colors.white,
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.md,
    marginBottom: spacing.sm,
  },
  image: {
    width: 96,
    height: 96,
    borderRadius: radius.lg,
    backgroundColor: colors.slate100,
  },
  previewBody: { flex: 1, minWidth: 0, justifyContent: 'center' },
  productName: { fontSize: 17, color: colors.slate900, ...font('700') },
  sku: { fontSize: 12, color: colors.slate500, marginTop: 2 },
  price: { fontSize: 16, color: colors.brand700, ...font('700'), marginTop: 8 },
  label: {
    fontSize: 13,
    color: colors.slate700,
    ...font('600'),
    marginTop: spacing.sm,
    marginBottom: 6,
  },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: {
    borderRadius: radius.full,
    borderWidth: 1,
    borderColor: colors.slate200,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    backgroundColor: colors.white,
    maxWidth: '100%',
  },
  chipOn: { backgroundColor: colors.brand600, borderColor: colors.brand600 },
  chipText: { fontSize: 12, color: colors.slate600, ...font('500') },
  chipTextOn: { color: colors.white },
  input: {
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate300,
    padding: spacing.md,
    backgroundColor: colors.white,
    color: colors.slate900,
    fontSize: 15,
  },
  textarea: {
    minHeight: 110,
    textAlignVertical: 'top',
  },
  outline: {
    minHeight: 44,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate300,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.white,
  },
  outlineText: { color: colors.slate700, ...font('600') },
  footer: {
    flexDirection: 'row',
    gap: spacing.sm,
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.sm,
    borderTopWidth: 1,
    borderTopColor: colors.slate200,
    backgroundColor: colors.white,
  },
  secondaryBtn: {
    flex: 1,
    minHeight: 48,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate300,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.white,
  },
  secondaryBtnText: { color: colors.slate700, ...font('600') },
  primaryBtn: {
    flex: 1.4,
    minHeight: 48,
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  primaryBtnText: { color: colors.white, ...font('700') },
});
