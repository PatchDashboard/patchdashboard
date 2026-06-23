import http from 'node:http';
import { WebSocketServer } from 'ws';
import { connectRedis, consumeConsoleSession } from './redis.mjs';

const PORT = Number(process.env.CONSOLE_PORT ?? 8090);
const DEV_STUB = (process.env.CONSOLE_DEV_STUB ?? 'true').toLowerCase() !== 'false';
const SSH_KEY_PATH = process.env.CONSOLE_SSH_KEY_PATH ?? '/keys/patchdashboard_ed25519';

function stubSession(ws, session) {
  const alias = session.server_alias || `server #${session.server_id}`;
  const host = session.host || 'unknown';
  const user = session.ssh_user || 'root';

  const banner = [
    '\r\n\x1b[33m[PatchDashboard SSH console — development stub]\x1b[0m\r\n',
    `\r\nThis would be an SSH terminal to \x1b[1m${alias}\x1b[0m (${host}) as \x1b[1m${user}\x1b[0m.\r\n`,
    '\r\nReal SSH (ssh2 + stored dashboard key) is not wired up yet.\r\n',
    'Type commands and press Enter — nothing is executed on the remote host.\r\n\r\n',
    '$ ',
  ].join('');

  ws.send(banner);

  let line = '';

  ws.on('message', (data) => {
    const input = Buffer.isBuffer(data) ? data.toString('utf8') : String(data);

    for (const ch of input) {
      if (ch === '\r' || ch === '\n') {
        ws.send(`\r\n\x1b[90m(dev stub) Would run:\x1b[0m ${line || '(empty)'}\r\n$ `);
        line = '';
        continue;
      }

      if (ch === '\u007f' || ch === '\b') {
        if (line.length > 0) {
          line = line.slice(0, -1);
          ws.send('\b \b');
        }
        continue;
      }

      if (ch === '\u0003') {
        ws.send('^C\r\n$ ');
        line = '';
        continue;
      }

      if (ch.charCodeAt(0) >= 32) {
        line += ch;
        ws.send(ch);
      }
    }
  });
}

async function realSession(ws, session) {
  void session;
  void SSH_KEY_PATH;
  ws.send('\r\n\x1b[31mReal SSH mode is not implemented yet.\x1b[0m Set CONSOLE_DEV_STUB=true for the placeholder terminal.\r\n');
  ws.close();
}

const server = http.createServer((_req, res) => {
  res.writeHead(200, { 'Content-Type': 'text/plain' });
  res.end('PatchDashboard console WebSocket service\n');
});

const wss = new WebSocketServer({ server });

wss.on('connection', async (ws, req) => {
  const url = new URL(req.url ?? '/', `http://${req.headers.host ?? 'localhost'}`);
  const token = url.searchParams.get('token') ?? '';

  if (!token) {
    ws.close(4401, 'Missing token');
    return;
  }

  let session;
  try {
    session = await consumeConsoleSession(token);
  } catch (error) {
    console.error('Session lookup failed:', error);
    ws.close(4500, 'Session store error');
    return;
  }

  if (!session) {
    ws.close(4403, 'Invalid or expired session');
    return;
  }

  if (DEV_STUB) {
    stubSession(ws, session);
    return;
  }

  ws.send('\r\nConnecting via SSH...\r\n');
  try {
    await realSession(ws, session);
  } catch (error) {
    console.error('SSH session failed:', error);
    ws.send(`\r\n\x1b[31mSSH connection failed:\x1b[0m ${error.message}\r\n`);
    ws.close();
  }
});

await connectRedis();

server.listen(PORT, () => {
  console.log(`PatchDashboard console listening on :${PORT} (dev_stub=${DEV_STUB}, sessions=redis)`);
});
