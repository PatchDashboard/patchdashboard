#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/os-detect.sh
source "${SCRIPT_DIR}/../lib/os-detect.sh"
# shellcheck source=../lib/api-client.sh
source "${SCRIPT_DIR}/../lib/api-client.sh"

pd_load_client_key
detect_os
detect_capabilities

client_host="$(hostname -f 2>/dev/null || hostname)"
client_os="$(sanitize_os_label "${PD_OS_LABEL}")"
client_os_ver="${PD_OS_VERSION}"

capabilities_json="{\"docker\":${PD_CAP_DOCKER},\"podman\":${PD_CAP_PODMAN},\"proxmox\":${PD_CAP_PROXMOX}}"

response="$(curl -k -s \
    -H "X-CLIENT-KEY: ${PD_CLIENT_KEY}" \
    -H "X-CLIENT-HOST: ${client_host}" \
    -H "X-CLIENT-OS: ${client_os}" \
    -H "X-CLIENT-OS-VER: ${client_os_ver}" \
    -H "Content-Type: application/json" \
    -d "{\"capabilities\":${capabilities_json}}" \
    "${PD_API_BASE%/}/api/v1/agent/check-in")"

tmp_file="/tmp/pd-check-in-${PD_CLIENT_KEY}"
echo "${response}" > "${tmp_file}"
# shellcheck disable=SC1090
source "${tmp_file}"
rm -f "${tmp_file}"

    if [[ "${allowed:-FALSE}" == "TRUE" && "${key_to_check:-FALSE}" == "${PD_AUTH_KEY}" ]]; then
        if [[ "${check_patches:-FALSE}" == "TRUE" ]]; then
            "${SCRIPT_DIR}/patch_checker.sh" &
            "${SCRIPT_DIR}/package_checker.sh" &
        fi
        if [[ "${check_cve_scan:-FALSE}" == "TRUE" ]]; then
            "${SCRIPT_DIR}/application_cve_scan.sh" &
        fi
        "${SCRIPT_DIR}/run_commands.sh" &
    fi
