import AsyncStorage from '@react-native-async-storage/async-storage';
import { useRouter, usePathname } from 'expo-router';
import { useCallback, useEffect, useState, type ReactNode } from 'react';
import {
  Alert,
  Image,
  Modal,
  Pressable,
  ScrollView,
  StyleSheet,
  Text,
  useWindowDimensions,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { authApi, pinApi } from '@/api/kasir';
import { ROLE_META, useAuth, type Role } from '@/auth';
import { colors, font, fontDisplay, radius, spacing } from '@/theme';
import { resolveMediaUrl } from '@/utils/mediaUrl';

type NavItem = { label: string; icon: string; route: string; match?: string[] };

/** Samakan web: sidebar ≥768, POS split menu|cart saat landscape atau ≥900. */
export const SIDEBAR_BREAKPOINT = 768;
export const POS_SPLIT_MIN_WIDTH = 900;
export const SIDEBAR_WIDTH = 256;
const SIDEBAR_STORAGE_KEY = 'pos-sidebar-collapsed';

const NAV: Record<Role, NavItem[]> = {
  cogs: [
    { label: 'Beranda', icon: '🏠', route: '/cogs' },
    { label: 'Overhead', icon: '⚙️', route: '/cogs/overhead' },
    { label: 'Produk & Resep', icon: '📦', route: '/cogs/products', match: ['/cogs/products', '/cogs/product-detail'] },
    { label: 'Stok Bahan Baku', icon: '🧺', route: '/cogs/inventory' },
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
  return targets.some((target) =>
    target === '/cogs' || target === '/kasir' ? pathname === target : pathname.startsWith(target),
  );
}

/** Menu|cart berdampingan: landscape ATAU lebar tablet (≥700). */
export function usePosSplitLayout(): boolean {
  const { width, height } = useWindowDimensions();
  // Rasio landscape longgar agar tablet/phone rotasi pasti split.
  return width > height * 0.95 || width >= 700;
}

export function useSidebarLayout() {
  const { width } = useWindowDimensions();
  const isDesktop = width >= SIDEBAR_BREAKPOINT;
  const [collapsed, setCollapsedState] = useState(false);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    let alive = true;
    AsyncStorage.getItem(SIDEBAR_STORAGE_KEY)
      .then((value) => {
        if (!alive) return;
        setCollapsedState(value === '1');
      })
      .finally(() => {
        if (alive) setReady(true);
      });
    return () => {
      alive = false;
    };
  }, []);

  const setCollapsed = useCallback((next: boolean) => {
    setCollapsedState(next);
    void AsyncStorage.setItem(SIDEBAR_STORAGE_KEY, next ? '1' : '0');
  }, []);

  const toggleCollapsed = useCallback(() => {
    setCollapsed(!collapsed);
  }, [collapsed, setCollapsed]);

  const showPermanent = isDesktop && !collapsed;

  return {
    isDesktop,
    collapsed,
    ready,
    showPermanent,
    setCollapsed,
    toggleCollapsed,
  };
}

/** @deprecated pakai useSidebarLayout().showPermanent */
export function useShowPermanentSidebar(): boolean {
  return useSidebarLayout().showPermanent;
}

type SidebarBodyProps = {
  moduleType: Role;
  onNavigate?: () => void;
  onCollapse?: () => void;
  showCollapse?: boolean;
  compact?: boolean;
};

function SidebarBody({ moduleType, onNavigate, onCollapse, showCollapse, compact }: SidebarBodyProps) {
  const router = useRouter();
  const pathname = usePathname();
  const insets = useSafeAreaInsets();
  const { user, logout, pin, lockPinSession } = useAuth();
  const header = BRAND_HEADER[moduleType];
  const [shopName, setShopName] = useState(header.title);
  const [shopLogo, setShopLogo] = useState<string | null>(null);
  const [shopInitial, setShopInitial] = useState(header.badge);

  useEffect(() => {
    if (moduleType !== 'kasir') return;
    authApi
      .shop()
      .then((res) => {
        setShopName(res.data.name || header.title);
        setShopLogo(resolveMediaUrl(res.data.logo_url));
        setShopInitial(res.data.initial || (res.data.name?.[0] || 'K').toUpperCase());
      })
      .catch(() => {});
  }, [moduleType, header.title]);

  const go = (route: string) => {
    onNavigate?.();
    if (pathname !== route) {
      router.replace(route as never);
    }
  };

  const handleLock = () => {
    onNavigate?.();
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
          lockPinSession();
          router.replace('/kasir/pin' as never);
        },
      },
    ]);
  };

  const handleLogout = () => {
    onNavigate?.();
    Alert.alert('Keluar', 'Yakin ingin keluar dari akun ini?', [
      { text: 'Batal', style: 'cancel' },
      { text: 'Keluar', style: 'destructive', onPress: () => logout() },
    ]);
  };

  const operatorName = pin?.operator_name || user?.name || 'Pengguna';
  const hasOperatorPin = Boolean(pin?.operator_name);

  return (
    <View style={[styles.sidebarInner, compact && styles.sidebarInnerCompact, { paddingTop: insets.top + spacing.md }]}>
      <View style={styles.sidebarHead}>
        {shopLogo ? (
          <Image source={{ uri: shopLogo }} style={styles.brandLogo} />
        ) : (
          <View style={styles.brandBadge}>
            <Text style={styles.brandBadgeText}>{moduleType === 'kasir' ? shopInitial : header.badge}</Text>
          </View>
        )}
        <View style={{ flex: 1, minWidth: 0 }}>
          <Text style={styles.brandTitle} numberOfLines={1}>
            {moduleType === 'kasir' ? shopName : header.title}
          </Text>
          <Text style={styles.brandSubtitle}>{header.subtitle}</Text>
        </View>
        {showCollapse && onCollapse ? (
          <Pressable onPress={onCollapse} style={styles.collapseBtn} hitSlop={8} accessibilityLabel="Sembunyikan menu">
            <Text style={styles.collapseBtnText}>«</Text>
          </Pressable>
        ) : null}
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
              <View style={[styles.navIconWrap, active && styles.navIconWrapActive]}>
                <Text style={styles.navIcon}>{item.icon}</Text>
              </View>
              <Text style={[styles.navLabel, active && styles.navLabelActive]} numberOfLines={1}>
                {item.label}
              </Text>
            </Pressable>
          );
        })}
      </ScrollView>

      <View style={[styles.sidebarFoot, { paddingBottom: Math.max(insets.bottom, spacing.md) }]}>
        <View style={styles.userChip}>
          <Text style={styles.userName} numberOfLines={1}>
            {operatorName}
          </Text>
          <Text style={styles.userRole}>
            {moduleType === 'kasir'
              ? hasOperatorPin
                ? 'Kasir bertugas · PIN aktif'
                : 'Modul Kasir'
              : ROLE_META[moduleType].label}
          </Text>
          {moduleType === 'kasir' && hasOperatorPin && user?.name ? (
            <Text style={styles.userHint} numberOfLines={1}>
              Stasiun: {user.name}
            </Text>
          ) : null}
        </View>

        {moduleType === 'kasir' ? (
          <Pressable
            onPress={() => {
              onNavigate?.();
              router.push('/kasir/ubah-pin' as never);
            }}
            style={styles.logoutBtn}
          >
            <Text style={styles.logoutText}>🔢  Ubah PIN</Text>
          </Pressable>
        ) : null}

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
  );
}

export function PermanentSidebar({
  moduleType,
  onCollapse,
}: {
  moduleType: Role;
  onCollapse?: () => void;
}) {
  return (
    <View style={styles.permanentSidebar}>
      <SidebarBody moduleType={moduleType} compact showCollapse={!!onCollapse} onCollapse={onCollapse} />
    </View>
  );
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
  return (
    <Modal visible={visible} transparent animationType="fade" onRequestClose={onClose}>
      <View style={styles.overlayRoot}>
        <View style={styles.drawerSidebar}>
          <SidebarBody moduleType={moduleType} onNavigate={onClose} />
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
  const { isDesktop, showPermanent, toggleCollapsed, setCollapsed } = useSidebarLayout();
  const [drawerOpen, setDrawerOpen] = useState(false);

  const onMenuPress = () => {
    if (isDesktop) {
      // Mirror web: ☰ di desktop = expand/collapse sidebar
      toggleCollapsed();
      return;
    }
    setDrawerOpen(true);
  };

  return (
    <View style={[styles.root, showPermanent && styles.rootWithSidebar]}>
      {showPermanent ? (
        <PermanentSidebar moduleType={moduleType} onCollapse={() => setCollapsed(true)} />
      ) : null}

      <View style={styles.contentCol}>
        <View style={[styles.topbar, { paddingTop: insets.top + spacing.sm }]}>
          <Pressable onPress={onMenuPress} style={styles.menuBtn} hitSlop={8} accessibilityLabel="Menu">
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
      </View>

      {!isDesktop ? (
        <AppDrawer moduleType={moduleType} visible={drawerOpen} onClose={() => setDrawerOpen(false)} />
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  root: { flex: 1, backgroundColor: colors.slate100 },
  rootWithSidebar: { flexDirection: 'row' },
  contentCol: { flex: 1, minWidth: 0 },
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
  overlayBackdrop: { flex: 1, backgroundColor: 'rgba(28,20,16,0.42)' },
  permanentSidebar: {
    width: SIDEBAR_WIDTH,
    flexGrow: 0,
    flexShrink: 0,
    backgroundColor: '#ebe3d6',
    borderRightWidth: 1,
    borderRightColor: colors.brand200,
  },
  drawerSidebar: {
    width: '82%',
    maxWidth: 300,
    backgroundColor: '#ebe3d6',
    borderRightWidth: 1,
    borderRightColor: colors.brand200,
    shadowColor: '#1c1410',
    shadowOpacity: 0.08,
    shadowRadius: 16,
    elevation: 8,
  },
  sidebarInner: {
    flex: 1,
    paddingHorizontal: spacing.md,
  },
  sidebarInnerCompact: {
    paddingHorizontal: spacing.sm,
  },
  sidebarHead: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.sm,
    borderBottomWidth: 1,
    borderBottomColor: colors.brand200,
    paddingBottom: spacing.lg,
    paddingHorizontal: spacing.sm,
  },
  collapseBtn: {
    width: 32,
    height: 32,
    borderRadius: radius.sm,
    borderWidth: 1,
    borderColor: colors.brand200,
    backgroundColor: colors.white,
    alignItems: 'center',
    justifyContent: 'center',
  },
  collapseBtnText: { color: colors.espresso, fontSize: 16, ...font('700') },
  brandBadge: {
    width: 40,
    height: 40,
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  brandLogo: {
    width: 40,
    height: 40,
    borderRadius: radius.md,
    backgroundColor: colors.white,
  },
  brandBadgeText: { color: colors.white, fontSize: 18, ...font('700') },
  brandTitle: { color: colors.espresso, fontSize: 15, ...fontDisplay('600') },
  brandSubtitle: { color: colors.copper, fontSize: 12, marginTop: 1, ...font('500') },
  navScroll: { flex: 1 },
  navList: { paddingVertical: spacing.md, gap: 4 },
  navItem: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    minHeight: 44,
    borderRadius: radius.lg,
    paddingHorizontal: spacing.sm,
    paddingVertical: spacing.sm,
  },
  navItemActive: {
    backgroundColor: colors.brand600,
    shadowColor: colors.brand600,
    shadowOpacity: 0.25,
    shadowRadius: 8,
    elevation: 3,
  },
  navIconWrap: {
    width: 28,
    height: 28,
    borderRadius: radius.sm,
    backgroundColor: colors.brand100,
    borderWidth: 1,
    borderColor: colors.brand200,
    alignItems: 'center',
    justifyContent: 'center',
  },
  navIconWrapActive: {
    backgroundColor: 'rgba(255,255,255,0.18)',
    borderColor: 'rgba(255,255,255,0.25)',
  },
  navIcon: { fontSize: 14 },
  navLabel: { flex: 1, color: colors.slate600, fontSize: 14, ...font('500') },
  navLabelActive: { color: colors.white, ...font('600') },
  sidebarFoot: {
    borderTopWidth: 1,
    borderTopColor: colors.brand200,
    paddingTop: spacing.md,
    gap: 2,
  },
  userChip: {
    borderRadius: radius.md,
    backgroundColor: colors.white,
    borderWidth: 1,
    borderColor: colors.brand100,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.sm,
    marginBottom: spacing.sm,
  },
  userName: { color: colors.espresso, fontSize: 13, ...font('600') },
  userRole: { color: colors.slate500, fontSize: 11, marginTop: 1 },
  userHint: { color: colors.slate400, fontSize: 10, marginTop: 2 },
  logoutBtn: {
    minHeight: 40,
    borderRadius: radius.md,
    alignItems: 'flex-start',
    justifyContent: 'center',
    paddingHorizontal: spacing.md,
  },
  logoutText: { color: colors.slate600, fontSize: 13, ...font('500') },
});
