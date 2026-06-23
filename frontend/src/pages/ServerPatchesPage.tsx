import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import clsx from 'clsx';
import { ServerPowerControls } from '../components/ServerPowerControls';
import { SuppressPackageActions } from '../components/SuppressPackageActions';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';
import type { PowerMode } from '../lib/serverPower';

type Patch = {
  id: number;
  package_name: string;
  current: string;
  new: string;
  urgency?: string;
  bug_url?: string;
  reference_url?: string;
  suppressed?: boolean;
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
  const [serverAlias, setServerAlias] = useState('');
  const [serverName, setServerName] = useState('');
  const [patches, setPatches] = useState<Patch[]>([]);
  const [upgradeCommand, setUpgradeCommand] = useState<string | null>(null);
  const [message, setMessage] = useState('');
  const [loading, setLoading] = useState(false);
  const [installRebootMode, setInstallRebootMode] = useState<PowerMode>('graceful');
  const [powerMessage, setPowerMessage] = useState('');

  async function loadPatches() {
    if (!token || !id) return;
    const data = await api<{
      server: { server_alias: string; server_name: string };
      patches: Patch[];
      upgrade_command?: string | null;
    }>(`/api/v1/servers/${id}/patches`, {}, token);
    setServerAlias(data.server.server_alias);
    setServerName(data.server.server_name);
    setPatches(data.patches);
    setUpgradeCommand(data.upgrade_command ?? null);
  }

  useEffect(() => {
    loadPatches().catch(() => setMessage('Failed to load patches.'));
  }, [token, id]);

  async function installAll(reboot = false) {
    if (!token || !id) return;
    const result = await api<{ updated: number; reboot_scheduled?: boolean; reboot_mode?: string }>(
      `/api/v1/servers/${id}/patches/install`,
      {
        method: 'POST',
        body: JSON.stringify({ reboot, reboot_mode: installRebootMode }),
      },
      token,
    );
    const rebootNote = result.reboot_scheduled
      ? ` Server will ${result.reboot_mode ?? 'graceful'} reboot after install.`
      : '';
    setMessage(`Queued ${result.updated} packages for install.${rebootNote}`);
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
          <p className="text-slate-400">
            {serverAlias || `Server #${id}`} ·{' '}
            <Link className="text-sky-300 hover:underline" to={`/servers/${id}/packages`}>
              All installed packages
            </Link>
          </p>
        </div>
        <div className="flex flex-wrap gap-2">
          <button className="btn btn-secondary" type="button" disabled={loading} onClick={refreshVulnerabilities}>
            {loading ? 'Refreshing...' : 'Refresh vulnerabilities'}
          </button>
          <button className="btn btn-primary" type="button" onClick={() => installAll(false)}>
            Install all
          </button>
          <select
            className="input w-auto"
            value={installRebootMode}
            onChange={(e) => setInstallRebootMode(e.target.value as PowerMode)}
            aria-label="Reboot mode after install"
          >
            <option value="graceful">Graceful reboot</option>
            <option value="forced">Forced reboot</option>
          </select>
          <button className="btn btn-secondary" type="button" onClick={() => installAll(true)}>
            Install all + reboot
          </button>
        </div>
      </div>

      {message ? <div className="rounded-lg bg-sky-500/10 px-3 py-2 text-sm text-sky-200">{message}</div> : null}

      {token && id ? (
        <ServerPowerControls
          serverId={Number(id)}
          token={token}
          onScheduled={(action, mode, scheduledAt) => {
            const when = scheduledAt ? ` at ${scheduledAt}` : '';
            setPowerMessage(`Queued ${action} (${mode})${when}.`);
          }}
        />
      ) : null}

      {powerMessage ? (
        <div className="rounded-lg bg-sky-500/10 px-3 py-2 text-sm text-sky-200">{powerMessage}</div>
      ) : null}

      {upgradeCommand ? (
        <div className="card">
          <h2 className="mb-2 text-sm font-medium text-slate-300">Manual install command</h2>
          <pre className="overflow-x-auto rounded-lg bg-slate-950 p-3 text-xs text-slate-200">{upgradeCommand}</pre>
        </div>
      ) : null}

      <div className="card overflow-x-auto">
        <table className="min-w-full text-left text-sm">
          <thead className="text-slate-400">
            <tr>
              <th className="pb-2 pr-4">Package</th>
              <th className="pb-2 pr-4">Current</th>
              <th className="pb-2 pr-4">New</th>
              <th className="pb-2 pr-4">Urgency</th>
              <th className="pb-2 pr-4">Bug report</th>
              <th className="pb-2">Suppress</th>
            </tr>
          </thead>
          <tbody>
            {patches.map((patch) => (
              <tr key={patch.id} className="border-t border-slate-800">
                <td className="py-3 pr-4">
                  <Link
                    className={clsx('hover:underline', patch.suppressed ? 'text-slate-500' : 'text-emerald-300')}
                    to={`/search?q=${encodeURIComponent(patch.package_name)}&exact=true`}
                  >
                    {patch.package_name}
                  </Link>
                </td>
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
                <td className="py-3 pr-4">
                  {patch.bug_url ? (
                    <a className="text-sky-300 hover:underline" href={patch.bug_url} target="_blank" rel="noreferrer">
                      {bugLabel(patch.bug_url)}
                    </a>
                  ) : (
                    '—'
                  )}
                </td>
                <td className="py-3">
                  <SuppressPackageActions
                    packageName={patch.package_name}
                    serverName={serverName}
                    serverAlias={serverAlias}
                    suppressions={[]}
                    alreadySuppressed={patch.suppressed}
                    onSuppressed={() =>
                      setPatches((current) =>
                        current.map((item) =>
                          item.package_name === patch.package_name ? { ...item, suppressed: true } : item,
                        ),
                      )
                    }
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
