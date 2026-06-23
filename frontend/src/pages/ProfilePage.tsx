import { FormEvent, useEffect, useState } from 'react';
import QRCode from 'qrcode';
import { api } from '../lib/api';
import { useAuth } from '../lib/auth';

type ProfileUser = {
  user_id: string;
  display_name: string;
  email: string;
  receive_alerts: boolean;
  mfa_enabled?: boolean;
};

type MfaSetup = {
  secret: string;
  otpauth_uri: string;
};

export function ProfilePage() {
  const { token, refreshUser } = useAuth();
  const [form, setForm] = useState<ProfileUser | null>(null);
  const [password, setPassword] = useState('');
  const [confirmPassword, setConfirmPassword] = useState('');
  const [message, setMessage] = useState('');
  const [mfaSetup, setMfaSetup] = useState<MfaSetup | null>(null);
  const [mfaConfirmCode, setMfaConfirmCode] = useState('');
  const [disablePassword, setDisablePassword] = useState('');
  const [mfaBusy, setMfaBusy] = useState(false);
  const [mfaQrDataUrl, setMfaQrDataUrl] = useState('');

  useEffect(() => {
    if (!mfaSetup?.otpauth_uri) {
      setMfaQrDataUrl('');
      return;
    }

    let cancelled = false;
    QRCode.toDataURL(mfaSetup.otpauth_uri, {
      width: 220,
      margin: 2,
      color: { dark: '#0f172a', light: '#ffffff' },
    })
      .then((url) => {
        if (!cancelled) {
          setMfaQrDataUrl(url);
        }
      })
      .catch(() => {
        if (!cancelled) {
          setMfaQrDataUrl('');
        }
      });

    return () => {
      cancelled = true;
    };
  }, [mfaSetup?.otpauth_uri]);

  useEffect(() => {
    if (!token) return;
    api<{ user: ProfileUser }>('/api/v1/auth/me', {}, token).then((data) => setForm(data.user));
  }, [token]);

  async function onSubmit(event: FormEvent) {
    event.preventDefault();
    if (!token || !form) return;
    setMessage('');
    try {
      const body: Record<string, unknown> = {
        display_name: form.display_name,
        email: form.email,
        receive_alerts: form.receive_alerts,
      };
      if (password) {
        if (password !== confirmPassword) {
          setMessage('Passwords do not match.');
          return;
        }
        body.password = password;
        body.confirm_password = confirmPassword;
      }
      await api('/api/v1/auth/me', { method: 'PATCH', body: JSON.stringify(body) }, token);
      await refreshUser();
      setPassword('');
      setConfirmPassword('');
      setMessage('Profile updated.');
    } catch (err) {
      setMessage(err instanceof Error ? err.message : 'Update failed.');
    }
  }

  async function startMfaSetup() {
    if (!token) return;
    setMfaBusy(true);
    setMessage('');
    try {
      const data = await api<{ setup: MfaSetup }>('/api/v1/auth/mfa/setup', { method: 'POST' }, token);
      setMfaSetup(data.setup);
      setMfaConfirmCode('');
      setMessage('Scan the QR code with your authenticator app, then enter a code to confirm.');
    } catch (err) {
      setMessage(err instanceof Error ? err.message : 'Unable to start MFA setup.');
    } finally {
      setMfaBusy(false);
    }
  }

  async function confirmMfaSetup(event: FormEvent) {
    event.preventDefault();
    if (!token) return;
    setMfaBusy(true);
    setMessage('');
    try {
      const data = await api<{ user: ProfileUser }>(
        '/api/v1/auth/mfa/confirm',
        { method: 'POST', body: JSON.stringify({ code: mfaConfirmCode }) },
        token,
      );
      setForm(data.user);
      setMfaSetup(null);
      setMfaConfirmCode('');
      await refreshUser();
      setMessage('Two-factor authentication is now enabled.');
    } catch (err) {
      setMessage(err instanceof Error ? err.message : 'Invalid authentication code.');
    } finally {
      setMfaBusy(false);
    }
  }

  async function disableMfa(event: FormEvent) {
    event.preventDefault();
    if (!token) return;
    setMfaBusy(true);
    setMessage('');
    try {
      const data = await api<{ user: ProfileUser }>(
        '/api/v1/auth/mfa/disable',
        { method: 'POST', body: JSON.stringify({ password: disablePassword }) },
        token,
      );
      setForm(data.user);
      setDisablePassword('');
      await refreshUser();
      setMessage('Two-factor authentication disabled.');
    } catch (err) {
      setMessage(err instanceof Error ? err.message : 'Unable to disable MFA.');
    } finally {
      setMfaBusy(false);
    }
  }

  if (!form) {
    return <div className="text-slate-400">Loading profile...</div>;
  }

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-3xl font-semibold">Profile</h1>
        <p className="text-slate-400">Edit your account settings.</p>
      </div>

      {message ? <div className="rounded-lg bg-sky-500/10 px-3 py-2 text-sm text-sky-200">{message}</div> : null}

      <form onSubmit={onSubmit} className="card max-w-xl space-y-4">
        <label className="block space-y-1 text-sm">
          <span>Username</span>
          <input className="input" value={form.user_id} readOnly />
        </label>
        <label className="block space-y-1 text-sm">
          <span>Display name</span>
          <input
            className="input"
            value={form.display_name}
            onChange={(e) => setForm({ ...form, display_name: e.target.value })}
          />
        </label>
        <label className="block space-y-1 text-sm">
          <span>Email</span>
          <input
            className="input"
            type="email"
            value={form.email}
            onChange={(e) => setForm({ ...form, email: e.target.value })}
          />
        </label>
        <label className="block space-y-1 text-sm">
          <span>New password (leave blank to keep)</span>
          <input className="input" type="password" value={password} onChange={(e) => setPassword(e.target.value)} />
        </label>
        <label className="block space-y-1 text-sm">
          <span>Confirm password</span>
          <input
            className="input"
            type="password"
            value={confirmPassword}
            onChange={(e) => setConfirmPassword(e.target.value)}
          />
        </label>
        <label className="flex items-center gap-2 text-sm">
          <input
            type="checkbox"
            checked={form.receive_alerts}
            onChange={(e) => setForm({ ...form, receive_alerts: e.target.checked })}
          />
          Receive alert notifications
        </label>
        <button className="btn btn-primary" type="submit">
          Save profile
        </button>
      </form>

      <div className="card max-w-xl space-y-4">
        <div>
          <h2 className="text-lg font-medium">Two-factor authentication</h2>
          <p className="mt-1 text-sm text-slate-400">
            Require a code from an authenticator app (Google Authenticator, 1Password, etc.) when signing in.
          </p>
        </div>

        {form.mfa_enabled ? (
          <form onSubmit={disableMfa} className="space-y-3">
            <div className="rounded-lg bg-emerald-500/10 px-3 py-2 text-sm text-emerald-200">
              MFA is enabled on your account.
            </div>
            <label className="block space-y-1 text-sm">
              <span>Current password</span>
              <input
                className="input"
                type="password"
                value={disablePassword}
                onChange={(e) => setDisablePassword(e.target.value)}
                autoComplete="current-password"
                required
              />
            </label>
            <button className="btn btn-secondary" type="submit" disabled={mfaBusy}>
              {mfaBusy ? 'Disabling…' : 'Disable MFA'}
            </button>
          </form>
        ) : mfaSetup ? (
          <form onSubmit={confirmMfaSetup} className="space-y-3">
            <p className="text-sm text-slate-300">
              Scan this QR code with your authenticator app, then enter the 6-digit code to confirm.
            </p>
            <div className="flex flex-col items-start gap-3 sm:flex-row sm:items-center">
              {mfaQrDataUrl ? (
                <img
                  src={mfaQrDataUrl}
                  alt="MFA setup QR code"
                  className="rounded-lg border border-slate-700 bg-white p-2"
                  width={220}
                  height={220}
                />
              ) : (
                <div className="flex h-[220px] w-[220px] items-center justify-center rounded-lg border border-slate-700 bg-slate-950 text-xs text-slate-500">
                  Generating QR…
                </div>
              )}
              <div className="text-sm text-slate-400">
                <p className="mb-2">Can&apos;t scan? Enter this key manually:</p>
                <div className="rounded-lg bg-slate-950 p-3 font-mono text-xs tracking-wider text-sky-200 break-all">
                  {mfaSetup.secret}
                </div>
              </div>
            </div>
            <label className="block space-y-1 text-sm">
              <span>Verification code</span>
              <input
                className="input font-mono tracking-widest"
                inputMode="numeric"
                maxLength={6}
                value={mfaConfirmCode}
                onChange={(e) => setMfaConfirmCode(e.target.value.replace(/\D/g, '').slice(0, 6))}
                autoFocus
              />
            </label>
            <div className="flex flex-wrap gap-2">
              <button className="btn btn-primary" type="submit" disabled={mfaBusy || mfaConfirmCode.length !== 6}>
                {mfaBusy ? 'Confirming…' : 'Confirm MFA'}
              </button>
              <button
                type="button"
                className="btn btn-secondary"
                onClick={() => {
                  setMfaSetup(null);
                  setMfaConfirmCode('');
                  setMfaQrDataUrl('');
                }}
              >
                Cancel
              </button>
            </div>
          </form>
        ) : (
          <button type="button" className="btn btn-primary" disabled={mfaBusy} onClick={startMfaSetup}>
            {mfaBusy ? 'Starting…' : 'Enable MFA'}
          </button>
        )}
      </div>
    </div>
  );
}
