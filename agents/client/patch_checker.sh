#!/bin/bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
# shellcheck source=../lib/os-detect.sh
source "${SCRIPT_DIR}/../lib/os-detect.sh"
# shellcheck source=../lib/pkg-mgr.sh
source "${SCRIPT_DIR}/../lib/pkg-mgr.sh"
# shellcheck source=../lib/api-client.sh
source "${SCRIPT_DIR}/../lib/api-client.sh"

pd_load_client_key
detect_os

os="${PD_OS_LABEL}"
os="$(sanitize_os_label "${os}")"
version="${PD_OS_VERSION}"
tmp_file="/tmp/patch_${PD_CLIENT_KEY}"
rm -f "${tmp_file}"

if [[ "${PD_OS_FAMILY}" == "redhat" ]]; then
    while IFS= read -r line; do
        formatted="$(format_rpm_line "${line}")"
        [[ -n "${formatted}" ]] && echo "${formatted}" >> "${tmp_file}"
    done < <(check_updates "${os}" "${version}" | grep -v '^$')
elif [[ "${PD_OS_FAMILY}" == "debian" ]]; then
    check_updates "${os}" "${version}" >> "${tmp_file}" || true
else
    echo "Unsupported OS: ${os}" >&2
    exit 0
fi

if [[ -f "${tmp_file}" ]]; then
    pd_api_post_raw "patches" < "${tmp_file}"
    rm -f "${tmp_file}"
fi
