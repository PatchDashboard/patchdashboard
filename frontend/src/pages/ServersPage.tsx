import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';

type Server = {
  id: number;
  server_alias: string;
  server_name: string;
  server_ip: string;
  trusted: number;
  distro_name?: string;
  version_num?: string;
  last_seen?: string;
};

export function ServersPage() {
  const { token } = useAuth();
  const [servers, setServers] = useState<Server[]>([]);

  useEffect(() => {
    if (!token) return;
    api<{ servers: Server[] }>('/api/v1/servers', {}, token).then((data) => setServers(data.servers));
  }, [token]);

  async function toggleTrusted(server: Server) {
    if (!token) return;
    const path = server.trusted ? 'deactivate' : 'activate';
    await api(`/api/v1/servers/${server.id}/${path}`, { method: 'POST' }, token);
    setServers((current) =>
      current.map((item) =>
        item.id === server.id ? { ...item, trusted: server.trusted ? 0 : 1 } : item,
      ),
    );
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-semibold">Servers</h1>
        <p className="text-slate-400">Manage registered nodes using pull or push agents.</p>
      </div>

      <div className="card overflow-x-auto">
        <table className="min-w-full text-left text-sm">
          <thead className="text-slate-400">
            <tr>
              <th className="pb-2 pr-4">Alias</th>
              <th className="pb-2 pr-4">Distro</th>
              <th className="pb-2 pr-4">IP</th>
              <th className="pb-2 pr-4">Trusted</th>
              <th className="pb-2">Actions</th>
            </tr>
          </thead>
          <tbody>
            {servers.map((server) => (
              <tr key={server.id} className="border-t border-slate-800">
                <td className="py-3 pr-4">{server.server_alias}</td>
                <td className="py-3 pr-4">
                  {server.distro_name ?? 'Unknown'} {server.version_num ?? ''}
                </td>
                <td className="py-3 pr-4">{server.server_ip}</td>
                <td className="py-3 pr-4">{server.trusted ? 'Yes' : 'No'}</td>
                <td className="py-3">
                  <div className="flex gap-2">
                    <Link className="btn btn-secondary" to={`/servers/${server.id}/patches`}>
                      Patches
                    </Link>
                    <button className="btn btn-secondary" type="button" onClick={() => toggleTrusted(server)}>
                      {server.trusted ? 'Deactivate' : 'Activate'}
                    </button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
