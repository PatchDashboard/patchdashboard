#!/bin/bash

# generated installation key and server URI from install
auth_key="__SERVER_AUTHKEY_SET_ME__"
server_uri="__SERVER_URI_SET_ME__"
submit_patch_uri="${server_uri}client/send_patches.php"
# set client_path
if [[ -d /opt/patch_client ]]; then
        client_path="/opt/patch_client/"
else
        client_path="/opt/patch_manager/"
fi
# remove old file is blank
if [[ -s ${client_path}.patchrc ]]; then
        rm -rf ${client_path}.patchrc
fi

# generate client key
if [[ ! -f "${client_path}.pachrc" ]]; then
	host=$(hostname -f)
	random_bits=$(< /dev/urandom tr -dc 'a-zA-Z0-9~!@#$%^&*_-' | head -c${1:-32})
	client_key=$(echo "${host}${random_bits}"|sha256sum|cut -d ' ' -f 1)
	if [[ ! -d ${client_path} ]]; then
		mkdir -p ${client_path}
	fi
	echo "client_key=\"$client_key\"" > ${client_path}.patchrc
fi
# load the file
. ${client_path}.patchrc
rm -rf /tmp/$client_key > /dev/null 2>&1
if [[ -f /etc/lsb-release && -f /etc/debian_version ]]; then
        os=$(lsb_release -s -d|head -1|awk {'print $1'})
elif [[ -f /etc/debian_version ]]; then
        os="Debian"
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
	need_patched="true"
        yum -q check-update| while read i
        do
                i=$(echo $i) #this strips off yum's irritating use of whitespace
                if [[ "${i}x" != "x" ]]
                then
                        UVERSION=${i#*\ }
                        UVERSION=${UVERSION%\ *}
                        PNAME=${i%%\ *}
                        PNAME=${PNAME%.*}
                        echo $(rpm -q "${PNAME}" --qf '%{NAME}:::%{VERSION}:::')${UVERSION}
			patches_to_install=$(echo $(rpm -q "${PNAME}" --qf '%{NAME}:::%{VERSION}:::')${UVERSION})
			echo "$patches_to_install" >> /tmp/$client_key
                fi
        done
elif [[ "$os" = "Ubuntu" ]] || [[ "$os" = "Debian" ]]; then
	need_patched="true"
        apt-get --just-print upgrade 2>&1 | perl -ne 'if (/Inst\s([\w,\-,\d,\.,~,:,\+]+)\s\[([\w,\-,\d,\.,~,:,\+]+)\]\s\(([\w,\-,\d,\.,~,:,\+]+)\)? /i) {print "$1:::$2:::$3\n"}'
	patches_to_install=$(apt-get --just-print upgrade 2>&1 | perl -ne 'if (/Inst\s([\w,\-,\d,\.,~,:,\+]+)\s\[([\w,\-,\d,\.,~,:,\+]+)\]\s\(([\w,\-,\d,\.,~,:,\+]+)\)? /i) {print "$1:::$2:::$3\n"}')
	echo "$patches_to_install\n" >> /tmp/$client_key
elif [[ "$os" = "Linux" ]]; then
	echo "unspecified $os not supported"
	exit 0
fi
if [[ "$need_patched" = "true" ]]; then
	patch_list=$(cat /tmp/$client_key)
	curl -H "X-CLIENT-KEY: $client_key" $submit_patch_uri -d "$patch_list" > /dev/null 2>&1
	rm -rf /tmp/$client_key > /dev/null 2>&1
fi
