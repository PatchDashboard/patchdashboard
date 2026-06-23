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

mgr="$(pkg_manager_for_os "${PD_OS_LABEL}" "${PD_OS_VERSION}")"
tmp_file="/tmp/packages_${PD_CLIENT_KEY}"
rm -f "${tmp_file}"

case "${mgr}" in
    dnf)
        dnf list installed -q 2>/dev/null | awk 'NR>1 {print $1 ":::" $2}' >> "${tmp_file}"
        ;;
    yum)
        yum list installed -q 2>/dev/null | awk 'NR>1 {print $1 ":::" $2}' >> "${tmp_file}"
        ;;
    apt)
        dpkg-query -W -f='${Package}:::${Version}\n' >> "${tmp_file}" 2>/dev/null || true
        ;;
esac

if [[ -f "${tmp_file}" ]]; then
    pd_api_post_raw "packages" < "${tmp_file}"
    rm -f "${tmp_file}"
fi
