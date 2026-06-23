import { Navigate, Route, Routes } from 'react-router-dom';
import { Layout } from './components/Layout';
import { useAuth } from './lib/auth';
import { AlertsPage } from './pages/AlertsPage';
import { ContainersPage } from './pages/ContainersPage';
import { DashboardPage } from './pages/DashboardPage';
import { LoginPage } from './pages/LoginPage';
import { SetupPage } from './pages/SetupPage';
import { ServerPatchesPage } from './pages/ServerPatchesPage';
import { ServersPage } from './pages/ServersPage';
import { SuppressionsPage } from './pages/SuppressionsPage';
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
        <Route path="servers" element={<ServersPage />} />
        <Route path="servers/:id/patches" element={<ServerPatchesPage />} />
        <Route path="containers" element={<ContainersPage />} />
        <Route path="suppressions" element={<SuppressionsPage />} />
        <Route path="alerts" element={<AlertsPage />} />
        <Route path="users" element={<UsersPage />} />
      </Route>
      <Route path="*" element={<Navigate to="/" replace />} />
    </Routes>
  );
}
