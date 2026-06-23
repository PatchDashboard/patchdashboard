import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
  type ReactNode,
} from 'react';
import { api } from './api';

type User = {
  id: number;
  user_id: string;
  display_name: string;
  email: string;
  admin: boolean;
};

type AuthContextValue = {
  token: string | null;
  user: User | null;
  login: (username: string, password: string) => Promise<void>;
  logout: () => void;
  loading: boolean;
};

const AuthContext = createContext<AuthContextValue | undefined>(undefined);
const TOKEN_KEY = 'pd_token';
const USER_KEY = 'pd_user';

export function AuthProvider({ children }: { children: ReactNode }) {
  const [token, setToken] = useState<string | null>(() => localStorage.getItem(TOKEN_KEY));
  const [user, setUser] = useState<User | null>(() => {
    const raw = localStorage.getItem(USER_KEY);
    return raw ? (JSON.parse(raw) as User) : null;
  });
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!token) {
      setLoading(false);
      return;
    }
    api<{ user: User }>('/api/v1/auth/me', {}, token)
      .then((data) => {
        setUser(data.user);
        localStorage.setItem(USER_KEY, JSON.stringify(data.user));
      })
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
      login: async (username, password) => {
        const data = await api<{ token: string; user: User }>('/api/v1/auth/login', {
          method: 'POST',
          body: JSON.stringify({ username, password }),
        });
        setToken(data.token);
        setUser(data.user);
        localStorage.setItem(TOKEN_KEY, data.token);
        localStorage.setItem(USER_KEY, JSON.stringify(data.user));
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
