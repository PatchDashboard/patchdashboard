import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';

type DashboardResponse = {
  percent_good: number;
  server_count: number;
  total_patches: number;
  servers: Array<{
    server_id: number;
    server_alias: string;
    patch_count: number;
    distro_name?: string;
  }>;
};

type VulnSummary = {
  critical: number;
  high: number;
  total: number;
};

export function DashboardPage() {
  const { token } = useAuth();
  const [dashboard, setDashboard] = useState<DashboardResponse | null>(null);
  const [vulns, setVulns] = useState<VulnSummary | null>(null);

  useEffect(() => {
    if (!token) return;
    api<DashboardResponse>('/api/v1/dashboard', {}, token).then(setDashboard);
    api<VulnSummary>('/api/v1/containers/vulnerabilities/summary', {}, token).then(setVulns);
  }, [token]);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-semibold">Dashboard</h1>
        <p className="text-slate-400">Overview of patch and container vulnerability status.</p>
      </div>

      <div className="grid gap-4 md:grid-cols-3">
        <div className="card">
          <div className="text-sm text-slate-400">Servers healthy</div>
          <div className="mt-2 text-3xl font-semibold text-sky-300">
            {dashboard?.percent_good ?? 0}%
          </div>
        </div>
        <div className="card">
          <div className="text-sm text-slate-400">Pending patches</div>
          <div className="mt-2 text-3xl font-semibold">{dashboard?.total_patches ?? 0}</div>
        </div>
        <div className="card">
          <div className="text-sm text-slate-400">Container CVEs</div>
          <div className="mt-2 text-3xl font-semibold text-orange-300">{vulns?.total ?? 0}</div>
          <div className="mt-1 text-xs text-slate-400">
            {vulns?.critical ?? 0} critical · {vulns?.high ?? 0} high
          </div>
        </div>
      </div>

      <div className="card overflow-x-auto">
        <h2 className="mb-4 text-lg font-medium">Servers</h2>
        <table className="min-w-full text-left text-sm">
          <thead className="text-slate-400">
            <tr>
              <th className="pb-2 pr-4">Server</th>
              <th className="pb-2 pr-4">Distro</th>
              <th className="pb-2">Patches</th>
            </tr>
          </thead>
          <tbody>
            {dashboard?.servers.map((server) => (
              <tr key={server.server_id} className="border-t border-slate-800">
                <td className="py-3 pr-4">
                  <Link className="text-sky-300 hover:underline" to={`/servers/${server.server_id}/patches`}>
                    {server.server_alias}
                  </Link>
                </td>
                <td className="py-3 pr-4">{server.distro_name ?? 'Unknown'}</td>
                <td className="py-3">{server.patch_count}</td>
              </tr>
            )) ?? (
              <tr>
                <td colSpan={3} className="py-6 text-slate-400">
                  No trusted servers yet.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </div>
  );
}
