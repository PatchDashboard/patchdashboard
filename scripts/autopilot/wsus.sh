#!/bin/bash
DAV_USER='dav_reboot'
DAV_PRIVATE_KEY='/home/upgrade/.ssh/dav_servers'
SERVER=$1
if (( $SERVER % 2 )); then
        dest_server=`/home/ugrade/autopilot/lowest.php odd`
else
        dest_server=`/home/ugrade/autopilot/lowest.php`
/home/upgrade/autopilot/change_dns.sh $SERVER $dest_server
server_load=`curl --user hpAdmin:SzVNYltoV5RT1fHpZbTx https://dav$SERVER.tappin.com:8443/HomePipe/Communication/HealthInfo -s|python -mjson.tool|grep ConnectedAgent|cut -d ':' -f2|sed -e 's/,//g'|sed -e 's/ //g'`
while [ "$server_load" -gt "1000" ]; do
        sleep 60
        server_load=`curl --user hpAdmin:SzVNYltoV5RT1fHpZbTx https://dav$SERVER.tappin.com:8443/HomePipe/Communication/HealthInfo -s|python -mjson.tool|grep ConnectedAgent|cut -d ':' -f2|sed -e 's/,//g'|sed -e 's/ //g'`
done
ssh -i $DAV_PRIVATE_KEY $DAV_USER@dav$SERVER.tappin.com 'powershell.exe -File C:\wsus.ps1'
sleep 900
/home/upgrade/autopilot/change_dns.sh $SERVER