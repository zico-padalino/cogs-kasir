import { useFocusEffect } from 'expo-router';
import { useCallback, useState } from 'react';
import { Alert, Pressable, ScrollView, StyleSheet, Switch, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import {
  Badge,
  Callout,
  Card,
  EmptyState,
  Field,
  Input,
  PrimaryButton,
  RupiahInput,
  ScreenHeader,
  SectionTitle,
  Segmented,
} from '@/components/cogs-ui';
import {
  createLocalProduct,
  deleteLocalProduct,
  getMenuProducts,
  toggleLocalProduct,
  updateLocalProduct,
} from '@/local-db/repository';
import type { LocalProduct } from '@/local-db/types';
import { colors, font, spacing } from '@/theme';
import { formatRupiah, parseRupiahInput } from '@/utils/rupiah';

const CATEGORY_OPTIONS: { value: string; label: string }[] = [
  { value: 'minuman', label: 'Minuman' },
  { value: 'makanan', label: 'Makanan' },
  { value: 'pastry', label: 'Pastry' },
  { value: 'snack', label: 'Snack' },
  { value: 'lainnya', label: 'Lainnya' },
];

const CATEGORY_LABELS: Record<string, string> = Object.fromEntries(
  CATEGORY_OPTIONS.map((option) => [option.value, option.label]),
);

const EMOJI_PRESETS = ['☕', '🥛', '🍵', '🥐', '🍩', '🍞', '🥪', '🍟', '🍰', '🧋', '🍔', '🍕'];

export default function KasirMenuScreen() {
  const insets = useSafeAreaInsets();
  const [products, setProducts] = useState<LocalProduct[]>([]);
  const [editingId, setEditingId] = useState<number | null>(null);
  const [showForm, setShowForm] = useState(false);

  const [name, setName] = useState('');
  const [category, setCategory] = useState('minuman');
  const [price, setPrice] = useState('');
  const [emoji, setEmoji] = useState('☕');
  const [description, setDescription] = useState('');

  const refresh = useCallback(async () => {
    setProducts(await getMenuProducts());
  }, []);

  useFocusEffect(
    useCallback(() => {
      refresh();
    }, [refresh]),
  );

  const resetForm = () => {
    setEditingId(null);
    setName('');
    setCategory('minuman');
    setPrice('');
    setEmoji('☕');
    setDescription('');
    setShowForm(false);
  };

  const startEdit = (product: LocalProduct) => {
    setEditingId(product.id);
    setName(product.name);
    setCategory(product.category);
    setPrice(String(product.price));
    setEmoji(product.emoji);
    setDescription(product.description ?? '');
    setShowForm(true);
  };

  const handleSave = async () => {
    if (!name.trim()) {
      Alert.alert('Lengkapi', 'Nama menu wajib diisi.');
      return;
    }

    const payload = {
      name: name.trim(),
      category,
      price: parseRupiahInput(price),
      emoji: emoji.trim() || '☕',
      description: description.trim() || null,
    };

    if (editingId) {
      await updateLocalProduct(editingId, payload);
    } else {
      await createLocalProduct(payload);
    }

    resetForm();
    await refresh();
  };

  const handleDelete = (product: LocalProduct) => {
    Alert.alert('Hapus menu?', `"${product.name}" akan dihapus dari daftar menu.`, [
      { text: 'Batal', style: 'cancel' },
      {
        text: 'Hapus',
        style: 'destructive',
        onPress: async () => {
          await deleteLocalProduct(product.id);
          await refresh();
        },
      },
    ]);
  };

  return (
    <View style={styles.root}>
      <ScreenHeader title="Kelola Menu" subtitle="Item yang dijual di kasir" />
      <ScrollView
        contentContainerStyle={{
          padding: spacing.lg,
          paddingBottom: insets.bottom + spacing.xxl,
          gap: spacing.lg,
        }}
      >
        {showForm ? (
          <Card>
            <SectionTitle>{editingId ? 'Ubah Menu' : 'Menu Baru'}</SectionTitle>
            <View style={styles.row}>
              <View style={{ flex: 1 }}>
                <Field label="Nama menu">
                  <Input value={name} onChangeText={setName} placeholder="Contoh: Cappuccino" />
                </Field>
              </View>
              <View style={{ width: 80 }}>
                <Field label="Ikon">
                  <Input value={emoji} onChangeText={setEmoji} placeholder="☕" />
                </Field>
              </View>
            </View>

            <View style={styles.emojiRow}>
              {EMOJI_PRESETS.map((preset) => (
                <Pressable
                  key={preset}
                  onPress={() => setEmoji(preset)}
                  style={[styles.emojiChip, emoji === preset && styles.emojiChipActive]}
                >
                  <Text style={styles.emojiChipText}>{preset}</Text>
                </Pressable>
              ))}
            </View>

            <Field label="Kategori">
              <Segmented options={CATEGORY_OPTIONS} value={category} onChange={setCategory} />
            </Field>
            <Field label="Harga jual">
              <RupiahInput value={price} onChangeText={setPrice} placeholder="0" />
            </Field>
            <Field label="Keterangan (opsional)">
              <Input value={description} onChangeText={setDescription} placeholder="Catatan singkat" />
            </Field>
            <View style={styles.formActions}>
              <View style={{ flex: 1 }}>
                <PrimaryButton label="Batal" tone="secondary" onPress={resetForm} />
              </View>
              <View style={{ flex: 1 }}>
                <PrimaryButton label={editingId ? 'Simpan' : 'Tambah'} onPress={handleSave} />
              </View>
            </View>
          </Card>
        ) : (
          <PrimaryButton label="+ Tambah Menu" onPress={() => setShowForm(true)} />
        )}

        <Callout tone="info">
          Menu aktif otomatis muncul di layar Kasir POS dan Pesan Online. Nonaktifkan untuk
          menyembunyikannya tanpa menghapus.
        </Callout>

        <View style={{ gap: spacing.sm }}>
          <SectionTitle>Semua Menu ({products.length})</SectionTitle>
          {products.length === 0 ? (
            <Card>
              <EmptyState icon="🍽️" title="Belum ada menu" hint="Tambahkan item menu pertama." />
            </Card>
          ) : (
            products.map((product) => (
              <Card key={product.id} style={styles.menuCard}>
                <View style={styles.menuHead}>
                  <View style={styles.emojiWrap}>
                    <Text style={styles.emoji}>{product.emoji}</Text>
                  </View>
                  <View style={{ flex: 1 }}>
                    <Text style={styles.menuName}>{product.name}</Text>
                    <View style={styles.menuMetaRow}>
                      <Badge label={CATEGORY_LABELS[product.category] ?? product.category} tone="slate" />
                      {product.is_active !== 1 ? <Badge label="Nonaktif" tone="rose" /> : null}
                    </View>
                    {product.description ? (
                      <Text style={styles.menuDesc}>{product.description}</Text>
                    ) : null}
                  </View>
                  <Text style={styles.menuPrice}>{formatRupiah(product.price)}</Text>
                </View>
                <View style={styles.menuFoot}>
                  <View style={styles.switchRow}>
                    <Switch
                      value={product.is_active === 1}
                      onValueChange={(value) => toggleLocalProduct(product.id, value).then(refresh)}
                      trackColor={{ true: colors.brand600, false: colors.slate200 }}
                    />
                    <Text style={styles.switchLabel}>{product.is_active === 1 ? 'Aktif' : 'Nonaktif'}</Text>
                  </View>
                  <View style={styles.footActions}>
                    <Pressable onPress={() => startEdit(product)} hitSlop={8}>
                      <Text style={styles.editText}>Ubah</Text>
                    </Pressable>
                    <Pressable onPress={() => handleDelete(product)} hitSlop={8}>
                      <Text style={styles.deleteText}>Hapus</Text>
                    </Pressable>
                  </View>
                </View>
              </Card>
            ))
          )}
        </View>
      </ScrollView>
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.slate100 },
  row: { flexDirection: 'row', gap: spacing.md },
  formActions: { flexDirection: 'row', gap: spacing.md },
  emojiRow: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  emojiChip: {
    width: 40,
    height: 40,
    borderRadius: 10,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    alignItems: 'center',
    justifyContent: 'center',
  },
  emojiChipActive: { borderColor: colors.brand600, backgroundColor: colors.brand50 },
  emojiChipText: { fontSize: 20 },
  menuCard: { gap: spacing.sm },
  menuHead: { flexDirection: 'row', alignItems: 'flex-start', gap: spacing.md },
  emojiWrap: {
    width: 48,
    height: 48,
    borderRadius: 12,
    backgroundColor: colors.brand50,
    alignItems: 'center',
    justifyContent: 'center',
  },
  emoji: { fontSize: 24 },
  menuName: { fontSize: 15, color: colors.slate900, ...font('700') },
  menuMetaRow: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs, marginTop: 4 },
  menuDesc: { fontSize: 12, color: colors.slate500, marginTop: 4 },
  menuPrice: { fontSize: 15, color: colors.brand600, ...font('700') },
  menuFoot: {
    flexDirection: 'row',
    alignItems: 'center',
    justifyContent: 'space-between',
    borderTopWidth: 1,
    borderTopColor: colors.slate100,
    paddingTop: spacing.sm,
  },
  switchRow: { flexDirection: 'row', alignItems: 'center', gap: spacing.sm },
  switchLabel: { fontSize: 13, color: colors.slate600 },
  footActions: { flexDirection: 'row', gap: spacing.lg },
  editText: { fontSize: 13, color: colors.brand600, ...font('700') },
  deleteText: { fontSize: 13, color: colors.red600, ...font('700') },
});
