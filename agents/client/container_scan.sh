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

TRIVY="${TRIVY_BIN:-trivy}"
containers_json="[]"

append_container_scan() {
    local runtime="$1"
    local runtime_id="$2"
    local name="$3"
    local image="$4"
    local scan_target="$5"

    [[ -x "$(command -v "${TRIVY}")" ]] || return 0

    local report
    report="$("${TRIVY}" rootfs --format json --quiet "${scan_target}" 2>/dev/null || true)"
    [[ -n "${report}" ]] || return 0

    local vulns
    vulns="$(echo "${report}" | python3 - <<'PY' 2>/dev/null || true
import json, sys
data = json.load(sys.stdin)
out = []
for result in data.get("Results", []):
    for vuln in result.get("Vulnerabilities") or []:
        out.append({
            "cve_id": vuln.get("VulnerabilityID"),
            "severity": vuln.get("Severity", "UNKNOWN"),
            "package": vuln.get("PkgName"),
            "installed_version": vuln.get("InstalledVersion"),
            "fixed_version": vuln.get("FixedVersion"),
            "title": vuln.get("Title"),
        })
print(json.dumps(out))
PY
)"

    local entry
    entry="$(python3 - <<PY
import json
print(json.dumps({
    "runtime": "${runtime}",
    "runtime_id": "${runtime_id}",
    "name": "${name}",
    "image": "${image}",
    "status": "running",
    "vulnerabilities": json.loads('''${vulns:-[]}''')
}))
PY
)"
    containers_json="$(python3 - <<PY
import json
items = json.loads('''${containers_json}''')
items.append(json.loads('''${entry}'''))
print(json.dumps(items))
PY
)"
}

scan_docker() {
    command -v docker >/dev/null 2>&1 || return 0
    while read -r cid name image; do
        [[ -n "${cid}" ]] || continue
        append_container_scan "docker" "${cid}" "${name}" "${image}" "${cid}"
    done < <(docker ps --format '{{.ID}} {{.Names}} {{.Image}}')
}

scan_podman() {
    command -v podman >/dev/null 2>&1 || return 0
    while read -r cid name image; do
        [[ -n "${cid}" ]] || continue
        append_container_scan "podman" "${cid}" "${name}" "${image}" "${cid}"
    done < <(podman ps --format '{{.ID}} {{.Names}} {{.Image}}')
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
        append_container_scan "lxc" "${vmid}" "${name:-ct-${vmid}}" "lxc:${vmid}" "${rootfs}"
    done < <(pct list 2>/dev/null | awk 'NR>1 {print $1, $4}')
}

[[ "${PD_CAP_DOCKER}" == "1" ]] && scan_docker
[[ "${PD_CAP_PODMAN}" == "1" ]] && scan_podman
[[ "${PD_CAP_PROXMOX}" == "1" ]] && scan_proxmox_lxc

payload="{\"containers\": ${containers_json}}"
pd_api_post "containers/scan" "${payload}"
