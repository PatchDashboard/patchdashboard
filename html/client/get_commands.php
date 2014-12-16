<?php
include '../lib/db_config.inc.php';
$client_key = filter_input(INPUT_SERVER, 'HTTP_X_CLIENT_KEY');
if (isset($client_key) && !empty($client_key)) {
    $sql = "SELECT * FROM `servers` WHERE `client_key`='$client_key' and `trusted`= 1;";
    $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
    mysql_select_db(DB_NAME, $link);
    $res = mysql_query($sql);
    if (mysql_num_rows($res) == 0) {
        $sql_check = "SELECT * FROM `servers` WHERE `client_key`='$client_key';";
        $check_res = mysql_query($sql_check);
        if (mysql_num_rows($check_res) == 0) {
            $server_ip = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
            $sql2 = "INSERT INTO `servers`(`server_name`,`distro_id`,`distro_version`,`server_ip`,`client_key`) VALUES('UNKNOWN SERVER',0,0,'$server_ip',$client_key');";
            mysql_query($sql2);
        }
    } else {
        $row1 = mysql_fetch_array($res);
        $server_name = $row['server_name'];
        $sql2 = "UPDATE `servers` SET `last_seen` = NOW() WHERE `client_key`='$client_key';";
        mysql_query($sql2);
        $sql3 = "SELECT `package_name` FROM `patches` WHERE `to_upgrade`=` and `upgraded`=0;";
        $res3 = mysql_query($sql3);
        if (mysql_num_rows($res3) > 0){
            $suppression_sql = "SELECT * FROM `supressed` WHERE `server_name` IN (0,'$server_name');";
            $suppression_res = mysql_query($sql);
            while ($suppression_row = mysql_fetch_assoc($suppression_res)){
                $suppression_array[] = $suppression_row['package_name'];
            }
            while ($row3 = mysql_fetch_assoc($res3)){
                $package_name = $row3['package_name'];
                if (!in_array($package_name, $supressed_array)){
                    $package_array = $package_name;
                }
            }
            $package_string = implode(" ", $package_array);
        }
        //CMD GOES HERE
        $company = YOUR_COMPANY;
        $company_sql = "SELECT * FROM `company` WHERE `name`='$company' LIMIT 1;";
        $company_res = mysql_query($company_sql);
        $company_row = mysql_fetch_array($company_res);
        $key_to_check=$row['install_key'];
        $cmd_sql = "SELECT d.upgrade_command as cmd from servers s left join distro d on s.distro_id=d.id where s.server_name='$server_name' LIMIT 1;";
        $cmd_res = mysql_query($cmd_sql);
        $cmd_row = mysql_fetch_array($cmd_res);
        $cmd = $cmd_row['cmd'];
        echo "$key_to_check\n$cmd $package_string";
    }
}
mysql_close($link);
