import { NavLink, Outlet } from 'react-router-dom';
import {
  Bell,
  Box,
  LayoutDashboard,
  LogOut,
  MinusCircle,
  Server,
  Users,
} from 'lucide-react';
import clsx from 'clsx';
import { useAuth } from '../lib/auth';

const navItems = [
  { to: '/', label: 'Dashboard', icon: LayoutDashboard, end: true },
  { to: '/servers', label: 'Servers', icon: Server },
  { to: '/containers', label: 'Application CVEs', icon: Box },
  { to: '/suppressions', label: 'Suppressions', icon: MinusCircle },
  { to: '/alerts', label: 'Alerts', icon: Bell },
  { to: '/users', label: 'Users', icon: Users, adminOnly: true },
];

export function Layout() {
  const { user, logout } = useAuth();

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
            .map(({ to, label, icon: Icon, end }) => (
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
                {label}
              </NavLink>
            ))}
        </nav>
        <div className="mt-auto border-t border-slate-800 px-5 py-4 text-sm text-slate-400">
          <div>{user?.display_name ?? user?.user_id}</div>
          <button
            type="button"
            onClick={logout}
            className="mt-3 flex items-center gap-2 text-slate-300 hover:text-white"
          >
            <LogOut size={16} />
            Sign out
          </button>
        </div>
      </aside>
      <main className="px-4 py-6 sm:px-8">
        <Outlet />
      </main>
    </div>
  );
}
