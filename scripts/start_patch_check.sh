#!/bin/bash
server_list="SERVER_NAMES SEPARATED_BY A_SPACE"
db_user='root'
db_password='*****'
db_name='reports'
for i in `echo $server_list`; do
	echo "Running against server [$i]"
	server=$i
	sql="DELETE FROM patches where server_name='$server';"
	supression_list="ruby1.8"
	mysql -u $db_user -p$db_password -D $db_name -e "$sql"
	script='/root/scripts/patch_checker.sh'
	ssh -i /root/.ssh/id_rsa root@$i 'apt-get update;mkdir -p /root/scripts/'
	scp -i /root/.ssh/id_rsa $script root@$i:~/scripts/
	ssh -i /root/.ssh/id_rsa root@$i "chmod +x $script"
	data=`ssh -i /root/.ssh/id_rsa root@${i} "bash $script"`
	/root/scripts/run_patch_check.php "$server" "$data" "$supression_list"
done
