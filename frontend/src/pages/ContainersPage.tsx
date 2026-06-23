import { Fragment, useEffect, useMemo, useState } from 'react';
import { Link } from 'react-router-dom';
import clsx from 'clsx';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';
import { runtimeLabel, severityClass } from '../lib/vulnerabilitySearch';

type ContainerRow = {
  id: number;
  name: string;
  runtime: string;
  runtime_id?: string;
  image_ref?: string;
  status?: string;
  server_id: number;
  server_alias: string;
  last_scanned_at?: string;
};

type Vulnerability = {
  cve_id: string;
  severity: string;
  package_name?: string;
  container_name: string;
  server_id?: number;
  runtime?: string;
};

function formatSeen(value?: string) {
  if (!value || value.startsWith('0000')) {
    return 'Never';
  }
  return value;
}

export function ContainersPage() {
  const { token } = useAuth();
  const [containers, setContainers] = useState<ContainerRow[]>([]);
  const [vulnerabilities, setVulnerabilities] = useState<Vulnerability[]>([]);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!token) return;
    Promise.all([
      api<{ containers: ContainerRow[] }>('/api/v1/containers', {}, token),
      api<{ vulnerabilities: Vulnerability[] }>('/api/v1/containers/vulnerabilities', {}, token),
    ])
      .then(([inventory, vulns]) => {
        setContainers(inventory.containers.filter((row) => row.runtime !== 'host'));
        setVulnerabilities(vulns.vulnerabilities.filter((row) => row.runtime !== 'host'));
      })
      .catch(() => setError('Failed to load container data.'));
  }, [token]);

  const cveCountByContainer = useMemo(() => {
    const counts = new Map<string, number>();
    for (const vuln of vulnerabilities) {
      const key = `${vuln.server_id ?? 0}:${vuln.container_name}`;
      counts.set(key, (counts.get(key) ?? 0) + 1);
    }
    return counts;
  }, [vulnerabilities]);

  const grouped = useMemo(() => {
    const byServer = new Map<number, { server_alias: string; containers: ContainerRow[] }>();
    for (const container of containers) {
      const entry = byServer.get(container.server_id) ?? {
        server_alias: container.server_alias,
        containers: [],
      };
      entry.containers.push(container);
      byServer.set(container.server_id, entry);
    }
    return [...byServer.entries()].sort((a, b) => a[1].server_alias.localeCompare(b[1].server_alias));
  }, [containers]);

  const totalCves = vulnerabilities.length;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-semibold">Containers</h1>
        <p className="text-slate-400">
          Workloads nested under trusted servers — monitored for CVEs only. Rebuild images or redeploy to remediate;
          PatchDashboard does not patch containers directly.
        </p>
        {containers.length > 0 ? (
          <p className="mt-1 text-sm text-slate-500">
            {containers.length} container{containers.length === 1 ? '' : 's'} across {grouped.length} server
            {grouped.length === 1 ? '' : 's'}
            {totalCves > 0 ? ` · ${totalCves} open CVE${totalCves === 1 ? '' : 's'}` : ''}
          </p>
        ) : null}
      </div>

      {error ? <div className="rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-200">{error}</div> : null}

      {grouped.length === 0 && !error ? (
        <div className="card text-sm text-slate-400">
          No container or image scans yet. Enable container scanning on a trusted server and wait for the agent
          check-in.
        </div>
      ) : null}

      {grouped.map(([serverId, { server_alias, containers: rows }]) => (
        <div key={serverId} className="card overflow-x-auto">
          <div className="mb-4 flex flex-wrap items-center justify-between gap-2">
            <div>
              <h2 className="text-lg font-medium">{server_alias}</h2>
              <p className="text-sm text-slate-400">
                Parent server ·{' '}
                <Link className="text-sky-400 hover:underline" to={`/servers/${serverId}/workloads`}>
                  View monitoring
                </Link>
              </p>
            </div>
            <Link className="btn btn-secondary" to={`/servers/${serverId}/workloads`}>
              Monitoring
            </Link>
          </div>
          <table className="min-w-full text-left text-sm">
            <thead className="text-slate-400">
              <tr>
                <th className="pb-2 pr-4">Container</th>
                <th className="pb-2 pr-4">Runtime</th>
                <th className="pb-2 pr-4">Image</th>
                <th className="pb-2 pr-4">Status</th>
                <th className="pb-2 pr-4">CVEs</th>
                <th className="pb-2">Last scan</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((container) => {
                const cveCount = cveCountByContainer.get(`${serverId}:${container.name}`) ?? 0;
                return (
                  <tr key={container.id} className="border-t border-slate-800">
                    <td className="py-3 pr-4">{container.name}</td>
                    <td className="py-3 pr-4">{runtimeLabel(container.runtime)}</td>
                    <td className="py-3 pr-4">{container.image_ref ?? '—'}</td>
                    <td className="py-3 pr-4 capitalize">{container.status ?? '—'}</td>
                    <td className="py-3 pr-4">
                      {cveCount > 0 ? (
                        <Link
                          className="text-orange-300 hover:underline"
                          to={`/servers/${serverId}/workloads`}
                        >
                          {cveCount}
                        </Link>
                      ) : (
                        '0'
                      )}
                    </td>
                    <td className="py-3">{formatSeen(container.last_scanned_at)}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      ))}

      {vulnerabilities.length > 0 ? (
        <div className="card overflow-x-auto">
          <h2 className="mb-1 text-lg font-medium">Open container CVEs ({vulnerabilities.length})</h2>
          <p className="mb-4 text-sm text-slate-400">Fleet-wide inventory findings — not patchable from PatchDashboard.</p>
          <table className="min-w-full text-left text-sm">
            <thead className="text-slate-400">
              <tr>
                <th className="pb-2 pr-4">CVE</th>
                <th className="pb-2 pr-4">Severity</th>
                <th className="pb-2 pr-4">Container</th>
                <th className="pb-2 pr-4">Server</th>
                <th className="pb-2">Package</th>
              </tr>
            </thead>
            <tbody>
              {vulnerabilities.map((vuln) => (
                <Fragment key={`${vuln.server_id}-${vuln.container_name}-${vuln.cve_id}`}>
                  <tr className="border-t border-slate-800">
                    <td className="py-3 pr-4">
                      <Link
                        className="text-sky-300 hover:underline"
                        to={`/vulnerabilities?cve=${encodeURIComponent(vuln.cve_id)}&server_id=${vuln.server_id ?? ''}`}
                      >
                        {vuln.cve_id}
                      </Link>
                    </td>
                    <td className="py-3 pr-4">
                      <span className={clsx('badge', severityClass(vuln.severity))}>{vuln.severity}</span>
                    </td>
                    <td className="py-3 pr-4">{vuln.container_name}</td>
                    <td className="py-3 pr-4">
                      {vuln.server_id ? (
                        <Link className="text-sky-400 hover:underline" to={`/servers/${vuln.server_id}/workloads`}>
                          View server
                        </Link>
                      ) : (
                        '—'
                      )}
                    </td>
                    <td className="py-3">{vuln.package_name ?? '—'}</td>
                  </tr>
                </Fragment>
              ))}
            </tbody>
          </table>
        </div>
      ) : null}
    </div>
  );
}
