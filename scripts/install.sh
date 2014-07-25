#!/bin/bash
os=`cat /etc/issue|head -1|awk '{print $1}'`
if [ "$os" = "Ubuntu" ]; then
	web_dir="/var/www/patch_manager/"
	web_user="www-data"
	web_service="apache2"
elif [ "$os" = "CentOS" ]; then
	web_dir="/var/www/html/patch_manager/"
	web_user="apache"
	web_service="httpd"
fi
relative_path="/patch_manager/"
echo "Please enter Database Host:"
read db_host
echo "Please enter Database User:"
read db_user
echo "Please enter Database Password:"
read -s db_pass
echo "Please enter the Database Name:"
read db_name
mysql -u $db_user -h $db_host -p"$db_pass" -e "create database $db_name;"
mysql -u $db_user -h $db_host -p"$db_pass" < reports.sql
echo "Please enter location for web interface [$web_dir]:"
read new_web_dir
echo "Please enter the relative path [$relative_path]"
read new_relative_path
if [ "$new_relative_path" != "$relative_path" ] && [ "$new_relative_path" != "" ]; then
	relative_path="$new_relative_path"
fi
if [ "$new_web_dir" != "$web_dir" ] && [ "$new_web_dir" != "" ]; then
	web_dir="$new_web_dir"
fi
echo "Please enter the web user [$web_user]:"
read new_web_user
if [ "$new_web_user" != "$web_user" ] && [ "$new_web_user" != "" ]; then
	web_user="$new_web_user"
fi
rewrite_config="RewriteEngine On
RewriteBase $relative_path
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule patches/server/(.*)$ /patch_list?server=$1 [QSA,NC,L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule packages/server/(.*)$ /packages?server=$1 [QSA,NC,L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule search/exact/(.*)$ /search?package=$1&exact=true [QSA,NC,L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule search/(.*)$ /search?package=$1 [QSA,NC,L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule rewrite_check/(.*)$ /rewrite_check.php [QSA,NC,L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([^/]*)$ /index.php?page=$1 [QSA,L]"

php_config="<?php
define(DB_HOST,'$db_host');
define(DB_USER,'$db_user');
define(DB_PASS,'$db_pass');
define(DB_NAME,'$db_name');
?>"
bash_config="db_host='$db_host'
db_user='$db_user'
db_pass='$db_pass'
db_name='$db_name'"
mkdir -p /root/patch_scripts/
cp install/patch_scripts/* /root/patch_scripts/ -R
mkdir -p $web_dir
cp install/web_scripts/* $web_dir -R
find $web_dir -type d -print0|xargs -0 chmod 755
find $web_dir -type f -print0|xargs -0 chmod 644
echo "$rewrite_config" > ${web_dir}.htaccess
chown $web_user:$web_user $web_dir -R
echo "$php_config" > /root/patch_scripts/db_config.php
echo "$bash_config" > /root/patch_scripts/db_config.sh
service $web_service restart
rewrite_check=`curl -s localhost/rewrite_check|grep 404|wc -l`
if [ "$rewrite_check" = "1" ]; then
	echo "Apache Mod_rewrite or .htaccess is not working.  Please ensure you have mod_rewrite installed and enabled.  If it is, please make sure you change 'AllowOverride None' to 'AllowOverride All'"
	echo "If you don't, this site won't work.  You've been warned."
fi
echo "Install is now complete. You can now go to http://localhost$relative_path and begin working with this tool.  To add servers, use the following command:
	/root/patch_scripts/add_server.sh 'server_name' 'IP_ADDRESS'
	It will ask you some questions regarding the user, password, and some other things.  Just follow the prompts.
	
	To add a server without the script granting super user, you will need to do the following:
	* Create ssh key (ssh-key-gen -t rsa) with no password on the key
	* Add public key on each server in /root/.ssh/authorized_keys
	* make sure /root/.ssh has 700 permissions on each node
	* make sure /root/.ssh/authorized_keys has 600 permissions on each node
	* go to the web UI, and click 'Manually add server'
	* Tell the UI the server name, the IP, and the distro.  The distro is not required (defaults to Ubuntu), but it will give the incorrect install command if the distro isn't apt-based
	
	Have fun!"
