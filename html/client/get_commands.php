<?php
include '../lib/db_config.php';
$client_key = filter_input(INPUT_SERVER, 'HTTP_X_CLIENT_KEY');
$client_host = filter_input(INPUT_SERVER, 'HTTP_X_CLIENT_HOST');
$client_os = filter_input(INPUT_SERVER, 'HTTP_X_CLIENT_OS');
$client_os_ver = filter_input(INPUT_SERVER, 'HTTP_X_CLIENT_OS_VER');
if (isset($client_key) && !empty($client_key)) {
    $sql = "SELECT * FROM `servers` WHERE `client_key`='$client_key' and `trusted`= 1;";
    $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
    mysql_select_db(DB_NAME, $link);
    $res = mysql_query($sql);
    if (mysql_num_rows($res) == 0) {
        $sql_check = "SELECT * FROM `servers` WHERE `client_key`='$client_key';";
        $check_res = mysql_query($sql_check);
        if (mysql_num_rows($check_res) == 0) {
            $os_versql = "SELECT `id` FROM `distro` WHERE `distro_name` LIKE '$client_os' LIMIT 1;";
            $os_version = mysql_query($os_versql); $os_version = mysql_result($os_version, 0);
            $os_dissql = "SELECT `id` FROM `distro_version` WHERE `distro_id`='$os_version' AND `version_num` LIKE '$client_os_ver%' LIMIT 1;";
            $os_distro = mysql_query($os_dissql); $os_distro = mysql_result($os_distro, 0);
            if (empty($client_host)) {$client_host = 'UNKNOWN SERVER';}
            if (empty($client_os)) {$os_id = 0;}
            if (empty($client_os_ver)) {$client_os_ver = 0;}
            $sql2 = "INSERT INTO `servers`(`server_name`,`server_alias`,`distro_id`,`distro_version`,`server_ip`,`client_key`) VALUES('$client_host','$client_host','$os_version','$os_distro','$server_ip','$client_key');";
            mysql_query($sql2);
        }
    } else {
        $row1 = mysql_fetch_array($res);
        $server_name = $row1['server_name'];
        $id = $row1['id'];
        $to_reboot = $row1['reboot_cmd_sent'];
        $sql2 = "UPDATE `servers` SET `last_seen` = NOW() WHERE `client_key`='$client_key';";
        mysql_query($sql2);
        $sql3 = "SELECT `package_name` FROM `patches` WHERE `to_upgrade`=1 and `upgraded`=0 AND `server_name`='$server_name';";
        $res3 = mysql_query($sql3);
        $suppression_array = array();
        $package_array = array();
        if (mysql_num_rows($res3) > 0){
            $suppression_sql = "SELECT * FROM `supressed` WHERE `server_name` IN (0,'$server_name');";
            $suppression_res = mysql_query($sql);
            if (mysql_num_rows($suppression_res) > 0) {
                while ($suppression_row = mysql_fetch_assoc($suppression_res)){
                    if (isset($suppression_row['package_name'])) {
                        $suppression_array[] = $suppression_row['package_name'];
                    }
                }
                while ($row3 = mysql_fetch_assoc($res3)){
                    $package_name = $row3['package_name'];
                    if (!in_array($package_name, $supressed_array)){
                        $package_array[] = $package_name;
                    }
                }
                foreach ($package_array as $val){
                    mysql_query("UPDATE `patches` SET `to_upgrade` = 0, `upgraded` = 1 WHERE `server_name` = '$server_name' AND `package_name` = '$val' LIMIT 1;");
                }
                $package_string = implode(" ", $package_array);
            }
        }
        //CMD GOES HERE
        $company = YOUR_COMPANY;
        $company_sql = "SELECT * FROM `company` WHERE `display_name`='$company' LIMIT 1;";
        $company_res = mysql_query($company_sql);
        $company_row = mysql_fetch_array($company_res);
        $key_to_check_array = explode(" ",$company_row['install_key']);
        $key_to_check = $key_to_check_array[0];
        $cmd_sql = "SELECT d.upgrade_command as cmd from servers s left join distro d on s.distro_id=d.id where s.server_name='$server_name' LIMIT 1;";
        $cmd_res = mysql_query($cmd_sql);
        $cmd_row = mysql_fetch_array($cmd_res);
        $cmd = $cmd_row['cmd'];
        // If it needs to be rebooted, lets add it on to the end of the rest of the cmd.
        if ($to_reboot == 1){
            $reboot_cmd_sent_sql = "UPDATE `servers` SET `reboot_cmd_sent`=0 WHERE `id`=$id LIMIT 1;";
            mysql_query($reboot_cmd_sent_sql);
            $add_after = "/sbin/reboot";
        }
        else{
            $add_after = "";
        }
        if (isset($package_string)) {
            echo "key_to_check='$key_to_check'
cmd_to_run='$cmd $package_string;$add_after'";
        } else {
            echo "key_to_check='$key_to_check'
cmd_to_run='$add_after'";
        }
    }
}
mysql_close($link);
