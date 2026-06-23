<?php
session_start();
include '../../lib/db_config.php';
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == true) {
    if (isset($_GET)) {
	$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
	if (isset($id) && !empty($id)){
		$sql = "UPDATE `servers` SET `trusted`=0 WHERE `id`=$id LIMIT 1;";
		$link = mysql_connect(DB_HOST,DB_USER,DB_PASS);
		mysql_select_db(DB_NAME,$link);
        	mysql_query($sql);
                $servername_sql = "SELECT `server_name` FROM `servers` WHERE `id`=$id LIMIT 1;";
                $servername_res = mysql_query($servername_sql);
                $servername_row = mysql_fetch_array($servername_res);
                $server_name = $servername_row['server_name'];
	        mysql_close($link); 
        	$_SESSION['good_notice'] = "$server_name No Longer Trusted. I always knew they were a sneaky, no-good <strong>#!*&amp; :-(</strong>";
            	header('location:'.BASE_PATH.'manage_servers');
        }
        else{
            $_SESSION['error_notice'] = "A required field was not filled in";
   	     header('location:'.BASE_PATH."manage_servers");
        }
    }
    else{
   	    $_SESSION['warning_notice'] = "You didn't pick a server to distrust";
            header('location:'.BASE_PATH."manage_servers");
    }
}
else{
    $_SESSION['error_notice'] = "You do not have permission to distrust servers. This even thas been logged, and the admin has been notified.";
    header('location:'.BASE_PATH);
    exit();
}
?>

