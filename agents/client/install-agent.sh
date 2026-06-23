#!/bin/bash
set -euo pipefail

# Install or refresh the PatchDashboard pull agent on this host.
# Requires PD_API_BASE and PD_AUTH_KEY (from /etc/patchdashboard/agent.env or environment).

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
AGENT_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
INSTALL_DIR="${PD_AGENT_DIR:-/opt/patchdashboard-agent}"
ENV_FILE="/etc/patchdashboard/agent.env"

if [[ -f "${ENV_FILE}" ]]; then
    # shellcheck disable=SC1091
    source "${ENV_FILE}"
fi

if [[ -z "${PD_API_BASE:-}" || "${PD_API_BASE}" == "__SERVER_URI_SET_ME__" ]]; then
    echo "PD_API_BASE is not configured. Set it in ${ENV_FILE}." >&2
    exit 1
fi

if [[ -z "${PD_AUTH_KEY:-}" || "${PD_AUTH_KEY}" == "__SERVER_AUTHKEY_SET_ME__" ]]; then
    echo "PD_AUTH_KEY is not configured. Set it in ${ENV_FILE}." >&2
    exit 1
fi

mkdir -p "${INSTALL_DIR}"
rsync -a --delete "${AGENT_ROOT}/" "${INSTALL_DIR}/"

mkdir -p /etc/patchdashboard
cat > "${ENV_FILE}" <<EOF
PD_API_BASE="${PD_API_BASE}"
PD_AUTH_KEY="${PD_AUTH_KEY}"
EOF
chmod 640 "${ENV_FILE}"

# shellcheck source=../lib/api-client.sh
source "${INSTALL_DIR}/lib/api-client.sh"
pd_load_client_key

CRON_FILE="/etc/cron.d/patchdashboard"
if [[ ! -f "${CRON_FILE}" ]]; then
    cp "${INSTALL_DIR}/client/cron/patchdashboard" "${CRON_FILE}"
    chmod 644 "${CRON_FILE}"
fi

echo "PatchDashboard agent installed to ${INSTALL_DIR}"
echo "Client key: ${PD_CLIENT_KEY}"
echo "Run ${INSTALL_DIR}/client/check-in.sh to register with the dashboard."
