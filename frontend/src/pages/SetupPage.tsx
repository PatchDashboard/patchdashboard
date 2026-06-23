import { FormEvent, useEffect, useState } from 'react';
import { Link, Navigate, useSearchParams } from 'react-router-dom';
import { api, ApiError } from '../lib/api';

type SetupStatus = {
  installed: boolean;
  webSetupPending: boolean;
  requiresToken: boolean;
};

type CompleteResponse = {
  ok: boolean;
  installKey: string;
  dashboardPublicKey?: string | null;
};

export function SetupPage() {
  const [searchParams] = useSearchParams();
  const token = searchParams.get('token') ?? '';

  const [status, setStatus] = useState<SetupStatus | null>(null);
  const [loading, setLoading] = useState(true);
  const [tokenValid, setTokenValid] = useState<boolean | null>(null);
  const [company, setCompany] = useState('');
  const [adminUser, setAdminUser] = useState('admin');
  const [adminEmail, setAdminEmail] = useState('');
  const [adminPass, setAdminPass] = useState('');
  const [adminPassConfirm, setAdminPassConfirm] = useState('');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [installKey, setInstallKey] = useState('');
  const [dashboardPublicKey, setDashboardPublicKey] = useState('');
  const [completed, setCompleted] = useState(false);

  useEffect(() => {
    api<SetupStatus>('/api/v1/setup/status')
      .then(setStatus)
      .catch(() => setError('Unable to load setup status.'))
      .finally(() => setLoading(false));
  }, []);

  useEffect(() => {
    if (!status?.webSetupPending || !token) {
      setTokenValid(status?.webSetupPending ? false : null);
      return;
    }

    api<{ valid: boolean }>('/api/v1/setup/verify-token', {
      method: 'POST',
      body: JSON.stringify({ token }),
    })
      .then((data) => setTokenValid(data.valid))
      .catch(() => setTokenValid(false));
  }, [status, token]);

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        Loading setup...
      </div>
    );
  }

  if (status?.installed) {
    return <Navigate to="/login" replace />;
  }

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    setError('');

    if (adminPass !== adminPassConfirm) {
      setError('Passwords do not match.');
      return;
    }

    setSubmitting(true);
    try {
      const result = await api<CompleteResponse>('/api/v1/setup/complete', {
        method: 'POST',
        body: JSON.stringify({
          token,
          company,
          adminUser,
          adminEmail,
          adminPass,
        }),
      });
      setInstallKey(result.installKey);
      setDashboardPublicKey(result.dashboardPublicKey ?? '');
      setCompleted(true);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Setup failed.');
    } finally {
      setSubmitting(false);
    }
  }

  if (completed) {
    return (
      <div className="flex min-h-screen items-center justify-center px-4">
        <div className="card w-full max-w-md space-y-4">
          <h1 className="text-2xl font-semibold">Setup complete</h1>
          <p className="text-sm text-slate-400">
            PatchDashboard is ready. Sign in with the admin account you just created.
          </p>
          <div className="rounded-lg bg-slate-800 px-3 py-2 text-sm">
            <div className="text-slate-400">Agent install key (PD_AUTH_KEY)</div>
            <code className="break-all">{installKey}</code>
          </div>
          {dashboardPublicKey ? (
            <div className="rounded-lg bg-slate-800 px-3 py-2 text-sm">
              <div className="text-slate-400">Dashboard SSH public key (for server enrollment)</div>
              <code className="break-all text-xs">{dashboardPublicKey}</code>
            </div>
          ) : null}
          <p className="text-sm text-slate-400">
            Enroll servers from Add server or run{' '}
            <code>agents/client/enroll-server.sh</code> on this host.
          </p>
          <Link to="/login" className="btn btn-primary block text-center">
            Go to sign in
          </Link>
        </div>
      </div>
    );
  }

  if (!status?.webSetupPending) {
    return (
      <div className="flex min-h-screen items-center justify-center px-4">
        <div className="card w-full max-w-md space-y-4">
          <h1 className="text-2xl font-semibold">Web setup unavailable</h1>
          <p className="text-sm text-slate-400">
            Run the VM installer with <code>--prepare-web-setup</code> to generate a one-time setup
            URL, or complete installation with <code>sudo ./install.sh</code>.
          </p>
          <Link to="/login" className="btn block text-center">
            Back to sign in
          </Link>
        </div>
      </div>
    );
  }

  if (!token || tokenValid === false) {
    return (
      <div className="flex min-h-screen items-center justify-center px-4">
        <div className="card w-full max-w-md space-y-4">
          <h1 className="text-2xl font-semibold">Invalid setup link</h1>
          <p className="text-sm text-slate-400">
            Open the full setup URL printed by <code>sudo ./install.sh --prepare-web-setup</code>.
            The link includes a one-time token and expires after setup completes.
          </p>
        </div>
      </div>
    );
  }

  if (tokenValid === null) {
    return (
      <div className="flex min-h-screen items-center justify-center">
        Verifying setup token...
      </div>
    );
  }

  return (
    <div className="flex min-h-screen items-center justify-center px-4">
      <form onSubmit={onSubmit} className="card w-full max-w-md space-y-4">
        <div>
          <h1 className="text-2xl font-semibold">Finish PatchDashboard setup</h1>
          <p className="mt-1 text-sm text-slate-400">
            Create your organization and administrator account. This page locks itself after setup.
          </p>
        </div>
        {error ? <div className="rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-300">{error}</div> : null}
        <label className="block space-y-1 text-sm">
          <span>Company name</span>
          <input className="input" value={company} onChange={(e) => setCompany(e.target.value)} required />
        </label>
        <label className="block space-y-1 text-sm">
          <span>Admin username</span>
          <input className="input" value={adminUser} onChange={(e) => setAdminUser(e.target.value)} required />
        </label>
        <label className="block space-y-1 text-sm">
          <span>Admin email</span>
          <input
            className="input"
            type="email"
            value={adminEmail}
            onChange={(e) => setAdminEmail(e.target.value)}
            required
          />
        </label>
        <label className="block space-y-1 text-sm">
          <span>Admin password</span>
          <input
            className="input"
            type="password"
            value={adminPass}
            onChange={(e) => setAdminPass(e.target.value)}
            minLength={8}
            required
          />
        </label>
        <label className="block space-y-1 text-sm">
          <span>Confirm password</span>
          <input
            className="input"
            type="password"
            value={adminPassConfirm}
            onChange={(e) => setAdminPassConfirm(e.target.value)}
            minLength={8}
            required
          />
        </label>
        <button className="btn btn-primary w-full" disabled={submitting}>
          {submitting ? 'Finishing setup...' : 'Complete setup'}
        </button>
      </form>
    </div>
  );
}
