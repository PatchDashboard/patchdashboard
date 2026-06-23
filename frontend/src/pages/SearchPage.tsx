import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';
import { PackageSearchBox } from '../components/PackageSearchBox';
import { SuppressPackageActions } from '../components/SuppressPackageActions';
import { fetchSuppressions, type Suppression } from '../lib/suppressions';

type SearchResult = {
  server_name: string;
  server_id?: number;
  package_name: string;
  package_version: string;
};

export function SearchPage() {
  const { token } = useAuth();
  const [params, setParams] = useSearchParams();
  const query = params.get('q') ?? '';
  const exact = params.get('exact') === 'true';
  const [results, setResults] = useState<SearchResult[]>([]);
  const [suppressions, setSuppressions] = useState<Suppression[]>([]);

  useEffect(() => {
    if (!token) return;
    fetchSuppressions(token).then(setSuppressions);
  }, [token]);

  useEffect(() => {
    if (!token || !query) {
      setResults([]);
      return;
    }
    const path = `/api/v1/search/packages?q=${encodeURIComponent(query)}${exact ? '&exact=true' : ''}`;
    api<{ results: SearchResult[] }>(path, {}, token).then((data) => setResults(data.results));
  }, [token, query, exact]);

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-semibold">Package search</h1>
        <p className="text-slate-400">Find which servers have a package installed.</p>
      </div>

      <div className="card max-w-xl">
        <PackageSearchBox />
      </div>

      {query ? (
        <div className="space-y-3">
          <div className="flex flex-wrap items-center gap-3 text-sm text-slate-400">
            <span>
              Results for &ldquo;{query}&rdquo; ({results.length} found)
            </span>
            <button
              type="button"
              className="btn btn-secondary"
              onClick={() =>
                setParams({
                  q: query,
                  exact: exact ? 'false' : 'true',
                })
              }
            >
              {exact ? 'Fuzzy match' : 'Exact match'}
            </button>
          </div>

          <div className="card overflow-x-auto">
            <table className="min-w-full text-left text-sm">
              <thead className="text-slate-400">
                <tr>
                  <th className="pb-2 pr-4">Server</th>
                  <th className="pb-2 pr-4">Package</th>
                  <th className="pb-2 pr-4">Version</th>
                  <th className="pb-2">Suppress</th>
                </tr>
              </thead>
              <tbody>
                {results.map((row) => (
                  <tr key={`${row.server_name}-${row.package_name}`} className="border-t border-slate-800">
                    <td className="py-3 pr-4">
                      {row.server_id ? (
                        <Link className="text-sky-300 hover:underline" to={`/servers/${row.server_id}/patches`}>
                          {row.server_name}
                        </Link>
                      ) : (
                        row.server_name
                      )}
                    </td>
                    <td className="py-3 pr-4">
                      <Link
                        className="text-emerald-300 hover:underline"
                        to={`/search?q=${encodeURIComponent(row.package_name)}&exact=true`}
                      >
                        {row.package_name}
                      </Link>
                    </td>
                    <td className="py-3">{row.package_version}</td>
                    <td className="py-3">
                      <SuppressPackageActions
                        packageName={row.package_name}
                        serverName={row.server_name}
                        serverAlias={row.server_name}
                        suppressions={suppressions}
                        onSuppressed={(created) => setSuppressions((current) => [...current, created])}
                      />
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </div>
      ) : null}
    </div>
  );
}
