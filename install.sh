#!/bin/bash
user=`whoami`
if [ "$user" != "root" ]; then
	echo "You must be root to install this"
	exit 0
fi
if [ ! -f /root/.ssh/id_rsa ]; then
	echo "creating pub/private keys now"
	ssh-keygen -f /root/.ssh/id_rsa -t rsa -N ''
	echo "keypair created"
fi
os=`cat /etc/issue|head -1|awk '{print $1}'`
if [ "$os" = "Ubuntu" ] || [ "$os" = "Debian" ]; then
	if [ -d "/var/www/html" ]; then
		web_dir="/var/www/html/patch_manager/"
	else
		web_dir="/var/www/patch_manager"
	fi
	apache_exists=`which apache2`
	if [ "$apache_exists" = "" ]; then
		echo "Please install the full LAMP stack before trying to install this"
		exit 0
	fi
	web_user="www-data"
	web_service="apache2"
elif [ "$os" = "CentOS" ] || [ "$os" = "Fedora" ] || [ "$os" = "Red" ]; then
	apache_exists=`which httpd`
	if [ "$apache_exists" = "" ]; then
		echo "Please install the full LAMP stack before trying to install this"
		exit 0
	fi
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
echo "Please enter the name you want this copyrighted to ['YOUR COMPANY']:"
read your_company
if [ "$your_company" = "" ]; then
	your_company='YOUR COMPANY'
fi
rewrite_config="RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ${relative_path}patches/server/(.*)$ ${relative_path}patch_list?server=$1 [QSA,NC,L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ${relative_path}packages/server/(.*)$ ${relative_path}packages?server=$1 [QSA,NC,L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ${relative_path}search/exact/(.*)$ ${relative_path}search?package=$1&exact=true [QSA,NC,L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ${relative_path}search/(.*)$ ${relative_path}search?package=$1 [QSA,NC,L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule${relative_path}rewrite_check/(.*)$ ${relative_path}rewrite_check.php [QSA,NC,L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([^/]*)$ ${relative_path}index.php?page=$1 [QSA,L]"

php_config="<?php
define(DB_HOST,'$db_host');
define(DB_USER,'$db_user');
define(DB_PASS,'$db_pass');
define(DB_NAME,'$db_name');
define(BASE_PATH,'$relative_path');
define(YOUR_COMPANY,'$your_company');
?>"
bash_config="db_host='$db_host'
db_user='$db_user'
db_pass='$db_pass'
db_name='$db_name'"
mkdir -p /root/scripts/
cp scripts/* /root/scripts/ -R
echo "$rewrite_config" > html/.htaccess
echo "$php_config" > html/lib/db_config.php
mkdir -p $web_dir
cp html/* $web_dir -R
find $web_dir -type d -print0|xargs -0 chmod 755
find $web_dir -type f -print0|xargs -0 chmod 644
echo "$rewrite_config" > ${web_dir}.htaccess
chown $web_user:$web_user $web_dir -R
echo "$php_config" > /root/scripts/db_config.php
echo "$bash_config" > /root/scripts/db.conf
service $web_service restart
rewrite_check=`curl -s localhost/rewrite_check|grep 404|wc -l`
if [ "$rewrite_check" = "1" ]; then
	echo "Apache Mod_rewrite or .htaccess is not working.  Please ensure you have mod_rewrite installed and enabled.  If it is, please make sure you change 'AllowOverride None' to 'AllowOverride All'"
	echo "If you don't, this site won't work.  You've been warned."
fi
echo "Install is now complete. You can now go to http://localhost$relative_path and begin working with this tool.  To add servers, use the following command:
	/root/scripts/add_server.sh 'server_name' 'IP_ADDRESS'
	It will ask you some questions regarding the user, password, and some other things.  Just follow the prompts.
	
	To add a server without the script granting super user, you will need to do the following:
	* Create ssh key (ssh-key-gen -t rsa) with no password on the key
	* Add public key on each server in /root/.ssh/authorized_keys
	* make sure /root/.ssh has 700 permissions on each node
	* make sure /root/.ssh/authorized_keys has 600 permissions on each node
	* go to the web UI, and click 'Manually add server'
	* Tell the UI the server name, the IP, and the distro.  The distro is not required (defaults to Ubuntu), but it will give the incorrect install command if the distro isn't apt-based
	
	Have fun!"
