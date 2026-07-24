import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  FlatList,
  Image,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { kasirApi } from '@/api/kasir';
import type { MenuProduct } from '@/api/types';
import { AppScaffold } from '@/components/AppScaffold';
import { colors, font, radius, shadow, spacing } from '@/theme';
import { formatRupiah } from '@/utils/rupiah';

export default function MenuAdminScreen() {
  const router = useRouter();
  const insets = useSafeAreaInsets();
  const [products, setProducts] = useState<MenuProduct[]>([]);
  const [labels, setLabels] = useState<Record<string, string>>({});
  const [categories, setCategories] = useState<string[]>([]);
  const [category, setCategory] = useState('all');
  const [search, setSearch] = useState('');
  const [loading, setLoading] = useState(true);

  const [togglingId, setTogglingId] = useState<number | null>(null);

  const refresh = useCallback(async () => {
    setLoading(true);
    try {
      const res = await kasirApi.products();
      setProducts(res.data.products);
      const cats = res.data.menu_categories;
      if (Array.isArray(cats)) {
        const map: Record<string, string> = {};
        const keys: string[] = [];
        for (const c of cats as { slug: string; name: string }[]) {
          map[c.slug] = c.name;
          keys.push(c.slug);
        }
        setLabels(map);
        setCategories(keys);
      } else {
        const map = (cats as Record<string, string>) || {};
        setLabels(map);
        setCategories(Object.keys(map));
      }
    } catch {
      // PIN_LOCKED → redirect global
    } finally {
      setLoading(false);
    }
  }, []);

  const toggleSoldOut = async (item: MenuProduct) => {
    const next = !(item.sold_out_manual ?? item.is_sold_out ?? false);
    setTogglingId(item.id);
    try {
      const res = await kasirApi.toggleSoldOut(item.id, next);
      setProducts((prev) =>
        prev.map((row) => (row.id === item.id ? { ...row, ...res.data } : row)),
      );
    } catch {
      // PIN_LOCKED → redirect global
    } finally {
      setTogglingId(null);
    }
  };

  useFocusEffect(
    useCallback(() => {
      void refresh();
    }, [refresh]),
  );

  const filtered = useMemo(() => {
    const q = search.trim().toLowerCase();
    return products.filter((p) => {
      const cat = p.menu_category || 'lainnya';
      if (category !== 'all' && cat !== category) return false;
      if (!q) return true;
      const label = labels[cat] || cat;
      return (
        p.name.toLowerCase().includes(q) ||
        (p.sku || '').toLowerCase().includes(q) ||
        label.toLowerCase().includes(q)
      );
    });
  }, [products, search, category, labels]);

  return (
    <AppScaffold moduleType="kasir" title="Kelola Menu" subtitle="Gambar, kategori & deskripsi">
      <View style={styles.body}>
        <View style={styles.toolbar}>
          <Text style={styles.countHint}>
            {products.length} item · centang Habis agar tidak bisa dipesan
          </Text>
          <TextInput
            value={search}
            onChangeText={setSearch}
            placeholder="Cari menu…"
            placeholderTextColor={colors.slate400}
            style={styles.search}
          />
          <ScrollView
            horizontal
            showsHorizontalScrollIndicator={false}
            contentContainerStyle={styles.tabs}
          >
            <Pressable
              onPress={() => setCategory('all')}
              style={[styles.tab, category === 'all' && styles.tabOn]}
            >
              <Text style={[styles.tabText, category === 'all' && styles.tabTextOn]}>Semua</Text>
            </Pressable>
            {categories.map((slug) => (
              <Pressable
                key={slug}
                onPress={() => setCategory(slug)}
                style={[styles.tab, category === slug && styles.tabOn]}
              >
                <Text style={[styles.tabText, category === slug && styles.tabTextOn]} numberOfLines={1}>
                  {labels[slug] || slug}
                </Text>
              </Pressable>
            ))}
          </ScrollView>
        </View>

        {loading ? (
          <View style={styles.center}>
            <ActivityIndicator color={colors.brand600} />
          </View>
        ) : (
          <FlatList
            style={styles.list}
            data={filtered}
            keyExtractor={(item) => String(item.id)}
            contentContainerStyle={[
              styles.listContent,
              { paddingBottom: Math.max(insets.bottom, 16) + spacing.xl },
            ]}
            ListEmptyComponent={
              <Text style={styles.empty}>
                {products.length === 0
                  ? 'Belum ada produk jadi untuk dijual.'
                  : 'Tidak ada menu yang cocok dengan filter.'}
              </Text>
            }
            renderItem={({ item }) => {
              const cat = item.menu_category || 'lainnya';
              const catLabel = labels[cat] || cat;
              const price = item.selling_price || 0;
              const manualSoldOut = Boolean(item.sold_out_manual ?? false);
              const soldOut = Boolean(item.is_sold_out);
              const busy = togglingId === item.id;

              return (
                <View style={[styles.card, soldOut && styles.cardSoldOut]}>
                  <Image source={{ uri: item.image_url }} style={styles.thumb} />
                  <View style={styles.cardBody}>
                    <Text style={styles.name} numberOfLines={2}>
                      {item.name}
                      {soldOut ? ' · Habis' : ''}
                    </Text>
                    <Text style={styles.meta} numberOfLines={1}>
                      {[item.sku, catLabel].filter(Boolean).join(' · ')}
                    </Text>
                    {item.description ? (
                      <Text style={styles.desc} numberOfLines={2}>
                        {item.description}
                      </Text>
                    ) : null}
                    <Text style={styles.price} numberOfLines={1}>
                      {price > 0 ? formatRupiah(price) : 'Harga belum diatur'}
                    </Text>
                  </View>
                  <View style={styles.actions}>
                    <Pressable
                      onPress={() => void toggleSoldOut(item)}
                      disabled={busy}
                      style={[styles.soldOutBtn, manualSoldOut && styles.soldOutBtnOn]}
                    >
                      <Text style={[styles.soldOutBtnText, manualSoldOut && styles.soldOutBtnTextOn]}>
                        {busy ? '…' : manualSoldOut ? '✓ Habis' : 'Habis'}
                      </Text>
                    </Pressable>
                    <Pressable
                      onPress={() => router.push(`/kasir/menu-edit?id=${item.id}` as never)}
                      style={styles.editBtn}
                    >
                      <Text style={styles.editBtnText}>Atur</Text>
                    </Pressable>
                  </View>
                </View>
              );
            }}
          />
        )}
      </View>
    </AppScaffold>
  );
}

const styles = StyleSheet.create({
  body: { flex: 1 },
  toolbar: {
    paddingHorizontal: spacing.lg,
    paddingTop: spacing.md,
    gap: spacing.sm,
    borderBottomWidth: 1,
    borderBottomColor: colors.slate200,
    backgroundColor: colors.slate50,
    paddingBottom: spacing.md,
  },
  countHint: { fontSize: 12, color: colors.slate500 },
  search: {
    minHeight: 44,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    paddingHorizontal: spacing.md,
    color: colors.slate900,
    fontSize: 15,
  },
  tabs: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: 8,
    paddingVertical: 2,
  },
  tab: {
    flexShrink: 0,
    borderRadius: radius.full,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    paddingHorizontal: spacing.md,
    paddingVertical: 8,
  },
  tabOn: {
    borderColor: colors.brand500,
    backgroundColor: colors.brand50,
  },
  tabText: { fontSize: 12, color: colors.slate600, ...font('600') },
  tabTextOn: { color: colors.brand700 },
  list: { flex: 1 },
  listContent: {
    padding: spacing.lg,
    gap: spacing.md,
  },
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  empty: {
    textAlign: 'center',
    color: colors.slate500,
    fontSize: 14,
    marginTop: spacing.xxl,
    paddingHorizontal: spacing.lg,
  },
  card: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: spacing.md,
    backgroundColor: colors.white,
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.md,
    ...shadow.sm,
  },
  cardSoldOut: {
    borderColor: '#fecdd3',
    backgroundColor: '#fff1f2',
  },
  thumb: {
    width: 72,
    height: 72,
    borderRadius: radius.lg,
    backgroundColor: colors.slate100,
  },
  cardBody: {
    flex: 1,
    minWidth: 0,
    paddingRight: 4,
  },
  name: { fontSize: 15, color: colors.slate900, ...font('700') },
  meta: { fontSize: 12, color: colors.slate500, marginTop: 2 },
  desc: { fontSize: 12, color: colors.slate600, marginTop: 4, lineHeight: 17 },
  price: { fontSize: 14, color: colors.brand700, ...font('700'), marginTop: 6 },
  actions: {
    alignSelf: 'center',
    flexShrink: 0,
    gap: 8,
    minWidth: 72,
  },
  soldOutBtn: {
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: '#fecdd3',
    backgroundColor: colors.white,
    paddingHorizontal: spacing.sm,
    paddingVertical: 8,
    alignItems: 'center',
  },
  soldOutBtnOn: {
    borderColor: '#e11d48',
    backgroundColor: '#e11d48',
  },
  soldOutBtnText: { color: '#9f1239', fontSize: 12, ...font('700') },
  soldOutBtnTextOn: { color: colors.white },
  editBtn: {
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    paddingHorizontal: spacing.md,
    paddingVertical: 10,
    alignItems: 'center',
  },
  editBtnText: { color: colors.white, fontSize: 13, ...font('700') },
});
