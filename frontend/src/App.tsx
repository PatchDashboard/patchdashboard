import { Navigate, Route, Routes } from 'react-router-dom';
import { Layout } from './components/Layout';
import { useAuth } from './lib/auth';
import { AddServerPage } from './pages/AddServerPage';
import { AlertsPage } from './pages/AlertsPage';
import { ContainersPage } from './pages/ContainersPage';
import { CveFeedsPage } from './pages/CveFeedsPage';
import { DashboardPage } from './pages/DashboardPage';
import { LoginPage } from './pages/LoginPage';
import { PluginsPage } from './pages/PluginsPage';
import { PreferencesPage } from './pages/PreferencesPage';
import { ProfilePage } from './pages/ProfilePage';
import { SearchPage } from './pages/SearchPage';
import { SetupPage } from './pages/SetupPage';
import { ServerConsolePage } from './pages/ServerConsolePage';
import { ServerEditPage } from './pages/ServerEditPage';
import { ServerPackagesPage } from './pages/ServerPackagesPage';
import { ServerPatchesPage } from './pages/ServerPatchesPage';
import { ServerWorkloadsPage } from './pages/ServerWorkloadsPage';
import { ServersPage } from './pages/ServersPage';
import { SuppressionsPage } from './pages/SuppressionsPage';
import { VulnerabilitiesPage } from './pages/VulnerabilitiesPage';
import { UsersPage } from './pages/UsersPage';

function Protected({ children }: { children: React.ReactNode }) {
  const { token, loading } = useAuth();
  if (loading) {
    return <div className="flex min-h-screen items-center justify-center">Loading...</div>;
  }
  if (!token) {
    return <Navigate to="/login" replace />;
  }
  return <>{children}</>;
}

export default function App() {
  return (
    <Routes>
      <Route path="/setup" element={<SetupPage />} />
      <Route path="/login" element={<LoginPage />} />
      <Route
        path="/"
        element={
          <Protected>
            <Layout />
          </Protected>
        }
      >
        <Route index element={<DashboardPage />} />
        <Route path="search" element={<SearchPage />} />
        <Route path="servers" element={<ServersPage />} />
        <Route path="servers/add" element={<AddServerPage />} />
        <Route path="servers/:id/edit" element={<ServerEditPage />} />
        <Route path="servers/:id/console" element={<ServerConsolePage />} />
        <Route path="servers/:id/patches" element={<ServerPatchesPage />} />
        <Route path="servers/:id/workloads" element={<ServerWorkloadsPage />} />
        <Route path="servers/:id/packages" element={<ServerPackagesPage />} />
        <Route path="containers" element={<ContainersPage />} />
        <Route path="vulnerabilities" element={<VulnerabilitiesPage />} />
        <Route path="suppressions" element={<SuppressionsPage />} />
        <Route path="alerts" element={<AlertsPage />} />
        <Route path="profile" element={<ProfilePage />} />
        <Route path="preferences" element={<PreferencesPage />} />
        <Route path="plugins" element={<PluginsPage />} />
        <Route path="cve-feeds" element={<CveFeedsPage />} />
        <Route path="users" element={<UsersPage />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
