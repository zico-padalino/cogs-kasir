import { useRouter, usePathname } from 'expo-router';
import { useState, type ReactNode } from 'react';
import { Alert, Modal, Pressable, ScrollView, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { pinApi } from '@/api/kasir';
import { ROLE_META, useAuth, type Role } from '@/auth';
import { colors, font, radius, spacing } from '@/theme';

type NavItem = { label: string; icon: string; route: string; match?: string[] };

const NAV: Record<Role, NavItem[]> = {
  cogs: [
    { label: 'Beranda', icon: '🏠', route: '/cogs' },
    { label: 'Overhead', icon: '⚙️', route: '/cogs/overhead' },
    { label: 'Produk & Resep', icon: '📦', route: '/cogs/products', match: ['/cogs/products', '/cogs/product-detail'] },
    { label: 'Stok Bahan', icon: '🧺', route: '/cogs/inventory' },
    { label: 'Hasil COGS', icon: '📊', route: '/cogs/history', match: ['/cogs/history', '/cogs/calculate', '/cogs/cogs-detail'] },
  ],
  kasir: [
    { label: 'Point of Sale', icon: '🛒', route: '/kasir' },
    { label: 'Riwayat Pesanan', icon: '📋', route: '/kasir/orders', match: ['/kasir/orders', '/kasir/order-detail'] },
    { label: 'Meja QR', icon: '🪑', route: '/kasir/tables' },
    { label: 'Kelola Menu', icon: '🍽️', route: '/kasir/menu', match: ['/kasir/menu', '/kasir/menu-edit'] },
    { label: 'Atur Kategori', icon: '🏷️', route: '/kasir/categories' },
    { label: 'Pembukuan', icon: '📒', route: '/kasir/pembukuan' },
    { label: 'Kas Tunai', icon: '💵', route: '/kasir/kas-tunai' },
  ],
};

const BRAND_HEADER: Record<Role, { badge: string; title: string; subtitle: string }> = {
  cogs: { badge: 'C', title: 'COGS Sederhana', subtitle: 'Hitung biaya produk' },
  kasir: { badge: 'K', title: 'Coffee & Kitchen', subtitle: 'Modul Kasir' },
};

function isActive(item: NavItem, pathname: string): boolean {
  const targets = item.match ?? [item.route];

  return targets.some((target) => (target === '/cogs' || target === '/kasir' ? pathname === target : pathname.startsWith(target)));
}

export function AppDrawer({
  moduleType,
  visible,
  onClose,
}: {
  moduleType: Role;
  visible: boolean;
  onClose: () => void;
}) {
  const router = useRouter();
  const pathname = usePathname();
  const insets = useSafeAreaInsets();
  const { user, logout, pin, setPin } = useAuth();
  const header = BRAND_HEADER[moduleType];

  const go = (route: string) => {
    onClose();
    if (pathname !== route) {
      router.replace(route as never);
    }
  };

  const handleLock = () => {
    onClose();
    Alert.alert('Kunci Kasir', 'Kunci sesi PIN sekarang?', [
      { text: 'Batal', style: 'cancel' },
      {
        text: 'Kunci',
        onPress: async () => {
          try {
            await pinApi.lock();
          } catch {
            // ignore
          }
          setPin({ unlocked: false, expires_at: null, server_now: 0, remaining_seconds: 0 });
          router.replace('/kasir/pin' as never);
        },
      },
    ]);
  };

  const handleLogout = () => {
    onClose();
    Alert.alert('Keluar', 'Yakin ingin keluar dari akun ini?', [
      { text: 'Batal', style: 'cancel' },
      { text: 'Keluar', style: 'destructive', onPress: () => logout() },
    ]);
  };

  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View style={styles.overlayRoot}>
        <View style={[styles.sidebar, { paddingTop: insets.top + spacing.md }]}>
          <View style={styles.sidebarHead}>
            <View style={styles.brandBadge}>
              <Text style={styles.brandBadgeText}>{header.badge}</Text>
            </View>
            <View style={{ flex: 1 }}>
              <Text style={styles.brandTitle}>{header.title}</Text>
              <Text style={styles.brandSubtitle}>{header.subtitle}</Text>
            </View>
          </View>

          <ScrollView style={styles.navScroll} contentContainerStyle={styles.navList}>
            {NAV[moduleType].map((item) => {
              const active = isActive(item, pathname);

              return (
                <Pressable
                  key={item.route}
                  onPress={() => go(item.route)}
                  style={[styles.navItem, active && styles.navItemActive]}
                >
                  <View style={styles.navIconWrap}>
                    <Text style={styles.navIcon}>{item.icon}</Text>
                  </View>
                  <Text style={[styles.navLabel, active && styles.navLabelActive]}>{item.label}</Text>
                </Pressable>
              );
            })}

          </ScrollView>

          <View style={[styles.sidebarFoot, { paddingBottom: insets.bottom + spacing.md }]}>
            <View style={styles.userChip}>
              <Text style={styles.userName} numberOfLines={1}>
                {pin?.operator_name || user?.name || 'Pengguna'}
              </Text>
              <Text style={styles.userRole}>
                {moduleType === 'kasir' ? 'Modul Kasir' : ROLE_META[moduleType].label}
              </Text>
            </View>
            {moduleType === 'kasir' ? (
              <Pressable onPress={handleLock} style={styles.logoutBtn}>
                <Text style={styles.logoutText}>🔒  Kunci Kasir</Text>
              </Pressable>
            ) : null}
            <Pressable onPress={handleLogout} style={styles.logoutBtn}>
              <Text style={styles.logoutText}>↩  Keluar</Text>
            </Pressable>
          </View>
        </View>

        <Pressable style={styles.overlayBackdrop} onPress={onClose} />
      </View>
    </Modal>
  );
}

export function AppScaffold({
  moduleType,
  title,
  subtitle,
  children,
}: {
  moduleType: Role;
  title: string;
  subtitle?: string;
  children: ReactNode;
}) {
  const insets = useSafeAreaInsets();
  const [drawerOpen, setDrawerOpen] = useState(false);

  return (
    <View style={styles.root}>
      <View style={[styles.topbar, { paddingTop: insets.top + spacing.sm }]}>
        <Pressable onPress={() => setDrawerOpen(true)} style={styles.menuBtn} hitSlop={8}>
          <View style={styles.menuLine} />
          <View style={styles.menuLine} />
          <View style={styles.menuLine} />
        </Pressable>
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text style={styles.topbarTitle} numberOfLines={1}>
            {title}
          </Text>
          {subtitle ? (
            <Text style={styles.topbarSubtitle} numberOfLines={1}>
              {subtitle}
            </Text>
          ) : null}
        </View>
      </View>

      <View style={{ flex: 1 }}>{children}</View>

      <AppDrawer moduleType={moduleType} visible={drawerOpen} onClose={() => setDrawerOpen(false)} />
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.slate100 },
  topbar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.slate200,
    backgroundColor: colors.white,
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing.md,
  },
  menuBtn: {
    width: 44,
    height: 44,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    alignItems: 'center',
    justifyContent: 'center',
    gap: 4,
  },
  menuLine: { width: 18, height: 2, borderRadius: 2, backgroundColor: colors.slate700 },
  topbarTitle: { fontSize: 18, color: colors.slate900, ...font('600') },
  topbarSubtitle: { fontSize: 13, color: colors.slate500, marginTop: 2 },
  overlayRoot: { flex: 1, flexDirection: 'row' },
  overlayBackdrop: { flex: 1, backgroundColor: 'rgba(15,23,42,0.5)' },
  sidebar: {
    width: '82%',
    maxWidth: 300,
    backgroundColor: colors.slate900,
    paddingHorizontal: spacing.md,
  },
  sidebarHead: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.slate800,
    paddingBottom: spacing.lg,
    paddingHorizontal: spacing.sm,
  },
  brandBadge: {
    width: 40,
    height: 40,
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  brandBadgeText: { color: colors.white, fontSize: 18, ...font('700') },
  brandTitle: { color: colors.white, fontSize: 15, ...font('600') },
  brandSubtitle: { color: colors.slate400, fontSize: 12, marginTop: 1 },
  navScroll: { flex: 1 },
  navList: { paddingVertical: spacing.md, gap: 2 },
  navItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    minHeight: 44,
    borderRadius: radius.md,
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.sm,
  },
  navItemActive: { backgroundColor: colors.brand600 },
  navIconWrap: {
    width: 26,
    height: 26,
    borderRadius: radius.sm,
    backgroundColor: 'rgba(255,255,255,0.1)',
    alignItems: 'center',
    justifyContent: 'center',
  },
  navIcon: { fontSize: 14 },
  navLabel: { flex: 1, color: colors.slate300, fontSize: 14, ...font('500') },
  navLabelActive: { color: colors.white, ...font('600') },
  sidebarFoot: {
    borderTopWidth: 1,
    borderTopColor: colors.slate800,
    paddingTop: spacing.md,
    gap: spacing.sm,
  },
  userChip: {
    borderRadius: radius.md,
    backgroundColor: 'rgba(30,41,59,0.8)',
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
  },
  userName: { color: colors.white, fontSize: 13, ...font('600') },
  userRole: { color: colors.slate400, fontSize: 11, marginTop: 1 },
  logoutBtn: {
    minHeight: 44,
    borderRadius: radius.md,
    alignItems: 'flex-start',
    justifyContent: 'center',
    paddingHorizontal: spacing.md,
  },
  logoutText: { color: colors.slate300, fontSize: 13, ...font('500') },
});
