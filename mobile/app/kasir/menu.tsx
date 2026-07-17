import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Image,
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { kasirApi } from '@/api/kasir';
import type { MenuProduct } from '@/api/types';
import { asApiError } from '@/auth';
import { AppScaffold } from '@/components/AppScaffold';
import { colors, font, radius, spacing } from '@/theme';
import { formatRupiah } from '@/utils/rupiah';

export default function MenuAdminScreen() {
  const router = useRouter();
  const [products, setProducts] = useState<MenuProduct[]>([]);
  const [labels, setLabels] = useState<Record<string, string>>({});
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);

  const refresh = useCallback(async () => {
    setLoading(true);
    try {
      const res = await kasirApi.products();
      setProducts(res.data.products);
      const cats = res.data.menu_categories;
      if (Array.isArray(cats)) {
        const map: Record<string, string> = {};
        for (const c of cats as { slug: string; name: string }[]) {
          map[c.slug] = c.name;
        }
        setLabels(map);
      } else {
        setLabels((cats as Record<string, string>) || {});
      }
    } catch (err) {
      if (asApiError(err).status === 423) router.replace('/kasir/pin' as never);
    } finally {
      setLoading(false);
    }
  }, [router]);

  useFocusEffect(
    useCallback(() => {
      void refresh();
    }, [refresh]),
  );

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    if (!q) return products;
    return products.filter((p) => p.name.toLowerCase().includes(q));
  }, [products, search]);

  return (
    <AppScaffold moduleType="kasir" title="Kelola Menu" subtitle="Gambar, kategori & deskripsi">
      <TextInput
        value={search}
        onChangeText={setSearch}
        placeholder="Cari menu…"
        placeholderTextColor={colors.slate400}
        style={styles.search}
      />
      {loading ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.brand600} />
        </View>
      ) : (
        <FlatList
          data={filtered}
          keyExtractor={(item) => String(item.id)}
          contentContainerStyle={{ padding: spacing.lg, gap: spacing.sm }}
          renderItem={({ item }) => (
            <Pressable
              onPress={() => router.push(`/kasir/menu-edit?id=${item.id}` as never)}
              style={styles.card}
            >
              <Image source={{ uri: item.image_url }} style={styles.image} />
              <View style={{ flex: 1 }}>
                <Text style={styles.name}>{item.name}</Text>
                <Text style={styles.meta}>
                  {labels[item.menu_category || ''] || item.menu_category || '-'} · {formatRupiah(item.selling_price)}
                </Text>
              </View>
              <Text style={styles.edit}>Edit</Text>
            </Pressable>
          )}
        />
      )}
    </AppScaffold>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  search: {
    marginHorizontal: spacing.lg,
    marginTop: spacing.md,
    minHeight: 44,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    paddingHorizontal: spacing.md,
    color: colors.slate900,
  },
  card: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.sm,
  },
  image: { width: 56, height: 56, borderRadius: radius.md, backgroundColor: colors.slate100 },
  name: { fontSize: 14, color: colors.slate900, ...font('600') },
  meta: { fontSize: 12, color: colors.slate500, marginTop: 2 },
  edit: { color: colors.brand700, ...font('600'), fontSize: 13, paddingHorizontal: spacing.sm },
});
