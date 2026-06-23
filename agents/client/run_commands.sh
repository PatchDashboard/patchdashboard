#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/api-client.sh
source "${SCRIPT_DIR}/../lib/api-client.sh"

pd_load_client_key

response="$(curl -k -s -H "X-CLIENT-KEY: ${PD_CLIENT_KEY}" "${PD_API_BASE%/}/api/v1/agent/commands")"
packages="$(echo "${response}" | python3 -c "import json,sys; d=json.load(sys.stdin); print(' '.join(d.get('commands',{}).get('upgrade_packages',[])))" 2>/dev/null || true)"

if [[ -n "${packages}" ]]; then
    # shellcheck source=../lib/os-detect.sh
    source "${SCRIPT_DIR}/../lib/os-detect.sh"
    # shellcheck source=../lib/pkg-mgr.sh
    source "${SCRIPT_DIR}/../lib/pkg-mgr.sh"
    detect_os
    mgr="$(pkg_manager_for_os "${PD_OS_LABEL}" "${PD_OS_VERSION}")"
    case "${mgr}" in
        dnf) dnf -y install ${packages} ;;
        yum) yum -y install ${packages} ;;
        apt) DEBIAN_FRONTEND=noninteractive apt-get -y install ${packages} ;;
    esac
fi
