import { FormEvent, useEffect, useState } from 'react';
import { Navigate } from 'react-router-dom';
import { api, ApiError } from '../lib/api';
import { useAuth } from '../lib/auth';

type SshKeyInfo = {
  public_key?: string | null;
  fingerprint?: string | null;
  configured?: boolean;
};

type Onboarding = {
  https: boolean;
  base_url: string;
  dashboard_public_key?: string | null;
  dashboard_ssh_fingerprint?: string | null;
  bootstrap_ssh_configured?: boolean;
  bootstrap_public_key?: string | null;
  bootstrap_ssh_fingerprint?: string | null;
  enroll_command_example?: string;
};

type EnrollmentSshSettings = {
  dashboard: SshKeyInfo;
  bootstrap: SshKeyInfo;
};

export function AddServerPage() {
  const { token, user } = useAuth();
  const [info, setInfo] = useState<Onboarding | null>(null);
  const [sshSettings, setSshSettings] = useState<EnrollmentSshSettings | null>(null);
  const [serverIp, setServerIp] = useState('');
  const [sshUser, setSshUser] = useState('root');
  const [serverAlias, setServerAlias] = useState('');
  const [sshPassword, setSshPassword] = useState('');
  const [privateKey, setPrivateKey] = useState('');
  const [publicKey, setPublicKey] = useState('');
  const [loading, setLoading] = useState(false);
  const [keyBusy, setKeyBusy] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  async function loadSshSettings() {
    if (!token) return;
    const data = await api<EnrollmentSshSettings>('/api/v1/settings/enrollment-ssh-key', {}, token);
    setSshSettings(data);
  }

  useEffect(() => {
    if (!token || !user?.admin) return;
    api<Onboarding>('/api/v1/servers/onboarding', {}, token).then(setInfo);
    loadSshSettings().catch(() => undefined);
  }, [token, user]);

  if (!user?.admin) {
    return <Navigate to="/" replace />;
  }

  async function enroll(event: FormEvent) {
    event.preventDefault();
    if (!token || !serverIp.trim()) return;
    setLoading(true);
    setError('');
    setMessage('');
    try {
      const result = await api<{ message?: string; output?: string }>(
        '/api/v1/servers/enroll',
        {
          method: 'POST',
          body: JSON.stringify({
            server_ip: serverIp.trim(),
            ssh_user: sshUser.trim() || 'root',
            server_alias: serverAlias.trim() || undefined,
            ssh_password: sshPassword || undefined,
          }),
        },
        token,
      );
      setSshPassword('');
      setMessage(result.message ?? 'Server enrolled. Trust it under Servers when it appears.');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Enrollment failed.');
    } finally {
      setLoading(false);
    }
  }

  async function saveEnrollmentKey(event: FormEvent) {
    event.preventDefault();
    if (!token || !privateKey.trim()) return;
    setKeyBusy(true);
    setError('');
    setMessage('');
    try {
      await api('/api/v1/settings/enrollment-ssh-key', {
        method: 'PUT',
        body: JSON.stringify({
          private_key: privateKey.trim(),
          public_key: publicKey.trim() || undefined,
        }),
      }, token);
      setPrivateKey('');
      setPublicKey('');
      await loadSshSettings();
      setMessage('Enrollment SSH key saved. PatchDashboard will use it to reach servers during enrollment.');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Unable to save SSH key.');
    } finally {
      setKeyBusy(false);
    }
  }

  async function removeEnrollmentKey() {
    if (!token) return;
    setKeyBusy(true);
    setError('');
    try {
      await api('/api/v1/settings/enrollment-ssh-key', { method: 'DELETE' }, token);
      await loadSshSettings();
      setMessage('Enrollment SSH key removed.');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Unable to remove SSH key.');
    } finally {
      setKeyBusy(false);
    }
  }

  function onPrivateKeyFile(file: File | undefined) {
    if (!file) return;
    file.text().then(setPrivateKey).catch(() => setError('Unable to read key file.'));
  }

  const dashboardPublicKey = sshSettings?.dashboard?.public_key ?? info?.dashboard_public_key;
  const bootstrapConfigured = sshSettings?.bootstrap?.configured ?? info?.bootstrap_ssh_configured;

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-semibold">Add a server</h1>
        <p className="text-slate-400">
          Enroll a node over SSH. PatchDashboard installs its managed key and agent credentials on the server.
        </p>
      </div>

      <form onSubmit={saveEnrollmentKey} className="card max-w-xl space-y-4">
        <h2 className="text-lg font-medium">Enrollment SSH key</h2>
        <p className="text-sm text-slate-400">
          Upload a private key the PatchDashboard host can use to SSH into target servers. This is typically your
          existing admin key with passwordless access. The dashboard&apos;s own key (below) is installed on enrolled
          servers for console access and future management.
        </p>
        {bootstrapConfigured ? (
          <div className="rounded-lg bg-emerald-500/10 px-3 py-2 text-sm text-emerald-200">
            Enrollment key configured
            {sshSettings?.bootstrap?.fingerprint ? ` (${sshSettings.bootstrap.fingerprint})` : null}
          </div>
        ) : (
          <div className="rounded-lg bg-amber-500/10 px-3 py-2 text-sm text-amber-200">
            No enrollment key configured — upload a key, enter an SSH password when enrolling, or pre-install the
            dashboard public key on the target server.
          </div>
        )}
        <label className="block space-y-1 text-sm">
          <span>Private key</span>
          <textarea
            className="input min-h-[8rem] font-mono text-xs"
            value={privateKey}
            onChange={(e) => setPrivateKey(e.target.value)}
            placeholder="-----BEGIN OPENSSH PRIVATE KEY-----"
          />
        </label>
        <label className="block space-y-1 text-sm">
          <span>Or upload key file</span>
          <input
            type="file"
            accept=".pem,.key,text/plain"
            className="block w-full text-sm text-slate-400"
            onChange={(e) => onPrivateKeyFile(e.target.files?.[0])}
          />
        </label>
        <label className="block space-y-1 text-sm">
          <span>Public key (optional — verified against private key)</span>
          <textarea
            className="input min-h-[4rem] font-mono text-xs"
            value={publicKey}
            onChange={(e) => setPublicKey(e.target.value)}
            placeholder="ssh-ed25519 AAAA... optional"
          />
        </label>
        <div className="flex flex-wrap gap-2">
          <button className="btn btn-primary" type="submit" disabled={keyBusy || !privateKey.trim()}>
            {keyBusy ? 'Saving…' : 'Save enrollment key'}
          </button>
          {bootstrapConfigured ? (
            <button className="btn btn-secondary" type="button" disabled={keyBusy} onClick={removeEnrollmentKey}>
              Remove key
            </button>
          ) : null}
        </div>
      </form>

      {dashboardPublicKey ? (
        <div className="card max-w-xl space-y-2 text-sm">
          <h2 className="text-lg font-medium">Dashboard managed key (installed on servers)</h2>
          <p className="text-slate-400">
            Added to <code>authorized_keys</code> on enrolled servers as <code>patchdashboard-managed</code>.
          </p>
          <pre className="overflow-x-auto rounded-lg bg-slate-950 p-3 text-xs text-slate-200">
            {dashboardPublicKey}
          </pre>
        </div>
      ) : null}

      <form onSubmit={enroll} className="card max-w-xl space-y-4">
        <h2 className="text-lg font-medium">Enroll over SSH</h2>
        <p className="text-sm text-slate-400">
          Connects from this PatchDashboard host using the SSH password below, a saved enrollment key, or the
          dashboard key if the target already has it in <code>authorized_keys</code>.
        </p>
        <label className="block space-y-1 text-sm">
          <span>Server IP or hostname</span>
          <input className="input" value={serverIp} onChange={(e) => setServerIp(e.target.value)} required />
        </label>
        <label className="block space-y-1 text-sm">
          <span>SSH user</span>
          <input className="input" value={sshUser} onChange={(e) => setSshUser(e.target.value)} />
        </label>
        <label className="block space-y-1 text-sm">
          <span>SSH password (optional, not stored)</span>
          <input
            className="input"
            type="password"
            value={sshPassword}
            onChange={(e) => setSshPassword(e.target.value)}
            autoComplete="off"
            placeholder="For first-time password login to the server"
          />
        </label>
        <label className="block space-y-1 text-sm">
          <span>Alias (optional)</span>
          <input className="input" value={serverAlias} onChange={(e) => setServerAlias(e.target.value)} />
        </label>
        {error ? <div className="text-sm text-red-300">{error}</div> : null}
        {message ? <div className="text-sm text-sky-200">{message}</div> : null}
        <button className="btn btn-primary" type="submit" disabled={loading}>
          {loading ? 'Enrolling…' : 'Enroll server'}
        </button>
      </form>

      <div className="card max-w-xl space-y-4">
        <h2 className="text-lg font-medium">Manual enrollment</h2>
        <p className="text-sm text-slate-400">Run from the PatchDashboard host if you prefer the CLI:</p>
        <pre className="overflow-x-auto rounded-lg bg-slate-950 p-4 text-sm text-slate-200">
          {info?.enroll_command_example ??
            'sudo /opt/patchdashboard/agents/client/enroll-server.sh --host SERVER_IP --user root'}
        </pre>
      </div>

      <div className="card max-w-xl space-y-4">
        <h2 className="text-lg font-medium">Pull method (self-install)</h2>
        <p className="text-sm text-slate-400">
          On the managed node, run <code>install-agent.sh</code> after setting{' '}
          <code>/etc/patchdashboard/agent.env</code> with <code>PD_API_BASE</code> and <code>PD_AUTH_KEY</code>.
        </p>
        <pre className="overflow-x-auto rounded-lg bg-slate-950 p-4 text-sm text-slate-200">
          sudo /opt/patchdashboard-agent/client/install-agent.sh{'\n'}
          sudo /opt/patchdashboard-agent/client/check-in.sh
        </pre>
      </div>

      <div className="card text-sm text-slate-400">
        After enrollment, trust the server under <span className="text-sky-300">Servers</span> before patches are
        applied.
      </div>
    </div>
  );
}
