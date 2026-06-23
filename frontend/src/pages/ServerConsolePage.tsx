import { FormEvent, useEffect, useRef, useState } from 'react';
import { Link, Navigate, useParams } from 'react-router-dom';
import { Terminal } from '@xterm/xterm';
import { FitAddon } from '@xterm/addon-fit';
import '@xterm/xterm/css/xterm.css';
import { api, ApiError } from '../lib/api';
import { useAuth } from '../lib/auth';

type ConsoleSession = {
  token: string;
  ws_url: string;
  expires_in: number;
  dev_stub: boolean;
  server: {
    id: number;
    server_alias: string;
    server_ip: string;
    ssh_user: string;
  };
};

function consoleWsUrl(base: string, token: string): string {
  const url = new URL(base);
  url.searchParams.set('token', token);
  return url.toString();
}

export function ServerConsolePage() {
  const { id } = useParams();
  const { token, user } = useAuth();
  const containerRef = useRef<HTMLDivElement | null>(null);
  const terminalRef = useRef<Terminal | null>(null);
  const fitRef = useRef<FitAddon | null>(null);
  const socketRef = useRef<WebSocket | null>(null);
  const [session, setSession] = useState<ConsoleSession | null>(null);
  const [password, setPassword] = useState('');
  const [error, setError] = useState('');
  const [submitting, setSubmitting] = useState(false);

  async function startSession(event: FormEvent) {
    event.preventDefault();
    if (!token || !id) {
      return;
    }

    setSubmitting(true);
    setError('');

    try {
      const data = await api<{ session: ConsoleSession }>(
        `/api/v1/servers/${id}/console/session`,
        { method: 'POST', body: JSON.stringify({ password }) },
        token,
      );
      setSession(data.session);
      setPassword('');
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Unable to start console session.');
    } finally {
      setSubmitting(false);
    }
  }

  useEffect(() => {
    if (!session || !containerRef.current) {
      return;
    }

    const terminal = new Terminal({
      cursorBlink: true,
      fontSize: 14,
      theme: {
        background: '#020617',
        foreground: '#e2e8f0',
        cursor: '#38bdf8',
      },
    });
    const fitAddon = new FitAddon();
    terminal.loadAddon(fitAddon);
    terminal.open(containerRef.current);
    fitAddon.fit();

    terminalRef.current = terminal;
    fitRef.current = fitAddon;

    const ws = new WebSocket(consoleWsUrl(session.ws_url, session.token));
    socketRef.current = ws;

    ws.onmessage = (event) => {
      terminal.write(typeof event.data === 'string' ? event.data : '');
    };

    ws.onopen = () => {
      terminal.focus();
    };

    ws.onerror = () => {
      terminal.writeln('\r\n\x1b[31mWebSocket connection failed.\x1b[0m');
    };

    ws.onclose = (event) => {
      if (event.code !== 1000) {
        terminal.writeln(`\r\n\x1b[33mSession closed (${event.code}).\x1b[0m`);
      }
    };

    terminal.onData((data) => {
      if (ws.readyState === WebSocket.OPEN) {
        ws.send(data);
      }
    });

    const onResize = () => fitAddon.fit();
    window.addEventListener('resize', onResize);

    return () => {
      window.removeEventListener('resize', onResize);
      ws.close();
      terminal.dispose();
      terminalRef.current = null;
      fitRef.current = null;
      socketRef.current = null;
    };
  }, [session]);

  if (!user?.admin) {
    return <Navigate to="/" replace />;
  }

  return (
    <div className="flex h-[calc(100vh-8rem)] flex-col gap-4">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-3xl font-semibold">Server console</h1>
          <p className="text-slate-400">
            {session
              ? `${session.server.server_alias} · ${session.server.server_ip} · ${session.server.ssh_user}`
              : `Server #${id}`}
            {session?.dev_stub ? ' · development stub' : null}
          </p>
        </div>
        <Link className="btn btn-secondary" to={`/servers/${id}/edit`}>
          Back to server
        </Link>
      </div>

      {!session ? (
        <form onSubmit={startSession} className="card mx-auto w-full max-w-md space-y-4">
          <div>
            <h2 className="text-lg font-medium">Confirm your password</h2>
            <p className="mt-1 text-sm text-slate-400">
              Re-enter your PatchDashboard password to open a remote SSH console session.
            </p>
          </div>
          <label className="block text-sm">
            <span className="text-slate-400">Password</span>
            <input
              type="password"
              className="input mt-1 w-full"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              autoComplete="current-password"
              autoFocus
              required
            />
          </label>
          {error ? <div className="text-sm text-red-300">{error}</div> : null}
          <button type="submit" className="btn btn-primary w-full" disabled={submitting}>
            {submitting ? 'Connecting…' : 'Open console'}
          </button>
        </form>
      ) : null}

      {session ? (
        <div className="card min-h-0 flex-1 overflow-hidden p-2">
          <div ref={containerRef} className="h-full min-h-[24rem] w-full" />
        </div>
      ) : null}
    </div>
  );
}
