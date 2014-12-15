#!/bin/bash

# load DB config
. /opt/patch_manager/db.conf

# define server from argument
server="$1"

# connect to DB and clean up packages
sql="DELETE FROM patch_allpackages where server_name='$server';"

# define other functions
mysql -u $DB_USER -p"$DB_PASS" -D $DB_NAME -e "$sql" -h $DB_HOST
script='/opt/patch_manager/package_checker.sh'
export server sql script

function useHost()
{
        ssh -i /root/.ssh/id_rsa root@$server 'mkdir -p /opt/patch_manager/'
        scp -i /root/.ssh/id_rsa $script root@$server:/opt/patch_manager/
        ssh -i /root/.ssh/id_rsa root@$server "chmod +x $script"
        data=`ssh -i /root/.ssh/id_rsa root@${server} "bash $script"`
        /opt/patch_manager/run_get_package_list.php "$server" "$data"
}

function useIP()
{
        server_ip=`mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" -Nse "SELECT server_ip from servers where server_name='$server';" $DB_NAME`
        ssh -i /root/.ssh/id_rsa root@$server_ip 'mkdir -p /opt/patch_manager/'
        scp -i /root/.ssh/id_rsa $script root@$server_ip:/opt/patch_manager/
        ssh -i /root/.ssh/id_rsa root@$server_ip "chmod +x $script"
        data=`ssh -i /root/.ssh/id_rsa root@${server_ip} "bash $script"`
        /opt/patch_manager/run_get_package_list.php "$server" "$data"
}

# check provided hostname and if not connect use IP from DB
ping -c 1 $server > /dev/null 2>&1
if [[ $? -eq 0 ]]; then
        useHost
else
        useIP
fi
