#!/bin/bash
. /root/scripts/db.conf
server_list=`mysql -u $DB_USER -p"$DB_PASS" -Nse 'SELECT server_name from servers' $DB_NAME`
for i in `echo $server_list`; do
	/root/scripts/queue_package_check.sh "$i" &
done
