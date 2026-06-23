import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import { api } from './api';

export type User = {
  id: number;
  user_id: string;
  display_name: string;
  email: string;
  admin: boolean;
  active?: boolean;
  receive_alerts?: boolean;
  mfa_enabled?: boolean;
};

export type LoginResult =
  | { status: 'success' }
  | { status: 'mfa_required'; mfaToken: string };

type AuthContextValue = {
  token: string | null;
  user: User | null;
  login: (username: string, password: string) => Promise<LoginResult>;
  verifyMfa: (mfaToken: string, code: string) => Promise<void>;
  logout: () => void;
  refreshUser: () => Promise<void>;
  loading: boolean;
};

const AuthContext = createContext<AuthContextValue | undefined>(undefined);
const TOKEN_KEY = 'pd_token';
const USER_KEY = 'pd_user';

function readStoredUser(): User | null {
  const raw = localStorage.getItem(USER_KEY);
  if (!raw) {
    return null;
  }

  try {
    return JSON.parse(raw) as User;
  } catch {
    localStorage.removeItem(USER_KEY);
    return null;
  }
}

function storeSession(token: string, user: User) {
  localStorage.setItem(TOKEN_KEY, token);
  localStorage.setItem(USER_KEY, JSON.stringify(user));
}

export function AuthProvider({ children }: { children: ReactNode }) {
  const [token, setToken] = useState<string | null>(() => localStorage.getItem(TOKEN_KEY));
  const [user, setUser] = useState<User | null>(() => readStoredUser());
  const [loading, setLoading] = useState(true);

  async function refreshUser() {
    if (!token) return;
    const data = await api<{ user: User }>('/api/v1/auth/me', {}, token);
    setUser(data.user);
    localStorage.setItem(USER_KEY, JSON.stringify(data.user));
  }

  useEffect(() => {
    if (!token) {
      setLoading(false);
      return;
    }
    refreshUser()
      .catch(() => {
        setToken(null);
        setUser(null);
        localStorage.removeItem(TOKEN_KEY);
        localStorage.removeItem(USER_KEY);
      })
      .finally(() => setLoading(false));
  }, [token]);

  const value = useMemo<AuthContextValue>(
    () => ({
      token,
      user,
      loading,
      refreshUser,
      login: async (username, password) => {
        const data = await api<{
          token?: string;
          user?: User;
          mfa_required?: boolean;
          mfa_token?: string;
        }>('/api/v1/auth/login', {
          method: 'POST',
          body: JSON.stringify({ username, password }),
        });

        if (data.mfa_required && data.mfa_token) {
          return { status: 'mfa_required', mfaToken: data.mfa_token };
        }

        if (!data.token || !data.user) {
          throw new Error('Invalid login response');
        }

        setToken(data.token);
        setUser(data.user);
        storeSession(data.token, data.user);
        return { status: 'success' };
      },
      verifyMfa: async (mfaToken, code) => {
        const data = await api<{ token: string; user: User }>('/api/v1/auth/mfa/verify', {
          method: 'POST',
          body: JSON.stringify({ mfa_token: mfaToken, code }),
        });
        setToken(data.token);
        setUser(data.user);
        storeSession(data.token, data.user);
      },
      logout: () => {
        setToken(null);
        setUser(null);
        localStorage.removeItem(TOKEN_KEY);
        localStorage.removeItem(USER_KEY);
      },
    }),
    [token, user, loading],
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
