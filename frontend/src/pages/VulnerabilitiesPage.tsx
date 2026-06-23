import { useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import clsx from 'clsx';
import { X } from 'lucide-react';
import { SortableTh } from '../components/SortableTh';
import { VulnerabilityFilters } from '../components/VulnerabilityFilters';
import { sortRows, severityRank, toggleSort, type SortState } from '../lib/tableSort';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';
import {
  buildVulnerabilitySearchParams,
  defaultParamsForSearch,
  fetchCveDetail,
  isPackageLikeQuery,
  runtimeLabel,
  searchVulnerabilities,
  SERVER_SCOPE_ALL,
  serverScopeLabel,
  severityClass,
  type AdvisoryVulnerability,
  type CveDetail,
  type InstalledVulnerability,
  type VulnerabilitySearchParams,
  type VulnerabilitySearchResult,
} from '../lib/vulnerabilitySearch';

type ServerRow = { id: number; server_alias: string };
type DistroRow = { id: number; distro_name: string };

function paramsToSearchParams(params: VulnerabilitySearchParams): URLSearchParams {
  const next = new URLSearchParams();
  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== '') {
      next.set(key, value);
    }
  });
  return next;
}

function hasActiveSearch(params: VulnerabilitySearchParams): boolean {
  return Object.values(params).some((value) => value !== undefined && value !== '');
}

export function VulnerabilitiesPage() {
  const { token } = useAuth();
  const [urlParams, setUrlParams] = useSearchParams();
  const [servers, setServers] = useState<ServerRow[]>([]);
  const [distros, setDistros] = useState<DistroRow[]>([]);
  const [result, setResult] = useState<VulnerabilitySearchResult | null>(null);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState('');
  const [selectedCve, setSelectedCve] = useState<string | null>(null);
  const [cveDetail, setCveDetail] = useState<CveDetail | null>(null);
  const [detailLoading, setDetailLoading] = useState(false);

  const filters = useMemo(() => buildVulnerabilitySearchParams(urlParams), [urlParams]);

  useEffect(() => {
    if (!token) return;
    api<{ servers: ServerRow[] }>('/api/v1/servers', {}, token).then((data) => setServers(data.servers));
    api<{ distros: DistroRow[] }>('/api/v1/distros', {}, token).then((data) => setDistros(data.distros));
  }, [token]);

  useEffect(() => {
    if (!token || !hasActiveSearch(filters)) {
      setResult(null);
      return;
    }

    setLoading(true);
    setError('');
    searchVulnerabilities(filters, token)
      .then(setResult)
      .catch(() => setError('Search failed.'))
      .finally(() => setLoading(false));
  }, [token, urlParams]);

  useEffect(() => {
    if (!token || !selectedCve) {
      setCveDetail(null);
      return;
    }

    setDetailLoading(true);
    fetchCveDetail(selectedCve, token)
      .then(setCveDetail)
      .catch(() => setCveDetail(null))
      .finally(() => setDetailLoading(false));
  }, [token, selectedCve]);

  function applyFilters(next: VulnerabilitySearchParams) {
    setUrlParams(paramsToSearchParams(defaultParamsForSearch(next)));
  }

  async function openCve(cveId: string | null | undefined) {
    if (!cveId) return;
    setSelectedCve(cveId);
  }

  const showInstalled = filters.source !== 'advisory';
  const showAdvisories = filters.source !== 'installed';
  const packageSearch = isPackageLikeQuery(filters);

  function setScope(serverId: string | undefined) {
    if (serverId === SERVER_SCOPE_ALL) {
      applyFilters({
        q: filters.q,
        cve: filters.cve,
        package: filters.package,
        severity: filters.severity,
        min_cvss: filters.min_cvss,
        server_id: SERVER_SCOPE_ALL,
      });
      return;
    }
    applyFilters({
      ...filters,
      server_id: serverId,
      source: serverId === SERVER_SCOPE_ALL ? undefined : filters.source,
    });
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-semibold">Vulnerabilities</h1>
        <p className="text-slate-400">
          Search by package or CVE to see known vulnerabilities from synced feeds and NVD (all time), plus what is
          currently on your servers.
        </p>
      </div>

      <VulnerabilityFilters params={filters} servers={servers} distros={distros} onChange={applyFilters} />

      {packageSearch && hasActiveSearch(filters) ? (
        <div className="flex flex-wrap items-center gap-2 text-sm">
          <span className="text-slate-400">Scope:</span>
          <button
            type="button"
            className={clsx(
              'rounded-lg px-3 py-1.5 transition',
              filters.server_id === SERVER_SCOPE_ALL
                ? 'bg-sky-500/20 text-sky-200'
                : 'bg-slate-900 text-slate-300 hover:bg-slate-800',
            )}
            onClick={() => setScope(SERVER_SCOPE_ALL)}
          >
            Any
          </button>
          <button
            type="button"
            className={clsx(
              'rounded-lg px-3 py-1.5 transition',
              !filters.server_id
                ? 'bg-sky-500/20 text-sky-200'
                : 'bg-slate-900 text-slate-300 hover:bg-slate-800',
            )}
            onClick={() => setScope(undefined)}
          >
            Any server
          </button>
          {servers.map((server) => (
            <button
              key={server.id}
              type="button"
              className={clsx(
                'rounded-lg px-3 py-1.5 transition',
                filters.server_id === String(server.id)
                  ? 'bg-sky-500/20 text-sky-200'
                  : 'bg-slate-900 text-slate-300 hover:bg-slate-800',
              )}
              onClick={() => setScope(String(server.id))}
            >
              {server.server_alias}
            </button>
          ))}
          <span className="text-xs text-slate-500">
            {filters.server_id === SERVER_SCOPE_ALL
              ? 'Showing all known CVEs for this package from synced feeds and NVD, whether or not it is installed.'
              : `Showing ${serverScopeLabel(filters, servers).toLowerCase()} matches.`}
          </span>
        </div>
      ) : null}

      {!hasActiveSearch(filters) ? (
        <div className="card text-sm text-slate-400">
          Enter a CVE ID or package name above, or set filters to search your fleet and advisory library.
        </div>
      ) : null}

      {loading ? <div className="card text-sm text-slate-400">Searching…</div> : null}
      {error ? <div className="card text-sm text-red-300">{error}</div> : null}

      {result && showAdvisories && packageSearch ? (
        <div className="card overflow-x-auto">
          <h2 className="mb-1 text-lg font-medium">Known CVEs for this package ({result.totals.advisories})</h2>
          <p className="mb-4 text-sm text-slate-400">
            From synced distro advisories and NVD. Includes historical records not tied to your current installs.
          </p>
          {result.advisories.length === 0 ? (
            <p className="text-sm text-slate-400">
              No library matches yet. Sync CVE feeds (admin → CVE feeds) or run NVD sync if the library is empty.
            </p>
          ) : (
            <AdvisoryTable rows={result.advisories} onOpenCve={openCve} />
          )}
        </div>
      ) : null}

      {result && showInstalled ? (
        <div className="card overflow-x-auto">
          <h2 className="mb-4 text-lg font-medium">On your servers ({result.totals.installed})</h2>
          {result.installed.length === 0 ? (
            <p className="text-sm text-slate-400">No installed matches for this query.</p>
          ) : (
            <InstalledTable rows={result.installed} onOpenCve={openCve} />
          )}
        </div>
      ) : null}

      {result && showAdvisories && !packageSearch ? (
        <div className="card overflow-x-auto">
          <h2 className="mb-4 text-lg font-medium">Advisory library ({result.totals.advisories})</h2>
          {result.advisories.length === 0 ? (
            <p className="text-sm text-slate-400">No advisory library matches. Sync CVE feeds if the library is empty.</p>
          ) : (
            <AdvisoryTable rows={result.advisories} onOpenCve={openCve} />
          )}
        </div>
      ) : null}

      {selectedCve ? (
        <div className="fixed inset-0 z-50 flex justify-end bg-black/50" onClick={() => setSelectedCve(null)}>
          <div
            className="h-full w-full max-w-lg overflow-y-auto border-l border-slate-800 bg-slate-950 p-6"
            onClick={(e) => e.stopPropagation()}
          >
            <div className="mb-4 flex items-start justify-between gap-3">
              <div>
                <h2 className="text-xl font-semibold">{selectedCve}</h2>
                <a
                  className="text-sm text-sky-400 hover:underline"
                  href={`https://nvd.nist.gov/vuln/detail/${selectedCve}`}
                  target="_blank"
                  rel="noreferrer"
                >
                  View on NVD
                </a>
              </div>
              <button type="button" className="btn btn-secondary" onClick={() => setSelectedCve(null)}>
                <X size={16} />
              </button>
            </div>

            {detailLoading ? <p className="text-sm text-slate-400">Loading details…</p> : null}

            {cveDetail?.catalog ? (
              <div className="mb-6 space-y-2 rounded-lg border border-slate-800 bg-slate-900 p-4 text-sm">
                <div className="flex flex-wrap gap-2">
                  {cveDetail.catalog.severity ? (
                    <span className={clsx('badge', severityClass(cveDetail.catalog.severity))}>
                      {cveDetail.catalog.severity}
                    </span>
                  ) : null}
                  {cveDetail.catalog.cvss_score != null ? (
                    <span className="text-slate-300">CVSS {cveDetail.catalog.cvss_score}</span>
                  ) : null}
                </div>
                <p className="text-slate-300">{cveDetail.catalog.summary ?? 'No summary in local catalog.'}</p>
              </div>
            ) : null}

            {cveDetail && cveDetail.installed.length > 0 ? (
              <div className="mb-6">
                <h3 className="mb-2 font-medium">On your servers ({cveDetail.installed.length})</h3>
                <ul className="space-y-2 text-sm text-slate-300">
                  {cveDetail.installed.map((row) => (
                    <li key={installedKey(row)} className="rounded border border-slate-800 p-3">
                      <div>{row.server_alias}</div>
                      <div className="text-slate-400">
                        {row.package_name} · {runtimeLabel(row.runtime)} · {row.severity}
                      </div>
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}

            {cveDetail && cveDetail.advisories.length > 0 ? (
              <div>
                <h3 className="mb-2 font-medium">Advisories ({cveDetail.advisories.length})</h3>
                <ul className="space-y-2 text-sm text-slate-300">
                  {cveDetail.advisories.map((row) => (
                    <li key={advisoryKey(row)} className="rounded border border-slate-800 p-3">
                      <div>
                        {row.distro_name ?? 'Unknown distro'} · {row.package_name}
                      </div>
                      <div className="text-slate-400">
                        {row.provider_name ?? row.provider_id} · affects {row.affected_servers} server(s)
                      </div>
                    </li>
                  ))}
                </ul>
              </div>
            ) : null}
          </div>
        </div>
      ) : null}
    </div>
  );
}

type InstalledSortKey =
  | 'cve_id'
  | 'severity'
  | 'kind'
  | 'server_alias'
  | 'container_name'
  | 'package_name'
  | 'installed_version'
  | 'fixed_version';

const installedSortGetters: Record<InstalledSortKey, (row: InstalledVulnerability) => unknown> = {
  cve_id: (row) => row.cve_id ?? '',
  severity: (row) => severityRank(row.severity),
  kind: (row) => row.kind,
  server_alias: (row) => row.server_alias,
  container_name: (row) => row.container_name ?? '',
  package_name: (row) => row.package_name,
  installed_version: (row) => row.installed_version ?? '',
  fixed_version: (row) => row.fixed_version ?? '',
};

function InstalledTable({
  rows,
  onOpenCve,
}: {
  rows: InstalledVulnerability[];
  onOpenCve: (cveId: string) => void;
}) {
  const [sort, setSort] = useState<SortState<InstalledSortKey>>(null);
  const sortedRows = useMemo(() => sortRows(rows, sort, installedSortGetters), [rows, sort]);

  function handleSort(key: string) {
    setSort((current) => toggleSort(current, key as InstalledSortKey));
  }

  return (
    <table className="min-w-full text-left text-sm">
      <thead>
        <tr>
          <SortableTh label="CVE" sortKey="cve_id" sort={sort} onSort={handleSort} />
          <SortableTh label="Severity" sortKey="severity" sort={sort} onSort={handleSort} />
          <SortableTh label="Kind" sortKey="kind" sort={sort} onSort={handleSort} />
          <SortableTh label="Server" sortKey="server_alias" sort={sort} onSort={handleSort} />
          <SortableTh label="Application" sortKey="container_name" sort={sort} onSort={handleSort} />
          <SortableTh label="Package" sortKey="package_name" sort={sort} onSort={handleSort} />
          <SortableTh label="Installed" sortKey="installed_version" sort={sort} onSort={handleSort} />
          <SortableTh label="Fixed" sortKey="fixed_version" sort={sort} onSort={handleSort} className="pr-0" />
        </tr>
      </thead>
      <tbody>
        {sortedRows.map((row) => (
          <InstalledRow key={installedKey(row)} row={row} onOpenCve={onOpenCve} />
        ))}
      </tbody>
    </table>
  );
}

function InstalledRow({
  row,
  onOpenCve,
}: {
  row: InstalledVulnerability;
  onOpenCve: (cveId: string) => void;
}) {
  return (
    <tr className="border-t border-slate-800">
      <td className="py-3 pr-4">
        {row.cve_id ? (
          <button type="button" className="text-sky-300 hover:underline" onClick={() => onOpenCve(row.cve_id!)}>
            {row.cve_id}
          </button>
        ) : (
          '—'
        )}
      </td>
      <td className="py-3 pr-4">
        <span className={clsx('badge', severityClass(row.severity))}>{row.severity}</span>
      </td>
      <td className="py-3 pr-4 capitalize text-slate-400">{row.kind.replace('_', ' ')}</td>
      <td className="py-3 pr-4">
        <Link className="text-sky-300 hover:underline" to={`/servers/${row.server_id}/patches`}>
          {row.server_alias}
        </Link>
      </td>
      <td className="py-3 pr-4">{row.container_name ?? '—'}</td>
      <td className="py-3 pr-4">{row.package_name}</td>
      <td className="py-3 pr-4">{row.installed_version ?? '—'}</td>
      <td className="py-3">{row.fixed_version ?? '—'}</td>
    </tr>
  );
}

type AdvisorySortKey =
  | 'cve_id'
  | 'severity'
  | 'published_at'
  | 'distro_name'
  | 'provider_name'
  | 'package_name'
  | 'fixed_version'
  | 'affected_servers';

const advisorySortGetters: Record<AdvisorySortKey, (row: AdvisoryVulnerability) => unknown> = {
  cve_id: (row) => row.cve_id,
  severity: (row) => severityRank(row.severity),
  published_at: (row) => (row.published_at ? Date.parse(row.published_at) : 0),
  distro_name: (row) => row.distro_name ?? '',
  provider_name: (row) => row.provider_name ?? row.provider_id ?? '',
  package_name: (row) => row.package_name,
  fixed_version: (row) => row.fixed_version ?? '',
  affected_servers: (row) => row.affected_servers,
};

function AdvisoryTable({
  rows,
  onOpenCve,
}: {
  rows: AdvisoryVulnerability[];
  onOpenCve: (cveId: string) => void;
}) {
  const [sort, setSort] = useState<SortState<AdvisorySortKey>>({ key: 'published_at', dir: 'desc' });
  const sortedRows = useMemo(() => sortRows(rows, sort, advisorySortGetters), [rows, sort]);

  function handleSort(key: string) {
    setSort((current) => toggleSort(current, key as AdvisorySortKey));
  }

  return (
    <table className="min-w-full text-left text-sm">
      <thead>
        <tr>
          <SortableTh label="CVE" sortKey="cve_id" sort={sort} onSort={handleSort} />
          <SortableTh label="Severity" sortKey="severity" sort={sort} onSort={handleSort} />
          <SortableTh label="Published" sortKey="published_at" sort={sort} onSort={handleSort} />
          <SortableTh label="Distro" sortKey="distro_name" sort={sort} onSort={handleSort} />
          <SortableTh label="Provider" sortKey="provider_name" sort={sort} onSort={handleSort} />
          <SortableTh label="Package" sortKey="package_name" sort={sort} onSort={handleSort} />
          <SortableTh label="Fixed version" sortKey="fixed_version" sort={sort} onSort={handleSort} />
          <SortableTh
            label="On your servers"
            sortKey="affected_servers"
            sort={sort}
            onSort={handleSort}
            className="pr-0"
          />
        </tr>
      </thead>
      <tbody>
        {sortedRows.map((row) => (
          <AdvisoryRow key={advisoryKey(row)} row={row} onOpenCve={onOpenCve} />
        ))}
      </tbody>
    </table>
  );
}

function AdvisoryRow({
  row,
  onOpenCve,
}: {
  row: AdvisoryVulnerability;
  onOpenCve: (cveId: string) => void;
}) {
  return (
    <tr className="border-t border-slate-800">
      <td className="py-3 pr-4">
        <button type="button" className="text-sky-300 hover:underline" onClick={() => onOpenCve(row.cve_id)}>
          {row.cve_id}
        </button>
      </td>
      <td className="py-3 pr-4">
        {row.severity ? (
          <span className={clsx('badge', severityClass(row.severity))}>{row.severity}</span>
        ) : (
          '—'
        )}
      </td>
      <td className="py-3 pr-4 text-slate-400">{row.published_at ? row.published_at.slice(0, 10) : '—'}</td>
      <td className="py-3 pr-4">{row.distro_name ?? '—'}</td>
      <td className="py-3 pr-4">{row.provider_name ?? row.provider_id ?? '—'}</td>
      <td className="py-3 pr-4">{row.package_name}</td>
      <td className="py-3 pr-4">{row.fixed_version ?? '—'}</td>
      <td className="py-3">
        {row.affected_servers > 0 ? (
          <span className="rounded bg-amber-500/15 px-2 py-0.5 text-xs text-amber-200">
            {row.affected_servers} server{row.affected_servers === 1 ? '' : 's'}
          </span>
        ) : (
          <span className="text-slate-500">Not installed</span>
        )}
      </td>
    </tr>
  );
}

function installedKey(row: InstalledVulnerability): string {
  return `${row.kind}-${row.cve_id}-${row.server_id}-${row.package_name}-${row.runtime}-${row.container_name}`;
}

function advisoryKey(row: AdvisoryVulnerability): string {
  return `${row.kind}-${row.cve_id}-${row.distro_id}-${row.package_name}-${row.provider_id}`;
}
