import { FormEvent, useEffect, useState } from 'react';
import { Navigate } from 'react-router-dom';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';

export function LoginPage() {
  const { token, login, verifyMfa } = useAuth();
  const [step, setStep] = useState<'password' | 'mfa'>('password');
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [mfaToken, setMfaToken] = useState('');
  const [mfaCode, setMfaCode] = useState('');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);
  const [setupPending, setSetupPending] = useState(false);

  useEffect(() => {
    api<{ installed: boolean; webSetupPending: boolean }>('/api/v1/setup/status')
      .then((status) => setSetupPending(!status.installed && status.webSetupPending))
      .catch(() => undefined);
  }, []);

  if (token) {
    return <Navigate to="/" replace />;
  }

  async function onPasswordSubmit(event: FormEvent) {
    event.preventDefault();
    setSubmitting(true);
    setError('');
    try {
      const result = await login(username, password);
      if (result.status === 'mfa_required') {
        setMfaToken(result.mfaToken);
        setStep('mfa');
        setPassword('');
        return;
      }
    } catch {
      setError('Invalid username or password.');
    } finally {
      setSubmitting(false);
    }
  }

  async function onMfaSubmit(event: FormEvent) {
    event.preventDefault();
    setSubmitting(true);
    setError('');
    try {
      await verifyMfa(mfaToken, mfaCode);
    } catch {
      setError('Invalid or expired authentication code.');
    } finally {
      setSubmitting(false);
    }
  }

  return (
    <div className="flex min-h-screen items-center justify-center px-4">
      {step === 'password' ? (
        <form onSubmit={onPasswordSubmit} className="card w-full max-w-md space-y-4">
          <div>
            <h1 className="text-2xl font-semibold">Sign in</h1>
            <p className="mt-1 text-sm text-slate-400">
              PatchDashboard monitors host patches and container vulnerabilities.
            </p>
          </div>
          {setupPending ? (
            <div className="rounded-lg bg-amber-500/10 px-3 py-2 text-sm text-amber-200">
              Installation is not finished. Open the setup URL from{' '}
              <code>sudo ./install.sh --prepare-web-setup</code>.
            </div>
          ) : null}
          {error ? <div className="rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-300">{error}</div> : null}
          <label className="block space-y-1 text-sm">
            <span>Username</span>
            <input className="input" value={username} onChange={(e) => setUsername(e.target.value)} />
          </label>
          <label className="block space-y-1 text-sm">
            <span>Password</span>
            <input
              className="input"
              type="password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
            />
          </label>
          <button className="btn btn-primary w-full" disabled={submitting}>
            {submitting ? 'Signing in...' : 'Sign in'}
          </button>
        </form>
      ) : (
        <form onSubmit={onMfaSubmit} className="card w-full max-w-md space-y-4">
          <div>
            <h1 className="text-2xl font-semibold">Two-factor authentication</h1>
            <p className="mt-1 text-sm text-slate-400">
              Enter the 6-digit code from your authenticator app for <strong>{username}</strong>.
            </p>
          </div>
          {error ? <div className="rounded-lg bg-red-500/10 px-3 py-2 text-sm text-red-300">{error}</div> : null}
          <label className="block space-y-1 text-sm">
            <span>Authentication code</span>
            <input
              className="input font-mono tracking-widest"
              inputMode="numeric"
              autoComplete="one-time-code"
              maxLength={6}
              value={mfaCode}
              onChange={(e) => setMfaCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
              autoFocus
            />
          </label>
          <button className="btn btn-primary w-full" disabled={submitting || mfaCode.length !== 6}>
            {submitting ? 'Verifying...' : 'Verify and sign in'}
          </button>
          <button
            type="button"
            className="btn btn-secondary w-full"
            onClick={() => {
              setStep('password');
              setMfaToken('');
              setMfaCode('');
              setError('');
            }}
          >
            Back to sign in
          </button>
        </form>
      )}
    </div>
  );
}
