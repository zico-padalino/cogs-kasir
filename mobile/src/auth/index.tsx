import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import { Alert } from 'react-native';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { authApi } from '@/api/kasir';
import { getToken, isPinSessionError, setPinLockedListener, setToken } from '@/api/client';
import type { ApiError, AuthUser, PinStatus } from '@/api/types';
import { registerKasirPushToken, unregisterKasirPushToken } from '@/kasir/pushNotifications';

export type Role = 'cogs' | 'kasir';

export type { AuthUser };

export const ROLE_META: Record<Role, { label: string; description: string; homeRoute: '/cogs' | '/kasir' }> = {
  cogs: { label: 'COGS', description: 'Perhitungan biaya produk & produksi', homeRoute: '/cogs' },
  kasir: { label: 'Kasir', description: 'Penjualan & transaksi kasir', homeRoute: '/kasir' },
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
  login: (input: { email: string; password: string }) => Promise<void>;
  logout: () => Promise<void>;
  refreshMe: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

/** Mirror web preferredLoginModule: kasir > cogs. */
function preferredModule(user: AuthUser): Role {
  if (user.has_kasir) {
    return 'kasir';
  }
  if (user.has_cogs) {
    return 'cogs';
  }
  throw new Error('Akun ini belum memiliki akses modul.');
}

function resolveActiveModule(user: AuthUser, preferred?: Role | null): Role {
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
    setUser(null);
    setActiveModule(null);
    setPin(null);
  }, [persist]);

  const value = useMemo(
    () => ({
      user,
      activeModule,
      loading,
      pin,
      setPin,
      lockPinSession,
      login,
      logout,
      refreshMe,
    }),
    [user, activeModule, loading, pin, lockPinSession, login, logout, refreshMe],
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
  Alert.alert(title, asApiError(err).message || 'Terjadi kesalahan.');
}
