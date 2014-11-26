#!/bin/bash

# load DB config
. /opt/patch_manager/db.conf

server_list=`mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" -Nse 'SELECT server_name from servers' $DB_NAME`
for host in `echo "$server_list"`; do
        echo "Working on server [$host]...."
        install_cmd=`mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" -Nse "select d.upgrade_command from distro d left join distro_version dv on dv.distro_id = d.id left join servers s on s.distro_version = dv.id where server_name='$host';" $DB_NAME`
        package_list=`mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" -Nse "SELECT package_name FROM patches WHERE server_name = '$host' and package_name NOT IN (select package_name from supressed where server_name='$host' or server_name=0) AND to_upgrade=1 AND upgraded=0;" $DB_NAME`
        echo $package_list
        for pack in `echo "$package_list"`; do
                packages_to_install="$packages_to_install $pack"
        done
        install_cmd="$install_cmd $packages_to_install"
        server_ip=`mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" -Nse "select server_ip from servers where server_name='$host' limit 1;" $DB_NAME`
        ssh -i /root/.ssh/id_rsa root@${server_ip} "$install_cmd"
#       mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" -Nse "UPDATE patches set to_upgrade=0, upgraded=1 where server_name=$host" $DB_NAME
        echo "$install_cmd"
done
