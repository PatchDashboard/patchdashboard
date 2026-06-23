# PatchDashboard

PatchDashboard monitors Linux fleet patch status, enriches findings with CVE and advisory data, scans container images with Trivy, and alerts when new vulnerabilities appear.

**v2** (branch `2026-overhaul`) is a React SPA + PHP 8 REST API with pull-model agents. Legacy v1 PHP UI lives under [`legacy/`](legacy/).

Full architecture, install paths, and feature reference: **[OVERHAUL.md](OVERHAUL.md)**

## What v2 does

- **Patch inventory** — agents report pending OS updates; dashboard shows counts per server and fleet-wide search
- **Patch actions** — install all pending patches on a server from the UI (admin)
- **Suppressions** — global or per-server package suppressions; quick-suppress from patch/package/vulnerability views
- **Container CVEs** — Trivy scans Docker/Podman/LXC images; filterable inventory at `/containers`
- **Vulnerability search** — unified search across Trivy results, pending patches, and synced advisory library at `/vulnerabilities`
- **CVE feed sync** — bulk import from NVD, GitHub (GHSA), and OSV distro exports (Debian, Ubuntu, Red Hat, Rocky, AlmaLinux, Fedora)
- **Alerts** — email, generic webhooks, and Slack with configurable rules and templates
- **Server enrollment** — SSH-based agent install; trust/untrust with key revocation
- **Web console** — browser SSH session to trusted servers (password-gated; dev stub or real SSH via console service)
- **MFA** — optional TOTP authenticator app on login (QR setup on Profile)
- **Users & plugins** — admin user management; plugin page visibility toggles

Supported agent distros include Debian, Ubuntu, Devuan, RHEL family, Rocky, AlmaLinux, Fedora, and related derivatives (see seed data in `database/`).

## Quick start (Docker)

```bash
git clone https://github.com/PatchDashboard/patchdashboard
cd patchdashboard
git checkout 2026-overhaul
docker compose up --build
```

| URL | Service |
|-----|---------|
| http://localhost:5173 | React frontend (Vite dev) |
| http://localhost:8080/health | PHP API |
| ws://localhost:8090 | Web SSH console (dev stub) |

After MySQL is healthy, migrations run automatically on API start. Seed demo data (optional):

```bash
docker compose exec api php /database/seed.php --fresh
```

Default dev credentials come from seed/bootstrap; sign in at `/login`.

## Other install paths

| Method | Command |
|--------|---------|
| Production Docker | `./install.sh --docker-prod` |
| VM hybrid setup | `sudo ./install.sh --prepare-web-setup` |
| VM full CLI | `sudo ./install.sh` |

See [OVERHAUL.md — Install](OVERHAUL.md#vm--bare-metal-install) for nginx layout, setup tokens, and unattended flags.

## Agents

On each managed node, set:

- `PD_API_BASE` — dashboard API URL (e.g. `https://patch.example.com/`)
- `PD_AUTH_KEY` — install key from setup or `company.install_key`

Install from the dashboard host:

```bash
./agents/client/enroll-server.sh --host SERVER --user root
# or on the node:
./agents/client/install-agent.sh
```

Agent cron (see `agents/client/cron/patchdashboard`): daily full CVE scan, check-in every 5 minutes.

## Scheduled CVE feed sync

```bash
php database/cve_sync.php --all
```

Configure and enable feeds under **CVE feeds** (admin). Set `NVD_API_KEY` in `api/.env` for NVD rate limits.

## Legacy v1

The original PHP dashboard and push/pull installer remain for reference:

```bash
./legacy/install.sh
```

Do not mix v1 and v2 on the same database without migration planning.

## Links

- [OVERHAUL.md](OVERHAUL.md) — detailed v2 documentation
- [Issues](https://github.com/PatchDashboard/patchdashboard/issues)
- [Releases](https://github.com/PatchDashboard/patchdashboard/releases)
