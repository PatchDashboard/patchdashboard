#!/bin/bash
set -euo pipefail

# Enroll a remote server from the PatchDashboard host.
# Installs dashboard SSH access, deploys the agent, and runs an initial check-in.

usage() {
    cat <<EOF
Usage: $0 --host HOST --user SSH_USER [options]

Options:
  --host HOST          Remote server IP or hostname (required)
  --user SSH_USER      SSH user on the remote server (required)
  --alias ALIAS        Optional server alias for the dashboard
  --api-base URL       PatchDashboard API base URL (default: PD_API_BASE env)
  --auth-key KEY       Agent install key PD_AUTH_KEY (default: PD_AUTH_KEY env)
  --ssh-key PATH       Dashboard private SSH key (default: PDASH_SSH_KEY env)

Environment:
  PD_API_BASE, PD_AUTH_KEY, PDASH_SSH_KEY
EOF
}

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
AGENT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
INSTALL_ROOT="$(cd "${AGENT_ROOT}/.." && pwd)"

HOST=""
SSH_USER=""
ALIAS=""
API_BASE="${PD_API_BASE:-}"
AUTH_KEY="${PD_AUTH_KEY:-}"
SSH_KEY="${PDASH_SSH_KEY:-${INSTALL_ROOT}/api/storage/ssh/patchdashboard_ed25519}"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --host) HOST="$2"; shift 2 ;;
        --user) SSH_USER="$2"; shift 2 ;;
        --alias) ALIAS="$2"; shift 2 ;;
        --api-base) API_BASE="$2"; shift 2 ;;
        --auth-key) AUTH_KEY="$2"; shift 2 ;;
        --ssh-key) SSH_KEY="$2"; shift 2 ;;
        -h|--help) usage; exit 0 ;;
        *) echo "Unknown option: $1" >&2; usage; exit 1 ;;
    esac
done

if [[ -z "${HOST}" || -z "${SSH_USER}" ]]; then
    usage
    exit 1
fi

if [[ -z "${API_BASE}" || -z "${AUTH_KEY}" ]]; then
    echo "PD_API_BASE and PD_AUTH_KEY must be set (via flags or environment)." >&2
    exit 1
fi

if [[ ! -f "${SSH_KEY}" ]]; then
    echo "Dashboard SSH private key not found at ${SSH_KEY}. Run database/ensure_ssh_keys.php first." >&2
    exit 1
fi

PUBLIC_KEY="${SSH_KEY}.pub"
if [[ ! -f "${PUBLIC_KEY}" ]]; then
    echo "Dashboard SSH public key not found at ${PUBLIC_KEY}." >&2
    exit 1
fi

MARKER="patchdashboard-managed"
PUB_LINE="$(tr -d '\n' < "${PUBLIC_KEY}") ${MARKER}"

ssh_base=(ssh -i "${SSH_KEY}" -o BatchMode=yes -o StrictHostKeyChecking=accept-new)

echo "Installing dashboard SSH key on ${SSH_USER}@${HOST}..."
"${ssh_base[@]}" "${SSH_USER}@${HOST}" \
    "mkdir -p ~/.ssh && chmod 700 ~/.ssh && touch ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys && grep -qF '${MARKER}' ~/.ssh/authorized_keys 2>/dev/null || echo '${PUB_LINE}' >> ~/.ssh/authorized_keys"

echo "Deploying agent to ${HOST}..."
tar -C "${AGENT_ROOT}" -cf - . | "${ssh_base[@]}" "${SSH_USER}@${HOST}" \
    "mkdir -p /opt/patchdashboard-agent && tar -C /opt/patchdashboard-agent -xf -"

echo "Writing agent credentials on ${HOST}..."
"${ssh_base[@]}" "${SSH_USER}@${HOST}" \
    "mkdir -p /etc/patchdashboard && printf 'PD_API_BASE=%q\nPD_AUTH_KEY=%q\n' '${API_BASE}' '${AUTH_KEY}' > /etc/patchdashboard/agent.env && chmod 640 /etc/patchdashboard/agent.env"

echo "Running agent install on ${HOST}..."
"${ssh_base[@]}" "${SSH_USER}@${HOST}" \
    "PD_API_BASE='${API_BASE}' PD_AUTH_KEY='${AUTH_KEY}' bash /opt/patchdashboard-agent/client/install-agent.sh"

echo "Running initial check-in on ${HOST}..."
"${ssh_base[@]}" "${SSH_USER}@${HOST}" \
    "PD_API_BASE='${API_BASE}' PD_AUTH_KEY='${AUTH_KEY}' bash /opt/patchdashboard-agent/client/check-in.sh"

if [[ -n "${ALIAS}" ]]; then
    echo "Requested alias: ${ALIAS} (set in dashboard after the server registers)"
fi

echo "Enrollment complete for ${HOST}. Trust the server in the dashboard when it appears."
