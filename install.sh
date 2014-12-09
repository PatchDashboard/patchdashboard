#!/bin/bash
#####################################################################
##
## Title: Patch Management Dashboard Installer
##
## Authors: jonsjava <jonsjava@gmail.com>
##          metalcated <mike.gomon@gmail.com>
##
## Date: 11/22/2014
##
## Version: 0.3
##
## Changelog: 0.1 - Initial Release
##            0.2 - Improved base installer for OS detection
##            0.3 - 
##
######################################################################

# generate random passwords
function genPasswd()
{
	local p=$1
	[ "$p" == "" ] && p=12
	tr -dc A-Za-z0-9_ < /dev/urandom | head -c ${p} | xargs
}

# default admin and users for the admin web interface
# admin info
web_admin="pmdadmin"
web_admin_passwd=`genPasswd`
web_admin_email="no_admin@email.com"
# user info
web_duser="pmduser"
web_duser_passwd=`genPasswd`
web_duser_email="no_user@email.com"
# export to global
export web_admin web_admin_email web_admin_passwd 
export web_user web_user_email web_user_passwd

# default target path for php files
relative_path="/patchmgr/"

# get user running installer
user=`whoami`

# if user is not root, exit
if [ "$user" != "root" ]; then
	echo "You must be root to install this"
	exit 0
fi

# create keypair for root
if [ ! -f /root/.ssh/id_rsa ]; then
	echo -e "\n\e[32mNotice\e[0m: Creating pub/private keys for $user."
	ssh-keygen -f /root/.ssh/id_rsa -t rsa -N ''
	echo "\e[32mNotice\e[0m: Keypair created.\n"
fi

# get OS information and run applicable function
if [[ -f /etc/lsb-release ]]; then
	export os=$(lsb_release -s -d|head -1|awk {'print $1'})
elif [[ -f /etc/debian_version ]]; then
	export os="Debian $(cat /etc/debian_version)|head -1|awk {'print $1'}"
elif [[ -f /etc/redhat-release ]]; then
	export os=$(cat /etc/redhat-release|head -1|awk {'print $1'})
else
	export os="$(uname -s) $(uname -r)|head -1|awk {'print $1'}"
fi

## begin main functions of installer

function OSDetect()
{
	if [[ "$os" = "Red" ]]; then
		os="Red Hat"
	fi
	echo -e "Running install for: \e[32m$os\e[0m\n"

	if [[ "$os" = "Ubuntu" ]] || [[ "$os" = "Debian" ]] || [[ "$os" = "Linux" ]]; then
		apache_exists=`which apache2`
		if [ "$apache_exists" = "" ]; then
			echo "Please install the full LAMP stack before trying to install this"
			exit 0
		fi
		web_dir="/var/www/patch_manager"
		web_user="www-data"
		web_service="apache2"

	elif [[ "$os" = "CentOS" ]] || [[ "$os" = "Fedora" ]] || [[ "$os" = "Red Hat" ]]; then
		httpd_exists=`which httpd`
		if [ "$httpd_exists" = "" ]; then
			echo "Please install the full LAMP stack before trying to install this"
			exit 0
		fi
		web_dir="/var/www/patch_manager/"
		web_user="apache"
		web_service="httpd"
		
	fi
}

function dbAskHost()
{
	echo -e "\e[32m\e[4mDatabase Setup\e[0m\n"
	unset db_host
	read -p "Database Host: " db_host
	while [[ "$db_host" = "" ]]; do
		echo -e "\n\e[36mNotice\e[0m: Please provide a Database Host, please try again.\n"
		read -p "Database Host: " db_host
	done

	ping -c 1 $db_host > /dev/null 2>&1
	if [ $? -gt 0 ]; then
		echo -e "\n\e[31mNotice\e[0m: Inactive host: \e[36m$db_host\e[0m, please try again.\n"
		dbAskHost
	fi
}

function dbAskUser()
{
	unset db_user
	read -p "Database User: " db_user
	while [[ "$db_user" = "" ]]; do
	        echo -e "\n\e[36mNotice\e[0m: Please provide a Database User, please try again.\n"
	        read -p "Database User: " db_user
	done
}

function dbAskPass()
{
	unset db_pass
	read -s -p "Database Pass: " db_pass
	echo
	while [[ "$db_pass" = "" ]]; do
        	echo -e "\n\e[36mNotice\e[0m: Please provide a Database Password, please try again.\n"
        	read -s -p "Database Pass: " db_pass
		echo
	done
}

function dbAskName()
{
	unset db_name
	read -p "Database Name: " db_name
	while [[ "$db_name" = "" ]]; do
       		echo -e "\n\e[36mNotice\e[0m: Please provide a Database Name, please try again.\n"
       	 	read -p "Database Name: " db_name
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

function dbConnTest()
{
        # check connection to db
        db_connx=$(mysql --batch -u$db_user -p$db_pass -e ";" > /dev/null; echo "$?")
	if [ $db_connx -eq 0 ];then
                dbConnx=yes
        else
                dbConnx=no
        fi
}

function dbCreate()
{
	mysql -u $db_user -h $db_host -p"$db_pass" -e "create database $db_name;"
        mysql -u $db_user -h $db_host -p"$db_pass" -D $db_name < reports.sql
}
function dbUpdate()
{
        mysql -u $db_user -h $db_host -p"$db_pass" -D $db_name < reports.sql
}

function WebUIInfo()
{
	echo -e "\e[32m\e[4mWebpage Location Setup\e[0m\n"
	unset new_web_dir
	read -p "Please enter location for web interface [Default: $web_dir]: " new_web_dir
	while [[ "$new_web_dir" = "" ]]; do
        	echo -e "\e[32mNotice\e[0m: Default Location Used: $web_dir."
        	new_web_dir=$web_dir
	done
	echo
	unset new_relative_path
	read -p "Please enter the relative path [Default: $relative_path]: " new_relative_path
	while [[ "$new_relative_path" = "" ]]; do
        	echo -e "\e[32mNotice\e[0m: Default Location Used: $relative_path"
        	new_relative_path=$relative_path
	done
	echo
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
        	echo -e "\e[32mNotice\e[0m: Using Default WebUser $web_user"
        	new_web_user=$web_user
	done
	echo
	if [ "$new_web_user" != "$web_user" ] && [ "$new_web_user" != "" ]; then
        	web_user="$new_web_user"
	fi
	
	unset your_company
        read -p "Please enter the name you want this copyrighted to ['YOUR COMPANY']: " your_company
        while [ "$your_company" = "" ]; do
                your_company='YOUR COMPANY'
                echo -e "\e[32mNotice\e[0m: Using Default Company $your_company"
        done
	echo

        # Web-UI admin username
	unset new_web_admin
        read -p "Web Interface Admin [Default: $web_admin]: " new_web_admin
        while [[ "$new_web_admin" = "" ]]; do
                echo -e "\e[32mNotice\e[0m: Default Web Admin used: $web_admin"
                new_web_admin=$web_admin
        done
	echo

        # Web-UI admin e-mail address (for alerts)
	unset new_web_admin_email
        read -p "Web Admin Email Address [Default: $web_admin_email]: " new_web_admin_email
        while [[ "$new_web_admin_email" = "" ]]; do
                echo -e "\e[32mNotice\e[0m: Default Web Admin Email used: $web_admin_email"
                new_web_admin_email=$web_admin_email
        done
	echo

        # Web-UI admin password
	unset new_web_admin_passwd
        read -p "Web Admin Password [Default: $web_admin_passwd]: " new_web_admin_passwd
        while [[ "$new_web_admin_passwd" = "" ]]; do
                echo -e "\e[32mNotice\e[0m: Default Password used: $web_admin_passwd"
                new_web_admin_passwd=$web_admin_passwd
        done
	echo

        # Web-UI standard username
	unset new_web_duser
        read -p "Web Interface User [Default: $web_duser]: " new_web_duser
        while [[ "$new_web_duser" = "" ]]; do
                echo -e "\e[32mNotice\e[0m: Default User used: $web_duser."
                new_web_duser=$web_duser
        done
	echo

        # Web-UI standard e-mail address (for patch-based alerts)
        unset new_web_duser_email
        read -p "Web User Email Address [Default: $web_duser_email]: " new_web_duser_email
        while [[ "$new_web_duser_email" = "" ]]; do
                echo -e "\e[32mNotice\e[0m: Default Web User Email used: $web_duser_email"
                new_web_duser_email=$web_duser_email
        done
	echo

        # Web-UI standard password
	unset new_web_duser_passwd
        read -p "Please enter location for web interface [Default: $web_duser_passwd]: " new_web_duser_passwd
        while [[ "$new_web_duser_passwd" = "" ]]; do
                echo -e "\e[32mNotice\e[0m: Default Web User Password used: $web_duser_passwd"
                new_web_duser_passwd=$web_duser_passwd
        done
	echo

}

function dbUserCreate()
{
# add admin user
mysql -u $db_user -h $db_host -p"$db_pass" << EOF
INSERT INTO $db_name.users (id,user_id,email,admin,display_name,password) VALUES (NULL, '$new_web_admin', '$new_web_admin_email', '1', '$new_web_admin_email', '$new_web_admin_passwd');
EOF
# add basic user
mysql -u $db_user -h $db_host -p"$db_pass" << EOF
INSERT INTO $db_name.users (id,user_id,email,admin,display_name,password) VALUES (NULL, '$new_web_duser', '$new_web_duser_email', '0', '$new_web_duser_email', '$new_web_duser_passwd');
EOF
}

function InstallApp()
{

# rewrite config for .htaccess
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

# write php config for application
php_config="<?php
define('DB_HOST','$db_host');
define('DB_USER','$db_user');
define('DB_PASS','$db_pass');
define('DB_NAME','$db_name');
define('BASE_PATH','$relative_path');
define('YOUR_COMPANY','$your_company');
?>"

# write shell script config
bash_config="DB_HOST='$db_host'
DB_USER='$db_user'
DB_PASS='$db_pass'
DB_NAME='$db_name'"

# remove ending forward slash for conf
patchmgr=$(echo $relative_path|sed 's=/[^/]*$==;s/\.$//')
targetdir=$(echo $new_web_dir|sed 's=/[^/]*$==;s/\.$//')

# install virtualhost file to default conf.d dir apache/httpd
if [[ "$os" = "Ubuntu" ]] || [[ "$os" = "Debian" ]] || [[ "$os" = "Linux" ]]; then
# remove old conf
if [[ -f /etc/apache2/conf.d/patch_manager.conf ]]; then
	rm -f /etc/apache2/conf.d/patch_manager.conf
fi
# setup virtualhost
cat <<EOA>> /etc/apache2/conf.d/patch_manager.conf
Alias $patchmgr $targetdir

<Directory $targetdir>
        Options FollowSymLinks
        AllowOverride All
        Order allow,deny
        Allow from all
        RewriteEngine On
</Directory>
EOA

elif [[ "$os" = "CentOS" ]] || [[ "$os" = "Fedora" ]] || [[ "$os" = "Red Hat" ]]; then
# remove old conf
if [[ -f /etc/httpd/conf.d/patch_manager.conf ]]; then
        rm -f /etc/httpd/conf.d/patch_manager.conf
fi
# setup virtualhost
cat <<EOA>> /etc/httpd/conf.d/patch_manager.conf
Alias $patchmgr $targetdir
        
<Directory $targetdir>
        Options FollowSymLinks
        AllowOverride All
        Order allow,deny
        Allow from all
        RewriteEngine On
</Directory>
EOA
fi

# main application install
target_web_dir=$(echo $new_web_dir|sed 's=/[^/]*$==;s/\.$//')
## DO NOT CHANGE PATH ##
mkdir -p /opt/patch_manager/
cp scripts/* /opt/patch_manager/ -R
echo "$rewrite_config" > html/.htaccess
echo "$php_config" > html/lib/db_config.php
mkdir -p $web_dir
cp html/* $web_dir -R
find $web_dir -type d -print0|xargs -0 chmod 755
find $web_dir -type f -print0|xargs -0 chmod 644
echo "$rewrite_config" > ${web_dir}.htaccess
chown $web_user:$web_user $web_dir -R
echo "$php_config" > /opt/patch_manager/db_config.php
echo "$bash_config" > /opt/patch_manager/db.conf
service $web_service restart
rewrite_check=`curl -s localhost/patchmgr/rewrite_check|grep 404|wc -l`
if [ "$rewrite_check" = "1" ]; then
	echo -e "\n\e[31mError\e[0m: Apache Mod_rewrite or .htaccess is not working.  Please ensure you have mod_rewrite installed and enabled.  If it is, please make sure you change 'AllowOverride None' to 'AllowOverride All'"
	echo -e "\e[31mError\e[0m: If you don't, this site won't work.  You've been warned."
fi
echo -e "\n\e[32mNotice\e[0m: Install is now complete. You can now go to  http://`hostname`$relative_path and begin working with this tool.  To add servers, use the following command:
	/opt/patch_manager/add_server.sh -s server_name -ip ip_address
	It will ask you some questions regarding the user, password, and some other things.  Just follow the prompts.
	
	To add a server without the script granting super user, you will need to do the following:
	* Create ssh key (ssh-key-gen -t rsa) with no password on the key
	* Add public key on each server in /root/.ssh/authorized_keys
	* make sure /root/.ssh has 700 permissions on each node
	* make sure /root/.ssh/authorized_keys has 600 permissions on each node
	* go to the web UI, and click 'Manually add server'
	* Tell the UI the server name, the IP, and the distro.  The distro is not required (defaults to Ubuntu), but it will give the incorrect install command if the distro isn't apt-based
	
	Have fun!"
}

# show script header
clear
echo -e "\n###########################################################################"
echo -e "#################  Patch Management Dashboard Installer  ##################"
echo -e "###########################################################################\n"
echo -e " Github: https://github.com/jonsjava/patchdashboard\n"
echo -e " Patch Management Dashboard does one thing and does it well:\n"
echo -e "   - Monitors for needed patches on your nodes *Linux and Windows (soon!)*\n"
echo -e " In the coming releases of this application you will be given the ability"
echo -e " to suppress patches on a per-server or a global basis as well as tell the"
echo -e " system to install a single package or fully update a server all from a"
echo -e " custom built admin interface. This includes both Linux and Windows hosts.\n"
echo -e " Stay tuned... :)\n"
echo -e "###########################################################################\n"

# run OS function
echo -e "\e[36m# Detecting Operating System Version\n\e[0m"
OSDetect

# run DB functions
echo -e "\e[36m# Database Setup information\n\e[0m"
dbAskHost
dbAskUser
dbAskPass
dbAskName
dbConnTest
dbCheck

# check database connection from user provided details
if [[ "$dbConnx" = "no" ]]; then
        echo -e "\n\e[31mError\e[0m: Unable to connect to: \e[36m$db_host\e[0m, please try again.\n"
	dbAskHost
	dbAskUser
	dbAskPass
	dbAskName
	dbConnTest
        dbCheck
fi

if [[ "$dbExists" = "yes" ]]; then
	echo -e "\n\e[32mNotice\e[0m: \e[36m$db_name\e[0m already exists, updating tables."
        dbUpdate
else
	echo -e "\n\e[32mNotice\e[0m: \e[36m$db_name\e[0m does not exist, creating as new."
	dbCreate
fi

# Ask web information
echo -e "\n\e[36m# Webpage Location, User and Admin information.\e[0m\n"
WebUIInfo
# create users in database
echo -e "\e[32mNotice\e[0m: Adding web admin and web user to \e[36m$db_name\e[0m\n"
dbUserCreate
# Finalize the install
echo -e "\e[36m# Installing Apache related configurations\e[0m\n"
InstallApp
