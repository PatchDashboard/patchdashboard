import { FormEvent, useEffect, useState } from 'react';
import { Link, Navigate, useNavigate, useParams } from 'react-router-dom';
import { ServerPowerControls } from '../components/ServerPowerControls';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';
import { containerServerNotice, isContainerServer } from '../lib/servers';

type Server = {
  id: number;
  server_alias: string;
  server_group: string;
  server_ip: string;
  distro_id: number;
  distro_version: number;
  trusted: number;
  last_seen?: string;
  is_container?: boolean;
};

type Distro = {
  id: number;
  distro_name: string;
  versions: Array<{ id: number; version_num: string }>;
};

export function ServerEditPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { token, user } = useAuth();
  const [server, setServer] = useState<Server | null>(null);
  const [distros, setDistros] = useState<Distro[]>([]);
  const [message, setMessage] = useState('');

  useEffect(() => {
    if (!token || !id) return;
    Promise.all([
      api<{ server: Server }>(`/api/v1/servers/${id}`, {}, token),
      api<{ distros: Distro[] }>('/api/v1/distros', {}, token),
    ]).then(([serverData, distroData]) => {
      setServer(serverData.server);
      setDistros(distroData.distros);
    });
  }, [token, id]);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    if (!token || !server) return;
    await api(
      `/api/v1/servers/${server.id}`,
      {
        method: 'PATCH',
        body: JSON.stringify({
          server_alias: server.server_alias,
          server_group: server.server_group,
          server_ip: server.server_ip,
          distro_id: server.distro_id,
          distro_version: server.distro_version,
          is_container: server.is_container ?? false,
        }),
      },
      token,
    );
    setMessage('Server updated.');
  }

  async function removeServer() {
    if (!token || !server || !confirm(`Delete server ${server.server_alias}?`)) return;
    await api(`/api/v1/servers/${server.id}`, { method: 'DELETE' }, token);
    navigate('/servers');
  }

  if (!user?.admin) {
    return <Navigate to="/" replace />;
  }

  if (!server) {
    return <div className="text-slate-400">Loading...</div>;
  }

  const versionOptions =
    distros.find((d) => d.id === server.distro_id)?.versions ??
    distros.flatMap((d) => d.versions);
  const container = isContainerServer(server);

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-3xl font-semibold">Edit server</h1>
          <p className="text-slate-400">{server.server_alias}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          {server.trusted && !container ? (
            <Link className="btn btn-secondary" to={`/servers/${server.id}/console`}>
              Console
            </Link>
          ) : null}
          <Link className="btn btn-secondary" to="/servers">
            Back
          </Link>
        </div>
      </div>

      {container ? (
        <div className="rounded-lg bg-amber-500/10 px-3 py-2 text-sm text-amber-100">{containerServerNotice}</div>
      ) : null}

      {message ? <div className="rounded-lg bg-sky-500/10 px-3 py-2 text-sm text-sky-200">{message}</div> : null}

      <form onSubmit={onSubmit} className="card max-w-xl space-y-4">
        <label className="block space-y-1 text-sm">
          <span>Last seen</span>
          <input className="input" value={server.last_seen ?? 'Never'} readOnly />
        </label>
        <label className="block space-y-1 text-sm">
          <span>Alias</span>
          <input
            className="input"
            value={server.server_alias}
            onChange={(e) => setServer({ ...server, server_alias: e.target.value })}
          />
        </label>
        <label className="block space-y-1 text-sm">
          <span>Group</span>
          <input
            className="input"
            value={server.server_group ?? ''}
            onChange={(e) => setServer({ ...server, server_group: e.target.value })}
          />
        </label>
        <label className="block space-y-1 text-sm">
          <span>IP address</span>
          <input
            className="input"
            value={server.server_ip ?? ''}
            onChange={(e) => setServer({ ...server, server_ip: e.target.value })}
          />
        </label>
        <label className="block space-y-1 text-sm">
          <span>Distro</span>
          <select
            className="input"
            value={server.distro_id}
            onChange={(e) =>
              setServer({ ...server, distro_id: Number(e.target.value), distro_version: 0 })
            }
          >
            {distros.map((d) => (
              <option key={d.id} value={d.id}>
                {d.distro_name}
              </option>
            ))}
          </select>
        </label>
        <label className="block space-y-1 text-sm">
          <span>Version</span>
          <select
            className="input"
            value={server.distro_version}
            onChange={(e) => setServer({ ...server, distro_version: Number(e.target.value) })}
          >
            {versionOptions.map((v) => (
              <option key={v.id} value={v.id}>
                {v.version_num}
              </option>
            ))}
          </select>
        </label>
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={Boolean(server.is_container)}
            onChange={(e) => setServer({ ...server, is_container: e.target.checked })}
          />
          <span>Container workload (disable patch install, reboot, and console)</span>
        </label>
        <div className="flex gap-2">
          <button className="btn btn-primary" type="submit">
            Save
          </button>
          <button className="btn btn-secondary" type="button" onClick={removeServer}>
            Delete server
          </button>
        </div>
      </form>

      {token && server.trusted && !container ? (
        <ServerPowerControls serverId={server.id} token={token} />
      ) : null}
    </div>
  );
}
