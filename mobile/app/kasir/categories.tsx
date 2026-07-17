import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import {
  ActivityIndicator,
  Alert,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { kasirApi } from '@/api/kasir';
import { reportApiError } from '@/auth';
import { AppScaffold } from '@/components/AppScaffold';
import { colors, font, radius, spacing } from '@/theme';

type Category = { id: number; name: string; slug: string; product_count: number };

export default function CategoriesScreen() {
  const [categories, setCategories] = useState<Category[]>([]);
  const [name, setName] = useState('');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);

  const refresh = useCallback(async () => {
    setLoading(true);
    try {
      const res = await kasirApi.categories();
      setCategories(res.data || []);
    } catch (err) {
      reportApiError(err);
    } finally {
      setLoading(false);
    }
  }, []);

  useFocusEffect(
    useCallback(() => {
      void refresh();
    }, [refresh]),
  );

  const add = async () => {
    if (!name.trim()) return;
    setSaving(true);
    try {
      await kasirApi.createCategory(name.trim());
      setName('');
      await refresh();
    } catch (err) {
      reportApiError(err);
    } finally {
      setSaving(false);
    }
  };

  const remove = (cat: Category) => {
    Alert.alert('Hapus kategori', `Hapus "${cat.name}"?`, [
      { text: 'Batal', style: 'cancel' },
      {
        text: 'Hapus',
        style: 'destructive',
        onPress: async () => {
          try {
            await kasirApi.deleteCategory(cat.id);
            await refresh();
          } catch (err) {
            reportApiError(err);
          }
        },
      },
    ]);
  };

  return (
    <AppScaffold moduleType="kasir" title="Atur Kategori" subtitle="Tab kategori di POS">
      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.brand600} />
        </View>
      ) : (
        <ScrollView contentContainerStyle={{ padding: spacing.lg, gap: spacing.md }}>
          <View style={styles.card}>
            <Text style={styles.title}>Tambah kategori</Text>
            <TextInput value={name} onChangeText={setName} placeholder="Nama kategori" style={styles.input} />
            <Pressable onPress={add} disabled={saving} style={styles.btn}>
              <Text style={styles.btnText}>{saving ? 'Menyimpan…' : 'Tambah'}</Text>
            </Pressable>
          </View>
          {categories.map((cat) => (
            <View key={cat.id} style={styles.row}>
              <View style={{ flex: 1 }}>
                <Text style={styles.name}>{cat.name}</Text>
                <Text style={styles.meta}>
                  {cat.slug} · {cat.product_count} menu
                </Text>
              </View>
              <Pressable onPress={() => remove(cat)}>
                <Text style={styles.delete}>Hapus</Text>
              </Pressable>
            </View>
          ))}
        </ScrollView>
      )}
    </AppScaffold>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  card: {
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.lg,
    gap: spacing.sm,
  },
  title: { fontSize: 15, ...font('700'), color: colors.slate900 },
  input: {
    minHeight: 44,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate300,
    paddingHorizontal: spacing.md,
    color: colors.slate900,
  },
  btn: {
    minHeight: 44,
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  btnText: { color: colors.white, ...font('700') },
  row: {
    flexDirection: 'row',
    alignItems: 'center',
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.md,
  },
  name: { fontSize: 14, ...font('600'), color: colors.slate900 },
  meta: { fontSize: 12, color: colors.slate500 },
  delete: { color: colors.red600, ...font('600'), padding: spacing.sm },
});
