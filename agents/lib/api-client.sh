#!/bin/bash

PD_API_BASE="${PD_API_BASE:-__SERVER_URI_SET_ME__}"
PD_AUTH_KEY="${PD_AUTH_KEY:-__SERVER_AUTHKEY_SET_ME__}"

pd_client_path() {
    if [[ -d /opt/patch_client ]]; then
        echo "/opt/patch_client"
    else
        echo "/opt/patch_manager"
    fi
}

pd_load_client_key() {
    local client_path
    client_path="$(pd_client_path)"
    if [[ ! -f "${client_path}/.patchrc" ]]; then
        local random_bits client_key client_host
        random_bits="$(< /dev/urandom tr -dc 'a-zA-Z0-9~!@#$%^&*_-' | head -c 32)"
        client_host="$(hostname -f 2>/dev/null || hostname)"
        client_key="$(echo "${client_host}${random_bits}" | sha256sum | awk '{print $1}')"
        mkdir -p "${client_path}"
        echo "client_key=\"${client_key}\"" > "${client_path}/.patchrc"
    fi
    # shellcheck disable=SC1090
    source "${client_path}/.patchrc"
    export PD_CLIENT_KEY="${client_key}"
    export PD_CLIENT_PATH="${client_path}"
}

pd_api_post() {
    local endpoint="$1"
    local data="${2:-}"
    curl -k -s -H "X-CLIENT-KEY: ${PD_CLIENT_KEY}" \
        -H "Content-Type: application/json" \
        -X POST \
        -d "${data}" \
        "${PD_API_BASE%/}/api/v1/agent/${endpoint}"
}

pd_api_post_raw() {
    local endpoint="$1"
    curl -k -s -H "X-CLIENT-KEY: ${PD_CLIENT_KEY}" \
        -X POST \
        --data-binary @- \
        "${PD_API_BASE%/}/api/v1/agent/${endpoint}"
}
