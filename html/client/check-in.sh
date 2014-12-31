#!/bin/bash
auth_key="__SERVER_AUTHKEY_SET_ME__"
server_uri="__SERVER_URI_SET_ME__"
check_in="${server_uri}client/check-in.php"
client_host=$(hostname -f)

if [[ -f /etc/lsb-release ]]; then
        export client_os=$(lsb_release -s -d|head -1|awk {'print $1'})
        export client_os_ver=$(lsb_release -s -d|head -1|awk {'print $2'}|cut -d "." -f 1)
elif [[ -f /etc/debian_version ]]; then
        export client_os="Debian $(cat /etc/debian_version)|head -1|awk {'print $1'}"
        export client_os_ver="Debian $(cat /etc/debian_version)|head -1|awk {'print $2'}|cut -d "." -f 1"
elif [[ -f /etc/redhat-release ]]; then
        export client_os=$(cat /etc/redhat-release|head -1|awk {'print $1'})
        export client_os_ver=$(cat /etc/redhat-release|head -1|awk {'print $3'}|cut -d "." -f 1)
else
        export client_os=$(uname -s -r|head -1|awk {'print $1'})
        export client_os_ver=$(uname -s -r|head -1|awk {'print $2'}|cut -d "." -f 1)
fi

if [ ! -f /opt/patch_manager/.patchrc ]; then
        random_bits=$(< /dev/urandom tr -dc 'a-zA-Z0-9~!@#$%^&*_-' | head -c${1:-32})
        client_key=$(echo "${client_host}${random_bits}"|sha256sum|cut -d ' ' -f 1)
        if [[ ! -d /opt/patch_manager ]]; then
                mkdir -p /opt/patch_manager
        fi
        echo "client_key=\"$client_key\"" > /opt/patch_manager/.patchrc
else
        client_key=$(grep client_key /opt/patch_manager/.patchrc|awk -F\" {'print $2'})
fi
. /opt/patch_manager/.patchrc
curl -k -s -H "X-CLIENT-KEY: $client_key" -H "X-CLIENT-HOST: $client_host" -H "X-CLIENT-OS: $client_os" -H "X-CLIENT-OSVER: $client_os_ver" $check_in > /tmp/check-in_$client_key
cmds_line_count=$(cat /tmp/check-in_$client_key|wc -l)
if [ "$cmds_line_count" -gt "1" ]; then
        . /tmp/check-in_$client_key
        if [ "$allowed" = "TRUE" ]; then
                if [ "$key_to_check" = "$auth_key" ]; then
                        if [ "$check_patches" = "TRUE" ]; then
                                /opt/patch_manager/patch_checker.sh&
                                /opt/patch_manager/package_checker.sh&
                        fi
                        /opt/patch_manager/run_commands.sh&
                fi
        fi
        key_to_check=$(head -n 1 /tmp/check-in_$client_key)
fi
rm -rf /tmp/check-in_$client_key
