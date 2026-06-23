#!/bin/bash
auth_key="__SERVER_AUTHKEY_SET_ME__"
server_uri="__SERVER_URI_SET_ME__"
check_in="${server_uri}client/check-in.php"
# get hostname FQDN
client_host=$(hostname -f)
if [[ $(echo $client_host|grep '[^a-zA-Z0-9]') = "" ]]; then
        client_host=$(hostname -a)
fi
# get OS info and version
if [[ -f /etc/lsb-release && -f /etc/debian_version ]]; then
        export client_os=$(lsb_release -s -d|head -1|awk {'print $1'})
        export client_os_ver=$(lsb_release -r|head -1|awk {'print $2'}|cut -d "." -f 1)
elif [[ -f /etc/debian_version ]]; then
        export client_os="$(cat /etc/issue|head -n 1|awk {'print $1'})"
        export client_os_ver="$(cat /etc/debian_version|head -1|awk {'print $1'}|cut -d "." -f 1)"
elif [[ -f /etc/redhat-release ]]; then
	if [[ "$client_os" = "Red" && $(grep -i enterprise /etc/redhat-release) != "" ]]; then
		export client_os="RHEL"
		export client_os_ver=$(cat /etc/redhat-release|head -1|awk {'print $7'}|cut -d "." -f 1)
	elif [[ "$client_os" = "Red" ]]; then
		export client_os="RHEL"
		export client_os_ver=$(cat /etc/redhat-release|head -1|awk {'print $6'}|cut -d "." -f 1)
	else
		export client_os=$(cat /etc/redhat-release|head -1|awk {'print $1'})
		export client_os_ver=$(cat /etc/redhat-release|head -1|awk {'print $3'}|cut -d "." -f 1)

		if [[ "$client_os_ver" = "release" ]]; then
			export client_os_ver=$(cat /etc/redhat-release|head -1|awk {'print $4'}|cut -d "." -f 1)
		fi
	fi
else
        export client_os=$(uname -s -r|head -1|awk {'print $1'})
        export client_os_ver=$(uname -s -r|head -1|awk {'print $2'}|cut -d "." -f 1)
fi
# set client_path
if [[ -d /opt/patch_client ]]; then
        client_path="/opt/patch_client/"
else
        client_path="/opt/patch_manager/"
fi
# if client_path/.patchrc does not exist
if [ ! -f ${client_path}.patchrc ]; then
        random_bits=$(< /dev/urandom tr -dc 'a-zA-Z0-9~!@#$%^&*_-' | head -c${1:-32})
        client_key=$(echo "${client_host}${random_bits}"|sha256sum|cut -d ' ' -f 1)
        if [[ ! -d ${client_path} ]]; then
                mkdir -p ${client_path}
        fi
        echo "client_key=\"$client_key\"" > ${client_path}.patchrc
else
        client_key=$(grep client_key ${client_path}.patchrc|awk -F\" {'print $2'})
fi
# load client_key
. ${client_path}.patchrc
# remove any special characters
client_os=$(echo $client_os|sed -e 's/[^a-zA-Z0-9]//g')
curl -k -s -H "X-CLIENT-KEY: $client_key" -H "X-CLIENT-HOST: $client_host" -H "X-CLIENT-OS: $client_os" -H "X-CLIENT-OS-VER: $client_os_ver" $check_in > /tmp/check-in_$client_key
cmds_line_count=$(cat /tmp/check-in_$client_key|wc -l)
if [ "$cmds_line_count" -gt "1" ]; then
        . /tmp/check-in_$client_key
        if [ "$allowed" = "TRUE" ]; then
                if [ "$key_to_check" = "$auth_key" ]; then
                        if [ "$check_patches" = "TRUE" ]; then
                                ${client_path}patch_checker.sh&
                                ${client_path}package_checker.sh&
                        fi
                        ${client_path}run_commands.sh&
                fi
        fi
        key_to_check=$(head -n 1 /tmp/check-in_$client_key)
fi
rm -rf /tmp/check-in_$client_key
