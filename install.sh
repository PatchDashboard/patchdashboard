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
## Version: 1.0 RC1
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
##            0.8 - Added more logic for handling CentOS version installs
##            0.9 - Added a staged path and fixed how its copied to the web_dir
##            1.0 - Fixed password generation issues, default passwords now work
##                - This is a release ready version :)
##
##            RC1 - Updated the installer to handle new changes for RC1
##.           1.1 - Added EnableSSL function to setup SSL and certificates
##
#################################################################################

install_opts="db_host db_root_id db_root_pass db_user db_pass db_name new_web_admin new_web_admin_email new_web_admin_passwd new_web_duser new_web_duser_email new_web_duser_passwd your_company installation_key relative_path new_web_dir web_user" 
upgrade_opts="web_dir relative_path";

function show_help {
	cat <<EOF
Installer for PatchDashboard.


Options:

-ui|--unattended-install        run installation without user interaction
                                required options are:
EOF
	for var in $install_opts; do
		echo "    --$(echo $var | sed 's,_,-,g')" 
	done
	cat <<EOF

-uu|--unattended-upgrade        run upgrade without user interaction
                                required options are:
EOF
	for var in $upgrade_opts; do
		echo "    --$(echo $var | sed 's,_,-,g')" 
	done
	cat <<EOF

-g|--guided                     Interactive mode. 
                                Beware: interactive mode makes changes to your system, like installing packages and changing iptables. This is fine on a clean system, but on a multi-purpose system you might want to go the --unattended-install route.
EOF
}

# parse command line parameters

GUIDED=""
UNATTENDED=""
FORCE="no"

while [[ $# -gt 0 ]]; do
	key="$1"
	shift

	case $key in
		-ui|--unattended-install)
			UNATTENDED="YES"
			ACTION="INSTALL"
			;;

		-uu|--unattended-upgrade)
			UNATTENDED="YES"
			ACTION="UPGRADE"
			;;

			--force)
			FORCE="yes"
			;;

		-g|--guided)
			GUIDED="YES"
			;;

		-h)
			show_help
			exit
			;;

		*)
			found=""
			for var in $install_opts $upgrade_opts; do
				if [[ "--$(echo $var | sed 's,_,-,g')" == "$key" ]]; then
					eval $var=\"$1\"
					shift
					found=1
					break
				fi
			done
			if [ -z "$found" ]; then
				echo -e "Unknown option $key.\n"
				show_help
				exit 1
			fi
			;;
	esac
done

# get hostname into var
export host_node=`hostname`

# generate random passwords
SERVER_IP=$(ip addr|grep inet|grep eth0|awk '{print $2}'|cut -d'/' -f1)
MY_PATH="`dirname \"$0\"`"
hash_pass_script="$MY_PATH/hash_pass.php"
password_salt=$(dd if=/dev/urandom bs=1 count=32 2>/dev/null | base64 -w 0 | rev | cut -b 2- | rev)

function hash_password()
{
	export password="$1"
	$hash_pass_script "$password" "$password_salt"
}

function genPasswd()
{
	local p=$1
	[ "$p" == "" ] && p=12
	export randomPass=$(tr -dc A-Za-z0-9_ < /dev/urandom | head -c ${p} | xargs)
	echo $randomPass
}

function genInstallKey()
{
	export installation_key=$(< /dev/urandom tr -dc 'a-zA-Z0-9~!@#$%^&*_-' | head -c${1:-32}|sha256sum)
}


# get user running installer
user=`whoami`


# if user is not root, exit
if [ "$user" != "root" ]; then
	echo -e "\e[31Error\e[0m: You must be root to install this!"
	exit 0
fi


# get OS information and run applicable function
if [[ -f /etc/lsb-release && -f /etc/debian_version ]]; then
        export os=$(lsb_release -s -d|head -1|awk {'print $1'})
        export os_ver=$(lsb_release -s -d|head -1|awk {'print $2'}|cut -d "." -f 1)
elif [[ -f /etc/debian_version ]]; then
        export os="$(cat /etc/issue|head -n 1|awk {'print $1'})"
        export os_ver="$(cat /etc/debian_version|head -1|awk {'print $1'}|cut -d "." -f 1)"
elif [[ -f /etc/redhat-release ]]; then
	if [[ "$os" = "Red" && $(grep -i enterprise /etc/redhat-release) != "" ]]; then
		export os="Red Hat Enterprise"
		export os_ver=$(cat /etc/redhat-release|head -1|awk {'print $7'}|cut -d "." -f 1)
	elif [[ "$os" = "Red" ]]; then
		export os="Red Hat"
		export os_ver=$(cat /etc/redhat-release|head -1|awk {'print $6'}|cut -d "." -f 1)
	else
		export os=$(cat /etc/redhat-release|head -1|awk {'print $1'})
		export os_ver=$(cat /etc/redhat-release|head -1|awk {'print $3'}|cut -d "." -f 1)
	fi
else
        export os=$(uname -s -r|head -1|awk {'print $1'})
        export os_ver=$(uname -s -r|head -1|awk {'print $2'}|cut -d "." -f 1)
fi

# get DocumentRoot for error checking (not checked on all distros yet)
if [[ "$os" = "Ubuntu" ]] || [[ "$os" = "Debian" ]] || [[ "$os" = "Linux" ]]; then
        export doc_root=$(grep -s DocumentRoot /etc/apache2/sites-enabled/*|head -n 1|awk {'print $3'})
elif [[ "$os" = "CentOS" ]] || [[ "$os" = "Fedora" ]] || [[ "$os" = "Red Hat" ]] || [[ "$os" = "Red Hat Enterprise" ]]; then
        export doc_root=$(grep -s DocumentRoot /etc/httpd/conf/*|grep -v "#"|head -n 1|awk -F\" {'print $2'})
fi

## begin main functions of installer
function OSInstall()
{
	echo -e "Running install for: \e[32m$os $os_ver\e[0m\n"

	if [[ "$os" = "Ubuntu" ]] || [[ "$os" = "Debian" ]] || [[ "$os" = "Linux" ]]; then
		apache_exists=$(which apache2)
		php5_exists=$(which php)
		mysqld_exists=$(which mysqld)
		rsync_exists=$(which rsync)
		if [[ "$os" = "Linux" ]] && [[ "$apache_exists" = "" ]]; then
			echo -e "\n\e[31mNotice\e[0m: Please install the full LAMP stack before trying to install this application.\n\n\e[31mNotice\e[0m: https://community.rackspace.com/products/f/25/t/49\n"
                        exit 0
		fi
		if [[ "$rsync_exists" = "" ]]; then
 			echo -e "\e[31mNotice\e[0m: Rsync does not seem to be installed."
 			unset wait
 			echo -e "\e[32m";read -p "Press enter to continue install" wait;echo -e "\e[0m"
 			echo -e "\e[31mNotice\e[0m: Please wait while prerequisites are installed...\n\n\e[31mNotice\e[0m: Installing Rsync..."
 			while true;
 			do echo -n .;sleep 1;done &
 			apt-get install -y rsync >/dev/null 2>&1
			kill $!; trap 'kill $!' SIGTERM;
			echo -e "\n\n\e[32mNotice\e[0m: Rsync Installation Complete\n"
 		fi
		if [[ "$apache_exists" = "" ]]; then
			echo -e "\e[31mNotice\e[0m: Apache/PHP does not seem to be installed."
			unset wait
			echo -e "\e[32m";read -p "Press enter to continue install" wait;echo -e "\e[0m"
			echo -e "\e[31mNotice\e[0m: Please wait while prerequisites are installed...\n\n\e[31mNotice\e[0m: Installing Apache and PHP5..."
			while true;
			do echo -n .;sleep 1;done &
			apt-get install -y apache2 apache2-threaded-dev apache2-utils curl > /dev/null 2>&1
			kill $!; trap 'kill $!' SIGTERM;
			echo "ServerName localhost" >> /etc/apache2/httpd.conf
			echo -e "\n\e[32mNotice\e[0m: Apache/PHP Installation Complete\n"
		fi
		if [[ "$php5_exists" = "" ]]; then
                        echo -e "\e[31mNotice\e[0m: PHP does not seem to be installed."
                        unset wait
                        echo -e "\e[32m";read -p "Press enter to continue install" wait;echo -e "\e[0m"
                        echo -e "\e[31mNotice\e[0m: Installing PHP5..."
                        while true;
                        do echo -n .;sleep 1;done &
                        apt-get install -y php5 libapache2-mod-php5 php5-mcrypt php5-common php5-gd php5-cgi php5-cli php5-fpm php5-dev php5-xmlrpc php5-mysql php5-sybase > /dev/null 2>&1
                        kill $!; trap 'kill $!' SIGTERM;
                        echo -e "\n\n\e[32mNotice\e[0m: PHP Installation Complete\n"
                fi
		if [[ "$mysqld_exists" = "" ]]; then
			echo -e "\e[31mNotice\e[0m: MySQL does not seem to be installed."
                        unset wait
			echo -e "\e[32m";read -p "Press enter to continue install" wait;echo -e "\e[0m"
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
			apt-get install -y mysql-client mysql-server php5-mysql php5-sybase libapache2-mod-auth-mysql libmysqlclient-dev > /dev/null 2>&1
			kill $!; trap 'kill $!' SIGTERM;
			mysql_install_db > /dev/null 2>&1
			echo -e "\nInstalling MySQL system tables...\nOK"
			echo -e "Filling help tables...\nOK"
			echo -e "\n\e[36mNotice\e[0m: You may run /usr/bin/mysql_secure_installation to secure the MySQL installation once this application setup has been completed."
			echo -e "\n\e[32mNotice\e[0m: MySQL Installation Complete\n"
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
		PackageCheck
		checkIPtables

	elif [[ "$os" = "CentOS" ]] || [[ "$os" = "Fedora" ]] || [[ "$os" = "Red Hat" ]] || [[ "$os" = "Red Hat Enterprise" ]]; then
		httpd_exists=$(rpm -qa | grep "httpd")
		php_exists=$(rpm -qa | grep "php")
		mysqld_exists=$(rpm -qa | grep "mysql-server")
		rsync_exists=$(rpm -qa | grep "rsync")
 		if [[ "$rsync_exists" = "" ]]; then
 			echo -e "\e[31mNotice\e[0m: Rsync does not seem to be installed."
 			unset wait
 			echo -e "\e[32m";read -p "Press enter to continue install" wait;echo -e "\e[0m"
 			echo -e "\e[31mNotice\e[0m: Please wait while prerequisites are installed...\n\n\e[31mNotice\e[0m: Installing Rsync..."
 			while true;
 			do echo -n .;sleep 1;done &
 			yum install -y rsync >/dev/null 2>&1
			kill $!; trap 'kill $!' SIGTERM;
			echo -e "\n\n\e[32mNotice\e[0m: Rsync Installation Complete\n"
 		fi
		if [[ "$httpd_exists" = "" ]]; then
			echo -e "\e[31mNotice\e[0m: Apache does not seem to be installed."
                        unset wait
                        echo -e "\e[32m";read -p "Press enter to continue install" wait;echo -e "\e[0m"
                        echo -e "\e[31mNotice\e[0m: Please wait while prerequisites are installed...\n\n\e[31mNotice\e[0m: Installing Apache..."
			if [[ "$os_ver" = "5" ]]; then
				while true;
                        	do echo -n .;sleep 1;done &
				yum install --disablerepo=webtatic -y httpd httpd-devel httpd-tools curl > /dev/null 2>&1
                        	kill $!; trap 'kill $!' SIGTERM;
			else
				while true;
                                do echo -n .;sleep 1;done &
                                yum install -y httpd httpd-devel httpd-tools curl > /dev/null 2>&1
                                kill $!; trap 'kill $!' SIGTERM;
			fi
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
                        echo -e "\e[32m";read -p "Press enter to continue install" wait;echo -e "\e[0m"
                        echo -e "\e[31mNotice\e[0m: Installing PHP5..."
                        while true;
                        do echo -n .;sleep 1;done &
                        yum install -y php php-mysql php-common php-gd php-mbstring php-mcrypt php-devel php-xml php-cli php-pdo php-mssql > /dev/null 2>&1
                        kill $!; trap 'kill $!' SIGTERM;
                        echo -e "\n\n\e[32mNotice\e[0m: PHP Installation Complete\n"
                fi
		if [[ "$mysqld_exists" = "" ]]; then
                        echo -e "\e[31mNotice\e[0m: MySQL does not seem to be installed."
                        unset wait
                        echo -e "\e[32m";read -p "Press enter to continue install" wait;echo -e "\e[0m"
                        mysqlPasswd
			echo -e "\n\n\e[32m\e[4mMySQL Database Install and Setup\n\e[0m"
                        if [[ "$mysql_passwd" != "$mysql_passwd_again" ]]; then
                                echo -e "\e[31mNotice\e[0m: Passwords do not match, please try again.\n"
                                mysqlPasswd
                        fi
                        echo -e "\e[31mNotice\e[0m: Installing MySQL Client and Server..."
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
		PackageCheck
		checkIPtables
		localhostChk
	fi
}
function PackageCheck()
{
	echo -e "\e[32mChecking for dependencies and missing packages\n\e[0m"
        if [[ "$os" = "Ubuntu" ]] || [[ "$os" = "Debian" ]] || [[ "$os" = "Linux" ]]; then
	pkgList="apache2 apache2-threaded-dev apache2-utils php5 libapache2-mod-php5 php5-mcrypt php5-common php5-gd php5-cgi php5-cli php5-fpm php5-dev php5-xmlrpc mysql-client mysql-server php5-mysql php5-sybase libapache2-mod-auth-mysql libmysqlclient-dev curl rsync"
	echo -e "\e[31mWARNING\e[0m: Please keep in mind this is not a fool proof process, if you have 3rd party repo's, the automated package installer may fail.\n"
	for package in $pkgList; do
                dpkg-query -l $package > /dev/null 2>&1
                if [[ "$?" = "1" ]]; then
                        echo -e "\e[32mPackage\e[0m: \e[36m$package\e[0m not installed, installing missing package"
                        while true;
                        do echo -n .;sleep 1;done &
                        apt-get install -y $package > /dev/null 2>&1
                        kill $!; trap 'kill $!' SIGTERM;
                        echo -e "\n\e[32mPackage\e[0m: \e[36m$package\e[0m install complete\n"
                fi
        done
	elif [[ "$os" = "CentOS" ]] || [[ "$os" = "Fedora" ]] || [[ "$os" = "Red Hat" ]] || [[ "$os" = "Red Hat Enterprise" ]]; then
		ls /etc/yum/pluginconf.d/fastestmirror.conf > /dev/null 2>&1
		if [[ "$?" = 0 ]]; then
			if [[ $(grep "exclude=.at" /etc/yum/pluginconf.d/fastestmirror.conf) = "" ]]; then 
				echo "exclude=.at" >> /etc/yum/pluginconf.d/fastestmirror.conf
			fi
		fi
	# check for extra repos
	extraRepo=$(ls /etc/yum.repos.d/|grep 'remi\|webtatic')
	if [[ "$?" = 0 ]]; then
		if [[ $(yum list installed|grep -i "56") != "" ]]; then
			pVer="56"
		elif [[ $(yum list installed|grep -i "55") != "" ]]; then
			pVer="55"
		elif [[ $(yum list installed|grep -i "54") != "" ]]; then
			pVer="54"
		else
			pVer=""
		fi
		pkgList="php php${pVer}-php-mysqlnd php${pVer}-php-common php${pVer}-php-gd php${pVer}-php-mbstring php${pVer}-php-mcrypt php${pVer}-php-devel php${pVer}-php-xml php${pVer}-php-cli php${pVer}-php-pdo php${pVer}-php-mssql mysql mysql-server mysql-devel httpd httpd-devel httpd-tools curl rsync"
	else

		pkgList="php php-mysql php-common php-gd php-mbstring php-mcrypt php-devel php-xml php-cli php-pdo php-mssql mysql mysql-server mysql-devel httpd httpd-devel httpd-tools curl rsync"
	fi
	for package in $pkgList; do
		if [[ $(yum list installed|grep "$package[.]") = "" ]]; then
			repoName=$(echo $extraRepo|cut -d'.' -f 1|sed -e 's/ /,/g')
			echo -e "\e[32mPackage\e[0m: \e[36m$package\e[0m not installed, installing missing package"
			echo -e "\e[31mWARNING\e[0m: Detected 3rd party repos> $repoName - Please keep in mind this is not a fool proof process... but we try :)\n"
                	if [[ $? = 0 ]]; then
                        	echo -e "\n\e[32m3rd Party Repos Exist\e[0m: $repoName, attempting to enable the specific repos before installing dependancies.\n"
				while true;
	                        do echo -n .;sleep 1;done &
				yum install -y --enablerepo=$repoName $package > /dev/null 2>&1
	                        kill $!; trap 'kill $!' SIGTERM;
        	                echo -e "\n\e[32mPackage\e[0m: \e[36m$package\e[0m install complete\n"
			else
				while true;
		        	do echo -n .;sleep 1;done &
				yum install -y --skip-broken $package > /dev/null 2>&1
				kill $!; trap 'kill $!' SIGTERM;
				echo -e "\n\e[32mPackage\e[0m: \e[36m$package\e[0m install complete\n"
			fi
		fi
	done
	fi
}

function EnableSSL()
{

	echo -e "\e[32mEnableSSL\e[0m: Preparing to setup SSL for the Patch Management Dashboard web interface."
        echo -e "\e[32m";read -p "Press enter to continue install" wait;echo -e "\e[0m"

	if [[ "$os" = "Ubuntu" ]] || [[ "$os" = "Debian" ]] || [[ "$os" = "Linux" ]]; then
		# set ssl key path
		ssl_path="/etc/ssl"
		# install SSL
		a2enmod ssl 
		a2ensite default-ssl
		echo

	elif [[ "$os" = "CentOS" ]] || [[ "$os" = "Fedora" ]] || [[ "$os" = "Red Hat" ]] || [[ "$os" = "Red Hat Enterprise" ]]; then
		# set ssl key path
		ssl_path="/etc/pki/tls"
		# install SSL
		yum install mod_ssl openssl -y > /dev/null 2>&1
	fi

	# check existing keys, if not setup new
	if [[ -f $ssl_path/private/ca.key ]]; then
		echo -e "\e[31mSSL\e[0m: Keys already exist in $ssl_path/certs and $ssl_path/private.\n"
		unset yn
		read -p "Do you want to create a new keys? (yes/no): " yn
        	while [[ $yn = "" ]]; do
                	read -p "Do you want to create a new keys? (yes/no): " yn
        	done
		echo
		if [[ "$yn" = "yes" || "$yn" = "y" ]]; then
			# ask questions for key generation
        		echo -e "\e[32mSSL\e[0m: Generating SSL Keys for $web_service"
		        echo -e "\e[32mSSL\e[0m: please answer the following questions for the the location of the hosted environment.\n"
		        read -p "Country: " country
		        while [[ $country = "" ]]; do
		                read -p "Country: " country
		        done
		        read -p "State: " state
		        while [[ $state = "" ]]; do
		                read -p "State: " state
		        done
		        read -p "City: " city
		        while [[ $city = "" ]]; do
		                read -p "City: " city
		        done
		        read -p "Orginization: " org
		        while [[ $org = "" ]]; do
		                read -p "Orginization: " org
		        done
		        read -p "Orginizational Unit: " orgu
		        while [[ $orgu = "" ]]; do
		                read -p "Orginizational Unit: " orgu
		        done
			# generate private key 
			rm -rf $ssl_path/private/ca.key
			openssl genrsa -out $ssl_path/private/ca.key 4096 > /dev/null 2>&1
			# generate CSR
			rm -rf $ssl_path/private/ca.csr
			openssl req -new -subj "/C=$country/ST=$state/L=$city/O=$org/OU=$orgu/CN=$host_node" -key $ssl_path/private/ca.key -out $ssl_path/private/ca.csr
			# generate Self Signed Key
			rm -rf $ssl_path/private/ca.crt
			openssl x509 -req -days 1095 -in $ssl_path/private/ca.csr -signkey $ssl_path/private/ca.key -out $ssl_path/certs/ca.crt
		fi
	else
		# ask questions for key generation
                echo -e "\e[32mSSL\e[0m: Generating SSL Keys for $web_service"
                echo -e "\e[32mSSL\e[0m: please answer the following questions for the the location of the hosted environment.\n"
                read -p "Country: " country
                while [[ $country = "" ]]; do
	                read -p "Country: " country
                done
        	read -p "State: " state
                while [[ $state = "" ]]; do
                	read -p "State: " state
                done
                read -p "City: " city
                while [[ $city = "" ]]; do
                	read -p "City: " city
                done
                read -p "Orginization: " org
                while [[ $org = "" ]]; do
                	read -p "Orginization: " org
                done
                read -p "Orginizational Unit: " orgu
                while [[ $orgu = "" ]]; do
                	read -p "Orginizational Unit: " orgu
                done
                # generate private key 
                rm -rf $ssl_path/private/ca.key
                openssl genrsa -out $ssl_path/private/ca.key 4096 > /dev/null 2>&1
                # generate CSR
                rm -rf $ssl_path/private/ca.csr
                openssl req -new -subj "/C=$country/ST=$state/L=$city/O=$org/OU=$orgu/CN=$host_node" -key $ssl_path/private/ca.key -out $ssl_path/private/ca.csr
                # generate Self Signed Key
                rm -rf $ssl_path/private/ca.crt
                openssl x509 -req -days 1095 -in $ssl_path/private/ca.csr -signkey $ssl_path/private/ca.key -out $ssl_path/certs/ca.crt
		echo
	fi

        echo -e "\e[32m\e[4mWebpage Location Setup\e[0m\n"
        unset new_web_dir
        read -p "Please enter location for web interface [Default: $web_dir]: " new_web_dir
        while [[ "$new_web_dir" = "" ]]; do
                echo -e "\e[32mNotice\e[0m: Default Location Used: $web_dir"
                new_web_dir=$web_dir
        done
	echo $new_web_dir|grep --word-regexp "${doc_root%%/html}" > /dev/null 2>&1
        if [[ "$?" = 1 ]]; then
                echo -e "\n\e[31mNotice\e[0m: $new_web_dir is not within the DocumentRoot: $doc_root\n\e[31mNotice\e[0m: Please try again.\n"
                WebUIInfo
        fi
        echo
        unset new_relative_path
        read -p "Please enter the relative path [Default: $relative_path]: " new_relative_path
        while [[ "$new_relative_path" = "" ]]; do
                echo -e "\e[32mNotice\e[0m: Default Location Used: $relative_path"
                new_relative_path=$relative_path
                relpath=$(echo $new_relative_path|cut -d '/' -f 2)
        done
        echo
        if [ "$new_relative_path" != "$relative_path" ] && [ "$new_relative_path" != "" ]; then
                relative_path="$new_relative_path"
        fi
        if [ "${new_relative_path: -1}" != "/" ]; then
                new_relative_path=$new_relative_path"/"
        fi
        if [ "$new_web_dir" != "$web_dir" ] && [ "$new_web_dir" != "" ]; then
                web_dir="$new_web_dir"
        fi
        if [ "${web_dir: -1}" != "/" ]; then
                web_dir=$web_dir"/"
        fi
	if [[ $(grep "$ssl_path/certs/ca.crt" /etc/$web_service/conf.d/patch_manager.conf) = "" ]]; then
		targetdir=$(echo $new_web_dir|sed 's=/[^/]*$==;s/\.$//')
		echo -e "\e[32mSSL\e[0m: Adding SSL configuration to /etc/$web_service/conf.d/patch_manager.conf\n"

cat <<EOA >> /etc/$web_service/conf.d/patch_manager.conf
	
NameVirtualHost *:443
<VirtualHost *:443>
        SSLEngine on
        SSLCertificateFile $ssl_path/certs/ca.crt
        SSLCertificateKeyFile $ssl_path/private/ca.key
        <Directory $targetdir>
	   AllowOverride All
        </Directory>
        DocumentRoot $targetdir
        ServerName localhost
</VirtualHost>
EOA
	else
		echo -e "\e[31mSSL\e[0m: SSL configuration already exists in /etc/$web_service/conf.d/patch_manager.conf\n"
	fi
# process web service restart
service $web_service restart
rewrite_check=`curl -s localhost${relative_path}rewrite_check|grep 404|wc -l`
if [ "$rewrite_check" = "1" ]; then
        echo -e "\n\e[31mError\e[0m: Apache Mod_rewrite or .htaccess is not working.  Please ensure you have mod_rewrite installed and enabled.  If it is, please make sure you change 'AllowOverride None' to 'AllowOverride All'"
        echo -e "\e[31mError\e[0m: If you don't, this site won't work. \e[31mYou've been warned\e[0m."
fi
echo -e "\n\e[32mNotice\e[0m: SSL installation is now complete. You can now go to https://${host_node}${relative_path}\n"
exit 0

}

function phpversion()
{ 
	echo "$@" | awk -F. '{ printf("%d.%d.%d\n", $1,$2,$3); }';
}

function phpverCheck()
{
	phpver=$(php --version|grep "PHP 5"|awk {'print $2'})

	if [[ $(phpversion $phpver) < $(phpversion 5.2.0) ]]; then
		echo -e "\e[0mYou are running PHP Version: \e[031m$phpver\e[0m which is incompatible with this application.\n"
		phpExtraInst
	fi
}

function phpExtraInst()
{
	#export CentOSVer="5"
	echo -e "\e[32mPHP Install\e[0m: Installing PHP 5.3/5.4 depending on your distro\n"
	echo -e "\e[32mPHP Install\e[0m: Adding EPEL and WebTatic Repos"
	rpm -Uvh "http://dl.fedoraproject.org/pub/epel/5/x86_64/epel-release-5-4.noarch.rpm" > /dev/null 2>&1
	rpm -Uvh "http://repo.webtatic.com/yum/centos/5/latest.rpm" > /dev/null 2>&1
	sed -i 's/enabled=0/enabled=1/g' /etc/yum.repos.d/webtatic.repo
	unset wait
	echo -e "\e[32m";read -p "Press enter to continue install" wait;echo -e "\e[0m"
	echo -e "\e[32mPHP Install\e[0m: Installing PHP5.3 or greater..."
	while true;
	do echo -n .;sleep 1;done &
	yum install -y php php-mysql php-common php-gd php-mbstring php-mcrypt php-devel php-xml php-cli php-pdo php-php-gettext php-tidy > /dev/null 2>&1
	kill $!; trap 'kill $!' SIGTERM
	echo -e "\n\e[32mPHP Install\e[0m: PHP Installation Complete\n"
	echo -e "Running OS Check and Dependacies check again, please wait...\n"
	sleep 3
	$0
	exit 0
}

function checkIPtables()
{
	if [[ $(iptables -L|grep "dpt:http\|dpt:https") = "" ]]; then
		echo -e "\e[32mIptables\e[0m: Enabling port 80 and 443 on iptables\n"
		# detect which OS
		if [[ "$os" = "Ubuntu" ]] || [[ "$os" = "Debian" ]] || [[ "$os" = "Linux" ]]; then
			if [[ $(dpkg -s iptables-persistent|grep "Status:"|cut -d " " -f2-4) != "install ok installed" ]]; then
				# install iptables-persistent
	                        echo -e "\n\e[32mIptables\e[0m: $os detected, installing iptables-persistent\n"
	                        debconf-set-selections <<< "iptables-persistent iptables-persistent/autosave_v4 boolean true"
	                        debconf-set-selections <<< "iptables-persistent iptables-persistent/autosave_v6 boolean true"
                        	apt-get install -y iptables-persistent > /dev/null 2>&1
                	fi
			# add rules and save
			iptables -I INPUT -p tcp -m tcp --dport 80 -j ACCEPT
			iptables -I INPUT -p tcp -m tcp --dport 443 -j ACCEPT
			service iptables-persistent save
			echo
		elif [[ "$os" = "CentOS" ]] || [[ "$os" = "Fedora" ]] || [[ "$os" = "Red Hat" ]] || [[ "$os" = "Red Hat Enterprise" ]]; then
			# add rules and save
			iptables -I INPUT -p tcp -m tcp --dport 80 -j ACCEPT
			iptables -I INPUT -p tcp -m tcp --dport 443 -j ACCEPT
			service iptables save
			echo
		fi
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
        read -s -p "Enter MySQL $db_root_id password: " mysql_passwd
        while [[ "$mysql_passwd" = "" ]]; do
        	echo -e "\n\e[36mNotice\e[0m: Please provide the MySQL $db_root_id password, please try again.\n"
                read -p "Enter MySQL $db_root_id password: " mysql_passwd
        done
	echo
        unset mysql_passwd_again
        read -s -p "Enter MySQL $db_root_id password again: " mysql_passwd_again
        	while [[ "$mysql_passwd_again" = "" ]]; do
                echo -e "\n\e[36mNotice\e[0m: Please provide the MySQL $db_root_id password again, please try again.\n"
                read -p "Enter MySQL $db_root_id password again: " mysql_passwd_again
		export mysql_passwd_again
	done
}

function mysqlRootPwd()
{
	if [[ $(mysqladmin -s status) != "" ]]; then
		if [[ "$mysql_passwd_again" = "" ]] && [[ "$mysqld_exists" != "" ]]; then
			echo -e "\e[32mMySQL\e[0m: Your root password is blank, this will cause an issue during setup.\n"
			mysqlPasswd
			mysqladmin password "$mysql_passwd_again"
			echo -e "\n"
		else
			mysqladmin password "$mysql_passwd_again"
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
	if [[ "$db_host" != "localhost" ]]; then
		echo -e "\n\e[31mNotice\e[0m: You have provided a host other than the localhost, please ensure you have correctly setup remote"
		echo -e "\e[31mNotice\e[0m: access for the MySQL root account or provide an elevated ID with permissions to create new accounts.\n"
		unset yn
		read -p "Are you using a MySQL account other than root? (yes/no): " yn
		while [[ "$yn" = "" ]]; do
			read -p "Are you using a MySQL account other than root? (yes/no): " yn
		done
		if [[ "$yn" = "yes" || "$yn" = "y" ]]; then
			dbAskElevatedUser
		fi
	fi
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

function dbAskElevatedUser()
{
        echo -e "\nEnter the MySQL Elevated username and password your using to create the database user with.\n"
        unset db_root_id
        read -p "Elevated Database ID: " db_root_id
        while [[ "$db_root_id" = "" ]]; do
                echo -e "\n\e[36mNotice\e[0m: Please provide a MySQL Elevated User, please try again.\n"
                read -p "Elevated Database ID: " db_root_id
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
        unset db_pass_again
        read -s -p "Database Pass again: " db_pass_again
	echo
                while [[ "$db_pass_again" = "" ]]; do
		echo -e "\n\e[36mNotice\e[0m: Please provide a Database Passwordi again, please try again.\n"
		read -s -p "Database Pass again: " db_pass_again
		echo
        done
	if [[ "$db_pass" != "$db_pass_again" ]]; then
		echo -e "\n\n\e[36mNotice\e[0m: Database User passwords do not match, please try again.\n"
		dbAskPass
	fi
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
	db_exists=$(mysql --batch -u $db_root_id -p$db_root_pass -h $db_host --skip-column-names -e "show databases like '"$db_name"';" | grep "$db_name" > /dev/null; echo "$?")
	if [ $db_exists -eq 0 ];then
		dbExists=yes
	else
		dbExists=no
	fi
}

function dbConnTest()
{
        # check connection to db
	if [[ ${#} -gt 0 && "$1" == "root" ]]; then
        db_connx=$(mysql --batch -u $db_root_id -p"$db_root_pass" -h $db_host -e ";" > /dev/null; echo "$?")
	else
        db_connx=$(mysql --batch -u $db_user -p"$db_pass" -h $db_host -e ";" > /dev/null; echo "$?")
	fi
	if [ $db_connx -eq 0 ];then
                dbConnx=yes
        else
                dbConnx=no
        fi
}

function dbRootPasswd()
{
	# set db_host if not set
	if [[ "$db_host" = "" ]]; then
		export db_host="localhost"
	fi
        unset db_root_pass
	echo
        read -s -p "Enter the MySQL $db_root_id password: " db_root_pass
        while [[ "$db_root_pass" = "" ]]; do
                echo -e "\n\e[36mNotice\e[0m: Please provide the $db_root_id password, please try again.\n"
                read -s -p "Enter the MySQL $db_root_id password: " db_root_pass
		echo
        done
	if [[ "$db_host" != "localhost" ]]; then
		echo -e "\n"
	fi
	db_root_connx=$(mysql --batch -u $db_root_id -h $db_host -p"$db_root_pass" -e ";" > /dev/null; echo "$?"; echo)
        while [[ "$db_root_connx" -eq 1 ]]; do
                echo -e "\n\e[31mNotice\e[0m: Unable to connect to mysql, please try again." 
		echo -e "\n\e[36mNotice\e[0m: You may run /usr/bin/mysql_secure_installation to secure the MySQL installation and set the $db_root_id password.\n"
		unset yn
		read -p "Do you want to try again or exit? [yes to continue, no to exit] (y/n): " yn
		while [[ "$yn" != "yes" && "$yn" != "no" && "$yn" != "y" && "$yn" != "n" ]]; do
			read -p "Do you want to try again or exit? [yes to continue, no to exit] (y/n): " yn
			echo
		done
		if [[ "$yn" = "no" ]] || [[ "$yn" = "n" ]]; then
			echo -e "\n\e[32mExiting Installation as per your response.\n\e[0m"
			sleep 2
			exit 0
		else
			echo
			read -s -p "Enter the MySQL $db_root_id password: " db_root_pass
			db_root_connx=$(mysql --batch -u $db_root_id -h $db_host -p"$db_root_pass" -e ";" > /dev/null; echo "$?"; echo)
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
	done < <(mysql --batch --skip-column-names -u $db_root_id -h $db_host -p"$db_root_pass" -e 'use mysql; SELECT `user` FROM `user`;')

	if [[ "$db_user" != "$user" ]]; then
		echo -e "\n\e[32mNotice\e[0m: Creating \e[32m$db_user\e[0m and granting all privileges on \e[36m$db_name\e[0m"
		mysql -u $db_root_id -h $db_host -p"$db_root_pass" -e "CREATE USER '$db_user'@'$db_host' IDENTIFIED BY '$db_pass';"
        	mysql -u $db_root_id -h $db_host -p"$db_root_pass" -e "GRANT ALL PRIVILEGES ON $db_name.* TO '$db_user'@'$db_host';"
        	mysql -u $db_root_id -h $db_host -p"$db_root_pass" -e "FLUSH PRIVILEGES;"
	fi
	unset user
}

function dbCreate()
{
	if [[ "$db_user" = "" ]]; then
		mysql -u $db_root_id -h $db_host -p"$db_pass" -e "create database $db_name;"
        	mysql -u $db_root_id -h $db_host -p"$db_pass" -D $db_name < database/db_create.sql
	        mysql -u $db_root_id -h $db_host -p"$db_pass" -D $db_name < database/centos_data.sql
	else
		mysql -u $db_user -h $db_host -p"$db_pass" -e "create database $db_name;"
                mysql -u $db_user -h $db_host -p"$db_pass" -D $db_name < database/db_create.sql
                mysql -u $db_user -h $db_host -p"$db_pass" -D $db_name < database/centos_data.sql
	fi
}
function dbUpdate()
{
	if [[ "$db_user" = "" ]]; then
		mysql -u $db_root_id -h $db_host -p"$db_pass" -s -D $db_name < database/db_update.sql
		mysql -u $db_root_id -h $db_host -p"$db_pass" -D $db_name < database/centos_data.sql
	else
        	mysql -u $db_user -h $db_host -p"$db_pass" -s -D $db_name < database/db_update.sql
        	mysql -u $db_user -h $db_host -p"$db_pass" -D $db_name < database/centos_data.sql
	fi
}
function WebDaemonUser()
{
        unset new_web_user
        read -p "Please enter the web user [Default: $web_user]: " new_web_user
        echo $new_web_user|grep -P '[^\w\xC0-\xFF]' > /dev/null 2>&1
        if [[ "$?" = 0 ]]; then
                echo -e "\n\e[31mError:\e[0m Invalid name, non-alphanumeric characters are not allowed.\n"
                WebDaemonUser
        fi
        while [[ "$new_web_user" = "" ]]; do
                echo -e "\e[32mNotice\e[0m: Using Default WebUser $web_user"
                new_web_user=$web_user
        done
        echo
		if [ "$new_web_user" != "$web_user" ] && [ "$new_web_user" != "" ]; then
                web_user="$new_web_user"
        fi
}
function WebiUIAdmin()
{
        # Web-UI admin username
        unset new_web_admin
        read -p "Web Interface Admin [Default: $web_admin]: " new_web_admin
	echo $new_web_admin|grep -P '[^\w\xC0-\xFF]' > /dev/null 2>&1
        if [[ "$?" = 0 ]]; then
                echo -e "\n\e[31mError:\e[0m Invalid name, non-alphanumeric characters are not allowed.\n"
                WebiUIAdmin
        fi
        while [[ "$new_web_admin" = "" ]]; do
                echo -e "\e[32mNotice\e[0m: Default Web Admin used: $web_admin"
                new_web_admin=$web_admin
        done
        echo
}
function WebUIDUser()
{
	unset new_web_duser
        read -p "Web Interface User [Default: $web_duser]: " new_web_duser
	echo $new_web_duser|grep -P '[^\w\xC0-\xFF]' > /dev/null 2>&1
        if [[ "$?" = 0 ]]; then
                echo -e "\n\e[31mError:\e[0m Invalid name, non-alphanumeric characters are not allowed.\n"
                WebUIDUser
        fi
        while [[ "$new_web_duser" = "" ]]; do
                echo -e "\e[32mNotice\e[0m: Default User used: $web_duser."
                new_web_duser=$web_duser
        done
        echo
}
function WebUIAPWConfirm()
{
	if [[ "$new_web_admin_passwd" = "" ]]; then
		read -s -p "Web Admin Password [Default: $web_admin_passwd]: " new_web_admin_passwd
		echo
		read -s -p "Web Admin Password Confirm: " new_web_admin_passwd_confirm
		echo
	else
		echo
		read -s -p "Web Admin Password Confirm: " new_web_admin_passwd_confirm
		echo
	fi
	# check to make sure passwords match
	if [[ "$new_web_admin_passwd" != "$new_web_admin_passwd_confirm" ]]; then
		echo -e "\e[31mError\e[0m: Passwords do not match, try again."
		WebUIAPWConfirm
	fi
}
function WebUIUPWConfirm()
{
        if [[ "$new_web_duser_passwd" = "" ]]; then
		read -s -p "Web User Password [Default: $web_duser_passwd]: " new_web_duser_passwd
                echo
                read -s -p "Web User Password Confirm: " new_web_duser_passwd_confirm
                echo
        else
                echo
                read -s -p "Web User Password Confirm: " new_web_duser_passwd_confirm
                echo
        fi
        # check to make sure passwords match
        if [[ "$new_web_duser_passwd" != "$new_web_duser_passwd_confirm" ]]; then
                echo -e "\e[31mError\e[0m: Passwords do not match, try again."
                WebUIUPWConfirm
        fi
}
function WebUIInfo()
{
	echo -e "\e[32m\e[4mWebpage Location Setup\e[0m\n"
	unset new_web_dir
	read -p "Please enter location for web interface [Default: $web_dir]: " new_web_dir
	while [[ "$new_web_dir" = "" ]]; do
        	echo -e "\e[32mNotice\e[0m: Default Location Used: $web_dir"
        	new_web_dir=$web_dir
		EXTERNAL_WEB_URI="http://${SERVER_IP}${new_web_dir}"
	done
	echo $new_web_dir|grep --word-regexp "${doc_root%%/html}" > /dev/null 2>&1
        if [[ "$?" = 1 ]]; then
                echo -e "\n\e[31mNotice\e[0m: $new_web_dir is not within the DocumentRoot: $doc_root\n\e[31mNotice\e[0m: Please try again.\n"
                WebUIInfo
        fi
	echo
	unset new_relative_path
	read -p "Please enter the relative path [Default: $relative_path]: " new_relative_path
	while [[ "$new_relative_path" = "" ]]; do
        	echo -e "\e[32mNotice\e[0m: Default Location Used: $relative_path"
        	new_relative_path=$relative_path
		relpath=$(echo $new_relative_path|cut -d '/' -f 2)
	done
	echo
	if [ "$new_relative_path" != "$relative_path" ] && [ "$new_relative_path" != "" ]; then
        	relative_path="$new_relative_path"
	fi
	if [ "${new_relative_path: -1}" != "/" ]; then
        	new_relative_path=$new_relative_path"/"
	fi
	if [ "$new_web_dir" != "$web_dir" ] && [ "$new_web_dir" != "" ]; then
        	web_dir="$new_web_dir"
	fi
	if [ "${web_dir: -1}" != "/" ]; then
        	web_dir=$web_dir"/"
	fi
	# Get apache daemon ID
	WebDaemonUser

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
	WebiUIAdmin

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
        read -s -p "Web Admin Password [Default: $web_admin_passwd]: " new_web_admin_passwd
	if [[ "$new_web_admin_passwd" != "" ]]; then
		export $new_web_admin_passwd
		WebUIAPWConfirm
	fi
        while [[ "$new_web_admin_passwd" = "" ]]; do
                echo -e "\n\e[32mNotice\e[0m: Default Password used: $web_admin_passwd"
                new_web_admin_passwd=$web_admin_passwd
        done
	echo

        # Web-UI standard username
	WebUIDUser

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
        read -s -p "Web User Password [Default: $web_duser_passwd]: " new_web_duser_passwd
	if [[ "$new_web_duser_passwd" != "" ]]; then
                export $new_web_duser_passwd
                WebUIUPWConfirm
        fi
        while [[ "$new_web_duser_passwd" = "" ]]; do
                echo -e "\n\e[32mNotice\e[0m: Default Web User Password used: $web_duser_passwd"
                new_web_duser_passwd=$web_duser_passwd
        done
	echo
}

function WebUIInfoUpdate()
{
        echo -e "\e[32m\e[4mWebpage Location Setup\e[0m\n"
        unset new_web_dir
        read -p "Please enter location for web interface [Default: $web_dir]: " new_web_dir
        while [[ "$new_web_dir" = "" ]]; do
                echo -e "\e[32mNotice\e[0m: Default Location Used: $web_dir"
                new_web_dir=$web_dir
                EXTERNAL_WEB_URI="http://${SERVER_IP}${new_web_dir}"
        done
	echo $new_web_dir|grep --word-regexp "${doc_root%%/html}" > /dev/null 2>&1
        if [[ "$?" = 1 ]]; then
                echo -e "\n\e[31mNotice\e[0m: $new_web_dir is not within the DocumentRoot: $doc_root\n\e[31mNotice\e[0m: Please try again.\n"
                WebUIInfo
        fi
        echo
        unset new_relative_path
        read -p "Please enter the relative path [Default: $relative_path]: " new_relative_path
        while [[ "$new_relative_path" = "" ]]; do
                echo -e "\e[32mNotice\e[0m: Default Location Used: $relative_path"
                new_relative_path=$relative_path
                relpath=$(echo $new_relative_path|cut -d '/' -f 2)
        done
        echo
        if [ "$new_relative_path" != "$relative_path" ] && [ "$new_relative_path" != "" ]; then
                relative_path="$new_relative_path"
        fi
        if [ "${new_relative_path: -1}" != "/" ]; then
                new_relative_path=$new_relative_path"/"
        fi
        if [ "$new_web_dir" != "$web_dir" ] && [ "$new_web_dir" != "" ]; then
                web_dir="$new_web_dir"
        fi
        if [ "${web_dir: -1}" != "/" ]; then
                web_dir=$web_dir"/"
        fi
}

function dbUserCreate()
{

# check if admin user exist
unset adm_check
adm_check=$(mysql -u $db_user -h $db_host -p"$db_pass" -e "SELECT user_id from $db_name.users where user_id='$new_web_admin';")

# if not exist, add admin user
if [[ "$adm_check" = "" ]]; then
	# add passwd hash
	adm_passwd=$(hash_password "$new_web_admin_passwd" "password_salt")
	# add admin user
	mysql -u $db_user -h $db_host -p"$db_pass" -D $db_name -e "INSERT INTO users (user_id,email,admin,display_name,password,active) VALUES ('$new_web_admin', '$new_web_admin_email', '1', NULL, '$adm_passwd', '1');"
else
	echo -e "\e[32mNotice\e[0m: Web Admin User exists: \e[36m$new_web_admin\n\e[0m"
fi

# check if basic user exists
unset usr_check
usr_check=$(mysql -u $db_user -h $db_host -p"$db_pass" -e "SELECT user_id from $db_name.users where user_id='$new_web_duser';")

# if not exist, add basic user
if [[ "$usr_check" = "" ]]; then
	# add passwd hash
	usr_passwd=$(hash_password "$new_web_duser_passwd" "password_salt")
	# add basic user
	mysql -u $db_user -h $db_host -p"$db_pass" -D $db_name -e "INSERT INTO users (user_id,email,admin,display_name,password,active) VALUES ('$new_web_duser', '$new_web_duser_email', '0', NULL, '$usr_passwd', '1');"
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

# get count from company table
unset comp_count
comp_count=$(mysql -u $db_user -h $db_host -p"$db_pass" --skip-column-names -D $db_name -e "SELECT count(*) from company;")

# check if count is zero, if not skip
if [[ "$comp_count" -gt 0 ]]; then
	company_exist="yes"
	unset comp_name
        comp_name=$(mysql -u $db_user -h $db_host -p"$db_pass" --skip-column-names -D $db_name -e "SELECT name from company LIMIT 1;")
	# check if company display name exist
        unset comp_disp
        comp_disp=$(mysql -u $db_user -h $db_host -p"$db_pass" --skip-column-names -D $db_name -e "SELECT display_name from company LIMIT 1;")
        # check the key number from db
        unset comp_keynum
        comp_keynum=$(mysql -u $db_user -h $db_host -p"$db_pass" --skip-column-names -D $db_name -e "SELECT install_key from company LIMIT 1;")

	echo -e "\e[32mNotice\e[0m: A Company Name already exists: \e[36m$comp_disp\e[0m or \e[36m$comp_name\e[0m with installation key: $comp_keynum\n"
else 
	unset comp_name
	comp_name=$(mysql -u $db_user -h $db_host -p"$db_pass" --skip-column-names -D $db_name -e "SELECT name from company where name='$comp_id';")
	# check if company display name exist
	unset comp_disp
	comp_disp=$(mysql -u $db_user -h $db_host -p"$db_pass" --skip-column-names -D $db_name -e "SELECT name from company where display_name='$your_company';")
	# check if installation key exist
	unset comp_ikey
	comp_ikey_=$(mysql -u $db_user -h $db_host -p"$db_pass" --skip-column-names -D $db_name -e "SELECT install_key from company where install_key='$installation_key';")
	# check the key number from db
	unset comp_keynum
	comp_keynum=$(mysql -u $db_user -h $db_host -p"$db_pass" --skip-column-names -D $db_name -e "SELECT install_key from company LIMIT 1;")

	# if not exist, add company and installation key
	if [[ "$comp_name" = "" ]] && [[ "$comp_disp" = "" ]] && [[ "$comp_ikey" = "" ]]; then
		# add company and installation key
		company_exist="no"
		echo -e "\e[32mNotice\e[0m: Company added to \e[36m$db_name\e[0m: \e[36m$your_company\e[0m/\e[36m$comp_id\n\e[0m"
		echo -e "\e[32mNotice\e[0m: Installation Key added to \e[36m$db_name\e[0m: $install_key\n"
		mysql -u $db_user -h $db_host -p"$db_pass" -D $db_name -e "INSERT INTO company (name,display_name,install_key) VALUES ('$comp_id', '$your_company', '$installation_key');"
	else
		company_exist="yes"
        	echo -e "\e[32mNotice\e[0m: A Company Name already exists: \e[36m$comp_disp\e[0m or \e[36m$comp_name\e[0m with installation key: $comp_keynum\n"
	fi
fi
}

function AuthKeyURI()
{
	relpath=$(echo $relative_path | sed -e 's/[\/&]/\\&/g')
	# trim extra char if exists
        install_key=$(echo $install_key|awk {'print $1'})
	# check if changes were made in shell scripts via sed
	# auth key /opt/patch_manager/patch_checker.sh
	grep "__SERVER_AUTHKEY_SET_ME__" /opt/patch_manager/patch_checker.sh > /dev/null 2>&1
        if [[ "$?" -eq 0 ]]; then
                sed -i 's/__SERVER_AUTHKEY_SET_ME__/'$install_key'/g' /opt/patch_manager/patch_checker.sh
        fi
	# uri key /opt/patch_manager/patch_checker.sh
        grep "__SERVER_URI_SET_ME__" /opt/patch_manager/patch_checker.sh > /dev/null 2>&1
        if [[ "$?" -eq 0 ]]; then
                sed -i "s/__SERVER_URI_SET_ME__/http:\/\/${SERVER_IP}\/${relpath}\//" /opt/patch_manager/patch_checker.sh
        fi
	# echo if any of the above get changed
        if [[ "$?" -eq 0 ]]; then
		echo -e "\e[32mNotice\e[0m: URI & Auth Key correcly set in /opt/patch_manager/patch_checker.sh"
        fi
	# auth key check-in.sh
	grep "__SERVER_AUTHKEY_SET_ME__" "${new_web_dir}client/check-in.sh" > /dev/null 2>&1
        if [[ "$?" -eq 0 ]]; then
                sed -i 's/__SERVER_AUTHKEY_SET_ME__/'$install_key'/g' ${targetdir}/client/check-in.sh
        fi
        # auth key package_checker.sh
        grep "__SERVER_AUTHKEY_SET_ME__" "${new_web_dir}client/package_checker.sh" > /dev/null 2>&1
        if [[ "$?" -eq 0 ]]; then
                sed -i 's/__SERVER_AUTHKEY_SET_ME__/'$install_key'/g' ${targetdir}/client/package_checker.sh
        fi
        # auth key patch_checker.sh
        grep "__SERVER_AUTHKEY_SET_ME__" "${new_web_dir}client/patch_checker.sh" > /dev/null 2>&1
        if [[ "$?" -eq 0 ]]; then
                sed -i 's/__SERVER_AUTHKEY_SET_ME__/'$install_key'/g' ${targetdir}/client/patch_checker.sh
        fi
        # auth key run_commands.sh
        grep "__SERVER_AUTHKEY_SET_ME__" "${new_web_dir}client/run_commands.sh" > /dev/null 2>&1
        if [[ "$?" -eq 0 ]]; then
                sed -i 's/__SERVER_AUTHKEY_SET_ME__/'$install_key'/g' ${targetdir}/client/run_commands.sh
        fi
        # uri key check-in.sh
        grep "__SERVER_URI_SET_ME__" "${new_web_dir}client/check-in.sh" > /dev/null 2>&1
        if [[ "$?" -eq 0 ]]; then
                sed -i "s/__SERVER_URI_SET_ME__/http:\/\/${SERVER_IP}\/${relpath}\//" ${targetdir}/client/check-in.sh
        fi
        # uri key package_checker.sh
        grep "__SERVER_URI_SET_ME__" "${new_web_dir}client/package_checker.sh" > /dev/null 2>&1
        if [[ "$?" -eq 0 ]]; then
                sed -i "s/__SERVER_URI_SET_ME__/http:\/\/${SERVER_IP}\/${relpath}\//" ${targetdir}/client/package_checker.sh
        fi
        # uri key patch_checker.sh
        grep "__SERVER_URI_SET_ME__" "${new_web_dir}client/patch_checker.sh" > /dev/null 2>&1
        if [[ "$?" -eq 0 ]]; then
                sed -i "s/__SERVER_URI_SET_ME__/http:\/\/${SERVER_IP}\/${relpath}\//" ${targetdir}/client/patch_checker.sh
        fi
        # uri key run_commands.sh
        grep "__SERVER_URI_SET_ME__" "${new_web_dir}client/run_commands.sh" > /dev/null 2>&1
        if [[ "$?" -eq 0 ]]; then
                sed -i "s/__SERVER_URI_SET_ME__/http:\/\/${SERVER_IP}\/${relpath}\//" ${targetdir}/client/run_commands.sh
        fi
        # echo if any of the above get changed
        if [[ "$?" -eq 0 ]]; then
                echo -e "\e[32mNotice\e[0m: URI & Auth Key correcly set in client script(s) (${targetdir}/client/*.sh"
        fi
	if [[ "$?" -eq 0 ]]; then
		echo
	fi
}

function InstallApp()
{
# rewrite config for .htaccess

rewrite_config="ErrorDocument 404 ${relative_path}index.php?page=\$1
RewriteEngine On
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
RewriteRule ^([^/]*)$ ${relative_path}index.php?page=\$1 [QSA,L]"

# check display_name setting or your_company
if [[ "$company_exist" = "yes" ]]; then
	your_company=$comp_disp
fi
# write php config for application
php_config="<?php
define('DB_HOST','$db_host');
define('DB_USER','$db_user');
define('DB_PASS','$db_pass');
define('DB_NAME','$db_name');
define('BASE_PATH','$relative_path');
define('YOUR_COMPANY','$your_company');
define('PW_SALT','$password_salt');
/*
 * SET OFFLINE to TRUE if you want to disable the site.  All functionality will cease until you re-enable the site by setting OFFLINE back to FALSE
 */
define('OFFLINE','FALSE');
?>"

# write shell script config
bash_config="DB_HOST='$db_host'
DB_USER='$db_user'
DB_PASS='$db_pass'
DB_NAME='$db_name'"

# remove ending forward slash for conf
patchmgr=${relative_path%/}
targetdir=${new_web_dir%/}

# install virtualhost file to default conf.d dir apache/httpd
if [[ "$os" = "Ubuntu" ]] || [[ "$os" = "Debian" ]] || [[ "$os" = "Linux" ]]; then
# create log dir and set perms
mkdir -p /var/log/apache2/patch_manager/
chown $web_user:$web_user /var/log/apache2/patch_manager/ -R

# remove old conf
if [[ -f /etc/apache2/conf.d/patch_manager.conf ]]; then
	rm -f /etc/apache2/conf.d/patch_manager.conf
fi
# setup virtualhost
[ -d "/etc/apache2/conf-available" ] && confdir="/etc/apache2/conf-available" || confdir="/etc/apache2/conf.d"
cat <<EOA > $confdir/patch_manager.conf
Alias $patchmgr $targetdir
CustomLog /var/log/apache2/patch_manager/${host_node}_access.log common
ErrorLog /var/log/apache2/patch_manager/${host_node}_error.log

<Directory $targetdir>
        Options FollowSymLinks
        AllowOverride All
        Order allow,deny
        Allow from all
</Directory>
EOA

[ -d "/etc/apache2/conf-available" ] && a2enconf patch_manager

elif [[ "$os" = "CentOS" ]] || [[ "$os" = "Fedora" ]] || [[ "$os" = "Red Hat" ]] || [[ "$os" = "Red Hat Enterprise" ]]; then
# create log dir and set perms
mkdir -p /var/log/httpd/patch_manager/
chown $web_user:$web_user /var/log/httpd/patch_manager/ -R

# remove old conf
if [[ -f /etc/httpd/conf.d/patch_manager.conf ]]; then
        rm -f /etc/httpd/conf.d/patch_manager.conf
fi
# setup virtualhost
cat <<EOA > /etc/httpd/conf.d/patch_manager.conf
Alias $patchmgr $targetdir
CustomLog /var/log/httpd/patch_manager/${host_node}_access.log common
ErrorLog /var/log/httpd/patch_manager/${host_node}_error.log
        
<Directory $targetdir>
        Options FollowSymLinks
        AllowOverride All
        Order allow,deny
        Allow from all
</Directory>
EOA
fi

# main application install
target_web_dir=$(echo $new_web_dir|sed 's=/[^/]*$==;s/\.$//')
# check install mode
if [[ "$ModeType" = "Install" ]]; then

	mkdir -p /opt/patch_manager/
	rsync -aq scripts/ /opt/patch_manager/
	\cp -f html/.htaccess /opt/patch_manager/.htaccess
	\cp -f html/lib/db_config.php /opt/patch_manager/db_config.php
	echo "$rewrite_config" > /opt/patch_manager/.htaccess
	echo "$php_config" > /opt/patch_manager/db_config.php 
	echo "$bash_config" > /opt/patch_manager/db.conf
elif [[ "$ModeType" = "Update" ]]; then

	# get install key from mysql
	unset install_key
	install_key=$(mysql -u $db_user -h $db_host -p"$db_pass" --skip-column-names -D $db_name -e "SELECT install_key from company;")
	# check to see if patch_manager dir exists
	if [[ ! -d /opt/patch_manager/ ]]; then
		mkdir -p /opt/patch_manager/
	fi
	rsync -aq --exclude='db.conf' --exclude='db_config.php' scripts/ /opt/patch_manager/

fi
# check if new_web_dir exists
if [[ -d $new_web_dir ]]; then
	echo -e "\e[32mNotice\e[0m: $target_web_dir already exists.\n"
	# get authkey,uri from existing files
	ls ${new_web_dir}client/*sh > /dev/null 2>&1
	if [[ "$?" = 0 ]]; then	
		auth_key=$(grep auth_key=\" ${new_web_dir}client/*.sh|awk -F\" {'print $2'}|head -n 1)
		server_uri=$(grep server_uri=\" ${new_web_dir}client/*.sh|awk -F\" {'print $2'}|head -n 1)
	else
		echo -e "\e[31mError\e[0m: The client shell files do not exist, we recommend you run a new install.\n"
		[[ "$UNATTENDED" == "YES" ]] && exit 1
		unset wait
		read -p "Press 'Enter' to return to the Main Menu." wait
		mainMenu
	fi
	if [[ "$UNATTENDED" == "YES" ]]; then
		yn="$FORCE"
	else
		unset yn
		read -p "Do you want to overwrite the existing contents? (y/n) " yn
		echo
		while [[ "$yn" = "" ]]; do
			read -p "Do you want to overwrite the existing contents? (y/n) " yn
			echo
		done
	fi
	if [[ "$yn" = "yes" ]] || [[ "$yn" = "y" ]]; then
		rsync -aq --exclude='.htaccess' --exclude='db_config.php' html/ $new_web_dir
		if [[ ! -f /opt/patch_manager/db.conf ]]; then
			echo "$bash_config" > /opt/patch_manager/db.conf
		fi
	
		if [[ -f /opt/patch_manager/.htaccess ]]; then
			\cp -f -R /opt/patch_manager/.htaccess $new_web_dir
		else
			echo "$rewrite_config" > /opt/patch_manager/.htaccess
		fi

		if [[ -f /opt/patch_manager/db_config.php ]]; then
                        \cp -f -R /opt/patch_manager/db_config.php $new_web_dir/lib/
		else
			echo "$php_config" > /opt/patch_manager/db_config.php
                fi
	else
		mkdir -p $new_web_dir
		if [[ "$UNATTENDED" != "YES" ]]; then
			echo -e "Answer (y)es to overwrite and (n)o to skip.\n"
			cp -i -R html/* $new_web_dir
		fi
		if [[ ! -f /opt/patch_manager/db.conf ]]; then
                        echo "$bash_config" > /opt/patch_manager/db.conf
                fi

		if [[ -f /opt/patch_manager/.htaccess && "$UNATTENDED" != "YES" ]]; then
                        cp -i -R /opt/patch_manager/.htaccess $new_web_dir
		else
                        echo "$rewrite_config" > /opt/patch_manager/.htaccess
                fi

		if [[ -f /opt/patch_manager/db_config.php && "$UNATTENDED" != "YES" ]]; then
                        cp -i -R /opt/patch_manager/db_config.php $new_web_dir/lib/
                else
                        echo "$php_config" > /opt/patch_manager/db_config.php
                fi
	fi
	# run authkey and uri check
        AuthKeyURI
else
	mkdir -p $new_web_dir
	\cp -R html/* $new_web_dir
	\cp -R /opt/patch_manager/.htaccess $new_web_dir
	\cp -R /opt/patch_manager/db_config.php ${new_web_dir}lib/db_config.php
	# run authkey and uri check
        AuthKeyURI
fi
# change perms 
find $new_web_dir -type d -print0|xargs -0 chmod 755
find $new_web_dir -type f -print0|xargs -0 chmod 644
chmod 640 /opt/patch_manager/db_config.php
chmod 640 $new_web_dir/lib/db_config.php
chmod 640 /opt/patch_manager/db.conf
chown $web_user:$web_user $new_web_dir -R
# restart web service
service $web_service restart
rewrite_check=`curl -s localhost${relative_path}rewrite_check|grep 404|wc -l`
if [ "$rewrite_check" = "1" ]; then
	echo -e "\n\e[31mError\e[0m: Apache Mod_rewrite or .htaccess is not working.  Please ensure you have mod_rewrite installed and enabled.  If it is, please make sure you change 'AllowOverride None' to 'AllowOverride All'"
	echo -e "\e[31mError\e[0m: If you don't, this site won't work. \e[31mYou've been warned\e[0m."
fi
echo -e "\n\e[32mNotice\e[0m: Basic Installation is now complete. You can now go to http(s)://${host_node}${relative_path} and begin working with this tool.  To add servers, use the following command:
	/opt/patch_manager/add_server.sh -s server_name -ip ip_address
	It will ask you some questions regarding the user, password, and some other things.  Just follow the prompts.
	
	To add a server without the script granting super user, you will need to do the following:
	* Create ssh key (ssh-key-gen -t rsa) with no password on the key
	* Add public key on each server in /root/.ssh/authorized_keys
	* make sure /root/.ssh has 700 permissions on each node
	* make sure /root/.ssh/authorized_keys has 600 permissions on each node
	* go to the web UI, and click 'Manually add server'
	* Tell the UI the server name, the IP, and the distro. The distro is not required, it will be detected by the client agent script.	
	Have fun!\n"
}

function AddCrontab()
{
	if [[ ! -f /etc/cron.d/patch-manager ]]; then
		echo -e "\e[36m# Adding Crontab entries to /etc/cron.d/patch-manager\e[0m\n"
		echo "0 */2 * * * root /opt/patch_manager/start_get_package_list.sh > /dev/null 2>&1" > /etc/cron.d/patch-manager
		echo "1 */2 * * * root /opt/patch_manager/start_patch_check.sh > /dev/null 2>&1" >> /etc/cron.d/patch-manager
		echo -e "\e[32mNotice\e[0m: Added Crontab entries for 2 hour runs\e[0m\n"
	fi
}

function NewInstall()
{
	# run new install
	echo -e "\n\e[32mMode\e[0m: Running new install\n"

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
	ModeType="Install"
	InstallApp
	# end install
        exit 0
}

function UpdateUpgrade()
{

	# run update/upgrade
	echo -e "\e[32mMode\e[0m: Running Update/Upgrade\n"
	# check if script install dir exists, if not go back to menu
	if [[ ! -d /opt/patch_manager/ ]]; then
                echo -e "\e[31mNotice\e[0m: Detected the base directory is missing. Please run the installer in new install mode.\n"
		sleep 3
		mainMenu
        fi
	# Ask web information
        echo -e "\e[36m# Webpage Location, User and Admin information.\e[0m\n"
	WebUIInfoUpdate
	# check if db_config.php and db.conf exist
        if [[ ! -f /opt/patch_manager/db_config.php ]] || [[ ! -f /opt/patch_manager/db.conf ]]; then
		if [[ -f "${new_web_dir}lib/db_config.php" ]]; then
			echo -e "\e[31mMissing\e[0m: /opt/patch_manager/db_config.php"
			echo -e "\e[32mRestore\e[0m: making a copy of /opt/patch_manager/db_config.php to ${new_web_dir}lib/db_config.php\n"
			\cp -f ${new_web_dir}lib/db_config.php /opt/patch_manager/db_config.php
		else
			echo -e "\e[31mNotice\e[0m: Detected the database configuration files are missing. Please run the installer in new install mode.\n"
			sleep 3
			mainMenu
		fi
	fi
	# get database infomation
	export db_host=$(grep DB_HOST /opt/patch_manager/db_config.php|awk -F"'" {'print $4'})
	export db_user=$(grep DB_USER /opt/patch_manager/db_config.php|awk -F"'" {'print $4'})
	export db_pass=$(grep DB_PASS /opt/patch_manager/db_config.php|awk -F"'" {'print $4'})
	export db_name=$(grep DB_NAME /opt/patch_manager/db_config.php|awk -F"'" {'print $4'})
	# database upgrade
        echo -e "\e[36m# Database Setup information\e[0m"
        dbRootPasswd
        dbConnTest
        dbCheck
        # check database connection from user provided details
        if [[ "$dbConnx" = "no" ]]; then
                echo -e "\n\e[31mError\e[0m: Unable to connect to: \e[36m$db_host\e[0m, please try again.\n"
                dbRootPasswd
                dbConnTest
                dbCheck
        fi
	if [[ "$dbExists" = "yes" ]]; then
                echo -e "\n\n\e[32mNotice\e[0m: \e[36m$db_name\e[0m updating database tables\n"
                dbUpdate
        fi
        # Add crontab entry for every 2 hours
        AddCrontab
        # Finalize the install
        echo -e "\e[36m# Installing Apache related configurations\e[0m\n"
	ModeType="Update"
        InstallApp
	# end update
	exit 0
}

function mainMenu()
{

# show script header
clear
echo -e "\n#################################################################################"
echo -e "####################  Patch Management Dashboard Installer  #####################"
echo -e "#################################################################################\n"
echo -e " HomePage: https://patchdashboard.com"
echo -e " Forums: http://community.patchdashboard.com/index.php"
echo -e " Github: https://github.com/PatchDashboard/patchdashboard\n"
echo -e " Patch Management Dashboard does one thing and does it well:\n"
echo -e "   - Monitors for needed patches on your nodes *Linux (Windows soon!)*\n"
echo -e " This application gives you the ability to suppress patches on a per-server basis"
echo -e " This also has the ability to send a command to fully update a server all from a"
echo -e " custom built admin interface. This includes most Linux Distros (Windows soon!).\n"
echo -e " Stay tuned and watch to upcoming new features and plugins... :)\n"
echo -e "#################################################################################\n"

# run OS Detection and package installer
echo -e "\e[36m# Detecting Operating System Version\n\e[0m"
OSInstall

echo -e "\e[32mPlease choose an option below\n\e[0m"
cat <<EOF
Base Setup:
1) Install
2) Update

Optional:
3) EnableSSL

q) Quit
EOF

        echo -e "\e[32m";read -p "Choose mode: " opt;echo -e "\e[0m"

        # get token from user input
        case "$opt" in
                1|Install)
                NewInstall
                ;;
		2|Update)
                UpdateUpgrade
                ;;
		3|EnableSSL)
                EnableSSL
                ;;
                q|Quit)
                exit 0
                ;;
                *)
                echo -e "\e[31mNOTICE\e[0m: Invalid Option"
                $0
                ;;
        esac

        # show menu if not arguments
        if [[ -z $@ ]]; then
                echo -e "\n\e[31mError\e[0m: Invalid Option, try again.\n"
                sleep 2
                $0
                exit 1
        fi
        if [[ ! -z ${OPTARG} ]]; then
                echo -e "\n\e[31mError\e[0m: Invalid Option, try again.\n"
                sleep 2
                $0
                exit 1
        fi
}

function OSCheckUnattended() {

	function missingPackage() {
		echo >&2 "$1 does not seem to be installed. Exiting unattended installation."
		exit 1
	}

	if [[ "$os" = "Ubuntu" ]] || [[ "$os" = "Debian" ]] || [[ "$os" = "Linux" ]]; then
		# check for LAMP
		command -v apache2 >/dev/null 2>&1  || missingPackage Apache
		command -v php >/dev/null 2>&1	|| missingPackage PHP
		# we should not check for mysql on localhost if we allow another host for the database
		# command -v mysqld >/dev/null 2>&1   || missingPackage MySQL
		# check for mod_rewrite
		apache2ctl -M 2>/dev/null | grep -q rewrite || { echo "Apache2 mod_rewrite is not enabled. Aborting."; exit 1; }
		# mysql running?
		# service mysql status | grep -q "stop/waiting" && { echo "MySQL service not running. Aborting."; exit 1; }
		# other packages
		# removed mysql-server from list
		pkgList="apache2 apache2-threaded-dev apache2-utils php5 libapache2-mod-php5 php5-mcrypt php5-common php5-gd php5-cgi php5-cli php5-fpm php5-dev php5-xmlrpc mysql-client php5-mysql php5-sybase libapache2-mod-auth-mysql libmysqlclient-dev curl"
		for package in $pkgList; do
			dpkg-query -l "$package" > /dev/null 2>&1 || missingPackage "$package"
		done
		web_service="apache2"
	elif [[ "$os" = "CentOS" ]] || [[ "$os" = "Fedora" ]] || [[ "$os" = "Red Hat" ]] || [[ "$os" = "Red Hat Enterprise" ]]; then
		# check for LAMP
		rpm -qa | grep -q "httpd"		|| missingPackage Apache
		rpm -qa | grep -q "php" 2>&1		|| missingPackage PHP
		# we should not check for mysql on localhost if we allow another host for the database
		# rpm -qa | grep -q "mysql-server"	|| missingPackage MySQL
		# check for extra repos
		ls /etc/yum.repos.d/ | grep -q 'remi\|webtatic'
		if [[ "$?" = 0 ]]; then
			if [[ $(yum list installed|grep -i "56") != "" ]]; then
				pVer="56"
			elif [[ $(yum list installed|grep -i "55") != "" ]]; then
				pVer="55"
			elif [[ $(yum list installed|grep -i "54") != "" ]]; then
				pVer="54"
			else
				pVer=""
			fi
			# removed mysql-server from list
			pkgList="php php${pVer}-php-mysqlnd php${pVer}-php-common php${pVer}-php-gd php${pVer}-php-mbstring php${pVer}-php-mcrypt php${pVer}-php-devel php${pVer}-php-xml php${pVer}-php-cli php${pVer}-php-pdo php${pVer}-php-mssql mysql mysql-devel httpd httpd-devel httpd-tools curl"
		else
			# removed mysql-server from list
			pkgList="php php-mysql php-common php-gd php-mbstring php-mcrypt php-devel php-xml php-cli php-pdo php-mssql mysql mysql-devel httpd httpd-devel httpd-tools curl"
		fi
		for package in $pkgList; do
			yum list installed | grep -q "$package[.]" || missingPackage "$package"
		done
		web_service="httpd"
	fi
	# check for php > 5.2.0
	[[ $(phpversion "$(php --version|grep "PHP 5"|awk {'print $2'})") < $(phpversion 5.2.0) ]] && { echo "Installed PHP version is below 5.2.0. Aborting."; exit 1; }
	# skipping checkIPtables. Admin should know what he is doing.
	# same for localhostChk.

}

[ "$UNATTENDED" = "YES" ] && [ "$GUIDED" == "YES" ] && { echo "Cannot run in unattended and guided mode at the same time."; exit 1; }

if [ "$UNATTENDED" = "YES" ]; then
	case $ACTION in
		INSTALL)
			for var in $install_opts; do
				[[ -z "$(eval echo \$$var)" ]] && { echo "Parameter --$(echo $var | sed 's,_,-,g') was required but not set"; exit 1; }
			done
			
			[ ! -f /root/.ssh/id_rsa ] &&  { echo "There needs to be an id_rsa key in /root/.ssh/id_rsa."; exit 1; }
			
			[ "${relative_path: -1}" != "/" ] && relative_path=$relative_path"/"
			[ "${new_web_dir: -1}" != "/" ] && new_web_dir=$new_web_dir"/"
			
			OSCheckUnattended
			dbConnTest root
			[[ "$dbConnx" == "yes" ]] || { echo "Could not connect to the database."; exit 1; }

			dbUserDBCreate
			dbConnTest
			dbCheck
			if [[ "$dbExists" = "yes" ]]; then
				echo -e "\n\e[32mNotice\e[0m: \e[36m$db_name\e[0m already exists, updating tables."
				dbUpdate
			else
				echo -e "\n\e[32mNotice\e[0m: \e[36m$db_name\e[0m does not exist, creating as new."
				dbCreate
			fi

			dbUserCreate

			# Create Company entries in database
			dbCompCreate
			# Add crontab entry for every 2 hours
			AddCrontab
			# Finalize the install
			echo -e "\e[36m# Installing Apache related configurations\e[0m\n"
			ModeType="Install"
			InstallApp
			# end install
			exit 0
			;;
		UPGRADE)
			for var in $upgrade_opts; do
				[[ -z "$(eval echo \$$var)" ]] && { echo "Parameter --$(echo $var | sed 's,_,-,g') was required but not set"; exit 1; }
			done

			new_web_dir="$web_dir"
			new_relative_path="$relative_path"

			OSCheckUnattended

			[[ -d /opt/patch_manager/ ]] || { echo "Detected the base directory is missing. Please run the installer in new install mode"; exit 1; }

			# check if db_config.php and db.conf exist
			if [[ ! -f /opt/patch_manager/db_config.php ]] || [[ ! -f /opt/patch_manager/db.conf ]]; then
				if [[ -f "${new_web_dir}lib/db_config.php" ]]; then
					echo -e "Missing: /opt/patch_manager/db_config.php"
					echo -e "Restore copy from ${new_web_dir}lib/db_config.php\n"
					cp -f ${new_web_dir}lib/db_config.php /opt/patch_manager/db_config.php
				else
					echo -e "Notice: Detected the database configuration files are missing. Please run the installer in new install mode.\n"
					exit 1;
				fi
			fi

			# get database infomation
			export db_host=$(sed -n 's/.*DB_HOST.*,["'\'']\(.*\)["'\''].*/\1/p' /opt/patch_manager/db_config.php)
			export db_user=$(sed -n 's/.*DB_USER.*,["'\'']\(.*\)["'\''].*/\1/p' /opt/patch_manager/db_config.php)
			export db_pass=$(sed -n 's/.*DB_PASS.*,["'\'']\(.*\)["'\''].*/\1/p' /opt/patch_manager/db_config.php)
			export db_name=$(sed -n 's/.*DB_NAME.*,["'\'']\(.*\)["'\''].*/\1/p' /opt/patch_manager/db_config.php)

			# database upgrade
			echo -e "Database Setup information"

			dbConnTest
			[[ "$dbConnx" == "yes" ]] || { echo "Could not connect to the database."; exit 1; }

			dbCheck

			if [[ "$dbExists" = "yes" ]]; then
				echo -e "\n\nNotice: $db_name updating database tables\n"
				dbUpdate
			fi

			# Add crontab entry for every 2 hours
			AddCrontab
			# Finalize the install
			echo -e "Installing Apache related configurations"
			ModeType="Update"
			InstallApp
			# end update
			exit 0
			;;
	esac
elif [ "$GUIDED" == "YES" ]; then

	# default admin and users for the admin web interface
	# admin info
	web_admin="pmdadmin"
	web_admin_passwd=$(genPasswd)
	web_admin_email="no_admin@email.com"
	# user info
	web_duser="pmduser"
	web_duser_passwd=$(genPasswd)
	web_duser_email="no_user@email.com"
	# export to global
	export web_admin web_admin_email web_admin_passwd 
	export web_user web_user_email web_user_passwd

	# default target path for php files
	relative_path="/patchmgr/"

	# default elevated mysql id
	export db_root_id="root"

	# create keypair for root
	if [ ! -f /root/.ssh/id_rsa ]; then
		echo -e "\n\e[32mNotice\e[0m: Creating pub/private keys for $user."
		ssh-keygen -f /root/.ssh/id_rsa -t rsa -N ''
		echo -e "\e[32mNotice\e[0m: Keypair created.\n"
	fi

	# run ask menu for update or install
	mainMenu
else
	show_help
fi

# vim: tabstop=8:softtabstop=8:shiftwidth=8:noexpandtab 

