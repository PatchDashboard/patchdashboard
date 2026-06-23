import { useEffect, useMemo, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import clsx from 'clsx';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';
import { remediationLabel, runtimeLabel, severityClass } from '../lib/vulnerabilitySearch';

type Workload = {
  id: number;
  name: string;
  runtime: string;
  image_ref?: string;
  last_scanned_at?: string;
};

type Vulnerability = {
  cve_id: string;
  severity: string;
  package_name?: string;
  container_name: string;
  runtime?: string;
  title?: string;
  installed_version?: string;
  fixed_version?: string;
};

type WorkloadsResponse = {
  server: {
    id: number;
    server_alias: string;
    server_name: string;
    trusted: number;
  };
  host_workloads: Workload[];
  container_workloads: Workload[];
  host_vulnerabilities: Vulnerability[];
  container_vulnerabilities: Vulnerability[];
};

export function ServerWorkloadsPage() {
  const { id } = useParams();
  const { token } = useAuth();
  const [data, setData] = useState<WorkloadsResponse | null>(null);
  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');

  const serverId = Number(id);

  async function loadData() {
    if (!token || !serverId) return;
    const response = await api<WorkloadsResponse>(`/api/v1/servers/${serverId}/workloads`, {}, token);
    setData(response);
  }

  useEffect(() => {
    if (!token || !serverId) return;
    loadData().catch(() => setError('Failed to load monitoring data.'));
  }, [token, serverId]);

  async function refreshScan() {
    if (!token || !serverId) return;
    setLoading(true);
    setMessage('');
    try {
      const result = await api<{ message: string }>(
        `/api/v1/servers/${serverId}/cve/refresh`,
        { method: 'POST' },
        token,
      );
      setMessage(result.message);
    } catch {
      setMessage('Failed to queue CVE refresh.');
    } finally {
      setLoading(false);
    }
  }

  const hostCveCount = data?.host_vulnerabilities.length ?? 0;
  const containerCveCount = data?.container_vulnerabilities.length ?? 0;

  const summary = useMemo(
    () => ({
      workloads: (data?.host_workloads.length ?? 0) + (data?.container_workloads.length ?? 0),
      containers: data?.container_workloads.length ?? 0,
    }),
    [data],
  );

  if (error) {
    return (
      <div className="space-y-4">
        <BackLink />
        <div className="rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-200">{error}</div>
      </div>
    );
  }

  if (!data) {
    return (
      <div className="space-y-4">
        <BackLink />
        <p className="text-slate-400">Loading...</p>
      </div>
    );
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <BackLink />
          <h1 className="mt-2 text-3xl font-semibold">{data.server.server_alias}</h1>
          <p className="text-slate-400">
            Trusted server with read/write management. Host OS CVEs are patchable; nested containers and images are
            monitored only.
          </p>
          <p className="mt-1 text-sm text-slate-500">
            {summary.workloads} scanned workload{summary.workloads === 1 ? '' : 's'}
            {summary.containers > 0 ? ` · ${summary.containers} container/image` : ''}
            {hostCveCount + containerCveCount > 0
              ? ` · ${hostCveCount} patchable · ${containerCveCount} inventory-only CVEs`
              : ''}
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Link className="btn btn-secondary" to={`/servers/${serverId}/patches`}>
            Patches
          </Link>
          <button className="btn btn-primary" type="button" disabled={loading} onClick={refreshScan}>
            {loading ? 'Queueing...' : 'Refresh CVE scan'}
          </button>
        </div>
      </div>

      {message ? <div className="rounded-lg bg-sky-500/10 px-3 py-2 text-sm text-sky-200">{message}</div> : null}

      <WorkloadTable
        title="Host OS (patchable)"
        description="Root filesystem scan — remediate with OS updates or PatchDashboard patch actions."
        rows={data.host_workloads}
        emptyMessage="No host OS scan yet. Queue a CVE refresh and wait for the agent check-in."
        patchable
      />

      <WorkloadTable
        title="Containers & images (inventory only)"
        description="Workloads running on this server — rebuild or redeploy to remediate; not modified from PatchDashboard."
        rows={data.container_workloads}
        emptyMessage="No container or image scans on this server yet."
        patchable={false}
      />

      <VulnerabilityTable
        title={`Host OS CVEs (${hostCveCount})`}
        description="Patchable on this server via OS updates."
        rows={data.host_vulnerabilities}
        serverId={serverId}
        patchable
      />

      <VulnerabilityTable
        title={`Container & image CVEs (${containerCveCount})`}
        description="Detect only — nested under this server for visibility."
        rows={data.container_vulnerabilities}
        serverId={serverId}
        patchable={false}
      />
    </div>
  );
}

function BackLink() {
  return (
    <Link className="text-sm text-sky-400 hover:underline" to="/servers">
      ← Back to servers
    </Link>
  );
}

function WorkloadTable({
  title,
  description,
  rows,
  emptyMessage,
  patchable,
}: {
  title: string;
  description: string;
  rows: Workload[];
  emptyMessage: string;
  patchable: boolean;
}) {
  return (
    <div className="card overflow-x-auto">
      <div className="mb-4 flex flex-wrap items-center gap-2">
        <h2 className="text-lg font-medium">{title}</h2>
        <span
          className={clsx(
            'rounded px-2 py-0.5 text-xs',
            patchable ? 'bg-emerald-500/15 text-emerald-200' : 'bg-slate-800 text-slate-300',
          )}
        >
          {patchable ? 'Read/write' : 'Monitor only'}
        </span>
      </div>
      <p className="mb-4 text-sm text-slate-400">{description}</p>
      {rows.length === 0 ? (
        <p className="text-sm text-slate-500">{emptyMessage}</p>
      ) : (
        <table className="min-w-full text-left text-sm">
          <thead className="text-slate-400">
            <tr>
              <th className="pb-2 pr-4">Name</th>
              <th className="pb-2 pr-4">Type</th>
              <th className="pb-2 pr-4">Image / ref</th>
              <th className="pb-2">Last scan</th>
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => (
              <tr key={row.id} className="border-t border-slate-800">
                <td className="py-3 pr-4">{row.name}</td>
                <td className="py-3 pr-4">{runtimeLabel(row.runtime)}</td>
                <td className="py-3 pr-4">{row.image_ref ?? '—'}</td>
                <td className="py-3">{row.last_scanned_at ?? 'Never'}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
    </div>
  );
}

function VulnerabilityTable({
  title,
  description,
  rows,
  serverId,
  patchable,
}: {
  title: string;
  description: string;
  rows: Vulnerability[];
  serverId: number;
  patchable: boolean;
}) {
  if (rows.length === 0) {
    return null;
  }

  return (
    <div className="card overflow-x-auto">
      <h2 className="text-lg font-medium">{title}</h2>
      <p className="mb-4 text-sm text-slate-400">{description}</p>
      <table className="min-w-full text-left text-sm">
        <thead className="text-slate-400">
          <tr>
            <th className="pb-2 pr-4">CVE</th>
            <th className="pb-2 pr-4">Severity</th>
            <th className="pb-2 pr-4">Application</th>
            <th className="pb-2 pr-4">Type</th>
            <th className="pb-2 pr-4">Remediation</th>
            <th className="pb-2">Package</th>
          </tr>
        </thead>
        <tbody>
          {rows.map((vuln) => (
            <tr
              key={`${vuln.cve_id}-${vuln.container_name}-${vuln.runtime}`}
              className="border-t border-slate-800"
            >
              <td className="py-3 pr-4">
                <Link
                  className="text-sky-300 hover:underline"
                  to={`/vulnerabilities?cve=${encodeURIComponent(vuln.cve_id)}&server_id=${serverId}`}
                >
                  {vuln.cve_id}
                </Link>
              </td>
              <td className="py-3 pr-4">
                <span className={clsx('badge', severityClass(vuln.severity))}>{vuln.severity}</span>
              </td>
              <td className="py-3 pr-4">{vuln.container_name}</td>
              <td className="py-3 pr-4">{runtimeLabel(vuln.runtime)}</td>
              <td className="py-3 pr-4">
                <span
                  className={clsx(
                    'rounded px-2 py-0.5 text-xs',
                    patchable ? 'bg-emerald-500/15 text-emerald-200' : 'bg-slate-800 text-slate-300',
                  )}
                >
                  {remediationLabel(vuln.runtime)}
                </span>
              </td>
              <td className="py-3">{vuln.package_name ?? '—'}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
