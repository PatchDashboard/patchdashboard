#!/bin/bash

# generated installation key from install
auth_key="000DEFAULT000"

# remove old file is blank
if [[ -s /opt/patch_manager/.patchrc ]]; then
        rm -rf /opt/patch_manager/.patchrc
fi

# generate client key
if [[ ! -f "/opt/patch_manager/.pachrc" ]]; then
	host=$(hostname -f)
	random_bits=$(< /dev/urandom tr -dc 'a-zA-Z0-9~!@#$%^&*_-' | head -c${1:-32})
	client_key=$(echo "${host}${random_bits}"|sha256sum|cut -d ' ' -f 1)
	if [[ ! -d /opt/patch_manager ]]; then
		mkdir -p /opt/patch_manager
	fi
	echo "client_key=\"$client_key\"" > /opt/patch_manager/.patchrc
fi
# load the file
. /opt/patch_manager/.patchrc

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
        yum -q check-update| while read i
        do
                i=$(echo $i) #this strips off yum's irritating use of whitespace
                if [ "${i}x" != "x" ]
                then
                        UVERSION=${i#*\ }
                        UVERSION=${UVERSION%\ *}
                        PNAME=${i%%\ *}
                        PNAME=${PNAME%.*}
                        echo $(rpm -q "${PNAME}" --qf '%{NAME}:::%{VERSION}:::')${UVERSION}
                fi
        done
elif [ "$os" = "Ubuntu" ] || [ "$os" = "Debian" ]; then
        apt-get --just-print upgrade 2>&1 | perl -ne 'if (/Inst\s([\w,\-,\d,\.,~,:,\+]+)\s\[([\w,\-,\d,\.,~,:,\+]+)\]\s\(([\w,\-,\d,\.,~,:,\+]+)\)? /i) {print "$1:::$2:::$3\n"}'
elif [ "$os" = "Linux" ]; then
	echo "unspecified $os not supported"
	exit 0
fi
