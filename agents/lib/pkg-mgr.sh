#!/bin/bash
# Package manager selection for patch checks and remote installs.

pkg_manager_for_os() {
    local os="$1"
    local version="${2:-0}"

    case "${os}" in
        Ubuntu|Debian|Devuan|Raspbian)
            echo "apt"
            ;;
        RockyLinux|AlmaLinux|CentOSStream|Fedora|Oracle|RHEL)
            if [[ "${version}" -ge 8 ]] || [[ "${os}" == "Fedora" ]]; then
                echo "dnf"
            else
                echo "yum"
            fi
            ;;
        CentOS)
            if [[ "${version}" -ge 8 ]]; then
                echo "dnf"
            else
                echo "yum"
            fi
            ;;
        *)
            echo "unknown"
            ;;
    esac
}

check_updates() {
    local os="$1"
    local version="${2:-0}"
    local mgr
    mgr="$(pkg_manager_for_os "${os}" "${version}")"

    case "${mgr}" in
        dnf)
            dnf -q check-update 2>/dev/null | awk 'NF && $1 !~ /^(Last metadata|Security:|Updated)/ {print}'
            ;;
        yum)
            yum -q check-update 2>/dev/null | awk 'NF && $1 !~ /^(Loaded plugins|Security:|Updated)/ {print}'
            ;;
        apt)
            apt-get --just-print upgrade 2>&1 | perl -ne 'if (/Inst\s([\w,\-,\d,\.,~,:,\+]+)\s\[([\w,\-,\d,\.,~,:,\+]+)\]\s\(([\w,\-,\d,\.,~,:,\+]+)\)? /i) {print "$1:::$2:::$3\n"}'
            ;;
        *)
            echo "unsupported"
            return 1
            ;;
    esac
}

format_rpm_line() {
    local line="$1"
    line="$(echo "${line}")"
    [[ -z "${line}" ]] && return 0
    local uversion pname
    uversion="${line#*\ }"
    uversion="${uversion%\ *}"
    pname="${line%%\ *}"
    pname="${pname%.*}"
    echo "$(rpm -q "${pname}" --qf '%{NAME}:::%{VERSION}:::')${uversion}"
}
