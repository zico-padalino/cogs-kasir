import AsyncStorage from '@react-native-async-storage/async-storage';
import { createContext, useCallback, useContext, useEffect, useMemo, useState, type ReactNode } from 'react';

export type Role = 'cogs' | 'kasir';

export type AuthUser = {
  name: string;
  email: string;
  role: Role;
};

type DemoUser = AuthUser & { password: string };

// Akun demo lokal (mirror akun demo Laravel: cogs@local.test / kasir@local.test).
const DEMO_USERS: DemoUser[] = [
  { name: 'Pengguna COGS', email: 'cogs@local.test', password: 'password', role: 'cogs' },
  { name: 'Pengguna Kasir', email: 'kasir@local.test', password: 'password', role: 'kasir' },
];

export const ROLE_META: Record<Role, { label: string; description: string; homeRoute: '/cogs' | '/kasir' }> = {
  cogs: { label: 'COGS', description: 'Perhitungan biaya produk & produksi', homeRoute: '/cogs' },
  kasir: { label: 'Kasir', description: 'Penjualan & transaksi kasir', homeRoute: '/kasir' },
};

const STORAGE_KEY = 'auth_user';

type AuthContextValue = {
  user: AuthUser | null;
  loading: boolean;
  login: (input: { email: string; password: string; module: Role }) => Promise<void>;
  logout: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    (async () => {
      try {
        const raw = await AsyncStorage.getItem(STORAGE_KEY);
        if (raw) {
          setUser(JSON.parse(raw) as AuthUser);
        }
      } catch {
        // abaikan, mulai sebagai belum login
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  const login = useCallback(async ({ email, password, module }: { email: string; password: string; module: Role }) => {
    const match = DEMO_USERS.find(
      (candidate) => candidate.email.toLowerCase() === email.trim().toLowerCase() && candidate.password === password,
    );

    if (!match) {
      throw new Error('Email atau password salah.');
    }

    if (match.role !== module) {
      throw new Error(`Akun ini tidak memiliki akses modul ${ROLE_META[module].label}.`);
    }

    const nextUser: AuthUser = { name: match.name, email: match.email, role: match.role };
    await AsyncStorage.setItem(STORAGE_KEY, JSON.stringify(nextUser));
    setUser(nextUser);
  }, []);

  const logout = useCallback(async () => {
    await AsyncStorage.removeItem(STORAGE_KEY);
    setUser(null);
  }, []);

  const value = useMemo(() => ({ user, loading, login, logout }), [user, loading, login, logout]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth(): AuthContextValue {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error('useAuth harus dipakai di dalam AuthProvider.');
  }

  return context;
}
