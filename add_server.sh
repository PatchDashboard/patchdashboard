#!/bin/bash
. /root/scripts/db.conf
echo "What is the servers name?"
read server_name
echo "What is the servers IP?"
read server_ip
echo "Please enter the number corresponding to your distro:"
distro_names=`mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" -Nse 'SELECT distro_name from distro order by id ASC;' $DB_NAME`
for i in $distro_names; do
	distro_num=`mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" -Nse "SELECT id from distro where distro_name='$i';" $DB_NAME`
	echo "		[$distro_num] $i"
done
read dist_num_selected
echo "Please select which version of $distro_name you're using on this node:"
distro_ver=`mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" -Nse "SELECT id from distro_version where distro_id='$dist_num_selected' order by id ASC;" $DB_NAME`
for i in $distro_ver; do
	ver_name=`mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" -Nse "SELECT version_num from distro_version where id='$i';" $DB_NAME`
	echo "		[$i] $ver_name"
done
read dist_ver_selected
mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" -D $DB_NAME -e "INSERT INTO servers(server_name,distro_id,server_ip,distro_version) VALUES('$server_name',$dist_num_selected,'$server_ip',$dist_ver_selected);"
echo "Server added!
	Next:
		If you have access via SSH to the root user on the remote system, run this:
			ssh-copy-id root@$server_ip
		If you don't have access via SSH to the root user on the remote system, do this:
			echo /root/.ssh/id_rsa.pub
			copy the output of the above command
			
			ON THE REMOTE SYSTEM:
			add what you copied from the previous step to
				/root/.ssh/authorized_keys
			If the file does not exist, run this:
				mkdir /root/.ssh; touch /root/.ssh/authorized_keys; chmod 700 /root/.ssh; chmod 700 /root/.ssh/authorized_keys
			then add the content of the clipboard to that file
			
			Next: test:
				ssh root@$server_ip
			If it doesn't let you right in after accepting the key, something isn't working correctly.
"
