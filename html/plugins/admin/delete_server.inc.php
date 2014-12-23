<?php
session_start();
include '../../lib/db_config.php';
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == true) {
    if (isset($_GET)) {
	$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
	$sql = "DELETE FROM `servers` WHERE `id`=$id LIMIT 1;";
	$link = mysql_connect(DB_HOST,DB_USER,DB_PASS);
	mysql_select_db(DB_NAME,$link);
        $servername_sql = "SELECT `server_name` FROM `users` WHERE `id`=$id LIMIT 1;";
        $servername_res = mysql_query($servername_sql);
        $servername_row = mysql_fetch_array($servername_res);
        $server_name = $servername_row['server_name'];
        mysql_query($sql);
        mysql_close($link); 
        $_SESSION['good_notice'] = "$server_name DELETED!!! 1Gigawat of storage freed up.  I never learned how to calculate storage...";
            header('location:'.BASE_PATH.'manage_servers');
        }
        else{
            $_SESSION['error_notice'] = "A required field was not filled in";
   	     header('location:'.BASE_PATH."manage_servers");
        }
    }
else{
    $_SESSION['error_notice'] = "You do not have permission to delete servers. This even thas been logged, and the admin has been notified.";
    header('location:'.BASE_PATH);
    exit();
}
?>