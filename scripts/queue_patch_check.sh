#!/bin/bash
. /root/scripts/db.conf
server="$1"
i=$1
sql="DELETE FROM patches where server_name='$server';"
supression_list=`mysql -u $DB_USER -p"$DB_PASS" -Nse "SELECT package from where server_name in ('$server', 0);" $DB_NAME`
mysql -u root -p$DB_PASS -D $DB_NAME -e "$sql" -h $DB_HOST
server_ip=`mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" -Nse "SELECT server_ip from servers where server_name='$server';" $DB_NAME`
script='/root/scripts/patch_checker.sh'
ssh -i /root/.ssh/id_rsa root@$server_ip 'apt-get update;mkdir -p /root/scripts/'
scp -i /root/.ssh/id_rsa $script root@$server_ip:~/scripts/
ssh -i /root/.ssh/id_rsa root@$server_ip "chmod +x $script"
data=`ssh -i /root/.ssh/id_rsa root@${server_ip} "bash $script"`
/root/scripts/run_patch_check.php "$server" "$data" "$supression_list"
