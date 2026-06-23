import { useEffect, useState } from 'react';
import { useParams } from 'react-router-dom';
import clsx from 'clsx';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';

type Patch = {
  id: number;
  package_name: string;
  current: string;
  new: string;
  urgency?: string;
  bug_url?: string;
  reference_url?: string;
};

function urgencyClass(urgency?: string) {
  switch ((urgency ?? 'unknown').toLowerCase()) {
    case 'emergency':
    case 'high':
      return 'text-red-400';
    case 'medium':
      return 'text-orange-300';
    case 'low':
      return 'text-slate-300';
    default:
      return 'text-slate-400';
  }
}

function bugLabel(bugUrl?: string) {
  if (!bugUrl) {
    return null;
  }
  if (bugUrl.includes('debian')) {
    const cve = bugUrl.split('/').pop();
    return `Debian ${cve}`;
  }
  const bugId = bugUrl.split('/').pop();
  return `Launchpad #${bugId}`;
}

export function ServerPatchesPage() {
  const { id } = useParams();
  const { token } = useAuth();
  const [patches, setPatches] = useState<Patch[]>([]);
  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(false);

  async function loadPatches() {
    if (!token || !id) return;
    const data = await api<{ patches: Patch[] }>(`/api/v1/servers/${id}/patches`, {}, token);
    setPatches(data.patches);
  }

  useEffect(() => {
    loadPatches().catch(() => setMessage('Failed to load patches.'));
  }, [token, id]);

  async function installAll() {
    if (!token || !id) return;
    const result = await api<{ updated: number }>(
      `/api/v1/servers/${id}/patches/install`,
      { method: 'POST' },
      token,
    );
    setMessage(`Queued ${result.updated} packages for install.`);
  }

  async function refreshVulnerabilities() {
    if (!token || !id) return;
    setLoading(true);
    setMessage('');
    try {
      const result = await api<{ updated: number; message: string }>(
        `/api/v1/servers/${id}/patches/enrich-vulnerabilities`,
        { method: 'POST' },
        token,
      );
      setMessage(result.message);
      await loadPatches();
    } catch {
      setMessage('Failed to refresh vulnerability data.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-3xl font-semibold">Server patches</h1>
          <p className="text-slate-400">Available updates for server #{id}</p>
        </div>
        <div className="flex gap-2">
          <button className="btn btn-secondary" type="button" disabled={loading} onClick={refreshVulnerabilities}>
            {loading ? 'Refreshing...' : 'Refresh vulnerabilities'}
          </button>
          <button className="btn btn-primary" type="button" onClick={installAll}>
            Install all
          </button>
        </div>
      </div>

      {message ? <div className="rounded-lg bg-sky-500/10 px-3 py-2 text-sm text-sky-200">{message}</div> : null}

      <div className="card overflow-x-auto">
        <table className="min-w-full text-left text-sm">
          <thead className="text-slate-400">
            <tr>
              <th className="pb-2 pr-4">Package</th>
              <th className="pb-2 pr-4">Current</th>
              <th className="pb-2 pr-4">New</th>
              <th className="pb-2 pr-4">Urgency</th>
              <th className="pb-2">Bug report</th>
            </tr>
          </thead>
          <tbody>
            {patches.map((patch) => (
              <tr key={patch.id} className="border-t border-slate-800">
                <td className="py-3 pr-4">{patch.package_name}</td>
                <td className="py-3 pr-4">{patch.current}</td>
                <td className="py-3 pr-4">{patch.new}</td>
                <td className="py-3 pr-4">
                  {patch.reference_url ? (
                    <a
                      className={clsx('capitalize hover:underline', urgencyClass(patch.urgency))}
                      href={patch.reference_url}
                      target="_blank"
                      rel="noreferrer"
                    >
                      {patch.urgency ?? 'unknown'}
                    </a>
                  ) : (
                    <span className={clsx('capitalize', urgencyClass(patch.urgency))}>
                      {patch.urgency ?? 'unknown'}
                    </span>
                  )}
                </td>
                <td className="py-3">
                  {patch.bug_url ? (
                    <a className="text-sky-300 hover:underline" href={patch.bug_url} target="_blank" rel="noreferrer">
                      {bugLabel(patch.bug_url)}
                    </a>
                  ) : (
                    '—'
                  )}
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </div>
    </div>
  );
}
