import { Fragment, useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { PieChart } from '../components/PieChart';
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
    icon_path?: string;
    containers?: Array<{
      id: number;
      name: string;
      runtime: string;
      image_ref?: string | null;
      cve_count: number;
    }>;
  }>;
};

type SeverityBucket = {
  critical: number;
  high: number;
  medium: number;
  low: number;
  total: number;
};

type VulnSummary = SeverityBucket & {
  host: SeverityBucket;
  containers: SeverityBucket;
  workloads: {
    host: number;
    containers: number;
  };
};

function MetricCard({
  title,
  value,
  hint,
  detail,
  to,
  valueClassName,
}: {
  title: string;
  value: number;
  hint: string;
  detail?: string;
  to?: string;
  valueClassName?: string;
}) {
  const body = (
    <>
      <div className="text-sm text-slate-400">{title}</div>
      <div className={`mt-2 text-3xl font-semibold ${valueClassName ?? ''}`}>{value}</div>
      <div className="mt-2 text-xs leading-relaxed text-slate-500">{hint}</div>
      {detail ? <div className="mt-1 text-xs text-slate-400">{detail}</div> : null}
    </>
  );

  if (to) {
    return (
      <Link to={to} className="card block transition hover:border-slate-700">
        {body}
      </Link>
    );
  }

  return <div className="card">{body}</div>;
}

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
        <p className="text-slate-400">
          Trusted hosts and VMs are managed servers. CVE counts are open findings, not a server or container tally.
        </p>
      </div>

      <div className="grid gap-4 lg:grid-cols-[220px_1fr]">
        <div className="card flex items-center justify-center">
          <PieChart
            percent={dashboard?.percent_good ?? 0}
            label="Percent of servers not needing upgrades"
          />
        </div>
        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          <MetricCard
            title="Pending patches"
            value={dashboard?.total_patches ?? 0}
            hint="OS package updates waiting on trusted servers."
            to="/servers"
          />
          <MetricCard
            title="Trusted servers"
            value={dashboard?.server_count ?? 0}
            hint="Enrolled hosts and VMs — not Docker containers."
            to="/servers"
          />
          <MetricCard
            title="Host OS CVEs"
            value={vulns?.host.total ?? 0}
            hint="Patchable findings on server root filesystems."
            detail={`${vulns?.host.critical ?? 0} critical · ${vulns?.host.high ?? 0} high · ${vulns?.workloads.host ?? 0} host scan${(vulns?.workloads.host ?? 0) === 1 ? '' : 's'}`}
            to="/vulnerabilities?runtime=host"
            valueClassName="text-emerald-300"
          />
          <MetricCard
            title="Container/image CVEs"
            value={vulns?.containers.total ?? 0}
            hint="Inventory-only findings on Docker, Podman, LXC, and images."
            detail={`${vulns?.containers.critical ?? 0} critical · ${vulns?.containers.high ?? 0} high · ${vulns?.workloads.containers ?? 0} workload${(vulns?.workloads.containers ?? 0) === 1 ? '' : 's'} scanned`}
            to="/containers"
            valueClassName="text-orange-300"
          />
        </div>
      </div>

      <div className="card overflow-x-auto">
        <h2 className="mb-1 text-lg font-medium">Trusted servers</h2>
        <p className="mb-4 text-sm text-slate-400">
          Physical hosts, VMs, and bare-metal nodes. Nested rows are monitored containers — inventory only, not managed
          servers.
        </p>
        <table className="min-w-full text-left text-sm">
          <thead className="text-slate-400">
            <tr>
              <th className="pb-2 pr-4">Server / container</th>
              <th className="pb-2 pr-4">Distro / runtime</th>
              <th className="pb-2">Pending patches / CVEs</th>
            </tr>
          </thead>
          <tbody>
            {dashboard?.servers.map((server) => (
              <Fragment key={server.server_id}>
                <tr key={server.server_id} className="border-t border-slate-800">
                  <td className="py-3 pr-4">
                    <Link className="text-sky-300 hover:underline" to={`/servers/${server.server_id}/patches`}>
                      {server.server_alias}
                    </Link>
                  </td>
                  <td className="py-3 pr-4">{server.distro_name ?? 'Unknown'}</td>
                  <td className="py-3">{server.patch_count}</td>
                </tr>
                {(server.containers ?? []).map((container) => (
                  <tr key={`${server.server_id}-${container.id}`} className="border-t border-slate-800/50 bg-slate-950/30">
                    <td className="py-2 pr-4 pl-6">
                      <Link
                        className="text-slate-300 hover:underline"
                        to={`/servers/${server.server_id}/workloads`}
                      >
                        ↳ {container.name}
                      </Link>
                      <span className="ml-2 rounded bg-slate-800 px-1.5 py-0.5 text-xs text-slate-400">
                        container
                      </span>
                    </td>
                    <td className="py-2 pr-4 text-slate-400">{container.runtime}</td>
                    <td className="py-2 text-slate-400">
                      {container.cve_count > 0 ? (
                        <Link
                          className="text-orange-300 hover:underline"
                          to={`/servers/${server.server_id}/workloads`}
                        >
                          {container.cve_count} CVE{container.cve_count === 1 ? '' : 's'}
                        </Link>
                      ) : (
                        'None'
                      )}
                    </td>
                  </tr>
                ))}
              </Fragment>
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
