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
  --ssh-key PATH       Private SSH key for reaching the server (default: PDASH_SSH_KEY env,
                       then api/storage/ssh/enrollment_bootstrap, then patchdashboard_ed25519)

Environment:
  PD_API_BASE, PD_AUTH_KEY, PDASH_SSH_KEY, SSH_PASSWORD, SSH_PASSWORD_FILE
  PDASH_DASHBOARD_PUB  Path to dashboard public key installed on the server
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
SSH_PASSWORD="${SSH_PASSWORD:-}"
SSH_PASSWORD_FILE="${SSH_PASSWORD_FILE:-}"
SSH_KEY="${PDASH_SSH_KEY:-}"
DASHBOARD_PUBLIC="${PDASH_DASHBOARD_PUB:-${INSTALL_ROOT}/api/storage/ssh/patchdashboard_ed25519.pub}"

if [[ -z "${SSH_KEY}" && -f "${INSTALL_ROOT}/api/storage/ssh/enrollment_bootstrap" ]]; then
    SSH_KEY="${INSTALL_ROOT}/api/storage/ssh/enrollment_bootstrap"
fi
if [[ -z "${SSH_KEY}" && -z "${SSH_PASSWORD}" ]]; then
    SSH_KEY="${INSTALL_ROOT}/api/storage/ssh/patchdashboard_ed25519"
fi

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

if [[ -z "${SSH_PASSWORD}" && -z "${SSH_PASSWORD_FILE}" && ! -f "${SSH_KEY}" ]]; then
    echo "Set SSH_PASSWORD, SSH_PASSWORD_FILE, or provide an SSH private key (PDASH_SSH_KEY)." >&2
    exit 1
fi

if [[ ! -f "${DASHBOARD_PUBLIC}" ]]; then
    echo "Dashboard public key not found at ${DASHBOARD_PUBLIC}. Run database/ensure_ssh_keys.php first." >&2
    exit 1
fi

if [[ -n "${SSH_PASSWORD}" || -n "${SSH_PASSWORD_FILE}" ]] && ! command -v sshpass >/dev/null 2>&1; then
    echo "sshpass is required for password-based SSH enrollment." >&2
    exit 1
fi

MARKER="patchdashboard-managed"
PUB_LINE="$(tr -d '\n' < "${DASHBOARD_PUBLIC}") ${MARKER}"

run_ssh() {
    if [[ -n "${SSH_PASSWORD_FILE}" ]]; then
        sshpass -f "${SSH_PASSWORD_FILE}" ssh \
            -o PreferredAuthentications=password \
            -o PubkeyAuthentication=no \
            -o StrictHostKeyChecking=accept-new \
            "${SSH_USER}@${HOST}" "$@"
        return
    fi

    if [[ -n "${SSH_PASSWORD}" ]]; then
        SSHPASS="${SSH_PASSWORD}" sshpass -e ssh \
            -o PreferredAuthentications=password \
            -o PubkeyAuthentication=no \
            -o StrictHostKeyChecking=accept-new \
            "${SSH_USER}@${HOST}" "$@"
        return
    fi

    ssh -i "${SSH_KEY}" \
        -o BatchMode=yes \
        -o StrictHostKeyChecking=accept-new \
        "${SSH_USER}@${HOST}" "$@"
}

echo "Installing dashboard SSH key on ${SSH_USER}@${HOST}..."
run_ssh \
    "mkdir -p ~/.ssh && chmod 700 ~/.ssh && touch ~/.ssh/authorized_keys && chmod 600 ~/.ssh/authorized_keys && grep -qF '${MARKER}' ~/.ssh/authorized_keys 2>/dev/null || echo '${PUB_LINE}' >> ~/.ssh/authorized_keys"

echo "Deploying agent to ${HOST}..."
tar -C "${AGENT_ROOT}" -cf - . | run_ssh \
    "mkdir -p /opt/patchdashboard-agent && tar -C /opt/patchdashboard-agent -xf -"

echo "Writing agent credentials on ${HOST}..."
run_ssh \
    "mkdir -p /etc/patchdashboard && printf 'PD_API_BASE=%q\nPD_AUTH_KEY=%q\n' '${API_BASE}' '${AUTH_KEY}' > /etc/patchdashboard/agent.env && chmod 640 /etc/patchdashboard/agent.env"

echo "Running agent install on ${HOST}..."
run_ssh \
    "PD_API_BASE='${API_BASE}' PD_AUTH_KEY='${AUTH_KEY}' bash /opt/patchdashboard-agent/client/install-agent.sh"

echo "Running initial check-in on ${HOST}..."
run_ssh \
    "PD_API_BASE='${API_BASE}' PD_AUTH_KEY='${AUTH_KEY}' bash /opt/patchdashboard-agent/client/check-in.sh"

if [[ -n "${ALIAS}" ]]; then
    echo "Requested alias: ${ALIAS} (set in dashboard after the server registers)"
fi

echo "Enrollment complete for ${HOST}. Trust the server in the dashboard when it appears."
