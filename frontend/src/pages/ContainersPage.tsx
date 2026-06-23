import { useEffect, useState } from 'react';
import clsx from 'clsx';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';

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
  title?: string;
};

function runtimeLabel(runtime: string) {
  switch (runtime) {
    case 'host':
      return 'Host OS';
    case 'image':
      return 'Image';
    default:
      return runtime.toUpperCase();
  }
}

function severityClass(severity: string) {
  switch (severity.toUpperCase()) {
    case 'CRITICAL':
      return 'badge-critical';
    case 'HIGH':
      return 'badge-high';
    case 'MEDIUM':
      return 'badge-medium';
    default:
      return 'badge-low';
  }
}

export function ContainersPage() {
  const { token } = useAuth();
  const [containers, setContainers] = useState<Container[]>([]);
  const [vulnerabilities, setVulnerabilities] = useState<Vulnerability[]>([]);
  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(false);

  async function loadData() {
    if (!token) return;
    const [inventory, vulns] = await Promise.all([
      api<{ containers: Container[] }>('/api/v1/containers', {}, token),
      api<{ vulnerabilities: Vulnerability[] }>('/api/v1/containers/vulnerabilities', {}, token),
    ]);
    setContainers(inventory.containers);
    setVulnerabilities(vulns.vulnerabilities);
  }

  useEffect(() => {
    loadData().catch(() => setMessage('Failed to load CVE data.'));
  }, [token]);

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
        </div>
        <button className="btn btn-primary" type="button" disabled={loading} onClick={refreshAll}>
          {loading ? 'Queueing...' : 'Refresh all CVE scans'}
        </button>
      </div>

      {message ? <div className="rounded-lg bg-sky-500/10 px-3 py-2 text-sm text-sky-200">{message}</div> : null}

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
              <th className="pb-2">Package</th>
            </tr>
          </thead>
          <tbody>
            {vulnerabilities.map((vuln) => (
              <tr key={`${vuln.cve_id}-${vuln.container_name}-${vuln.server_alias}`} className="border-t border-slate-800">
                <td className="py-3 pr-4">
                  <a
                    className="text-sky-300 hover:underline"
                    href={`https://nvd.nist.gov/vuln/detail/${vuln.cve_id}`}
                    target="_blank"
                    rel="noreferrer"
                  >
                    {vuln.cve_id}
                  </a>
                </td>
                <td className="py-3 pr-4">
                  <span className={clsx('badge', severityClass(vuln.severity))}>{vuln.severity}</span>
                </td>
                <td className="py-3 pr-4">{vuln.container_name}</td>
                <td className="py-3 pr-4">{vuln.server_alias}</td>
                <td className="py-3">{vuln.package_name ?? '—'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
