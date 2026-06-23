#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/api-client.sh
source "${SCRIPT_DIR}/../lib/api-client.sh"

pd_load_client_key

response="$(curl -k -s -H "X-CLIENT-KEY: ${PD_CLIENT_KEY}" "${PD_API_BASE%/}/api/v1/agent/commands")"
packages="$(echo "${response}" | python3 -c "import json,sys; d=json.load(sys.stdin); print(' '.join(d.get('commands',{}).get('upgrade_packages',[])))" 2>/dev/null || true)"

read -r power_action power_mode <<< "$(echo "${response}" | python3 -c "
import json, sys
d = json.load(sys.stdin).get('commands', {})
power = d.get('power') or {}
action = power.get('action')
mode = power.get('mode')
if not action:
    if d.get('shutdown'):
        action, mode = 'shutdown', 'graceful'
    elif d.get('reboot'):
        action, mode = 'reboot', 'graceful'
print((action or ''), (mode or 'graceful'))
" 2>/dev/null || echo ' graceful')"

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

if [[ -z "${power_action}" ]]; then
    exit 0
fi

run_power_command() {
    local action="$1"
    local mode="$2"

    if [[ "${action}" == "shutdown" ]]; then
        case "${mode}" in
            forced)
                systemctl poweroff -f 2>/dev/null || /sbin/shutdown -h now -f || shutdown -h now -f
                ;;
            *)
                systemctl poweroff 2>/dev/null || /sbin/shutdown -h now || shutdown -h now
                ;;
        esac
        return
    fi

    case "${mode}" in
        forced)
            systemctl reboot -f 2>/dev/null || /sbin/reboot -f || reboot -f
            ;;
        *)
            systemctl reboot 2>/dev/null || /sbin/reboot || reboot
            ;;
    esac
}

run_power_command "${power_action}" "${power_mode}"
