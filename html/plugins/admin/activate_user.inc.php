<?php
session_start();
include '../../lib/db_config.php';
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == true) {
    if (isset($_GET)) {
	$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
	if (isset($id) && !empty($id)){
		$sql = "UPDATE `users`SET `active`=1 WHERE `id`=$id LIMIT 1;";
		$link = mysql_connect(DB_HOST,DB_USER,DB_PASS);
		mysql_select_db(DB_NAME,$link);
        	mysql_query($sql);
	        mysql_close($link); 
        	$_SESSION['good_notice'] = "$username Reactivated.  Back to the grind stone for them, I suppose.";
            	header('location:'.BASE_PATH.'manage_users');
        }
        else{
            $_SESSION['error_notice'] = "A required field was not filled in";
   	     header('location:'.BASE_PATH."manage_users");
        }
    }
    else{
   	    $_SESSION['warning_notice'] = "You didn't pick a user to reactivate";
            header('location:'.BASE_PATH."manage_users");
    }
}
else{
    $_SESSION['error_notice'] = "You do not have permission to reactivate users. This even thas been logged, and the admin has been notified.";
    header('location:'.BASE_PATH);
    exit();
}
?>

