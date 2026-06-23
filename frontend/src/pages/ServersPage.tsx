import { Fragment, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ServerPowerControls } from '../components/ServerPowerControls';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';
import { serverPowerPending, type PowerAction, type PowerMode } from '../lib/serverPower';

type Server = {
  id: number;
  server_alias: string;
  server_name: string;
  server_ip: string;
  server_group?: string;
  trusted: number;
  distro_name?: string;
  version_num?: string;
  last_seen?: string;
  reboot_cmd_sent?: number;
  shutdown_cmd_sent?: number;
  power_cmd_action?: string | null;
  power_cmd_mode?: string | null;
  power_cmd_scheduled_at?: string | null;
};

function formatSeen(value?: string) {
  if (!value || value.startsWith('0000')) {
    return 'Never';
  }
  return value;
}

export function ServersPage() {
  const { token, user } = useAuth();
  const [servers, setServers] = useState<Server[]>([]);

  useEffect(() => {
    if (!token) return;
    api<{ servers: Server[] }>('/api/v1/servers', {}, token).then((data) => setServers(data.servers));
  }, [token]);

  async function toggleTrusted(server: Server) {
    if (!token) return;

    if (server.trusted) {
      const confirmed = confirm(
        `Untrust ${server.server_alias}? This revokes its agent client key and removes PatchDashboard SSH access.`,
      );
      if (!confirmed) return;
    }

    const path = server.trusted ? 'untrust' : 'activate';
    await api(`/api/v1/servers/${server.id}/${path}`, { method: 'POST' }, token);
    setServers((current) =>
      current.map((item) =>
        item.id === server.id ? { ...item, trusted: server.trusted ? 0 : 1 } : item,
      ),
    );
  }

  async function deleteServer(server: Server) {
    if (!token || !confirm(`Delete server ${server.server_alias}?`)) return;
    await api(`/api/v1/servers/${server.id}`, { method: 'DELETE' }, token);
    setServers((current) => current.filter((item) => item.id !== server.id));
  }

  function markPowerScheduled(
    serverId: number,
    action: PowerAction,
    mode: PowerMode,
    scheduledAt?: string,
  ) {
    setServers((current) =>
      current.map((item) =>
        item.id === serverId
          ? {
              ...item,
              power_cmd_action: action,
              power_cmd_mode: mode,
              power_cmd_scheduled_at: scheduledAt ?? null,
              reboot_cmd_sent: 0,
              shutdown_cmd_sent: 0,
            }
          : item,
      ),
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-3xl font-semibold">Servers</h1>
          <p className="text-slate-400">Manage registered nodes using pull or push agents.</p>
        </div>
        {user?.admin ? (
          <Link className="btn btn-primary" to="/servers/add">
            Add server
          </Link>
        ) : null}
      </div>

      <div className="card overflow-x-auto">
        <table className="min-w-full text-left text-sm">
          <thead className="text-slate-400">
            <tr>
              <th className="pb-2 pr-4">Alias</th>
              <th className="pb-2 pr-4">Group</th>
              <th className="pb-2 pr-4">Distro</th>
              <th className="pb-2 pr-4">IP</th>
              <th className="pb-2 pr-4">Trusted</th>
              <th className="pb-2 pr-4">Last seen</th>
              <th className="pb-2 pr-4">Pending</th>
              <th className="pb-2">Actions</th>
            </tr>
          </thead>
          <tbody>
            {servers.map((server) => (
              <Fragment key={server.id}>
                <tr className="border-t border-slate-800">
                  <td className="py-3 pr-4">{server.server_alias}</td>
                  <td className="py-3 pr-4">{server.server_group || '—'}</td>
                  <td className="py-3 pr-4">
                    {server.distro_name ?? 'Unknown'} {server.version_num ?? ''}
                  </td>
                  <td className="py-3 pr-4">{server.server_ip}</td>
                  <td className="py-3 pr-4">{server.trusted ? 'Yes' : 'No'}</td>
                  <td className="py-3 pr-4">{formatSeen(server.last_seen)}</td>
                  <td className="py-3 pr-4 text-slate-300">{serverPowerPending(server) ?? '—'}</td>
                  <td className="py-3">
                    <div className="flex flex-wrap gap-2">
                      <Link className="btn btn-secondary" to={`/servers/${server.id}/patches`}>
                        Patches
                      </Link>
                      <Link className="btn btn-secondary" to={`/servers/${server.id}/packages`}>
                        Packages
                      </Link>
                      {user?.admin && server.trusted ? (
                        <Link className="btn btn-secondary" to={`/servers/${server.id}/console`}>
                          Console
                        </Link>
                      ) : null}
                      {user?.admin ? (
                        <Link className="btn btn-secondary" to={`/servers/${server.id}/edit`}>
                          Edit
                        </Link>
                      ) : null}
                      <button className="btn btn-secondary" type="button" onClick={() => toggleTrusted(server)}>
                        {server.trusted ? 'Untrust' : 'Trust'}
                      </button>
                      {user?.admin ? (
                        <button className="btn btn-secondary" type="button" onClick={() => deleteServer(server)}>
                          Delete
                        </button>
                      ) : null}
                    </div>
                  </td>
                </tr>
                {server.trusted && token ? (
                  <tr className="border-t border-slate-800/50 bg-slate-950/40">
                    <td colSpan={8} className="px-3 py-3">
                      <ServerPowerControls
                        compact
                        serverId={server.id}
                        token={token}
                        onScheduled={(action, mode, scheduledAt) =>
                          markPowerScheduled(server.id, action, mode, scheduledAt)
                        }
                      />
                    </td>
                  </tr>
                ) : null}
              </Fragment>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
