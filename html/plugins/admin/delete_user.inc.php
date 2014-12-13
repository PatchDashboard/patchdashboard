<?php
session_start();
include '../../lib/db_config.php';
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == true) {
    if (isset($_GET)) {
	$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
	$sql = "DELETE FROM `users` WHERE `id`=$id LIMIT 1;";
	$link = mysql_connect(DB_HOST,DB_USER,DB_PASS);
	mysql_select_db(DB_NAME,$link);
        mysql_query($sql);
        mysql_close($link); 
        $_SESSION['good_notice'] = "$username DELETED!!! Live Long and Prosper.";
            header('location:'.BASE_PATH.'manage_users');
        }
        else{
            $_SESSION['error_notice'] = "A required field was not filled in";
   	     header('location:'.BASE_PATH."manage_users");
        }
    }
else{
    $_SESSION['error_notice'] = "You do not have permission to add users. This even thas been logged, and the admin has been notified.";
    header('location:'.BASE_PATH);
    exit();
}
?>

