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
	echo -e \"You need to be root to run the installer.\nPlease run this: sudo curl ${SERVER_URI}client/client-installer.php|bash\"
fi
ls /opt/patch_manager/db.conf > /dev/null 2>&1
if [[ \"$?\" = \"0\" ]]; then 
	client_path=\"/opt/patch_client/\"
	echo \"Detected client install on PatchMD master server, installing in secondary directory: \${client_path}\"
else
	client_path=\"/opt/patch_manager/\"
fi
ls \${client_path}*.sh > /dev/null 2>&1
if [[ \"$?\" != \"0\" ]]; then
	mkdir -p \${client_path}
	curl -k -s ${SERVER_URI}client/check-in.sh > \${client_path}check-in.sh
	curl -k -s ${SERVER_URI}client/patch_checker.sh > \${client_path}patch_checker.sh
	curl -k -s ${SERVER_URI}client/package_checker.sh > \${client_path}package_checker.sh
	curl -k -s ${SERVER_URI}client/run_commands.sh > \${client_path}run_commands.sh
else
	echo \"Updating existing install located at: \${client_path}\"
	rm -rf /opt/patch_manager/*.sh
	curl -k -s ${SERVER_URI}client/check-in.sh > \${client_path}check-in.sh
	curl -k -s ${SERVER_URI}client/patch_checker.sh > \${client_path}patch_checker.sh
       	curl -k -s ${SERVER_URI}client/package_checker.sh > \${client_path}package_checker.sh
	curl -k -s ${SERVER_URI}client/run_commands.sh > \${client_path}run_commands.sh
fi
chmod +x \${client_path}*.sh > /dev/null 2>&1
ls \"/etc/cron.d/patch-manager\" > /dev/null 2>&1
if [[ \"$?\" != \"0\" ]]; then
	touch /etc/cron.d/patch-manager > /dev/null 2>&1
fi
grep \"\${client_path}check-in.sh\" \"/etc/cron.d/patch-manager\" > /dev/null 2>&1
if [[ \"$?\" != \"0\" ]]; then
	if [[ \"\$count_lines\" -gt \"0\" ]]; then
		echo -e \"* * * * * root \${client_path}check-in.sh >> /dev/null 2>&1\" >>  /etc/cron.d/patch-manager
	else
		echo -e \"* * * * * root \${client_path}check-in.sh >> /dev/null 2>&1\" >  /etc/cron.d/patch-manager
	fi
else
	echo \"Crontab entry already exists in: /etc/cron.d/patch-manager\"
fi
echo \"Client Install completed.\"
";
echo $script;
