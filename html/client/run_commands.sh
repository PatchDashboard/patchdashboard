#!/bin/bash
auth_key="__SERVER_AUTHKEY_SET_ME__"
server_uri="__SERVER_URI_SET_ME__"
get_cmd_uri="${server_uri}client/get_commands.php"
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
curl -H "X-CLIENT-KEY: $client_key" $get_cmd_uri > /tmp/cmds_$client_key
cmds_line_count=$(cat /tmp/cmds_$client_key|wc -l)
if [ "$cmds_line_count" = "2" ]; then
	key_to_check=$(head -n 1 /tmp/cmds_$client_key)
	if [ "$key_to_check" = "$auth_key" ]; then
		tail -n 1 /tmp/cmds_$client_key |bash
	fi
fi
rm -rf /tmp/cmds_$client_key