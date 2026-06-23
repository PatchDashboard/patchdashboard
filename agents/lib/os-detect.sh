#!/bin/bash
# Shared OS detection using /etc/os-release with legacy fallbacks.

detect_os() {
    if [[ -f /etc/os-release ]]; then
        # shellcheck disable=SC1091
        . /etc/os-release
        export PD_OS_ID="${ID}"
        export PD_OS_NAME="${NAME:-$ID}"
        export PD_OS_VERSION="${VERSION_ID:-unknown}"
        export PD_OS_CODENAME="${VERSION_CODENAME:-}"

        case "${ID}" in
            ubuntu|debian|devuan|raspbian)
                export PD_OS_FAMILY="debian"
                export PD_OS_LABEL="$(echo "${ID}" | awk '{print toupper(substr($0,1,1)) substr($0,2)}')"
                ;;
            rhel|centos|fedora|rocky|almalinux|ol|oraclelinux)
                export PD_OS_FAMILY="redhat"
                case "${ID}" in
                    ol|oraclelinux) export PD_OS_LABEL="Oracle" ;;
                    rocky) export PD_OS_LABEL="Rocky Linux" ;;
                    almalinux) export PD_OS_LABEL="AlmaLinux" ;;
                    rhel) export PD_OS_LABEL="RHEL" ;;
                    centos) export PD_OS_LABEL="CentOS" ;;
                    fedora) export PD_OS_LABEL="Fedora" ;;
                esac
                export PD_OS_VERSION="$(echo "${VERSION_ID}" | cut -d. -f1)"
                ;;
            *)
                export PD_OS_FAMILY="unknown"
                export PD_OS_LABEL="${ID}"
                ;;
        esac
    elif [[ -f /etc/redhat-release ]]; then
        export PD_OS_FAMILY="redhat"
        if grep -qi enterprise /etc/redhat-release; then
            export PD_OS_LABEL="RHEL"
        else
            export PD_OS_LABEL="$(awk '{print $1}' /etc/redhat-release)"
        fi
        export PD_OS_VERSION="$(grep -oE '[0-9]+' /etc/redhat-release | head -1)"
    elif [[ -f /etc/debian_version ]]; then
        export PD_OS_FAMILY="debian"
        export PD_OS_LABEL="Debian"
        export PD_OS_VERSION="$(cut -d. -f1 /etc/debian_version)"
    else
        export PD_OS_FAMILY="unknown"
        export PD_OS_LABEL="$(uname -s)"
        export PD_OS_VERSION="$(uname -r | cut -d. -f1)"
    fi

    export PD_OS_LABEL="$(echo "${PD_OS_LABEL}" | sed -e 's/[^a-zA-Z0-9 ]//g')"
}

detect_capabilities() {
    export PD_CAP_DOCKER=0
    export PD_CAP_PODMAN=0
    export PD_CAP_PROXMOX=0

    command -v docker >/dev/null 2>&1 && docker info >/dev/null 2>&1 && PD_CAP_DOCKER=1
    command -v podman >/dev/null 2>&1 && podman info >/dev/null 2>&1 && PD_CAP_PODMAN=1
    [[ -f /etc/pve/version ]] && PD_CAP_PROXMOX=1
}

sanitize_os_label() {
    echo "$1" | sed -e 's/[^a-zA-Z0-9]//g'
}
