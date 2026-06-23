#!/bin/bash
if [[ -f /etc/lsb-release && -f /etc/debian_version ]]; then
        os=$(lsb_release -s -d|head -1|awk {'print $1'})
elif test -f /etc/debian_version -o -f /etc/devuan_version; then
        os="$(cat /etc/issue|head -n 1|awk {'print $1'})"
elif [[ -f /etc/redhat-release ]]; then
        os=$(cat /etc/redhat-release|head -1|awk {'print $1'})
        if [[ "$os" = "Red" && $(grep -i enterprise /etc/redhat-release) != "" ]]; then
                os="RHEL"
        elif [[ "$os" = "Red" ]]; then
                os="RHEL"
        fi
else
        os=$(uname -s -r|head -1|awk {'print $1'})
fi
# remove any special characters
os=$(echo $os|sed -e 's/[^a-zA-Z0-9]//g')
# begin update checks
if [[ "$os" = "CentOS" ]] || [[ "$os" = "Fedora" ]] || [[ "$os" = "Red" ]]; then
        rpm -qa --qf '%{NAME}:::%{VERSION}\n'
elif test "$os" = Ubuntu -o "$os" = Debian -o "$os" = Devuan -o "$os" = Raspbian; then
	dpkg -l|grep "ii"|awk '{print $2":::"$3}'
elif [[ "$os" = "Linux" ]]; then
	echo "unspecified $os not supported"
	exit 0
fi
