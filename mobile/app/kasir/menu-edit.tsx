import { useLocalSearchParams, useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import * as ImagePicker from 'expo-image-picker';
import { kasirApi } from '@/api/kasir';
import type { MenuProduct } from '@/api/types';
import { asApiError } from '@/auth';
import { AppScaffold } from '@/components/AppScaffold';
import { colors, font, radius, spacing } from '@/theme';
import { formatRupiah } from '@/utils/rupiah';

export default function MenuEditScreen() {
  const { id } = useLocalSearchParams<{ id: string }>();
  const router = useRouter();
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
        setCategories(
          (catRes.data || []).map((c) => ({ slug: c.slug, name: c.name })),
        );
      } catch (err) {
        if (asApiError(err).status === 423) router.replace('/kasir/pin' as never);
      } finally {
        setLoading(false);
      }
    })();
  }, [id, router]);

  const pickImage = async () => {
    const result = await ImagePicker.launchImageLibraryAsync({
      mediaTypes: ['images'],
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
      Alert.alert('Gagal', asApiError(err).message);
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
      <ScrollView contentContainerStyle={{ padding: spacing.lg, gap: spacing.md }}>
        <Image source={{ uri: imageUri || product.image_url }} style={styles.image} />
        <Pressable onPress={pickImage} style={styles.outline}>
          <Text style={styles.outlineText}>Ganti gambar</Text>
        </Pressable>
        <Text style={styles.price}>{formatRupiah(product.selling_price)}</Text>
        <Text style={styles.label}>Kategori</Text>
        <View style={styles.chips}>
          {categories.map((c) => (
            <Pressable
              key={c.slug}
              onPress={() => setCategory(c.slug)}
              style={[styles.chip, category === c.slug && styles.chipOn]}
            >
              <Text style={[styles.chipText, category === c.slug && styles.chipTextOn]}>{c.name}</Text>
            </Pressable>
          ))}
        </View>
        <Text style={styles.label}>Deskripsi</Text>
        <TextInput
          value={description}
          onChangeText={setDescription}
          multiline
          style={[styles.input, { minHeight: 100, textAlignVertical: 'top' }]}
        />
        <Pressable onPress={save} disabled={saving} style={styles.btn}>
          <Text style={styles.btnText}>{saving ? 'Menyimpan…' : 'Simpan'}</Text>
        </Pressable>
      </ScrollView>
    </AppScaffold>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  image: { width: '100%', height: 180, borderRadius: radius.lg, backgroundColor: colors.slate100 },
  price: { fontSize: 18, color: colors.brand700, ...font('700') },
  label: { fontSize: 13, color: colors.slate700, ...font('600') },
  chips: { flexDirection: 'row', flexWrap: 'wrap', gap: 8 },
  chip: {
    borderRadius: radius.full,
    borderWidth: 1,
    borderColor: colors.slate200,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    backgroundColor: colors.white,
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
  btn: {
    minHeight: 48,
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  btnText: { color: colors.white, ...font('700') },
});
