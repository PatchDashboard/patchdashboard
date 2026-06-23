import { useEffect, useState } from 'react';
import { Navigate } from 'react-router-dom';
import { ExternalLink, RefreshCw } from 'lucide-react';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';

type CveFeed = {
  id: number;
  distro_id: number;
  distro_name: string;
  provider_id: string;
  provider_name: string;
  provider_description?: string;
  docs_url?: string;
  sync_mode: 'bulk' | 'on_demand' | 'both';
  enabled: number;
  priority: number;
  config: Record<string, unknown>;
  default_config: Record<string, unknown>;
  last_sync_at: string | null;
  last_sync_status: 'never' | 'running' | 'success' | 'error';
  last_sync_message: string | null;
  records_synced: number;
};

type DistroFeeds = {
  distro_id: number | null;
  distro_name: string;
  feeds: CveFeed[];
};

type Provider = {
  id: string;
  name: string;
  description: string;
  docs_url?: string;
  sync_mode: string;
};

type Distro = {
  id: number;
  distro_name: string;
};

export function CveFeedsPage() {
  const { token, user } = useAuth();
  const [distros, setDistros] = useState<DistroFeeds[]>([]);
  const [providers, setProviders] = useState<Provider[]>([]);
  const [allDistros, setAllDistros] = useState<Distro[]>([]);
  const [message, setMessage] = useState('');
  const [busyFeedId, setBusyFeedId] = useState<number | null>(null);
  const [syncAllBusy, setSyncAllBusy] = useState(false);
  const [expandedDistro, setExpandedDistro] = useState<number | null>(0);
  const [addDistroId, setAddDistroId] = useState<string>('global');
  const [addProviderId, setAddProviderId] = useState('');
  const [addBusy, setAddBusy] = useState(false);

  async function loadFeeds() {
    if (!token) return;
    const data = await api<{ distros: DistroFeeds[]; providers?: Provider[] }>(
      '/api/v1/cve-feeds',
      {},
      token,
    );
    setDistros(data.distros);
  }

  useEffect(() => {
    if (!token || !user?.admin) return;
    loadFeeds();
    api<{ providers: Provider[] }>('/api/v1/cve-feeds/providers', {}, token).then((data) =>
      setProviders(data.providers),
    );
    api<{ distros: Distro[] }>('/api/v1/distros', {}, token).then((data) => setAllDistros(data.distros));
  }, [token, user]);

  async function addFeed() {
    if (!token || !addProviderId) return;
    setAddBusy(true);
    setMessage('');
    try {
      await api<{ feed: CveFeed }>(
        '/api/v1/cve-feeds',
        {
          method: 'POST',
          body: JSON.stringify({
            provider_id: addProviderId,
            distro_id: addDistroId === 'global' ? null : Number(addDistroId),
          }),
        },
        token,
      );
      await loadFeeds();
      setMessage('Feed added. Enable it below and run Sync now when ready.');
      setAddProviderId('');
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Unable to add feed');
    } finally {
      setAddBusy(false);
    }
  }

  async function toggleFeed(feed: CveFeed, enabled: boolean) {
    if (!token) return;
    await api<{ feed: CveFeed }>(
      `/api/v1/cve-feeds/${feed.id}`,
      { method: 'PUT', body: JSON.stringify({ enabled }) },
      token,
    );
    await loadFeeds();
    setMessage(`${feed.provider_name} ${enabled ? 'enabled' : 'disabled'} for ${feed.distro_name}.`);
  }

  async function syncFeed(feed: CveFeed) {
    if (!token) return;
    setBusyFeedId(feed.id);
    setMessage('');
    try {
      const data = await api<{ result: { message: string } }>(
        `/api/v1/cve-feeds/${feed.id}/sync`,
        { method: 'POST' },
        token,
      );
      setMessage(data.result.message);
      await loadFeeds();
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Sync failed');
    } finally {
      setBusyFeedId(null);
    }
  }

  async function syncAll() {
    if (!token) return;
    setSyncAllBusy(true);
    setMessage('Syncing all enabled feeds…');
    try {
      const data = await api<{ results: Array<{ feed_id: number; status: string; message: string }> }>(
        '/api/v1/cve-feeds/sync-all',
        { method: 'POST' },
        token,
      );
      const errors = data.results.filter((r) => r.status === 'error').length;
      setMessage(
        errors === 0
          ? `Synced ${data.results.length} feeds successfully.`
          : `Completed with ${errors} error(s). Check individual feed status below.`,
      );
      await loadFeeds();
    } catch (error) {
      setMessage(error instanceof Error ? error.message : 'Bulk sync failed');
    } finally {
      setSyncAllBusy(false);
    }
  }

  if (!user?.admin) {
    return <Navigate to="/" replace />;
  }

  const configuredKeys = new Set(
    distros.flatMap((group) =>
      group.feeds.map((feed) => `${group.distro_id ?? 'global'}:${feed.provider_id}`),
    ),
  );
  const addableProviders = providers.filter((provider) => {
    const key = `${addDistroId === 'global' ? 'global' : addDistroId}:${provider.id}`;
    return !configuredKeys.has(key);
  });

  return (
    <div className="space-y-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="text-3xl font-semibold">CVE feed integration</h1>
          <p className="mt-1 max-w-3xl text-slate-400">
            Configure off-site vulnerability databases per OS/distro. Synced advisories enrich patch
            urgency and reference links alongside live lookups (UbuntuUpdates.org) and Trivy scans.
          </p>
        </div>
        <button
          type="button"
          className="btn btn-primary"
          disabled={syncAllBusy}
          onClick={syncAll}
        >
          <RefreshCw size={16} className={syncAllBusy ? 'animate-spin' : ''} />
          Sync all enabled
        </button>
      </div>

      {message ? <div className="card text-sm text-sky-200">{message}</div> : null}

      <div className="card space-y-4">
        <div>
          <h2 className="text-lg font-medium">Add a feed</h2>
          <p className="mt-1 text-sm text-slate-400">
            Global feeds (NVD, GitHub) apply to all distros. Per-distro feeds add OS-specific advisory
            sources such as Debian, Ubuntu USN, or Rocky errata.
          </p>
        </div>
        <div className="flex flex-wrap items-end gap-3">
          <label className="text-sm">
            <span className="text-slate-400">Scope</span>
            <select
              className="input mt-1 block min-w-[12rem]"
              value={addDistroId}
              onChange={(e) => {
                setAddDistroId(e.target.value);
                setAddProviderId('');
              }}
            >
              <option value="global">Global</option>
              {allDistros.map((distro) => (
                <option key={distro.id} value={String(distro.id)}>
                  {distro.distro_name}
                </option>
              ))}
            </select>
          </label>
          <label className="text-sm">
            <span className="text-slate-400">Provider</span>
            <select
              className="input mt-1 block min-w-[16rem]"
              value={addProviderId}
              onChange={(e) => setAddProviderId(e.target.value)}
            >
              <option value="">Select provider…</option>
              {addableProviders.map((provider) => (
                <option key={provider.id} value={provider.id}>
                  {provider.name}
                </option>
              ))}
            </select>
          </label>
          <button
            type="button"
            className="btn btn-secondary"
            disabled={addBusy || !addProviderId}
            onClick={addFeed}
          >
            {addBusy ? 'Adding…' : 'Add feed'}
          </button>
        </div>
      </div>

      <div className="card">
        <h2 className="mb-3 text-lg font-medium">Supported providers</h2>
        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
          {providers.map((provider) => (
            <div key={provider.id} className="rounded-lg border border-slate-800 bg-slate-950 p-3 text-sm">
              <div className="font-medium text-slate-100">{provider.name}</div>
              <div className="mt-1 text-xs uppercase tracking-wide text-slate-500">{provider.sync_mode}</div>
              <p className="mt-2 text-slate-400">{provider.description}</p>
              {provider.docs_url ? (
                <a
                  href={provider.docs_url}
                  target="_blank"
                  rel="noreferrer"
                  className="mt-2 inline-flex items-center gap-1 text-sky-400 hover:text-sky-300"
                >
                  Documentation
                  <ExternalLink size={14} />
                </a>
              ) : null}
            </div>
          ))}
        </div>
      </div>

      <div className="space-y-4">
        {distros.map((distro) => {
          const groupKey = distro.distro_id ?? 0;
          return (
          <div key={groupKey} className="card">
            <button
              type="button"
              className="flex w-full items-center justify-between text-left"
              onClick={() =>
                setExpandedDistro((current) => (current === groupKey ? null : groupKey))
              }
            >
              <div>
                <h2 className="text-lg font-medium">{distro.distro_name}</h2>
                <p className="text-sm text-slate-400">{distro.feeds.length} configured feeds</p>
              </div>
              <span className="text-slate-500">{expandedDistro === groupKey ? '−' : '+'}</span>
            </button>

            {expandedDistro === groupKey ? (
              <div className="mt-4 overflow-x-auto">
                <table className="min-w-full text-left text-sm">
                  <thead className="text-slate-400">
                    <tr>
                      <th className="pb-2 pr-4">Provider</th>
                      <th className="pb-2 pr-4">Mode</th>
                      <th className="pb-2 pr-4">Last sync</th>
                      <th className="pb-2 pr-4">Records</th>
                      <th className="pb-2 pr-4">Enabled</th>
                      <th className="pb-2">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {distro.feeds.map((feed) => (
                      <tr key={feed.id} className="border-t border-slate-800">
                        <td className="py-3 pr-4">
                          <div className="font-medium">{feed.provider_name}</div>
                          {feed.last_sync_message ? (
                            <div
                              className={`mt-1 text-xs ${
                                feed.last_sync_status === 'error' ? 'text-red-400' : 'text-slate-500'
                              }`}
                            >
                              {feed.last_sync_message}
                            </div>
                          ) : null}
                        </td>
                        <td className="py-3 pr-4 capitalize text-slate-400">{feed.sync_mode.replace('_', ' ')}</td>
                        <td className="py-3 pr-4">
                          <div>{feed.last_sync_at ?? 'Never'}</div>
                          <div className="text-xs capitalize text-slate-500">{feed.last_sync_status}</div>
                        </td>
                        <td className="py-3 pr-4">{feed.records_synced}</td>
                        <td className="py-3 pr-4">
                          <input
                            type="checkbox"
                            checked={Boolean(feed.enabled)}
                            onChange={(e) => toggleFeed(feed, e.target.checked)}
                          />
                        </td>
                        <td className="py-3">
                          {feed.sync_mode === 'on_demand' ? (
                            <span className="text-xs text-slate-500">Live lookup on patch ingest</span>
                          ) : (
                            <button
                              type="button"
                              className="btn btn-secondary text-xs"
                              disabled={!feed.enabled || busyFeedId === feed.id}
                              onClick={() => syncFeed(feed)}
                            >
                              {busyFeedId === feed.id ? 'Syncing…' : 'Sync now'}
                            </button>
                          )}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            ) : null}
          </div>
          );
        })}
      </div>

      <div className="card text-sm text-slate-400">
        <p>
          Set <code className="text-slate-300">NVD_API_KEY</code> in <code className="text-slate-300">api/.env</code>{' '}
          for higher NVD rate limits. Schedule bulk sync nightly:
        </p>
        <pre className="mt-2 overflow-x-auto rounded bg-slate-950 p-3 text-xs text-slate-300">
          0 2 * * * cd /opt/patchdashboard &amp;&amp; php database/cve_sync.php --all
        </pre>
      </div>
    </div>
  );
}
