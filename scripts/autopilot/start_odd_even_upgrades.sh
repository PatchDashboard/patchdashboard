#!/bin/bash
ODD_OR_EVEN=$1
if (( $ODD_OR_EVEN $ 2 )); then
        server_list="12 14 16 18 20"
else
        server_list="11 13 15 17 19"
fi
for i in `echo $server_list`; do
        /home/upgrade/autopilot/wsus.sh $i
done