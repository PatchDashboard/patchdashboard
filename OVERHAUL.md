# PatchDashboard v2.0

Branch `2026-overhaul` replaces the legacy PHP UI with a React SPA, PHP 8 Slim API, consolidated bash agents, optional Redis-backed services, and integrated CVE/advisory sync.

## Architecture

```
frontend/     React 19 + Vite + Tailwind SPA
api/          PHP 8.3 Slim 4 REST API (/api/v1)
console/      Node WebSocket service for browser SSH sessions
agents/       Pull-model client scripts + shared libs
database/     Schema (db_create.sql), numbered migrations, seed/bootstrap
legacy/       v1 PHP UI and installer (deprecated)
```

```
┌─────────────┐     REST/JWT      ┌─────────────┐
│   Browser   │◄─────────────────►│  PHP API    │
│  (React)    │                   │  (Slim)     │
└──────┬──────┘                   └──────┬──────┘
       │ WebSocket                       │ MySQL
       ▼                                 ▼
┌─────────────┐     Redis         ┌─────────────┐
│  console/   │◄─────────────────►│   MariaDB   │
└─────────────┘                   └─────────────┘
       ▲
       │ SSH (production) or dev stub
       ▼
┌─────────────┐     check-in      ┌─────────────┐
│   Agents    │──────────────────►│  PHP API    │
│  (bash)     │   patches/CVE     └─────────────┘
└─────────────┘
```

## Quick start (Docker development)

```bash
git checkout 2026-overhaul
docker compose up --build
```

| Service | Port | Role |
|---------|------|------|
| `frontend` | 5173 | Vite dev server; proxies API |
| `api` | 8080 | PHP built-in server; runs migrations on start |
| `mysql` | 3306 | MariaDB 11; schema from `db_create.sql` |
| `redis` | 6379 | Console session store |
| `console` | 8090 | WebSocket SSH console (`CONSOLE_DEV_STUB=true` by default) |

- Frontend: http://localhost:5173
- API health: http://localhost:8080/health

Optional seed data:

```bash
docker compose exec api php /database/seed.php --fresh
```

## Production Docker

```bash
git clone https://github.com/PatchDashboard/patchdashboard
cd patchdashboard
./install.sh --docker-prod
```

Hybrid browser setup:

```bash
./install.sh --docker-prod --prepare-web-setup
# Open the printed http://…/setup?token=… URL
```

Unattended example:

```bash
./install.sh --docker-prod --unattended \
  --public-url=http://patch.example.com \
  --admin-email=admin@example.com \
  --admin-pass='change-me' \
  --company='Acme Inc' \
  --db-pass='db-secret' \
  --db-root-pass='root-secret'
```

| Service | Role |
|---------|------|
| `web` | nginx serves React build; proxies `/api/` and `/health` to PHP-FPM |
| `api` | PHP 8.3 FPM Slim API |
| `mysql` | MariaDB 11 |
| `redis` | Shared session store for console |

Manage stack: `docker compose -f docker-compose.prod.yml ps|logs|up -d --build`

Put TLS termination (Caddy, Traefik, cloud LB) in front of the published HTTP port for HTTPS.

## VM / bare-metal install

**Hybrid web setup (recommended)** — CLI prepares nginx + PHP-FPM + MariaDB; admin account created in browser:

```bash
sudo ./install.sh --prepare-web-setup
# Open printed Setup URL: http://your-host/setup?token=…
```

**Full CLI install** — admin created on CLI, no browser wizard:

```bash
sudo ./install.sh
```

Supported distros: Debian 12+, Ubuntu 22.04+, Rocky/Alma/RHEL 9+. Default root: `/opt/patchdashboard`.

### Hybrid setup layout

| Path | Purpose |
|------|---------|
| `frontend/dist/` | React SPA (nginx root) |
| `api/public/index.php` | Slim front controller |
| `api/.env` | DB, JWT, Redis, console, alert config |
| `api/storage/setup.token` | One-time web setup token (deleted after use) |
| `api/storage/install.lock` | Marks completed installation |
| `api/storage/ssh/` | Dashboard Ed25519 keypair for enrollment/console |

## Web UI

| Route | Description |
|-------|-------------|
| `/` | Dashboard — patch counts, distro breakdown |
| `/search` | Fleet-wide package search |
| `/servers` | Server list, trust/untrust, power actions, console link |
| `/servers/add` | SSH enrollment (admin) |
| `/servers/:id/edit` | Server settings |
| `/servers/:id/patches` | Pending patches; install all; suppress; CVE enrich |
| `/servers/:id/packages` | Installed packages; suppress |
| `/servers/:id/console` | Web SSH (admin, trusted servers, password confirmation) |
| `/containers` | Application CVEs — raw Trivy inventory |
| `/vulnerabilities` | Unified vulnerability search (Trivy + advisories + patches) |
| `/suppressions` | Per-server suppression management |
| `/preferences` | Alert opt-in; global suppressions |
| `/profile` | Account settings; **MFA enrollment** |
| `/alerts` | Alert rules, webhooks, Slack integration |
| `/cve-feeds` | Enable/add/sync CVE feeds (admin) |
| `/users` | User CRUD (admin) |
| `/plugins` | Plugin page visibility (admin) |

Global package search box in the sidebar header searches the advisory library and fleet packages.

## Features

### Authentication

- JWT sessions (`Authorization: Bearer`); legacy SHA256 passwords transparently rehashed to bcrypt on login
- **TOTP MFA** — optional per user; Profile → Enable MFA (QR code + manual key); login prompts for 6-digit code when enabled
- Console and MFA disable require password re-entry

### Servers & agents

- **Enrollment** — admin adds server via SSH (`/servers/add` or `agents/client/enroll-server.sh`); installs agent env, cron, and dashboard SSH key
- **Trust / untrust** — untrust revokes agent key, removes SSH key from `authorized_keys`, blocks console
- **Check-in** — agents POST patches, packages, container scans; receive install/reboot/shutdown commands
- **Power commands** — reboot/shutdown queued per server (configurable command templates per distro)
- **Non-root SSH user** — optional `ssh_user` field per server

Agent environment on nodes: `/etc/patchdashboard/agent.env` with `PD_API_BASE`, `PD_CLIENT_KEY`, etc.

Agent cron (`agents/client/cron/patchdashboard`):

- **03:00 daily** — `application_cve_scan.sh` (host OS + all container images)
- **Every 5 min** — `check-in.sh` (patch check; CVE rescan if &gt; 24h since last)

### Patches & suppressions

- View pending updates per server; sortable columns
- **Install all** pending patches via API (admin)
- **Suppress** packages globally, per server, or inline from patch/package/vulnerability tables
- Live patch urgency lookup via UbuntuUpdates.org for supported distros

### Vulnerabilities & CVE data

- **Application CVEs** (`/containers`) — Trivy results from agents; filter by severity, server, image
- **Vulnerabilities** (`/vulnerabilities`) — cross-fleet search with filters for CVE ID, package, severity, distro, runtime, CVSS
- **CVE feed sync** — bulk import into local catalog and per-distro advisories

Feed providers (configure at **CVE feeds**, admin):

| Provider | Scope | Source |
|----------|-------|--------|
| NVD | Global | NIST API (incremental by last-modified window) |
| GitHub | Global | OSV unified export (GHSA records) |
| Debian / Ubuntu USN / Red Hat / Rocky / AlmaLinux / Fedora | Per distro | OSV bulk zips |
| UbuntuUpdates | Per distro | On-demand live lookup |

Enable feeds with the checkbox; **Add feed** for providers not yet configured for a distro. Sync manually or on schedule:

```bash
php database/cve_sync.php --all
php database/cve_sync.php --feed=12
php database/cve_sync.php --provider=nvd --distro=Ubuntu
```

Recommended cron on dashboard host:

```bash
0 2 * * * cd /opt/patchdashboard && php database/cve_sync.php --all
```

Set `NVD_API_KEY` in `api/.env` for NVD rate limits. Optional `CVE_SYNC_MEMORY_LIMIT=512M` for large OSV imports.

### Web SSH console

- Admin opens console from server list or edit page
- User re-enters dashboard password before session token is issued
- One-time WebSocket token stored in Redis (5-minute TTL)
- **Development:** `CONSOLE_DEV_STUB=true` — terminal stub, no real SSH
- **Production:** set `CONSOLE_DEV_STUB=false`; console service connects with dashboard key from `api/storage/ssh/`

Configure `CONSOLE_WS_PUBLIC_URL` (browser-facing WebSocket URL) and `REDIS_URL`.

### Alerting

- Rules for new container CVEs by severity threshold
- **Email** — SMTP settings in `api/.env`
- **Webhooks** — generic HTTP POST with JSON payload
- **Slack** — incoming webhook; customizable card template; optional `SLACK_WEBHOOK_URL` in env auto-registers on Alerts page load
- Per-user **receive alerts** toggle on Preferences

### Administration

- User management (admin): create, disable, assign admin role
- Plugin pages: enable/disable legacy plugin routes in nav
- Setup wizard (`/setup?token=…`) for hybrid installs — company name, admin account, install key

## Environment variables

See `api/.env.example` and `.env.prod.example`. Key settings:

| Variable | Purpose |
|----------|---------|
| `JWT_SECRET` / `JWT_TTL` | Session signing and lifetime |
| `MFA_ENCRYPTION_KEY` | Encrypts stored TOTP secrets (defaults to `JWT_SECRET`) |
| `NVD_API_KEY` | NVD bulk sync rate limits |
| `GITHUB_TOKEN` | GitHub advisory API (optional) |
| `CVE_SYNC_MEMORY_LIMIT` | PHP memory for CVE sync CLI |
| `REDIS_URL` | Console session store |
| `CONSOLE_WS_PUBLIC_URL` | Browser WebSocket URL for console |
| `CONSOLE_DEV_STUB` | `true` = stub terminal; `false` = real SSH |
| `SLACK_WEBHOOK_URL` | Optional default Slack webhook |
| `SMTP_*` / `MAIL_FROM` | Outbound email for alerts |

## Local development (without Docker)

### API

```bash
cd api && cp .env.example .env && composer install && composer start
```

### Frontend

```bash
cd frontend && npm install && npm run dev
```

### Database

```bash
php database/migrate.php
php database/seed.php --fresh   # optional demo data
```

Requires local MariaDB/MySQL and Redis if testing console sessions.

## Database migrations

Numbered SQL in `database/migrations/` (001–014). Applied by:

```bash
php database/migrate.php
```

Notable migrations: plugin pages, power commands, Slack integration, CVE feeds, vulnerability search indexes, server trust/SSH keys, user MFA columns.

## Legacy compatibility

- Agent check-in accepts legacy plain-text responses during rollout
- Legacy client endpoints under `legacy/html/client/` remain until agents are upgraded
- v1 installer: `./legacy/install.sh`

## Status

Active development on `2026-overhaul`. Use Docker Compose or `./install.sh` for v2. Legacy files are reference-only under `legacy/`.
