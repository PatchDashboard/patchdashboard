#!/bin/bash
# Full CVE scan for all installed applications on this host.
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LIB_DIR="${SCRIPT_DIR}/../lib"
# shellcheck source=../lib/os-detect.sh
source "${LIB_DIR}/os-detect.sh"
# shellcheck source=../lib/api-client.sh
source "${LIB_DIR}/api-client.sh"

pd_load_client_key
detect_os
detect_capabilities

TRIVY="${TRIVY_BIN:-trivy}"
PARSER="${LIB_DIR}/trivy-parse.py"
SCANNER="${LIB_DIR}/trivy-scan-entry.py"
OUTPUT="/tmp/pd-cve-scan-${PD_CLIENT_KEY}.jsonl"
: > "${OUTPUT}"

scan_target() {
    local runtime="$1"
    local runtime_id="$2"
    local name="$3"
    local image_ref="$4"
    shift 4

    command -v "${TRIVY}" >/dev/null 2>&1 || return 0
    python3 "${SCANNER}" "${OUTPUT}" "${PARSER}" "${runtime}" "${runtime_id}" "${name}" "${image_ref}" "${TRIVY}" "$@" >/dev/null 2>&1 || true
}

scan_host_rootfs() {
    local hostname
    hostname="$(hostname -s 2>/dev/null || hostname)"
    scan_target "host" "rootfs" "${hostname}" "os:rootfs" rootfs --format json --quiet /
}

scan_docker_images() {
    command -v docker >/dev/null 2>&1 || return 0
    while IFS=$'\t' read -r image_id image_ref; do
        [[ -n "${image_id}" ]] || continue
        [[ "${image_ref}" == "<none>"* ]] && continue
        scan_target "image" "docker:${image_id}" "${image_ref}" "${image_ref}" image --format json --quiet "${image_ref}"
    done < <(docker images --format '{{.ID}}\t{{.Repository}}:{{.Tag}}' 2>/dev/null || true)
}

scan_podman_images() {
    command -v podman >/dev/null 2>&1 || return 0
    while IFS=$'\t' read -r image_id image_ref; do
        [[ -n "${image_id}" ]] || continue
        [[ "${image_ref}" == "<none>"* ]] && continue
        scan_target "image" "podman:${image_id}" "${image_ref}" "${image_ref}" image --format json --quiet "${image_ref}"
    done < <(podman images --format '{{.ID}}\t{{.Repository}}:{{.Tag}}' 2>/dev/null || true)
}

scan_running_containers() {
    if [[ "${PD_CAP_DOCKER}" == "1" ]]; then
        while read -r cid name image; do
            [[ -n "${cid}" ]] || continue
            scan_target "docker" "${cid}" "${name}" "${image}" image --format json --quiet "${image}"
        done < <(docker ps --format '{{.ID}} {{.Names}} {{.Image}}' 2>/dev/null || true)
    fi

    if [[ "${PD_CAP_PODMAN}" == "1" ]]; then
        while read -r cid name image; do
            [[ -n "${cid}" ]] || continue
            scan_target "podman" "${cid}" "${name}" "${image}" image --format json --quiet "${image}"
        done < <(podman ps --format '{{.ID}} {{.Names}} {{.Image}}' 2>/dev/null || true)
    fi
}

scan_proxmox_lxc() {
    command -v pct >/dev/null 2>&1 || return 0
    while read -r vmid name; do
        [[ -n "${vmid}" ]] || continue
        local rootfs="/var/lib/lxc/${vmid}/rootfs"
        if [[ ! -d "${rootfs}" ]]; then
            rootfs="/var/lib/vz/private/${vmid}"
        fi
        [[ -d "${rootfs}" ]] || continue
        scan_target "lxc" "${vmid}" "${name:-ct-${vmid}}" "lxc:${vmid}" rootfs --format json --quiet "${rootfs}"
    done < <(pct list 2>/dev/null | awk 'NR>1 {print $1, $4}')
}

scan_host_rootfs
scan_docker_images
scan_podman_images
scan_running_containers
[[ "${PD_CAP_PROXMOX}" == "1" ]] && scan_proxmox_lxc

payload="$(python3 - <<PY
import json
from pathlib import Path
containers = []
path = Path("${OUTPUT}")
for line in path.read_text().splitlines():
    line = line.strip()
    if line:
        containers.append(json.loads(line))
print(json.dumps({"containers": containers}))
PY
)"

rm -f "${OUTPUT}"
pd_api_post "containers/scan" "${payload}"
