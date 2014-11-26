#!/bin/bash
if [[ -f /etc/lsb-release ]]; then
        export os=$(lsb_release -s -d|head -1|awk {'print $1'})
elif [[ -f /etc/debian_version ]]; then
        export os="Debian $(cat /etc/debian_version)|head -1|awk {'print $1'}"
elif [[ -f /etc/redhat-release ]]; then
        export os=$(cat /etc/redhat-release|head -1|awk {'print $1'})
else
        export os="$(uname -s) $(uname -r)|head -1|awk {'print $1'}"
fi
if [ "$os" = "CentOS" ] || [ "$os" = "Fedora" ] || [ "$os" = "Red" ]; then
        rpm -qa --qf '%{NAME}:::%{VERSION}\n'
elif [ "$os" = "Ubuntu" ] || [ "$os" = "Debian" ]; then
	dpkg -l|grep "ii"|awk '{print $2":::"$3}'
elif [ "$os" = "Linux" ]; then
	echo "unspecified $os not supported"
	exit 0
fi
