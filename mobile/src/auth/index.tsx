import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useRef,
  useState,
  type ReactNode,
} from 'react';
import { Alert, DevSettings } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { authApi } from '@/api/kasir';
import { getToken, isPinSessionError, setPinLockedListener, setToken } from '@/api/client';
import type { ApiError, AuthUser, PinStatus } from '@/api/types';
import { isDapurOnlyApp } from '@/config/appModule';
import { resetPendingTracker } from '@/kasir/pendingOrderTracker';
import { resetKitchenTracker } from '@/dapur/kitchenOrderTracker';
import { registerKasirPushToken, unregisterKasirPushToken } from '@/kasir/pushNotifications';

export type Role = 'cogs' | 'kasir' | 'dapur';

export type { AuthUser };

export const ROLE_META: Record<
  Role,
  { label: string; description: string; homeRoute: '/cogs' | '/kasir' | '/dapur' }
> = {
  cogs: { label: 'COGS', description: 'Perhitungan biaya produk & produksi', homeRoute: '/cogs' },
  kasir: { label: 'Kasir', description: 'Penjualan & transaksi kasir', homeRoute: '/kasir' },
  dapur: { label: 'Dapur', description: 'Antrian pesanan & suara menu', homeRoute: '/dapur' },
};

const USER_KEY = 'auth_user_v2';

const LOCKED_PIN: PinStatus = {
  unlocked: false,
  expires_at: null,
  server_now: 0,
  remaining_seconds: 0,
  operator_name: null,
};

type AuthContextValue = {
  user: AuthUser | null;
  activeModule: Role | null;
  loading: boolean;
  pin: PinStatus | null;
  setPin: (pin: PinStatus | null) => void;
  lockPinSession: () => void;
  switchModule: (module: Role) => Promise<void>;
  login: (input: { email: string; password: string }) => Promise<void>;
  logout: () => Promise<void>;
  refreshMe: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

/** Mirror web preferredLoginModule: kasir > cogs. APK dapur → selalu dapur. */
function preferredModule(user: AuthUser): Role {
  if (isDapurOnlyApp()) {
    if (!user.has_kasir) {
      throw new Error('Akun ini tidak punya akses dapur/kasir.');
    }
    return 'dapur';
  }
  if (user.has_kasir) {
    return 'kasir';
  }
  if (user.has_cogs) {
    return 'cogs';
  }
  throw new Error('Akun ini belum memiliki akses modul.');
}

function resolveActiveModule(user: AuthUser, preferred?: Role | null): Role {
  if (isDapurOnlyApp()) {
    if (!user.has_kasir) {
      return preferredModule(user);
    }
    return 'dapur';
  }
  if (preferred === 'dapur' && user.has_kasir) {
    return 'dapur';
  }
  if (preferred === 'kasir' && user.has_kasir) {
    return 'kasir';
  }
  if (preferred === 'cogs' && user.has_cogs) {
    return 'cogs';
  }
  return preferredModule(user);
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [activeModule, setActiveModule] = useState<Role | null>(null);
  const [loading, setLoading] = useState(true);
  const [pin, setPin] = useState<PinStatus | null>(null);
  const activeModuleRef = useRef<Role | null>(null);
  activeModuleRef.current = activeModule;

  const lockPinSession = useCallback(() => {
    setPin((prev) => ({
      ...(prev || LOCKED_PIN),
      unlocked: false,
      expires_at: null,
      remaining_seconds: 0,
      operator_name: null,
    }));
  }, []);

  useEffect(() => {
    setPinLockedListener(() => {
      // Dapur bebas PIN — abaikan 423 saat modul dapur aktif.
      if (activeModuleRef.current === 'dapur') {
        return;
      }
      lockPinSession();
    });
    return () => setPinLockedListener(null);
  }, [lockPinSession]);

  const persist = useCallback(async (nextUser: AuthUser | null, module: Role | null) => {
    if (!nextUser || !module) {
      await AsyncStorage.multiRemove([USER_KEY, 'auth_module']);
      return;
    }
    await AsyncStorage.setItem(USER_KEY, JSON.stringify(nextUser));
    await AsyncStorage.setItem('auth_module', module);
  }, []);

  const refreshMe = useCallback(async () => {
    const token = await getToken();
    if (!token) {
      setUser(null);
      setActiveModule(null);
      setPin(null);
      return;
    }

    const res = await authApi.me();
    setUser(res.data.user);
    setPin(res.data.pin);
    const storedModule = (await AsyncStorage.getItem('auth_module')) as Role | null;
    const module = resolveActiveModule(res.data.user, storedModule);
    setActiveModule(module);
    await persist(res.data.user, module);

    if (res.data.user.has_kasir) {
      registerKasirPushToken().catch(() => {});
    }
  }, [persist]);

  useEffect(() => {
    (async () => {
      try {
        const token = await getToken();
        if (!token) {
          return;
        }
        await refreshMe();
      } catch {
        await setToken(null);
        setUser(null);
        setActiveModule(null);
      } finally {
        setLoading(false);
      }
    })();
  }, [refreshMe]);

  const login = useCallback(
    async ({ email, password }: { email: string; password: string }) => {
      const res = await authApi.login(email.trim(), password);
      const nextUser = res.data.user;
      await setToken(res.data.token);
      const active = preferredModule(nextUser);
      setUser(nextUser);
      setActiveModule(active);
      setPin(LOCKED_PIN);
      await persist(nextUser, active);

      if (nextUser.has_kasir) {
        registerKasirPushToken().catch(() => {});
      }
    },
    [persist],
  );

  const logout = useCallback(async () => {
    try {
      await unregisterKasirPushToken();
      await authApi.logout();
    } catch {
      // ignore
    }
    await setToken(null);
    await persist(null, null);
    resetPendingTracker();
    resetKitchenTracker();
    setUser(null);
    setActiveModule(null);
    setPin(null);
  }, [persist]);

  const switchModule = useCallback(
    async (module: Role) => {
      if (!user) return;
      if (isDapurOnlyApp() && module !== 'dapur') {
        return;
      }
      const next = resolveActiveModule(user, module);
      // Backend hanya kenal kasir/cogs/admin — dapur = akses kasir di sisi server.
      const apiModule = next === 'dapur' ? 'kasir' : next;
      try {
        await authApi.switchModule(apiModule);
      } catch {
        // local switch tetap jalan jika endpoint gagal
      }
      setActiveModule(next);
      await persist(user, next);
    },
    [persist, user],
  );

  const value = useMemo(
    () => ({
      user,
      activeModule,
      loading,
      pin,
      setPin,
      lockPinSession,
      switchModule,
      login,
      logout,
      refreshMe,
    }),
    [user, activeModule, loading, pin, lockPinSession, switchModule, login, logout, refreshMe],
  );

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const ctx = useContext(AuthContext);
  if (!ctx) {
    throw new Error('useAuth must be used within AuthProvider');
  }
  return ctx;
}

export function asApiError(err: unknown): ApiError {
  return err as ApiError;
}

export { isPinSessionError };

/** Alert error biasa; sesi PIN habis diabaikan (redirect global ke /kasir/pin). */
export function reportApiError(err: unknown, title = 'Gagal'): void {
  if (isPinSessionError(err)) {
    return;
  }
  const apiErr = asApiError(err);
  if (apiErr.status === 503 || apiErr.status === 429 || apiErr.status === 508) {
    Alert.alert(
      'Server sedang sibuk',
      'Koneksi penuh sementara. Tunggu sebentar, lalu muat ulang.',
      [
        { text: 'Tutup', style: 'cancel' },
        {
          text: 'Muat ulang',
          onPress: () => {
            try {
              DevSettings.reload();
            } catch {
              // ignore
            }
          },
        },
      ],
    );
    return;
  }
  Alert.alert(title, apiErr.message || 'Terjadi kesalahan.');
}
