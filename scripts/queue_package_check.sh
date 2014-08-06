#!/bin/bash
. /root/scripts/db.conf
server="$1"
i=$1
sql="DELETE FROM patch_allpackages where server_name='$server';"
mysql -u $DB_USER -p"$DB_PASS" -D $DB_NAME -e "$sql" -h $DB_HOST
script='/root/scripts/package_checker.sh'
server_ip=`mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" -Nse "SELECT server_ip from servers where server_name='$server';" $DB_NAME`
ssh -i /root/.ssh/id_rsa root@$server_ip 'mkdir -p /root/scripts/'
scp -i /root/.ssh/id_rsa $script root@$server_ip:~/scripts/
ssh -i /root/.ssh/id_rsa root@$server_ip "chmod +x $script"
data=`ssh -i /root/.ssh/id_rsa root@${server_ip} "bash $script"`
/root/scripts/run_get_package_list.php "$server" "$data"
