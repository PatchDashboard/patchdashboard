# Legacy v1 PatchDashboard

This directory holds the original PatchDashboard v1 stack, kept for reference and transitional deployments. New development uses `api/`, `frontend/`, and `agents/` at the repository root.

## Contents

| Path | Description |
|------|-------------|
| `html/` | PHP 5 web UI and client endpoints |
| `scripts/` | Server-side bash agents (superseded by `agents/`) |
| `install.sh` | v1 interactive installer |
| `hash_pass.php` | Legacy SHA256 password hasher used by `install.sh` |
| `sendmail.sh` | Legacy mail helper |
| `database/` | v1 SQL updates (`db_update.sql`, `centos_data.sql`) |
| `nbproject/` | NetBeans project metadata |

Fresh database schema for v1 installs uses `../database/db_create.sql` (shared with v2). Incremental v2 schema changes live in `../database/migrations/`.

## v1 install

From the repository root:

```bash
./legacy/install.sh
```

The installer resolves paths relative to `legacy/` and does not need to be run from that directory.
