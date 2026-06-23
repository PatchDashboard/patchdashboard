<?php
include '../lib/db_config.php';
$client_key = filter_input(INPUT_SERVER, 'HTTP_X_CLIENT_KEY');
$client_check_sql = "SELECT `server_name` FROM `servers` WHERE `client_key` = '$client_key' AND `trusted`=1 LIMIT 1;";
$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
mysql_select_db(DB_NAME, $link);
$client_check_res = mysql_query($client_check_sql);
if (mysql_num_rows($client_check_res) == 1) {
    $row = mysql_fetch_array($client_check_res);
    $server_name = $row['server_name'];
    $data = file_get_contents("php://input");
    mysql_query("DELETE FROM `patch_allpackages` WHERE `server_name`='$server_name';");
    $package_array = explode("\n", $data);
    foreach ($package_array as $val) {
        $tmp_array = explode(":::", $val);
        $package_name = $tmp_array[0];
        $package_version = $tmp_array[1];
        $sql = "INSERT INTO patch_allpackages(server_name,package_name,package_version) VALUES('$server_name','$package_name','$package_version');";
        mysql_query($sql);
    }
}
mysql_close();
