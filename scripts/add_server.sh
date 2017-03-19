#!/bin/bash

# load db config
. /opt/patch_manager/db.conf

# get osversion from host
function OSVersion()
{
	if [[ -f /etc/lsb-release ]]; then
        	export osversion=$(lsb_release -s -d)
	elif [[ -f /etc/debian_version ]]; then
	        export osversion="Debian $(cat /etc/debian_version)"
	elif test -f /etc/devuan_version; then
	        export osversion="Devuan $(cat /etc/devuan_version)"
	elif [[ -f /etc/redhat-release ]]; then
	        export osversion=`cat /etc/redhat-release`
	else
	        export osversion="$(uname -s) $(uname -r)"
	fi
}

function SingleHost()
{
distro_names=`mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" -Nse 'SELECT distro_name from distro order by id ASC;' $DB_NAME`
for os in $distro_names; do
	distro_num=`mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" -Nse "SELECT id from distro where distro_name='$os';" $DB_NAME`
	echo -e "[\e[32m$distro_num\e[0m] $os"
done
echo
unset dist_num_selected
read -p "Please enter the number corresponding to your distro: " dist_num_selected
while [[ "$dist_num_selected" = "" ]]; do
	read -p "Please enter the number corresponding to your distro: " dist_num_selected
done

distro_ver=`mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" -Nse "SELECT id from distro_version where distro_id='$dist_num_selected' order by id ASC;" $DB_NAME`
for osv in $distro_ver; do
	ver_name=`mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" -Nse "SELECT version_num from distro_version where id='$osv';" $DB_NAME`
	echo -e "[\e[32m$osv\e[0m] $ver_name"
done
echo
unset dist_ver_selected
read -p "Please select which version of $os you're using on this node: " dist_ver_selected
while [[ "$dist_ver_selected" = "" ]]; do
	read -p "Please select which version of $distro_name you're using on this node: " dist_ver_selected
done

mysql -h $DB_HOST -u $DB_USER -p"$DB_PASS" -D $DB_NAME -e "INSERT INTO servers(server_name,distro_id,server_ip,distro_version) VALUES('$sHost',$dist_num_selected,'$ipAddr',$dist_ver_selected);"
echo -e "\n\e[32mNOTICE\e[0m: Server successfully added!
	Next:
		If you have access via SSH to the root user on the remote system, run this:
			ssh-copy-id root@$ipAddr
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
				ssh root@$ipAddr
			If it doesn't let you right in after accepting the key, something isn't working correctly.
"
}

# show menu to chose OS type
echo -e "\n###################################################"
echo "######### Patch Management Host Installer #########"
echo -e "###################################################\n"

function ShowHelp()
{
	echo -e " Available Arguments: -s [host] -ip [ip address] -or- -l ./hosts.txt\n"
	echo -e " $0 -s hostname -ip 192.168.0.10"
	echo -e " $0 -l ./hosts.txt (list format: host ip)"
	echo -e "\n####################################################"
}

# get token from user input
while getopts ":s:l:h?" opts; do
   case ${opts} in
        h|\?)
        ShowHelp
        exit 1
        ;;
	s)
	sHost=${OPTARG}
	echo "$@"|grep -q '\-ip'
	if [[ "$?" = 0 ]]; then
		ipAddr=`echo $@|grep -Eo '[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}'|sed -e 's/-ip//g'`
		SingleHost
	else
		echo -e "\n\e[31mERROR\e[0m: Bad format or missing IP, please try again.\n"
		exit 0
	fi
	;;
	l)
	fHost=${OPTARG}
        ListOfHosts
	;;
   esac
done

# show menu if not arguments
if [[ -z $@ ]]; then
        ShowHelp
        exit 1
fi
if [[ ! -z ${OPTARG} ]]; then
	ShowHelp
	exit 1
fi
