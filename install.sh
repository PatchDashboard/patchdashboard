#!/bin/bash
################################################################################
##
## Title: Patch Management Dashboard Installer
##
## Authors: jonsjava <jonsjava@gmail.com>
##          metalcated <mike.gomon@gmail.com>
##
## Date: 11/22/2014
##
## Version: 0.7
##
## Changelog: 0.1 - Initial Release
##            0.2 - Improved base installer for OS detection
##            0.3 - Script rework/overhaul. Added sql insert secion
##                - Cleaned up and organized into functions
##            0.4 - Added crontab function rather than manual proc
##                - Lots of logic added to read the existing db users
##                - Check connections to the provided db info 
##                - Added virtualhost file to apache/httpd
##            0.5 - Fixed relative_path in rewrite check
##                - Added SQL fixes for existing table data
##            0.6 - Added automated Debian flavor apache/php/mysql
##                - Added automated Red Hat flavor apache/php/mysql
##                - Added PHP Version check and cleaned up some output
##            0.7 - Added installation key for agent authentication
##                - Added more logic for mysql root passwords
##                - Added php install for unsupported versions
##                - Fixed issue with mysql root password being setup on el5
##                - Fixed some more issues with install apache/mysql/php on el5
##
#################################################################################

# generate random passwords
function genPasswd()
{
	local p=$1
	[ "$p" == "" ] && p=12
	salt='W[62~L41|]CU15b'
	random=$(tr -dc A-Za-z0-9_ < /dev/urandom | head -c ${p} | xargs)
	pass="${random}{$salt}"
	echo $pass
}

function genInstallKey()
{
	export installation_key=$(< /dev/urandom tr -dc 'a-zA-Z0-9~!@#$%^&*_-' | head -c${1:-32}|sha256sum)
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
	echo -e "\e[31Error\e[0m: You must be root to install this!"
	exit 0
fi

# create keypair for root
if [ ! -f /root/.ssh/id_rsa ]; then
	echo -e "\n\e[32mNotice\e[0m: Creating pub/private keys for $user."
	ssh-keygen -f /root/.ssh/id_rsa -t rsa -N ''
	echo -e "\e[32mNotice\e[0m: Keypair created.\n"
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
		mysqld_exists=`which mysqld`
		if [[ "$os" = "Linux" ]] && [[ "$apache_exists" = "" ]]; then
			echo -e "\n\e[31mNotice\e[0m: Please install the full LAMP stack before trying to install this application.\n\n\e[31mNotice\e[0m: https://community.rackspace.com/products/f/25/t/49\n"
                        exit 0
		fi
		if [[ "$apache_exists" = "" ]]; then
			echo -e "\e[31mNotice\e[0m: Apache/PHP does not seem to be installed."
			unset wait
			echo -e "\e[32m";read -p "Press enter to contunue install" wait;echo -e "\e[0m"
			echo -e "\e[31mNotice\e[0m: Please wait while prerequisites are installed...\n\n\e[31mNotice\e[0m: Installing Apache and PHP5..."
			while true;
			do echo -n .;sleep 1;done &
			apt-get install -y apache2 apache2-threaded-dev apache2-utils php5 libapache2-mod-php5 php5-mcrypt php5-common php5-gd php5-cgi php5-cli php5-fpm php5-dev php5-xmlrpc curl > /dev/null 2>&1
			kill $!; trap 'kill $!' SIGTERM;
			echo "ServerName localhost" >> /etc/apache2/httpd.conf
			echo -e "\n\e[32mNotice\e[0m: Apache/PHP Installation Complete\n"
		fi
		if [[ "$mysqld_exists" = "" ]]; then
			echo -e "\e[31mNotice\e[0m: MySQL does not seem to be installed."
                        unset wait
			echo -e "\e[32m";read -p "Press enter to contunue install" wait;echo -e "\e[0m"
			db_user_id="root"
			mysqlPasswd
			if [[ "$mysql_passwd" != "$mysql_passwd_again" ]]; then
				echo -e "\n\n\e[31mNotice\e[0m: Passwords do not match, please try again.\n"
				mysqlPasswd
			fi
			debconf-set-selections <<< "mysql-server mysql-server/root_password password $mysql_passwd"
			debconf-set-selections <<< "mysql-server mysql-server/root_password_again password $mysql_passwd_again"
			echo -e "\n\n\e[31mNotice\e[0m: Installing MySQL Client and Server..."
			while true;
                        do echo -n .;sleep 1;done &
			apt-get install -y mysql-client mysql-server php5-mysql libapache2-mod-auth-mysql libmysqlclient-dev > /dev/null 2>&1
			kill $!; trap 'kill $!' SIGTERM;
			mysql_install_db > /dev/null 2>&1
			echo -e "\nInstalling MySQL system tables...\nOK"
			echo -e "Filling help tables...\nOK"
			echo -e "\n\e[36mNotice\e[0m: You may run /usr/bin/mysql_secure_installation to secure the MySQL installation once this application setup has been completed."
			echo -e "\n\e[32mNotice\e[0m: MySQL Installation Complete\n"
			unset db_user_id
		fi
		web_dir="/var/www/patch_manager/"
		web_user="www-data"
		web_service="apache2"
		echo -e "\e[32mChecking apache2 rewrite module\n\e[0m"
		if [[ -z $(apache2ctl -M|grep rewrite) ]]; then
			# enable rewrite modules
			echo -e "\n\e[32mApache Module\e[0m: rewrite status = \e[31mdisabled\e[0m"
			a2enmod rewrite > /dev/null 2>&1
			echo -e "\n\e[32mApache Module\e[0m: rewrite enabled\n"
		else
			echo -e "\n\e[32mApache Module\e[0m: rewrite status = enabled\n"
		fi
		echo -e "\e[32mChecking if services are started\n\e[0m"
                if [[ -n $(service mysql status|grep "stop/waiting") ]]; then
                        # enable mysqld
                        echo -e "\e[32mService\e[0m: mysql status = \e[31mstop/waiting\n\e[0m"
                        service mysql start
                        echo
                else
                        echo -e "\e[32mService\e[0m: mysql status = started\n"
                fi
		# sanity checks
		phpverCheck
		checkIPtables

	elif [[ "$os" = "CentOS" ]] || [[ "$os" = "Fedora" ]] || [[ "$os" = "Red Hat" ]]; then
		httpd_exists=$(rpm -qa | grep "httpd")
		php_exists=$(rpm -qa | grep "php")
		mysqld_exists=$(rpm -qa | grep "mysql-server")
		if [[ "$httpd_exists" = "" ]]; then
			echo -e "\e[31mNotice\e[0m: Apache does not seem to be installed."
                        unset wait
                        echo -e "\e[32m";read -p "Press enter to contunue install" wait;echo -e "\e[0m"
                        echo -e "\e[31mNotice\e[0m: Please wait while prerequisites are installed...\n\n\e[31mNotice\e[0m: Installing Apache..."
                        while true;
                        do echo -n .;sleep 1;done &
			yum install --disablerepo=webtatic -y httpd httpd-devel curl > /dev/null 2>&1
                        kill $!; trap 'kill $!' SIGTERM;
                        echo -e "\n\e[32mNotice\e[0m: Apache Installation Complete\n"
			echo -e "\e[32mChecking httpd start up config\n\e[0m"
                        if [[ -z $(chkconfig --list httpd|grep "2:on\|3:on\|5:on") ]]; then
                                # enable httpd at startup 235
                                echo -e "\e[32mChkConfig\e[0m: httpd status = \e[31mdisabled\e[0m"
                                chkconfig --level 235 httpd on
				echo -e "\e[32mChkConfig\e[0m: httpd enabled\n"
                        else
                                echo -e "\e[32mChkConfig\e[0m: httpd status = enabled\n"
                        fi
		fi
		if [[ "$php_exists" = "" ]]; then
                        echo -e "\e[31mNotice\e[0m: PHP does not seem to be installed."
                        unset wait
                        echo -e "\e[32m";read -p "Press enter to contunue install" wait;echo -e "\e[0m"
                        echo -e "\e[31mNotice\e[0m: Installing PHP5..."
                        while true;
                        do echo -n .;sleep 1;done &
                        yum install -y php php-mysql php-common php-gd php-mbstring php-mcrypt php-devel php-xml php-cli > /dev/null 2>&1
                        kill $!; trap 'kill $!' SIGTERM;
                        echo -e "\n\n\e[32mNotice\e[0m: PHP Installation Complete\n"
                fi
		if [[ "$mysqld_exists" = "" ]]; then
                        echo -e "\e[31mNotice\e[0m: MySQL does not seem to be installed."
                        unset wait
                        echo -e "\e[32m";read -p "Press enter to contunue install" wait;echo -e "\e[0m"
                        db_user_id="root"
                        mysqlPasswd
			echo -e "\e[32m\e[4mMySQL Database Install and Setup\n\e[0m"
                        if [[ "$mysql_passwd" != "$mysql_passwd_again" ]]; then
                                echo -e "\n\n\e[31mNotice\e[0m: Passwords do not match, please try again.\n"
                                mysqlPasswd
                        fi
                        echo -e "\n\n\e[31mNotice\e[0m: Installing MySQL Client and Server..."
                        while true;
                        do echo -n .;sleep 1;done &
                        yum install -y mysql mysql-server mysql-devel > /dev/null 2>&1
                        kill $!; trap 'kill $!' SIGTERM;
			service mysqld restart > /dev/null 2>&1
                        mysql_install_db > /dev/null 2>&1
                        echo -e "\nInstalling MySQL system tables...\nOK"
                        echo -e "Filling help tables...\nOK"
                        echo -e "\n\e[36mNotice\e[0m: You may run /usr/bin/mysql_secure_installation to secure the MySQL installation once this application setup has been completed."
                        echo -e "\n\e[32mNotice\e[0m: MySQL Installation Complete\n"
                        unset db_user_id
			echo -e "\e[32mChecking mysqld start up config\n\e[0m"
                	if [[ -z $(chkconfig --list mysqld|grep "2:on\|3:on\|5:on") ]]; then
                        	# enable mysqld at startup 235
                        	echo -e "\e[32mChkConfig\e[0m: mysqld status = \e[31mdisabled\e[0m"
                        	chkconfig --level 235 mysqld on
				echo -e "\e[32mChkConfig\e[0m: mysqld enabled\n"
                	else
                        	echo -e "\e[32mChkConfig\e[0m: mysqld status = enabled\n"
                	fi
                fi
		web_dir="/var/www/patch_manager/"
		web_user="apache"
		web_service="httpd"
		echo -e "\e[32mChecking if services are started\n\e[0m"
                if [[ -n $(service mysqld status|grep "stopped") ]]; then
                        # enable mysqld
                        echo -e "\e[32mService\e[0m: mysqld status = \e[31mstopped\n\e[0m"
			service mysqld restart
			echo
                else
                        echo -e "\e[32mService\e[0m: mysqld status = started\n"
                fi
		# set initial mysql root password
		mysqlRootPwd
		# sanity checks
		phpverCheck
		checkIPtables
		localhostChk
	fi
}

function phpversion()
{ 
	echo "$@" | awk -F. '{ printf("%d.%d.%d\n", $1,$2,$3); }';
}

function phpverCheck()
{
	phpver=$(php -version|grep "PHP 5"|awk {'print $2'})

	if [[ $(phpversion $phpver) < $(phpversion 5.2.0) ]]; then
		echo -e "\e[0mYou are running PHP Version: \e[031m$phpver\e[0m which is incompatible with this application.\n"
		phpExtraInst
	fi
}

function phpExtraInst()
{
	echo -e "\e[32mPHP Install\e[0m: Installing PHP 5.3/5.4 depending on your distro\n"
	echo -e "\e[32mPHP Install\e[0m: Adding EPEL and WebTatic Repos"
	rpm -Uvh "http://dl.fedoraproject.org/pub/epel/5/x86_64/epel-release-5-4.noarch.rpm" > /dev/null 2>&1
	rpm -Uvh "http://repo.webtatic.com/yum/centos/5/latest.rpm" > /dev/null 2>&1
	sed -i 's/enabled=0/enabled=1/g' /etc/yum.repos.d/webtatic.repo
	unset wait
	echo -e "\e[32m";read -p "Press enter to contunue install" wait;echo -e "\e[0m"
	echo -e "\e[32mPHP Install\e[0m: Installing PHP5.3 or greater..."
	while true;
	do echo -n .;sleep 1;done &
	yum install -y php php-mysql php-common php-gd php-mbstring php-mcrypt php-devel php-xml php-cli > /dev/null 2>&1
	kill $!; trap 'kill $!' SIGTERM
	echo -e "\n\e[32mPHP Install\e[0m: PHP Installation Complete\n"
	echo -e "Running OS Check and Dependacies check again, please wait...\n"
	sleep 3
	$0
	exit 0
}

function checkIPtables()
{
	if [[ -z $(iptables -L|grep "dpt:http\|dpt:https") ]]; then
		echo -e "\n\e[32mIptables\e[0m: Enabling port 80 and 443 on iptables\n"
		iptables -I INPUT -p tcp -m tcp --dport 80 -j ACCEPT
		iptables -I INPUT -p tcp -m tcp --dport 443 -j ACCEPT
		service iptables save
		echo
	fi
}

function localhostChk()
{
	servername=$(grep "ServerName" /etc/httpd/conf/httpd.conf|grep -v "#"|awk {'print $2'})
	if [[ "$servername" != "localhost" ]]; then
		if [[ "$servername" = "" ]]; then
                        echo "ServerName localhost" >> /etc/httpd/conf/httpd.conf
		else
			unset yn
			echo -e "\e[32mServerName\e[0m: Current Apache ServerName = $servername\n"
                	read -p "Is the ServerName correct? [Enter 'yes' to skip and 'no' to enter localhost as ServerName] (y/n): " yn
                	while [[ "$yn" != "yes" && "$yn" != "no" && "$yn" != "y" && "$yn" != "n" ]]; do
				read -p "Is this ServerName correct? [Enter 'yes' to skip and 'no' to enter localhost as ServerName] (y/n): " yn
                        	echo
                	done
                	if [[ "$yn" = "yes" ]] || [[ "$yn" = "y" ]]; then
                        	echo -e "\n\e[32mServerName\e[0m: Skipping"
                	else
				echo
				sed -i 's/'$servername'/localhost/g' /etc/httpd/conf/httpd.conf
                	fi
		fi
	fi
}

function mysqlPasswd()
{
	echo -e "Create a new password for the root MySQL account\n"
	unset mysql_passwd
        read -s -p "Enter MySQL $db_user_id password: " mysql_passwd
        while [[ "$mysql_passwd" = "" ]]; do
        	echo -e "\n\e[36mNotice\e[0m: Please provide the MySQL $db_user_id password, please try again.\n"
                read -p "Enter MySQL $db_user_id password: " mysql_passwd
        done
	echo
        unset mysql_passwd_again
        read -s -p "Enter MySQL $db_user_id password again: " mysql_passwd_again
        	while [[ "$mysql_passwd_again" = "" ]]; do
                echo -e "\n\e[36mNotice\e[0m: Please provide the MySQL $db_user_id password again, please try again.\n"
                read -p "Enter MySQL $db_user_id password again: " mysql_passwd_again
		export mysql_passwd_again
		echo
	done
}

function mysqlRootPwd()
{
	if [[ $(mysqladmin -s status) != "" ]]; then
		if [[ "$mysql_passwd_again" = "" ]] && [[ "$mysqld_exists" != "" ]]; then
			echo -e "\e[32mMySQL\e[0m: Your root password is blank, this will cause an issue during setup.\n"
			export db_user_id="root"
			mysqlPasswd
			mysqladmins password "$mysql_passwd_again"
		else
			mysqladmins password "$mysql_passwd_again"
		fi
	else
		echo -e "\e[32mMySQL\e[0m: Root password already setup, skipping.\n" 
	fi
}

function dbAskHost()
{
	echo -e "\e[4m\e[32mDatabase Setup\e[0m\n\nThis step will create the user, set the password and create the database.\n"
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
	echo -e "\n\nEnter the database username and password you want to use for the application.\n"
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
	db_exists=$(mysql --batch -u root -p$db_root_pass --skip-column-names -e "show databases like '"$db_name"';" | grep "$db_name" > /dev/null; echo "$?")
	if [ $db_exists -eq 0 ];then
		dbExists=yes
	else
		dbExists=no
	fi
}

function dbConnTest()
{
        # check connection to db
        db_connx=$(mysql --batch -u $db_user -p"$db_pass" -e ";" > /dev/null; echo "$?")
	if [ $db_connx -eq 0 ];then
                dbConnx=yes
        else
                dbConnx=no
        fi
}

function dbRootPasswd()
{
        unset db_root_pass
	echo
        read -s -p "Enter the MySQL root password: " db_root_pass
        while [[ "$db_root_pass" = "" ]]; do
                echo -e "\n\e[36mNotice\e[0m: Please provide the root password, please try again.\n"
                read -s -p "Enter the MySQL root password: " db_root_pass
		echo
        done
	db_root_connx=$(mysql --batch -u root -p"$db_root_pass" -e ";" > /dev/null; echo "$?"; echo)
        while [[ "$db_root_connx" -eq 1 ]]; do
                echo -e "\n\e[31mNotice\e[0m: Unable to connect to mysql, please try again." 
		echo -e "\n\e[36mNotice\e[0m: You may run /usr/bin/mysql_secure_installation to secure the MySQL installation and set the root password.\n"
		unset yn
		read -p "Do you want to exit the script or try again? [yes to exit, no to try again] (y/n): " yn
		while [[ "$yn" != "yes" && "$yn" != "no" && "$yn" != "y" && "$yn" != "n" ]]; do
			read -p "Do you want to exit the script or try again? [yes to exit, no to try again] (y/n): " yn
			echo
		done
		if [[ "$yn" = "yes" ]] || [[ "$yn" = "y" ]]; then
			echo -e "\n\e[32mExiting Installation as per your response.\n\e[0m"
			sleep 2
			exit 0
		else
			echo
			read -s -p "Enter the MySQL root password: " db_root_pass
			db_root_connx=$(mysql --batch -u root -p"$db_root_pass" -e ";" > /dev/null; echo "$?"; echo)
		fi
        done
}

function dbUserDBCreate()
{
	while read user; do
	if [[ "$db_user" == "$user" ]]; then
		echo -e "\n\e[31mNotice\e[0m: $user already exists. Skipping."
		break
	fi
	done < <(mysql --batch --skip-column-names -p"$db_root_pass" -e 'use mysql; SELECT `user` FROM `user`;')

	if [[ "$db_user" != "$user" ]]; then
		echo -e "\n\e[32mNotice\e[0m: Creating \e[32m$db_user\e[0m and granting all privileges on \e[36m$db_name\e[0m"
		mysql -u root -h $db_host -p"$db_root_pass" -e "CREATE USER '$db_user'@'$db_host' IDENTIFIED BY '$db_pass';"
        	mysql -u root -h $db_host -p"$db_root_pass" -e "GRANT ALL PRIVILEGES ON $db_name.* TO '$db_user'@'$db_host';"
        	mysql -u root -h $db_host -p"$db_root_pass" -e "FLUSH PRIVILEGES;"
	fi
	unset user
}

function dbCreate()
{
	mysql -u $db_user -h $db_host -p"$db_pass" -e "create database $db_name;"
        mysql -u $db_user -h $db_host -p"$db_pass" -D $db_name < database/db_create.sql
}
function dbUpdate()
{
        mysql -u $db_user -h $db_host -p"$db_pass" -D $db_name < database/db_update.sql
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
	# convert company name for database
	comp_id=$(echo $your_company|awk '{print tolower($0)}'|sed 's/[^a-zA-Z 0-9]//g'|sed -e 's/ /-/g')

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

# check if admin user exist
unset adm_check
adm_check=$(mysql -u $db_user -h $db_host -p"$db_pass" -e "SELECT user_id from $db_name.users where user_id='$new_web_admin';")

# if not exist, add admin user
if [[ "$adm_check" = "" ]]; then
# add passwd hash
adm_passwd=$(echo $new_web_admin_passwd | sha256sum | awk {'print $1'})
# add admin user
mysql -u $db_user -h $db_host -p"$db_pass" << EOF
INSERT INTO $db_name.users (id,user_id,email,admin,display_name,password) VALUES (NULL, '$new_web_admin', '$new_web_admin_email', '1', NULL, '$adm_passwd');
EOF
else
	echo -e "\e[32mNotice\e[0m: Web Admin User exists: \e[36m$new_web_admin\n\e[0m"
fi

# check if basic user exists
unset usr_check
usr_check=$(mysql -u $db_user -h $db_host -p"$db_pass" -e "SELECT user_id from $db_name.users where user_id='$new_web_duser';")

# if not exist, add basic user
if [[ "$usr_check" = "" ]]; then
# add passwd hash
usr_passwd=$(echo $new_web_duser_passwd | sha256sum | awk {'print $1'})
# add basic user
mysql -u $db_user -h $db_host -p"$db_pass" << EOF
INSERT INTO $db_name.users (id,user_id,email,admin,display_name,password) VALUES (NULL, '$new_web_duser', '$new_web_duser_email', '0', NULL, '$usr_passwd');
EOF
else
        echo -e "\e[32mNotice\e[0m: Web Basic User exists: \e[36m$new_web_duser\n\e[0m"
fi

}

function dbCompCreate()
{

# generate key and store var to installation_key
genInstallKey

#trim end hiphen from installation_key for echo statements
export install_key=$(echo $installation_key|awk {'print $1'})

# check if company exist
unset comp_name_check
comp_name_check=$(mysql -u $db_user -h $db_host -p"$db_pass" -e "SELECT name from $db_name.company where name='$comp_id';")
unset comp_disp_check
comp_disp_check=$(mysql -u $db_user -h $db_host -p"$db_pass" -e "SELECT name from $db_name.company where display_name='$your_company';")
# check if installation key exist
unset comp_ikey_check
comp_ikey_check=$(mysql -u $db_user -h $db_host -p"$db_pass" -e "SELECT install_key from $db_name.company where install_key='$installation_key';")

# if not exist, add company and installation key
if [[ "$comp_name_check" = "" ]] && [[ "$comp_disp_check" = "" ]] && [[ "$comp_ikey_check" = "" ]]; then
# add company and installation key
echo -e "\e[32mNotice\e[0m: Company added to \e[36m$db_name\e[0m: \e[36m$your_company\e[0m/\e[36m$comp_id\n\e[0m"
echo -e "\e[32mNotice\e[0m: Installation Key added to \e[36m$db_name\e[0m: $install_key\n"
mysql -u $db_user -h $db_host -p"$db_pass" << EOF
INSERT INTO $db_name.company (id,name,display_name,install_key) VALUES (NULL, '$comp_id', '$your_company', '$installation_key');
EOF
else
	unset comp_ikey
	comp_ikey=$(mysql --skip-column-names -u $db_user -h $db_host -p"$db_pass" -e "SELECT install_key from $db_name.company;")
        echo -e "\e[32mNotice\e[0m: Company Name already exists: \e[36m$your_company\e[0m or \e[36m$comp_id\e[0m with installation key: $comp_ikey\n"
fi

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
sed -i 's/000DEFAULT000/'$install_key'/g' /opt/patch_manager/patch_checker.sh
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
rewrite_check=`curl -s localhost${relative_path}rewrite_check|grep 404|wc -l`
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

function AddCrontab()
{
	if [[ ! -f /etc/cron.d/patch-manager ]]; then
		echo -e "\e[36m# Adding Crontab entries to /etc/cron.d/patch-manager\e[0m\n"
		echo "0 */2 * * * /opt/patch_manager/start_get_package_list.sh > /dev/null 2>&1" > /etc/cron.d/patch-manager
		echo "1 */2 * * * /opt/patch_manager/start_patch_check.sh > /dev/null 2>&1" >> /etc/cron.d/patch-manager
		echo -e "\e[32mNotice\e[0m: Added Crontab entries for 2 hour runs\e[0m\n"
	fi
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
dbRootPasswd
dbAskUser
dbAskPass
dbAskName
dbUserDBCreate
dbConnTest
dbCheck

# check database connection from user provided details
if [[ "$dbConnx" = "no" ]]; then
        echo -e "\n\e[31mError\e[0m: Unable to connect to: \e[36m$db_host\e[0m, please try again.\n"
	dbAskHost
	dbRootPasswd
	dbAskUser
	dbAskPass
	dbAskName
	dbUserDBCreate
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
# Create Company entries in database
dbCompCreate
# Add crontab entry for every 2 hours
AddCrontab
# Finalize the install
echo -e "\e[36m# Installing Apache related configurations\e[0m\n"
InstallApp
