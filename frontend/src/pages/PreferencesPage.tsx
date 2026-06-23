import { FormEvent, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';

type Suppression = {
  id: number;
  package_name: string;
  server_name: string;
};

export function PreferencesPage() {
  const { token, refreshUser } = useAuth();
  const [receiveAlerts, setReceiveAlerts] = useState(false);
  const [globalPackage, setGlobalPackage] = useState('');
  const [suppressions, setSuppressions] = useState<Suppression[]>([]);
  const [message, setMessage] = useState('');

  async function loadSuppressions() {
    if (!token) return;
    const data = await api<{ suppressions: Suppression[] }>('/api/v1/suppressions', {}, token);
    setSuppressions(data.suppressions.filter((item) => item.server_name === '0'));
  }

  useEffect(() => {
    if (!token) return;
    api<{ user: { receive_alerts: boolean } }>('/api/v1/auth/me', {}, token).then((data) =>
      setReceiveAlerts(data.user.receive_alerts),
    );
    loadSuppressions();
  }, [token]);

  async function saveAlerts(event: FormEvent) {
    event.preventDefault();
    if (!token) return;
    await api(
      '/api/v1/auth/me',
      { method: 'PATCH', body: JSON.stringify({ receive_alerts: receiveAlerts }) },
      token,
    );
    await refreshUser();
    setMessage('Preferences saved.');
  }

  async function addGlobalSuppress(event: FormEvent) {
    event.preventDefault();
    if (!token || !globalPackage.trim()) return;
    const pkg = globalPackage.trim();
    await api(
      '/api/v1/suppressions',
      {
        method: 'POST',
        body: JSON.stringify({ package_name: pkg, server_name: '0' }),
      },
      token,
    );
    setGlobalPackage('');
    await loadSuppressions();
    setMessage(`Global suppression added for ${pkg}.`);
  }

  async function removeSuppression(id: number) {
    if (!token) return;
    await api(`/api/v1/suppressions/${id}`, { method: 'DELETE' }, token);
    setSuppressions((current) => current.filter((item) => item.id !== id));
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-semibold">Preferences</h1>
        <p className="text-slate-400">Notification and suppression defaults.</p>
      </div>

      {message ? <div className="rounded-lg bg-sky-500/10 px-3 py-2 text-sm text-sky-200">{message}</div> : null}

      <form onSubmit={saveAlerts} className="card max-w-xl space-y-4">
        <h2 className="text-lg font-medium">Notifications</h2>
        <label className="flex items-center gap-2 text-sm">
          <input type="checkbox" checked={receiveAlerts} onChange={(e) => setReceiveAlerts(e.target.checked)} />
          Email me when alert rules trigger
        </label>
        <button className="btn btn-primary" type="submit">
          Save notification preferences
        </button>
      </form>

      <form onSubmit={addGlobalSuppress} className="card max-w-xl space-y-4">
        <h2 className="text-lg font-medium">Global suppress</h2>
        <p className="text-sm text-slate-400">
          Quickly suppress a package on all servers. Manage the full list on the{' '}
          <Link className="text-sky-300 hover:underline" to="/suppressions">
            suppressions page
          </Link>
          .
        </p>
        <label className="block space-y-1 text-sm">
          <span>Package name</span>
          <input className="input" value={globalPackage} onChange={(e) => setGlobalPackage(e.target.value)} />
        </label>
        <button className="btn btn-secondary" type="submit">
          Add global suppression
        </button>
      </form>

      {suppressions.length > 0 ? (
        <div className="card max-w-xl overflow-x-auto">
          <h2 className="mb-4 text-lg font-medium">Suppressed packages</h2>
          <table className="min-w-full text-left text-sm">
            <thead className="text-slate-400">
              <tr>
                <th className="pb-2 pr-4">Package</th>
                <th className="pb-2">Actions</th>
              </tr>
            </thead>
            <tbody>
              {suppressions.map((item) => (
                <tr key={item.id} className="border-t border-slate-800">
                  <td className="py-3 pr-4">{item.package_name}</td>
                  <td className="py-3">
                    <button className="btn btn-secondary" type="button" onClick={() => removeSuppression(item.id)}>
                      Delete
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </div>
  );
}
