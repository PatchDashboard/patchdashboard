#!/bin/bash
auth_key="__SERVER_AUTHKEY_SET_ME__"
server_uri="__SERVER_URI_SET_ME__"
get_cmd_uri="${server_uri}client/get_commands.php"
#Force a run of check-in.sh if .patchrc is missing.
if [[ ! -f "/opt/patch_manager/.patchrc" ]]; then
	echo "Please run /opt/patch_manager/check-in.sh as root (sudo) before trying to run this manually"
	exit 0
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