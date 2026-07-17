import {
  createContext,
  useCallback,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import AsyncStorage from '@react-native-async-storage/async-storage';
import { authApi } from '@/api/kasir';
import { getToken, setToken } from '@/api/client';
import type { ApiError, AuthUser, PinStatus } from '@/api/types';

export type Role = 'cogs' | 'kasir';

export type { AuthUser };

export const ROLE_META: Record<Role, { label: string; description: string; homeRoute: '/cogs' | '/kasir' }> = {
  cogs: { label: 'COGS', description: 'Perhitungan biaya produk & produksi', homeRoute: '/cogs' },
  kasir: { label: 'Kasir', description: 'Penjualan & transaksi kasir', homeRoute: '/kasir' },
};

const USER_KEY = 'auth_user_v2';

type AuthContextValue = {
  user: AuthUser | null;
  activeModule: Role | null;
  loading: boolean;
  pin: PinStatus | null;
  setPin: (pin: PinStatus | null) => void;
  login: (input: { email: string; password: string; module: Role }) => Promise<void>;
  logout: () => Promise<void>;
  refreshMe: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

function resolveActiveModule(user: AuthUser, preferred: Role): Role {
  if (preferred === 'kasir' && user.has_kasir) {
    return 'kasir';
  }
  if (preferred === 'cogs' && user.has_cogs) {
    return 'cogs';
  }
  if (user.has_kasir) {
    return 'kasir';
  }
  if (user.has_cogs) {
    return 'cogs';
  }
  throw new Error('Akun ini tidak memiliki akses modul yang dipilih.');
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [activeModule, setActiveModule] = useState<Role | null>(null);
  const [loading, setLoading] = useState(true);
  const [pin, setPin] = useState<PinStatus | null>(null);

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
    const module = storedModule && (storedModule === 'kasir' || storedModule === 'cogs')
      ? resolveActiveModule(res.data.user, storedModule)
      : resolveActiveModule(res.data.user, res.data.user.has_kasir ? 'kasir' : 'cogs');
    setActiveModule(module);
    await persist(res.data.user, module);
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
    async ({ email, password, module }: { email: string; password: string; module: Role }) => {
      const res = await authApi.login(email.trim(), password);
      const nextUser = res.data.user;

      if (module === 'kasir' && !nextUser.has_kasir) {
        throw new Error(`Akun ini tidak memiliki akses modul ${ROLE_META.kasir.label}.`);
      }
      if (module === 'cogs' && !nextUser.has_cogs) {
        throw new Error(`Akun ini tidak memiliki akses modul ${ROLE_META.cogs.label}.`);
      }

      await setToken(res.data.token);
      const active = resolveActiveModule(nextUser, module);
      setUser(nextUser);
      setActiveModule(active);
      setPin(null);
      await persist(nextUser, active);
    },
    [persist],
  );

  const logout = useCallback(async () => {
    try {
      await authApi.logout();
    } catch {
      // ignore network errors on logout
    }
    await setToken(null);
    await persist(null, null);
    setUser(null);
    setActiveModule(null);
    setPin(null);
  }, [persist]);

  const value = useMemo(
    () => ({ user, activeModule, loading, pin, setPin, login, logout, refreshMe }),
    [user, activeModule, loading, pin, login, logout, refreshMe],
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
