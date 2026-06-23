import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { SuppressPackageActions } from '../components/SuppressPackageActions';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';
import { fetchSuppressions, type Suppression } from '../lib/suppressions';

type Package = {
  package_name: string;
  package_version: string;
};

export function ServerPackagesPage() {
  const { id } = useParams();
  const { token } = useAuth();
  const [alias, setAlias] = useState('');
  const [serverName, setServerName] = useState('');
  const [packages, setPackages] = useState<Package[]>([]);
  const [suppressions, setSuppressions] = useState<Suppression[]>([]);

  useEffect(() => {
    if (!token || !id) return;
    Promise.all([
      api<{ server: { server_alias: string; server_name: string }; packages: Package[] }>(
        `/api/v1/servers/${id}/packages`,
        {},
        token,
      ),
      fetchSuppressions(token),
    ]).then(([data, suppressionList]) => {
      setAlias(data.server.server_alias);
      setServerName(data.server.server_name);
      setPackages(data.packages);
      setSuppressions(suppressionList);
    });
  }, [token, id]);

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-3xl font-semibold">Installed packages</h1>
          <p className="text-slate-400">{alias || `Server #${id}`}</p>
        </div>
        <Link className="btn btn-secondary" to={`/servers/${id}/patches`}>
          Back to patches
        </Link>
      </div>

      <div className="card overflow-x-auto">
        <table className="min-w-full text-left text-sm">
          <thead className="text-slate-400">
            <tr>
              <th className="pb-2 pr-4">Package</th>
              <th className="pb-2 pr-4">Version</th>
              <th className="pb-2">Suppress</th>
            </tr>
          </thead>
          <tbody>
            {packages.map((pkg) => (
              <tr key={pkg.package_name} className="border-t border-slate-800">
                <td className="py-3 pr-4">
                  <Link
                    className="text-emerald-300 hover:underline"
                    to={`/search?q=${encodeURIComponent(pkg.package_name)}&exact=true`}
                  >
                    {pkg.package_name}
                  </Link>
                </td>
                <td className="py-3">{pkg.package_version}</td>
                <td className="py-3">
                  <SuppressPackageActions
                    packageName={pkg.package_name}
                    serverName={serverName}
                    serverAlias={alias}
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
  );
}
