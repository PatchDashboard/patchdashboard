#!/bin/bash

# load DB config
. /usr/scripts/patch_manager/db.conf

# define server from argument
server="$1"

# connect to DB and clean up packages
sql="DELETE FROM patches where server_name='$server';"

# define other functions
supression_list=`mysql -u $DB_USER -p"$DB_PASS" -Nse "SELECT package from where server_name in ('$server', 0);" $DB_NAME`
mysql -u root -p$DB_PASS -D $DB_NAME -e "$sql" -h $DB_HOST
script='/usr/scripts/patch_manager/patch_checker.sh'
export server supression_list sql script


function useHost()
{
        ssh -i /root/.ssh/id_rsa root@$server 'apt-get update;mkdir -p /usr/scripts/patch_manager/'
        scp -i /root/.ssh/id_rsa $script root@$server:/usr/scripts/patch_manager/
        ssh -i /root/.ssh/id_rsa root@$server "chmod +x $script"
        data=`ssh -i /root/.ssh/id_rsa root@${server} "bash $script"`
        /usr/scripts/patch_manager/run_patch_check.php "$server" "$data" "$supression_list"
}

function useIP()
{
	server_ip=`mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" -Nse "SELECT server_ip from servers where server_name='$server';" $DB_NAME`
	ssh -i /root/.ssh/id_rsa root@$server_ip 'apt-get update;mkdir -p /usr/scripts/patch_manager/'
	scp -i /root/.ssh/id_rsa $script root@$server_ip:/usr/scripts/patch_manager/
	ssh -i /root/.ssh/id_rsa root@$server_ip "chmod +x $script"
	data=`ssh -i /root/.ssh/id_rsa root@${server_ip} "bash $script"`
	/usr/scripts/patch_manager/run_patch_check.php "$server" "$data" "$supression_list"
}

# check provided hostname and if not connect use IP from DB
ping -c 1 $server > /dev/null 2>&1
if [[ $? -eq 0 ]]; then
        useHost
else
        useIP
fi
