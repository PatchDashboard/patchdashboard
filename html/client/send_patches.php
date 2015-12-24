<?php
include '../lib/db_config.php';
$client_key = filter_input(INPUT_SERVER, 'HTTP_X_CLIENT_KEY');
$client_check_sql = "SELECT `id`,`server_name` FROM `servers` WHERE `client_key` = '$client_key' AND `trusted`=1 LIMIT 1;";
$link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
mysql_select_db(DB_NAME, $link);
$client_check_res = mysql_query($client_check_sql);
if (mysql_num_rows($client_check_res) == 1) {
    $row = mysql_fetch_array($client_check_res);
    $server_name = $row['server_name'];
    $data = file_get_contents("php://input");
    mysql_query("DELETE FROM `patches` WHERE `server_name`='$server_name';");
    $package_array = explode("\n", $data);
    $suppression_sql = "SELECT * from `supressed` WHERE `server_name` IN('$server_name',0);";
    $suppression_res = mysql_query($suppression_sql);
    if (mysql_num_rows($suppression_res) == 0){
        $suppression_array = array("NO_SUPPRESSED_PACKAGES_FOUND");
    }
    else{
        while ($suppression_row = mysql_fetch_assoc($suppression_res)){
            $suppression_array[] = $suppression_row['package_name'];
        }
    }
    foreach ($package_array as $val) {
        $tmp_array = explode(":::", $val);
        $package_name = $tmp_array[0];
        $package_from = $tmp_array[1];
        $package_to = $tmp_array[2];
        $bug_curl = shell_exec("bash -c \"curl -s http://www.ubuntuupdates.org/bugs?package_name=$package_name|grep '<td>' 2>/dev/null|head -1\"");
        $url = str_replace("<td><a href='", "", $bug_curl);
        $url_array = explode("'", $url);
        $the_url = $url_array[0];
        $urgency_curl = shell_exec("bash -c \"curl http://www.ubuntuupdates.org/package/core/precise/main/updates/$package_name|grep '$package_to'|grep 'urgency='\"");
        if (stristr($urgency_curl, "emergency")) {
            $urgency = "emergency";
        } elseif (stristr($urgency_curl, "high")) {
            $urgency = "high";
        } elseif (stristr($urgency_curl, "medium")) {
            $urgency = "medium";
        } elseif (stristr($urgency_curl, "low")) {
            $urgency = "low";
        } else {
            $urgency = "unknown";
        }
        if (!in_array($package_name, $suppression_array)) {
            $sql = "INSERT INTO patches(server_name,package_name,current,new,urgency,bug_url) VALUES('$server_name','$package_name','$package_from','$package_to','$urgency','$the_url');";
            mysql_query($sql);
        }
    }
}
mysql_close();
