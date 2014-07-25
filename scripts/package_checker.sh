#!/bin/bash
os=`cat /etc/issue|head -1|awk '{print $1}'`
if [ "$os" = "CentOS" ]; then
        rpm -qa --qf '%{NAME}:::%{VERSION}\n'
elif [ "$os" = "Ubuntu" ]; then
        dpkg -l|grep "ii"|awk '{print $2":::"$3}'
fi
