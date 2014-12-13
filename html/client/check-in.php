<?php
include '../lib/db_config.inc.php';
$client_key = filter_input(INPUT_GET, 'client-id', FILTER_SANITIZE_SPECIAL_CHARS);
if(isset($client_key) && !empty($client_key)){
    $sql = "SELECT * FROM `servers` WHERE `client_key`='$client_key';";
    $link = mysql_connect(DB_HOST,DB_USER,DB_PASS);
    mysql_select_db(DB_NAME,$link);
    $res = mysql_query($sql);
    if (mysql_num_rows($res) == 0){
        $server_ip = filter_input(INPUT_SERVER, 'REMOTE_ADDR');
        $sql2 = "INSERT INTO `servers`(`server_name`,`distro_id`,`distro_version`,`server_ip`,`client_key`) VALUES('UNKNOWN SERVER',0,0,'$server_ip',$client_key');";
        mysql_query($sql2);
        echo "FALSE";
    }
    else{
        $sql2 = "UPDATE `servers` SET `last_seen` = NOW() WHERE `client_key`='$client_key';";
        echo "TRUE";
    }
}
else{
    echo "FALSE";
}
/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

