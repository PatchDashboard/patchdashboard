#!/bin/bash
auth_key="__SERVER_AUTHKEY_SET_ME__"
server_uri="__SERVER_URI_SET_ME__"
check_in="${server_uri}client/check-in.php"
if [[ -s /opt/patch_manager/.patchrc ]]; then
        rm -rf /opt/patch_manager/.patchrc
fi
if [[ ! -f "/opt/patch_manager/.pachrc" ]]; then
	host=$(hostname -f)
	random_bits=$(< /dev/urandom tr -dc 'a-zA-Z0-9~!@#$%^&*_-' | head -c${1:-32})
	client_key=$(echo "${host}${random_bits}"|sha256sum|cut -d ' ' -f 1)
	if [[ ! -d /opt/patch_manager ]]; then
		mkdir -p /opt/patch_manager
	fi
	echo "client_key=\"$client_key\"" > /opt/patch_manager/.patchrc
fi
. /opt/patch_manager/.pachrc
curl -H "X-CLIENT-KEY: $client_key" $get_cmd_uri > /tmp/check-in_$client_key
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