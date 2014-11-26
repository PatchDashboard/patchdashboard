#!/bin/bash
source="$1"
dest="$2"
date=`date +"%m-%d-%Y %T"`
if [ "$dest" = "" ]; then
        ### Destination is empty. We're just setting an A record back to default ###
        dest=$source
        #ip=`/home/upgrade/autopilot/get-ip.php $source`
        ip=`/root/autopilot/get_ip.php $source`
        log_entry="[$date] Reverted DNS for DAV$source to $ip"
else
        #ip=`/home/upgrade/autopilot/get-ip.php $dest`
        ip=`/root/autopilot/get_ip.php $dest`
        log_entry="[$date] changed DNS for DAV$source to $ip [DAV$dest]"
fi

echo "cli53 rrcreate tappin.com dav${source} A $ip --ttl 300 --replace"


echo $log_entry >> /var/log/route53_automation.log