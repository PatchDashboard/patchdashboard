import { useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import clsx from 'clsx';
import { VulnerabilityFilters } from '../components/VulnerabilityFilters';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';
import {
  buildVulnerabilitySearchParams,
  runtimeLabel,
  severityClass,
  type VulnerabilitySearchParams,
} from '../lib/vulnerabilitySearch';

type Container = {
  id: number;
  name: string;
  runtime: string;
  image_ref?: string;
  server_alias: string;
  last_scanned_at?: string;
};

type Vulnerability = {
  cve_id: string;
  severity: string;
  package_name?: string;
  container_name: string;
  server_alias: string;
  server_id?: number;
  runtime?: string;
  title?: string;
  installed_version?: string;
  fixed_version?: string;
};

function paramsToSearchParams(params: VulnerabilitySearchParams): URLSearchParams {
  const next = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== '') {
      next.set(key, value);
    }
  });
  return next;
}

function buildVulnQuery(params: VulnerabilitySearchParams): string {
  const search = paramsToSearchParams(params);
  const qs = search.toString();
  return qs ? `?${qs}` : '';
}

export function ContainersPage() {
  const { token } = useAuth();
  const [urlParams, setUrlParams] = useSearchParams();
  const [containers, setContainers] = useState<Container[]>([]);
  const [vulnerabilities, setVulnerabilities] = useState<Vulnerability[]>([]);
  const [servers, setServers] = useState<Array<{ id: number; server_alias: string }>>([]);
  const [distros, setDistros] = useState<Array<{ id: number; distro_name: string }>>([]);
  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(false);

  const filters = useMemo(() => buildVulnerabilitySearchParams(urlParams), [urlParams]);

  async function loadData() {
    if (!token) return;
    const [inventory, vulns] = await Promise.all([
      api<{ containers: Container[] }>('/api/v1/containers', {}, token),
      api<{ vulnerabilities: Vulnerability[] }>(
        `/api/v1/containers/vulnerabilities${buildVulnQuery(filters)}`,
        {},
        token,
      ),
    ]);
    setContainers(inventory.containers);
    setVulnerabilities(vulns.vulnerabilities);
  }

  useEffect(() => {
    if (!token) return;
    api<{ servers: Array<{ id: number; server_alias: string }> }>('/api/v1/servers', {}, token).then((data) =>
      setServers(data.servers),
    );
    api<{ distros: Array<{ id: number; distro_name: string }> }>('/api/v1/distros', {}, token).then((data) =>
      setDistros(data.distros),
    );
  }, [token]);

  useEffect(() => {
    loadData().catch(() => setMessage('Failed to load CVE data.'));
  }, [token, urlParams]);

  async function refreshAll() {
    if (!token) return;
    setLoading(true);
    setMessage('');
    try {
      const result = await api<{ message: string }>('/api/v1/cve/refresh-all', { method: 'POST' }, token);
      setMessage(result.message);
    } catch {
      setMessage('Failed to queue CVE refresh.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-3xl font-semibold">Application CVEs</h1>
          <p className="text-slate-400">
            Full CVE inventory for host OS packages, container images, and LXC guests. Agents refresh daily.
          </p>
          <Link className="mt-2 inline-block text-sm text-sky-400 hover:underline" to="/vulnerabilities">
            Search across fleet and advisory library
          </Link>
        </div>
        <button className="btn btn-primary" type="button" disabled={loading} onClick={refreshAll}>
          {loading ? 'Queueing...' : 'Refresh all CVE scans'}
        </button>
      </div>

      {message ? <div className="rounded-lg bg-sky-500/10 px-3 py-2 text-sm text-sky-200">{message}</div> : null}

      <VulnerabilityFilters
        params={filters}
        servers={servers}
        distros={distros}
        showSource={false}
        onChange={(next) => setUrlParams(paramsToSearchParams(next))}
      />

      <div className="card overflow-x-auto">
        <h2 className="mb-4 text-lg font-medium">Scanned applications</h2>
        <table className="min-w-full text-left text-sm">
          <thead className="text-slate-400">
            <tr>
              <th className="pb-2 pr-4">Name</th>
              <th className="pb-2 pr-4">Type</th>
              <th className="pb-2 pr-4">Server</th>
              <th className="pb-2 pr-4">Image / ref</th>
              <th className="pb-2">Last scan</th>
            </tr>
          </thead>
          <tbody>
            {containers.map((container) => (
              <tr key={container.id} className="border-t border-slate-800">
                <td className="py-3 pr-4">{container.name}</td>
                <td className="py-3 pr-4">{runtimeLabel(container.runtime)}</td>
                <td className="py-3 pr-4">{container.server_alias}</td>
                <td className="py-3 pr-4">{container.image_ref ?? '—'}</td>
                <td className="py-3">{container.last_scanned_at ?? 'Never'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="card overflow-x-auto">
        <h2 className="mb-4 text-lg font-medium">Open CVEs ({vulnerabilities.length})</h2>
        <table className="min-w-full text-left text-sm">
          <thead className="text-slate-400">
            <tr>
              <th className="pb-2 pr-4">CVE</th>
              <th className="pb-2 pr-4">Severity</th>
              <th className="pb-2 pr-4">Application</th>
              <th className="pb-2 pr-4">Server</th>
              <th className="pb-2 pr-4">Runtime</th>
              <th className="pb-2">Package</th>
            </tr>
          </thead>
          <tbody>
            {vulnerabilities.map((vuln) => (
              <tr key={`${vuln.cve_id}-${vuln.container_name}-${vuln.server_alias}`} className="border-t border-slate-800">
                <td className="py-3 pr-4">
                  <Link className="text-sky-300 hover:underline" to={`/vulnerabilities?cve=${encodeURIComponent(vuln.cve_id)}`}>
                    {vuln.cve_id}
                  </Link>
                </td>
                <td className="py-3 pr-4">
                  <span className={clsx('badge', severityClass(vuln.severity))}>{vuln.severity}</span>
                </td>
                <td className="py-3 pr-4">{vuln.container_name}</td>
                <td className="py-3 pr-4">{vuln.server_alias}</td>
                <td className="py-3 pr-4">{runtimeLabel(vuln.runtime)}</td>
                <td className="py-3">{vuln.package_name ?? '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
