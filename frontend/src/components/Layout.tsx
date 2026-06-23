import { useEffect, useState } from 'react';
import { NavLink, Outlet } from 'react-router-dom';
import {
  Bell,
  Box,
  LayoutDashboard,
  LogOut,
  MinusCircle,
  Puzzle,
  Search,
  Shield,
  ShieldAlert,
  Server,
  Settings,
  User,
  UserPlus,
  Users,
} from 'lucide-react';
import clsx from 'clsx';
import { PackageSearchBox } from './PackageSearchBox';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';

const navItems = [
  { to: '/', label: 'Dashboard', icon: LayoutDashboard, end: true, badgeKey: 'patches' as const },
  { to: '/search', label: 'Search', icon: Search },
  { to: '/servers', label: 'Servers', icon: Server },
  { to: '/containers', label: 'Application CVEs', icon: Box },
  { to: '/vulnerabilities', label: 'Vulnerabilities', icon: ShieldAlert },
  { to: '/suppressions', label: 'Suppressions', icon: MinusCircle },
  { to: '/alerts', label: 'Alerts', icon: Bell },
  { to: '/users', label: 'Users', icon: Users, adminOnly: true },
  { to: '/servers/add', label: 'Add server', icon: UserPlus, adminOnly: true },
  { to: '/plugins', label: 'Plugins', icon: Puzzle, adminOnly: true },
  { to: '/cve-feeds', label: 'CVE feeds', icon: Shield, adminOnly: true },
];

export function Layout() {
  const { user, token, logout } = useAuth();
  const [patchBadge, setPatchBadge] = useState(0);

  useEffect(() => {
    if (!token) return;
    api<{ total_patches: number }>('/api/v1/dashboard', {}, token).then((data) =>
      setPatchBadge(data.total_patches),
    );
  }, [token]);

  return (
    <div className="min-h-screen lg:grid lg:grid-cols-[260px_1fr]">
      <aside className="border-b border-slate-800 bg-slate-950 lg:border-b-0 lg:border-r">
        <div className="flex items-center justify-between px-5 py-4">
          <div>
            <div className="text-lg font-semibold text-sky-400">PatchDashboard</div>
            <div className="text-xs text-slate-400">v2.0 overhaul</div>
          </div>
        </div>
        <nav className="space-y-1 px-3 pb-4">
          {navItems
            .filter((item) => !item.adminOnly || user?.admin)
            .map(({ to, label, icon: Icon, end, badgeKey }) => (
              <NavLink
                key={to}
                to={to}
                end={end}
                className={({ isActive }) =>
                  clsx(
                    'flex items-center gap-3 rounded-lg px-3 py-2 text-sm transition',
                    isActive
                      ? 'bg-sky-500/15 text-sky-300'
                      : 'text-slate-300 hover:bg-slate-900 hover:text-white',
                  )
                }
              >
                <Icon size={18} />
                <span className="flex-1">{label}</span>
                {badgeKey === 'patches' && patchBadge > 0 ? (
                  <span className="rounded-full bg-sky-500/20 px-2 py-0.5 text-xs text-sky-200">{patchBadge}</span>
                ) : null}
              </NavLink>
            ))}
        </nav>
        <div className="border-t border-slate-800 px-5 py-4 text-sm text-slate-400">
          <div>{user?.display_name ?? user?.user_id}</div>
          <div className="mt-3 space-y-2">
            <NavLink to="/profile" className="flex items-center gap-2 text-slate-300 hover:text-white">
              <User size={16} />
              Profile
            </NavLink>
            <NavLink to="/preferences" className="flex items-center gap-2 text-slate-300 hover:text-white">
              <Settings size={16} />
              Preferences
            </NavLink>
            <button
              type="button"
              onClick={logout}
              className="flex items-center gap-2 text-slate-300 hover:text-white"
            >
              <LogOut size={16} />
              Sign out
            </button>
          </div>
        </div>
      </aside>
      <main className="px-4 py-6 sm:px-8">
        <div className="mb-6 max-w-xl">
          <PackageSearchBox />
        </div>
        <Outlet />
      </main>
    </div>
  );
}
