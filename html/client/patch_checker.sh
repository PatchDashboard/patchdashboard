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
# if $client_path does not exist
if [[ ! -f "${client_path}.patchrc" ]]; then
	echo "Please run ${client_path}check-in.sh as root (sudo) before trying to run this manually"
	exit 0
fi
# load the file
. ${client_path}.patchrc
rm -rf /tmp/patch_$client_key > /dev/null 2>&1
if [[ -f /etc/lsb-release && -f /etc/debian_version ]]; then
        os=$(lsb_release -s -d|head -1|awk {'print $1'})
elif [[ -f /etc/debian_version ]]; then
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
if [[ "$os" = "CentOS" ]] || [[ "$os" = "Fedora" ]] || [[ "$os" = "RHEL" ]]; then
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
                        #echo $(rpm -q "${PNAME}" --qf '%{NAME}:::%{VERSION}:::')${UVERSION}
                        patches_to_install=$(echo $(rpm -q "${PNAME}" --qf '%{NAME}:::%{VERSION}:::')${UVERSION})
                        echo "$patches_to_install" >> /tmp/patch_$client_key
                fi
        done
elif [[ "$os" = "Ubuntu" ]] || [[ "$os" = "Debian" ]]; then
        need_patched="true"
        #apt-get --just-print upgrade 2>&1 | perl -ne 'if (/Inst\s([\w,\-,\d,\.,~,:,\+]+)\s\[([\w,\-,\d,\.,~,:,\+]+)\]\s\(([\w,\-,\d,\.,~,:,\+]+)\)? /i) {print "$1:::$2:::$3\n"}'
        patches_to_install=$(apt-get --just-print upgrade 2>&1 | perl -ne 'if (/Inst\s([\w,\-,\d,\.,~,:,\+]+)\s\[([\w,\-,\d,\.,~,:,\+]+)\]\s\(([\w,\-,\d,\.,~,:,\+]+)\)? /i) {print "$1:::$2:::$3\n"}')
	echo "$patches_to_install" >> /tmp/patch_$client_key
elif [[ "$os" = "Linux" ]]; then
        echo "unspecified $os not supported"
        exit 0
fi
if [[ "$need_patched" == "true" ]]; then
        patch_list=$(cat /tmp/patch_$client_key)
        curl -k -s -H "X-CLIENT-KEY: $client_key" $submit_patch_uri -d "$patch_list"
        rm -rf /tmp/patch_$client_key > /dev/null 2>&1
fi

