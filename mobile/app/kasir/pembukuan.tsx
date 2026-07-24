import { useFocusEffect, useRouter } from 'expo-router';
import { useCallback, useMemo, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  ScrollView,
  Share,
  StyleSheet,
  Text,
  TextInput,
  View,
} from 'react-native';
import { kasirApi } from '@/api/kasir';
import type { PosOrder } from '@/api/types';
import { AppScaffold } from '@/components/AppScaffold';
import { colors, font, radius, spacing } from '@/theme';
import { formatRupiah } from '@/utils/rupiah';

function todayIso(): string {
  const d = new Date();
  const pad = (n: number) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
}

function currentWeekIso(): string {
  const date = new Date();
  const target = new Date(Date.UTC(date.getFullYear(), date.getMonth(), date.getDate()));
  const dayNum = target.getUTCDay() || 7;
  target.setUTCDate(target.getUTCDate() + 4 - dayNum);
  const yearStart = new Date(Date.UTC(target.getUTCFullYear(), 0, 1));
  const week = Math.ceil(((target.getTime() - yearStart.getTime()) / 86400000 + 1) / 7);
  return `${target.getUTCFullYear()}-W${String(week).padStart(2, '0')}`;
}

function currentMonthIso(): string {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
}

export default function PembukuanScreen() {
  const router = useRouter();
  const [period, setPeriod] = useState('day');
  const [date, setDate] = useState(todayIso());
  const [week, setWeek] = useState(currentWeekIso());
  const [month, setMonth] = useState(currentMonthIso());
  const [loading, setLoading] = useState(true);
  const [openingPdf, setOpeningPdf] = useState(false);
  const [data, setData] = useState<{
    omzet: number;
    omzet_kotor?: number;
    diskon_total?: number;
    count: number;
    average: number;
    range_label?: string;
    period_label?: string;
    orders?: PosOrder[];
    by_payment?: Record<string, { label: string; count: number; total: number }>;
  } | null>(null);

  const queryParams = useMemo(() => {
    const params: Record<string, string> = { period };
    if (period === 'day') params.date = date;
    if (period === 'week') params.week = week;
    if (period === 'month') params.month = month;
    return params;
  }, [period, date, week, month]);

  const refresh = useCallback(async () => {
    setLoading(true);
    try {
      const res = await kasirApi.pembukuan(queryParams);
      setData(res.data as typeof data);
    } catch {
      // PIN_LOCKED → redirect global
    } finally {
      setLoading(false);
    }
  }, [queryParams]);

  useFocusEffect(
    useCallback(() => {
      void refresh();
    }, [refresh]),
  );

  const openPdf = async () => {
    setOpeningPdf(true);
    try {
      const res = await kasirApi.pembukuanPdf(queryParams);
      const payload = res.data as {
        shop_name?: string;
        range_label?: string;
        period_label?: string;
        omzet?: number;
        omzet_kotor?: number;
        diskon_total?: number;
        count?: number;
        average?: number;
      };
      const lines = [
        payload.shop_name || 'Pembukuan',
        `${payload.period_label || ''} · ${payload.range_label || ''}`.trim(),
        `Transaksi: ${payload.count ?? 0}`,
        `Omzet kotor: ${formatRupiah(Number(payload.omzet_kotor ?? payload.omzet ?? 0))}`,
        `Diskon: ${formatRupiah(Number(payload.diskon_total || 0))}`,
        `Omzet bersih: ${formatRupiah(Number(payload.omzet || 0))}`,
        `Rata-rata: ${formatRupiah(Number(payload.average || 0))}`,
      ];
      await Share.share({
        title: 'Ringkasan Pembukuan',
        message: lines.filter(Boolean).join('\n'),
      });
    } catch {
      // ignore
    } finally {
      setOpeningPdf(false);
    }
  };

  return (
    <AppScaffold moduleType="kasir" title="Pembukuan" subtitle="Laporan penjualan">
      <View style={styles.filterCard}>
        <Text style={styles.filterLabel}>Periode</Text>
        <View style={styles.periodRow}>
          {[
            { value: 'day', label: 'Harian' },
            { value: 'week', label: 'Mingguan' },
            { value: 'month', label: 'Bulanan' },
            { value: 'all', label: 'Semua' },
          ].map((p) => (
            <Pressable
              key={p.value}
              onPress={() => setPeriod(p.value)}
              style={[styles.periodChip, period === p.value && styles.periodChipOn]}
            >
              <Text style={[styles.periodText, period === p.value && styles.periodTextOn]}>{p.label}</Text>
            </Pressable>
          ))}
        </View>

        {period === 'day' ? (
          <>
            <Text style={styles.filterLabel}>Tanggal (YYYY-MM-DD)</Text>
            <TextInput value={date} onChangeText={setDate} style={styles.input} placeholder="2026-07-24" />
          </>
        ) : null}
        {period === 'week' ? (
          <>
            <Text style={styles.filterLabel}>Minggu (YYYY-Www)</Text>
            <TextInput value={week} onChangeText={setWeek} style={styles.input} placeholder="2026-W30" />
          </>
        ) : null}
        {period === 'month' ? (
          <>
            <Text style={styles.filterLabel}>Bulan (YYYY-MM)</Text>
            <TextInput value={month} onChangeText={setMonth} style={styles.input} placeholder="2026-07" />
          </>
        ) : null}

        <View style={styles.filterActions}>
          <Pressable onPress={() => void refresh()} style={styles.primaryBtn}>
            <Text style={styles.primaryBtnText}>Tampilkan</Text>
          </Pressable>
          <Pressable onPress={() => void openPdf()} disabled={openingPdf} style={styles.outlineBtn}>
            <Text style={styles.outlineBtnText}>{openingPdf ? 'Menyiapkan…' : 'Bagikan ringkasan'}</Text>
          </Pressable>
        </View>
      </View>

      {loading || !data ? (
        <View style={styles.center}>
          <ActivityIndicator color={colors.brand600} />
        </View>
      ) : (
        <ScrollView contentContainerStyle={{ padding: spacing.lg, gap: spacing.md, paddingBottom: spacing.xxl }}>
          <Text style={styles.range}>
            {data.period_label ? `${data.period_label} · ` : ''}
            {data.range_label}
          </Text>
          <View style={styles.stats}>
            <View style={styles.stat}>
              <Text style={styles.statLabel}>Omzet kotor</Text>
              <Text style={styles.statValue}>{formatRupiah(Number(data.omzet_kotor ?? data.omzet ?? 0))}</Text>
            </View>
            <View style={styles.stat}>
              <Text style={styles.statLabel}>Diskon</Text>
              <Text style={styles.statValue}>{formatRupiah(Number(data.diskon_total || 0))}</Text>
            </View>
            <View style={styles.stat}>
              <Text style={styles.statLabel}>Omzet bersih</Text>
              <Text style={styles.statValue}>{formatRupiah(Number(data.omzet || 0))}</Text>
            </View>
          </View>
          <View style={styles.stats}>
            <View style={styles.stat}>
              <Text style={styles.statLabel}>Transaksi</Text>
              <Text style={styles.statValue}>{data.count}</Text>
            </View>
            <View style={styles.stat}>
              <Text style={styles.statLabel}>Rata-rata</Text>
              <Text style={styles.statValue}>{formatRupiah(Number(data.average || 0))}</Text>
            </View>
          </View>

          {data.by_payment
            ? Object.values(data.by_payment).map((p) => (
                <View key={p.label} style={styles.card}>
                  <Text style={styles.cardTitle}>{p.label}</Text>
                  <Text style={styles.meta}>
                    {p.count} trx · {formatRupiah(p.total)}
                  </Text>
                </View>
              ))
            : null}

          {(data.orders || []).map((order) => (
            <View key={order.id} style={styles.card}>
              <Text style={styles.cardTitle}>#{order.order_number}</Text>
              {order.has_discount || (order.discount_amount ?? 0) > 0 ? (
                <Text style={styles.meta}>
                  Normal {formatRupiah(order.subtotal)} · Diskon -{formatRupiah(order.discount_amount || 0)}
                </Text>
              ) : null}
              <Text style={styles.meta}>
                {order.payment_method_label} · Bayar {formatRupiah(order.total)}
              </Text>
              <View style={styles.orderActions}>
                <Pressable
                  onPress={() => router.push(`/kasir/order-detail?id=${order.id}` as never)}
                  style={styles.actionGhost}
                >
                  <Text style={styles.actionGhostText}>Detail</Text>
                </Pressable>
                {order.status === 'paid' || order.status === 'served' ? (
                  <Pressable
                    onPress={() => router.push(`/kasir/receipt?id=${order.id}&from=history` as never)}
                    style={styles.actionOutline}
                  >
                    <Text style={styles.actionOutlineText}>Struk</Text>
                  </Pressable>
                ) : null}
              </View>
            </View>
          ))}
        </ScrollView>
      )}
    </AppScaffold>
  );
}

const styles = StyleSheet.create({
  center: { flex: 1, alignItems: 'center', justifyContent: 'center' },
  filterCard: {
    marginHorizontal: spacing.lg,
    marginTop: spacing.md,
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.md,
    gap: spacing.sm,
  },
  filterLabel: { fontSize: 12, color: colors.slate500, ...font('600') },
  periodRow: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  periodChip: {
    paddingHorizontal: spacing.sm,
    paddingVertical: 6,
    borderRadius: radius.full,
    backgroundColor: colors.slate100,
  },
  periodChipOn: { backgroundColor: colors.brand600 },
  periodText: { fontSize: 12, color: colors.slate600, ...font('600') },
  periodTextOn: { color: colors.white },
  input: {
    borderWidth: 1,
    borderColor: colors.slate200,
    borderRadius: radius.md,
    paddingHorizontal: spacing.md,
    paddingVertical: 10,
    backgroundColor: colors.white,
    color: colors.slate900,
  },
  filterActions: { flexDirection: 'row', gap: spacing.sm, marginTop: spacing.xs },
  primaryBtn: {
    flex: 1,
    minHeight: 42,
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  primaryBtnText: { color: colors.white, ...font('700') },
  outlineBtn: {
    flex: 1,
    minHeight: 42,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate300,
    alignItems: 'center',
    justifyContent: 'center',
  },
  outlineBtnText: { color: colors.brand700, ...font('700'), fontSize: 12, textAlign: 'center' },
  range: { color: colors.slate500, fontSize: 13 },
  stats: { flexDirection: 'row', gap: spacing.sm },
  stat: {
    flex: 1,
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.md,
  },
  statLabel: { fontSize: 11, color: colors.slate500, ...font('600') },
  statValue: { fontSize: 13, color: colors.slate900, ...font('700'), marginTop: 4 },
  card: {
    backgroundColor: colors.white,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    padding: spacing.md,
    gap: 4,
  },
  cardTitle: { fontSize: 14, color: colors.slate900, ...font('700') },
  meta: { fontSize: 12, color: colors.slate500 },
  orderActions: { flexDirection: 'row', justifyContent: 'flex-end', gap: spacing.sm, marginTop: spacing.sm },
  actionGhost: {
    minHeight: 34,
    paddingHorizontal: spacing.md,
    alignItems: 'center',
    justifyContent: 'center',
  },
  actionGhostText: { color: colors.brand700, ...font('600'), fontSize: 13 },
  actionOutline: {
    minHeight: 34,
    paddingHorizontal: spacing.md,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate300,
    alignItems: 'center',
    justifyContent: 'center',
  },
  actionOutlineText: { color: colors.brand700, ...font('600'), fontSize: 13 },
});
