#!/bin/bash

relative_path="/patch_manager/"

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

if [[ -f /etc/lsb-release ]]; then
	export os=$(lsb_release -s -d|head -1|awk {'print $1'})
elif [[ -f /etc/debian_version ]]; then
	export os="Debian $(cat /etc/debian_version)|head -1|awk {'print $1'}"
elif [[ -f /etc/redhat-release ]]; then
	export os=$(cat /etc/redhat-release|head -1|awk {'print $1'})
else
	export os="$(uname -s) $(uname -r)|head -1|awk {'print $1'}"
fi

echo -e "\nRunning install for: \e[32m$os\e[0m\n"

if [ "$os" = "Ubuntu" ] || [ "$os" = "Debian" ] || [ "$os" = "Linux" ]; then
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

function dbAskHost()
{
	echo -e "\e[32m\e[4mDatabase Setup\e[0m\n"
	unset db_host
	read -p "Database Host: " db_host
	while [[ "$db_host" = "" ]]; do
		echo -e "\e[36mNOTICE\e[0m: Please provide a Database Host, Try again.\n"
		read -p "Please enter Database Host: " db_host
	done
}
function dbAskUser()
{
	unset db_user
	read -p "Database User: " db_user
	while [[ "$db_user" = "" ]]; do
	        echo -e "\e[36mNOTICE\e[0m: Please provide a Database User, Try again.\n"
	        read -p "Please enter Database User: " db_user
	done
}
function dbAskPass()
{
	unset db_pass
	read -s -p "Database Pass: " db_pass
	echo
	while [[ "$db_pass" = "" ]]; do
        	echo -e "\e[36mNOTICE\e[0m: Please provide a Database Password, Try again.\n"
        	read -s -p "Please enter Database Pass: " db_pass
		echo
	done
}
function dbAskName()
{
	unset db_name
	read -p "Database Name: " db_name
	while [[ "$db_name" = "" ]]; do
       		echo -e "\e[36mNOTICE\e[0m: Please provide a Database Name, Try again.\n"
       	 	read -p "Please enter Database Name: " db_name
	done
	export db_name
}

function dbCheck()
{
	# check if database exists
	db_exists=$(mysql --batch -u$db_user -p$db_pass --skip-column-names -e "show databases like '"$db_name"';" | grep "$db_name" > /dev/null; echo "$?")
	if [ $db_exists -eq 0 ];then
		dbExists=yes
	else
		dbExists=no
	fi
}

function dbCreate()
{
	mysql -u $db_user -h $db_host -p"$db_pass" -e "create database $db_name;"
        mysql -u $db_user -h $db_host -p"$db_pass" -D $db_name < reports.sql
}
# ask db stuff here
dbAskHost
dbAskUser
dbAskPass
dbAskName
dbCheck

while [[ "$dbExists" = "yes" ]]; do
	echo -e "\n\e[31mERROR\e[0m: \e[36m$db_name\e[0m already exists, enter a different database.\n"
	dbExists=yes
	dbAskName
	dbCheck
done
if [[ "$dbExists" = "no" ]]; then
	dbCreate
fi

echo -e "\n\e[32m\e[4mWebpage Location Setup\e[0m\n"
unset new_web_dir
read -p "Please enter location for web interface [Default: $web_dir]: " new_web_dir
while [[ "$new_web_dir" = "" ]]; do
        echo -e "\e[32mNOTICE\e[0m: Default Location Used: $web_dir.\n"
	new_web_dir=$web_dir
done
unset new_relative_path
read -p "Please enter the relative path [Default: $relative_path]: " new_relative_path
while [[ "$new_relative_path" = "" ]]; do
        echo -e "\e[32mNOTICE\e[0m: Default Location Used: $relative_path\n"
	new_relative_path=$relative_path
done
if [ "$new_relative_path" != "$relative_path" ] && [ "$new_relative_path" != "" ]; then
	relative_path="$new_relative_path"
fi
if [ "${new_relative_path:LEN}" != "/" ]; then
	new_relative_path=$new_relative_path"/"
fi
if [ "$new_web_dir" != "$web_dir" ] && [ "$new_web_dir" != "" ]; then
	web_dir="$new_web_dir"
fi
if [ "${web_dir:LEN}" != "/" ]; then
	web_dir=$web_dir"/"
fi
unset new_web_user
read -p "Please enter the web user [Default: $web_user]: " new_web_user
while [[ "$new_web_user" = "" ]]; do
        echo -e "\e[32mNOTICE\e[0m: Using Default WebUser $web_user\n"
        new_web_user=$web_user
done
if [ "$new_web_user" != "$web_user" ] && [ "$new_web_user" != "" ]; then
	web_user="$new_web_user"
fi
unset your_company
read -p "Please enter the name you want this copyrighted to ['YOUR COMPANY']: " your_company
while [ "$your_company" = "" ]; do
	your_company='YOUR COMPANY'
	echo -e "\e[32mNOTICE\e[0m: Using Default Company $your_company\n"
done

echo -e "\e[32m\e[4mInstalling Apache related configurations\e[0m\n"

rewrite_config="RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule patches/server/(.*)$ ${relative_path}index.php?page=patch_list&server=\$1 [QSA,NC,L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule packages/server/(.*)$ ${relative_path}index.php?page=packages&server=\$1 [QSA,NC,L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule search/exact/(.*)$ ${relative_path}index.php?page=search&package=\$1&exact=true [QSA,NC,L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule search/(.*)$ ${relative_path}index.php?page=search&package=\$1 [QSA,NC,L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule rewrite_check/(.*)$ ${relative_path}rewrite_check.php [QSA,NC,L]

RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^([^/]*)$ ${relative_path}index.php?page= [QSA,L]"

php_config="<?php
define('DB_HOST','$db_host');
define('DB_USER','$db_user');
define('DB_PASS','$db_pass');
define('DB_NAME','$db_name');
define('BASE_PATH','$relative_path');
define('YOUR_COMPANY','$your_company');
?>"
bash_config="DB_HOST='$db_host'
DB_USER='$db_user'
DB_PASS='$db_pass'
DB_NAME='$db_name'"
target_web_dir=$(echo $new_web_dir|sed 's=/[^/]*$==;s/\.$//')
mkdir -p /usr/scripts/patch_manager/
cp scripts/* /usr/scripts/patch_manager/ -R
echo "$rewrite_config" > html/.htaccess
echo "$php_config" > html/lib/db_config.php
mkdir -p $web_dir
cp html/* $web_dir -R
find $web_dir -type d -print0|xargs -0 chmod 755
find $web_dir -type f -print0|xargs -0 chmod 644
echo "$rewrite_config" > ${web_dir}.htaccess
chown $web_user:$web_user $web_dir -R
echo "$php_config" > /usr/scripts/patch_manager/db_config.php
echo "$bash_config" > /usr/scripts/patch_manager/db.conf
service $web_service restart
rewrite_check=`curl -s localhost/rewrite_check|grep 404|wc -l`
if [ "$rewrite_check" = "1" ]; then
	echo -e "\n\e[31mERROR\e[0m: Apache Mod_rewrite or .htaccess is not working.  Please ensure you have mod_rewrite installed and enabled.  If it is, please make sure you change 'AllowOverride None' to 'AllowOverride All'"
	echo -e "\e[31mERROR\e[0m: If you don't, this site won't work.  You've been warned."
fi
echo -e "\n\e[32mNOTICE\e[0m: Install is now complete. You can now go to http://localhost$relative_path and begin working with this tool.  To add servers, use the following command:
	/usr/scripts/patch_manager/add_server.sh -s server_name -ip IP_ADDRESS
	It will ask you some questions regarding the user, password, and some other things.  Just follow the prompts.
	
	To add a server without the script granting super user, you will need to do the following:
	* Create ssh key (ssh-key-gen -t rsa) with no password on the key
	* Add public key on each server in /root/.ssh/authorized_keys
	* make sure /root/.ssh has 700 permissions on each node
	* make sure /root/.ssh/authorized_keys has 600 permissions on each node
	* go to the web UI, and click 'Manually add server'
	* Tell the UI the server name, the IP, and the distro.  The distro is not required (defaults to Ubuntu), but it will give the incorrect install command if the distro isn't apt-based
	
	Have fun!"
