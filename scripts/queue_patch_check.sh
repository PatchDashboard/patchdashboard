#!/bin/bash
. /root/scripts/db.conf
server="$1"
i=$1
sql="DELETE FROM patches where server_name='$server';"
supression_list=`mysql -u $DB_USER -p"$DB_PASS" -Nse "SELECT package from where server_name in ('$server', 0);" $DB_NAME`
mysql -u root -p$db_password -D $db_name -e "$sql"
script='/root/scripts/patch_checker.sh'
ssh -i /root/.ssh/id_rsa root@$i 'apt-get update;mkdir -p /root/scripts/'
scp -i /root/.ssh/id_rsa $script root@$i:~/scripts/
ssh -i /root/.ssh/id_rsa root@$i "chmod +x $script"
data=`ssh -i /root/.ssh/id_rsa root@${i} "bash $script"`
/root/scripts/run_patch_check.php "$server" "$data" "$supression_list"
