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
    $company = YOUR_COMPANY;
    $company_sql = "SELECT * FROM `company` WHERE `display_name`='$company' LIMIT 1;";
    $company_res = mysql_query($company_sql);
    $company_row = mysql_fetch_array($company_res);
    $key_to_check_array = explode(" ",$company_row['install_key']);
    $key_to_check = $key_to_check_array[0];
    $res = mysql_query($sql);
    if (mysql_num_rows($res) == 0) {
        $sql_check = "SELECT * FROM `servers` WHERE `client_key`='$client_key';";
        $check_res = mysql_query($sql_check);
        if (mysql_num_rows($check_res) == 0) {
            $server_ip = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
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
        $out = "allowed='FALSE'
key_to_check='FALSE'
check_patches='FALSE'";
    } else {
        $time_sql = "SELECT * FROM `servers` WHERE `last_checked` < NOW() - INTERVAL 2 HOUR AND `client_key`='$client_key' LIMIT 1;";
        $time_res = mysql_query($time_sql);
        if (mysql_num_rows($time_res) == 1) {
            $CHECK_PATCHES = "TRUE";
            mysql_query("UPDATE `servers` SET `last_checked` = NOW() WHERE `client_key` = '$client_key' LIMIT 1;");
            #echo "UPDATE `servers` SET `last_checked` = NOW() WHERE `client_key` = '$client_key' LIMIT 1;";
        } else {
            $CHECK_PATCHES = "FALSE";
        }
        $sql2 = "UPDATE `servers` SET `last_seen` = NOW() WHERE `client_key`='$client_key';";
        #echo $sql2;
        mysql_query($sql2);
        $out = "allowed='TRUE'
key_to_check='$key_to_check'
check_patches='$CHECK_PATCHES'";
    }
} else {
    $out = "allowed='FALSE'
key_to_check='FALSE'
check_patches='FALSE'";
}
echo $out;
mysql_close($link);
