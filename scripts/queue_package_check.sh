#!/bin/bash
. /root/scripts/db.conf
server="$1"
i=$1
sql="DELETE FROM patch_allpackages where server_name='$server';"
mysql -u $DB_USER -p"$DB_PASS" -D $DB_NAME -e "$sql" -h $DB_HOST
script='/root/scripts/package_checker.sh'
ssh -i /root/.ssh/id_rsa root@$i 'mkdir -p /root/scripts/'
scp -i /root/.ssh/id_rsa $script root@$i:~/scripts/
ssh -i /root/.ssh/id_rsa root@$i "chmod +x $script"
data=`ssh -i /root/.ssh/id_rsa root@${i} "bash $script"`
/root/scripts/run_get_package_list.php "$server" "$data"