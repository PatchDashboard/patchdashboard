<?php
if (!empty($_SERVER['HTTPS'])){
    $protocol = 'https://';
}
else{
    $protocol = 'http://';
}
include '../lib/db_config.php';
$SERVER_URI = $protocol.$_SERVER['HTTP_HOST'].BASE_PATH;

$script = "#!/bin/bash
user=$(whoami)
if [ \"\$user\" != \"root\" ]; then
	echo \" You need to be root to run the installer. Please run this:
		sudo curl ${SERVER_URI}client/client-installer.php|bash
\"
fi
if [ -d \"/var/spool/cron/crontabs\" ]; then
    cron_dir='/var/spool/cron/crontabs/'
else
    cron_dir='/var/spool/cron/'
fi
mkdir -p /opt/patch_manager/
ls /opt/patch_manager/*.sh > /dev/null 2>&1
if [[ \"$?\" != \"0\" ]]; then
	curl -k -s ${SERVER_URI}client/check-in.sh > /opt/patch_manager/check-in.sh
	curl -k -s ${SERVER_URI}client/patch_checker.sh > /opt/patch_manager/patch_checker.sh
	curl -k -s ${SERVER_URI}client/package_checker.sh > /opt/patch_manager/package_checker.sh
	curl -k -s ${SERVER_URI}client/run_commands.sh > /opt/patch_manager/run_commands.sh
else
	echo \"Updating existing install located at: /opt/patch_manager/\"
	rm -rf /opt/patch_manager/*.sh
	curl -k -s ${SERVER_URI}client/check-in.sh > /opt/patch_manager/check-in.sh
	curl -k -s ${SERVER_URI}client/patch_checker.sh > /opt/patch_manager/patch_checker.sh
       	curl -k -s ${SERVER_URI}client/package_checker.sh > /opt/patch_manager/package_checker.sh
	curl -k -s ${SERVER_URI}client/run_commands.sh > /opt/patch_manager/run_commands.sh
fi
chmod +x /opt/patch_manager/*.sh
if [[ $(crontab -l|grep check-in.sh) = \"\" ]]; then
	if [ -f \"\${cron_dir}root\" ]; then
    		echo \"* * * * * /opt/patch_manager/check-in.sh\" >>  \${cron_dir}root
	else
    		echo \"* * * * * /opt/patch_manager/check-in.sh\" >  \${cron_dir}root
	fi
fi
";
echo $script;
