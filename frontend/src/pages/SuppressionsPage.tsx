import { FormEvent, useEffect, useState } from 'react';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';

type Suppression = {
  id: number;
  package_name: string;
  server_name: string;
};

export function SuppressionsPage() {
  const { token } = useAuth();
  const [suppressions, setSuppressions] = useState<Suppression[]>([]);
  const [packageName, setPackageName] = useState('');
  const [serverName, setServerName] = useState('0');

  useEffect(() => {
    if (!token) return;
    api<{ suppressions: Suppression[] }>('/api/v1/suppressions', {}, token).then((data) =>
      setSuppressions(data.suppressions),
    );
  }, [token]);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    if (!token) return;
    await api(
      '/api/v1/suppressions',
      { method: 'POST', body: JSON.stringify({ package_name: packageName, server_name: serverName }) },
      token,
    );
    setPackageName('');
    const data = await api<{ suppressions: Suppression[] }>('/api/v1/suppressions', {}, token);
    setSuppressions(data.suppressions);
  }

  async function remove(id: number) {
    if (!token) return;
    await api(`/api/v1/suppressions/${id}`, { method: 'DELETE' }, token);
    setSuppressions((current) => current.filter((item) => item.id !== id));
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-semibold">Suppressions</h1>
        <p className="text-slate-400">Suppress packages globally (server 0) or per server.</p>
      </div>

      <form onSubmit={onSubmit} className="card grid gap-4 md:grid-cols-3">
        <label className="space-y-1 text-sm">
          <span>Package name</span>
          <input className="input" value={packageName} onChange={(e) => setPackageName(e.target.value)} />
        </label>
        <label className="space-y-1 text-sm">
          <span>Server name (0 = global)</span>
          <input className="input" value={serverName} onChange={(e) => setServerName(e.target.value)} />
        </label>
        <div className="flex items-end">
          <button className="btn btn-primary" type="submit">
            Add suppression
          </button>
        </div>
      </form>

      <div className="card overflow-x-auto">
        <table className="min-w-full text-left text-sm">
          <thead className="text-slate-400">
            <tr>
              <th className="pb-2 pr-4">Package</th>
              <th className="pb-2 pr-4">Server</th>
              <th className="pb-2">Actions</th>
            </tr>
          </thead>
          <tbody>
            {suppressions.map((item) => (
              <tr key={item.id} className="border-t border-slate-800">
                <td className="py-3 pr-4">{item.package_name}</td>
                <td className="py-3 pr-4">{item.server_name === '0' ? 'Global' : item.server_name}</td>
                <td className="py-3">
                  <button className="btn btn-secondary" type="button" onClick={() => remove(item.id)}>
                    Delete
                  </button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
