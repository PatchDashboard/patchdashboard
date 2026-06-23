# PatchDashboard v2.0 Overhaul

Branch `2026-overhaul` modernizes PatchDashboard into a React SPA + PHP 8 REST API with consolidated bash agents and container vulnerability alerting.

## Architecture

```
frontend/     React 19 + Vite + Tailwind SPA
api/          PHP 8.3 Slim 4 REST API (/api/v1)
agents/       Pull-model client scripts + shared libs
database/     Schema, migrations, and seed data
legacy/       v1 PHP UI, bash agents, and installer (deprecated)
```

## Quick start (Docker)

```bash
git checkout 2026-overhaul
docker compose up --build
```

- Frontend: http://localhost:5173
- API: http://localhost:8080/health
- MySQL: localhost:3306 (`patchdashboard` / `patchdashboard`)

After MySQL initializes, run migrations:

```bash
docker compose exec api php /database/migrate.php
```

## VM / bare-metal install

For a dedicated Linux VM instead of Docker, use the v2 installer at `./install.sh`.

**Hybrid web setup (nginx + PHP-FPM)** — recommended when you want the CLI to prepare the VM and finish admin/company in the browser. This is the default web stack (`--webserver=nginx`).

```bash
git clone https://github.com/PatchDashboard/patchdashboard
cd patchdashboard
sudo ./install.sh --prepare-web-setup
# same as: sudo ./install.sh --prepare-web-setup --webserver=nginx
```

The installer prompts for the public dashboard URL, then:

1. **Pre-check** — OS support, disk space, MariaDB root access, no existing `install.lock`
2. **Packages** — nginx, PHP 8.2+ FPM (`php*-fpm`, `pdo_mysql`), MariaDB, Node.js, Composer
3. **Deploy** — copies the app to `/opt/patchdashboard` (override with `--install-root`)
4. **Database** — creates DB/user, imports `db_create.sql`, runs migrations
5. **Build** — `composer install` in `api/`, `npm run build` for the React SPA
6. **Web stack** — nginx site (`install/templates/nginx-site.conf`):
   - static files from `frontend/dist/`
   - `/api/*` and `/health` → PHP via PHP-FPM socket
7. **Post-check** — verifies nginx, PHP-FPM, DB connectivity, file permissions, `/health`, and `/api/v1/setup/status`
8. **Token** — writes a one-time token to `api/storage/setup.token` and prints:

```
Setup URL: http://your-host/setup?token=...
```

Open that URL in a browser. The `/setup` wizard asks for company name, admin username, email, and password. On success it:

- creates the admin user and `company` row (including agent `install_key`)
- updates `api/.env`
- writes `api/storage/install.lock` and **deletes** the setup token

Sign in at `/login`. Use the install key shown on the setup completion page as `PD_AUTH_KEY` on agents.

Unattended hybrid (nginx, no admin prompts on CLI):

```bash
sudo ./install.sh --unattended --prepare-web-setup \
  --web-url=http://patch.example.com \
  --webserver=nginx \
  --db-pass='choose-a-strong-db-password'
```

**Apache + mod_php** — use `--webserver=apache` instead of nginx if you prefer Apache; the browser setup flow is the same.

**Full CLI install** (interactive or unattended) — skips the browser wizard; creates the admin user on the CLI and writes `install.lock` immediately:

```bash
sudo ./install.sh
# or unattended:
sudo ./install.sh --unattended \
  --web-url=http://patch.example.com \
  --webserver=nginx \
  --admin-email=admin@example.com \
  --admin-pass='change-me' \
  --company='Acme Inc' \
  --db-pass='db-secret'
```

Supported distros: Debian 12+, Ubuntu 22.04+, Rocky/Alma/RHEL 9+. Default install root: `/opt/patchdashboard`. Legacy v1 installer: `./legacy/install.sh`.

### Hybrid setup: nginx/PHP-FPM layout

| Path | Purpose |
|------|---------|
| `/opt/patchdashboard/frontend/dist/` | React SPA (nginx `root`) |
| `/opt/patchdashboard/api/public/index.php` | Slim front controller (PHP-FPM) |
| `/opt/patchdashboard/api/.env` | DB credentials, JWT secret (mode `640`) |
| `/opt/patchdashboard/api/storage/setup.token` | One-time web setup token (removed after setup) |
| `/opt/patchdashboard/api/storage/install.lock` | Written when setup completes |
| `/etc/nginx/sites-available/patchdashboard` | Generated nginx vhost |

PHP-FPM runs as `www-data` (Debian/Ubuntu) or the user owning the FPM socket. `api/storage/` is `770 root:www-data` so the setup wizard can write `install.lock` via PHP.

### Troubleshooting hybrid setup

- **Post-check fails on PHP-FPM socket** — ensure `php8.2-fpm` or `php8.3-fpm` is running: `systemctl status php8.3-fpm`
- **Health check fails** — run `nginx -t`, confirm `Host` header matches your URL hostname, curl manually: `curl -H 'Host: patch.example.com' http://127.0.0.1/health`
- **Invalid setup link** — use the full URL printed by the installer; token is single-use and removed after setup
- **Already installed** — remove `api/storage/install.lock` only if you intend to reinstall; you may need to reset DB users/company manually

## Local development (without Docker)

### API

```bash
cd api
cp .env.example .env
composer install
composer start
```

### Frontend

```bash
cd frontend
npm install
npm run dev
```

### Database

Import `database/db_create.sql`, then:

```bash
php database/migrate.php
php database/seed.php --fresh   # mock servers, users, patches, containers
```

### Agents

Install scripts from `agents/client/` to `/opt/patch_manager/` on managed nodes. Set:

- `PD_API_BASE` — API base URL (e.g. `https://patch.example.com/`)
- `PD_AUTH_KEY` — install key from `company.install_key`

Agent cron (install [`agents/client/cron/patchdashboard`](agents/client/cron/patchdashboard) to `/etc/cron.d/patchdashboard`):

- **03:00 daily** — `application_cve_scan.sh` (full CVE list for host OS + all images/containers)
- **Every 5 min** — `check-in.sh` (patch checks + CVE scan if last scan &gt; 24h)

## New features in v2

- JWT auth with bcrypt password migration from legacy SHA256 hashes
- Suppression UI (fixes legacy stub pages and `nadda` placeholder bug)
- Web patch install via API (fixes GitHub #43)
- Host patch vulnerability lookup via ubuntuupdates.org (legacy parity) with dynamic distro codenames
- Modern distro seed data (Ubuntu 24.04, Debian 12/13, RHEL 8–10, Rocky, AlmaLinux)
- Non-root SSH user field on servers (GitHub #8 groundwork)
- Container vulnerability scanning (Docker, Podman, Proxmox LXC) via Trivy
- Daily full-application CVE scans (host OS + all container images) via agent cron and 24h check-in fallback
- Email + webhook alerting for new container CVEs

## Legacy compatibility

Agent check-in supports the legacy plain-text response format. Legacy client endpoints under `legacy/html/client/` remain until all agents are upgraded.

## Status

This is an active overhaul branch. Use Docker Compose or the v2 stack under `api/` and `frontend/`. Legacy v1 files live under `legacy/`.
