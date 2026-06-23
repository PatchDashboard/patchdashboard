import { FormEvent, useEffect, useState } from 'react';
import { Navigate } from 'react-router-dom';
import { api, ApiError } from '../lib/api';
import { useAuth } from '../lib/auth';

type Onboarding = {
  https: boolean;
  base_url: string;
  dashboard_public_key?: string | null;
  enroll_command_example?: string;
};

export function AddServerPage() {
  const { token, user } = useAuth();
  const [info, setInfo] = useState<Onboarding | null>(null);
  const [serverIp, setServerIp] = useState('');
  const [sshUser, setSshUser] = useState('root');
  const [serverAlias, setServerAlias] = useState('');
  const [loading, setLoading] = useState(false);
  const [message, setMessage] = useState('');
  const [error, setError] = useState('');

  useEffect(() => {
    if (!token || !user?.admin) return;
    api<Onboarding>('/api/v1/servers/onboarding', {}, token).then(setInfo);
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
          }),
        },
        token,
      );
      setMessage(result.message ?? 'Server enrolled. Trust it under Servers when it appears.');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Enrollment failed.');
    } finally {
      setLoading(false);
    }
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-semibold">Add a server</h1>
        <p className="text-slate-400">
          Enroll a node over SSH. PatchDashboard installs its SSH key and agent credentials on the server.
        </p>
      </div>

      <form onSubmit={enroll} className="card max-w-xl space-y-4">
        <h2 className="text-lg font-medium">Enroll over SSH</h2>
        <p className="text-sm text-slate-400">
          Requires SSH access from this PatchDashboard host to the target server (password or existing key for the
          first connection).
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
          <span>Alias (optional)</span>
          <input className="input" value={serverAlias} onChange={(e) => setServerAlias(e.target.value)} />
        </label>
        {error ? <div className="text-sm text-red-300">{error}</div> : null}
        {message ? <div className="text-sm text-sky-200">{message}</div> : null}
        <button className="btn btn-primary" type="submit" disabled={loading}>
          {loading ? 'Enrolling…' : 'Enroll server'}
        </button>
      </form>

      {info?.dashboard_public_key ? (
        <div className="card max-w-xl space-y-2 text-sm">
          <h2 className="text-lg font-medium">Dashboard SSH public key</h2>
          <p className="text-slate-400">Installed on enrolled servers as <code>patchdashboard-managed</code>.</p>
          <pre className="overflow-x-auto rounded-lg bg-slate-950 p-3 text-xs text-slate-200">
            {info.dashboard_public_key}
          </pre>
        </div>
      ) : null}

      <div className="card max-w-xl space-y-4">
        <h2 className="text-lg font-medium">Manual enrollment</h2>
        <p className="text-sm text-slate-400">
          Run from the PatchDashboard host if you prefer the CLI:
        </p>
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
