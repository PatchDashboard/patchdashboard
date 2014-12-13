<?php
session_start();
include '../../lib/db_config.php';
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == true) {
    if (isset($_POST)) {
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS);
        $id = filter_input(INPUT_POST, 'id', FILTER_SANITIZE_NUMBER_INT);
        $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_SPECIAL_CHARS);
        $confirmPassword = filter_input(INPUT_POST, 'confirmPassword', FILTER_SANITIZE_SPECIAL_CHARS);
        $display_name = filter_input(INPUT_POST, 'display_name', FILTER_SANITIZE_SPECIAL_CHARS);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_SPECIAL_CHARS);
        $is_admin = filter_input(INPUT_POST, 'is_admin', FILTER_SANITIZE_SPECIAL_CHARS);
        $alerts = filter_input(INPUT_POST, 'alerts', FILTER_SANITIZE_SPECIAL_CHARS);
        $sql_array = array();
        if (isset($username) && !empty($username) && isset($id) && !empty($id)){
            if (isset($password) && !empty($password) && $password == $confirmPassword){
                $encrypted_pass =  hash("sha256", $password.PW_SALT);
                $sql_array['key'][] = "`password`='$encrypted_pass'";
            }
            if (isset($is_admin) && !empty($is_admin)){
                $sql_array[] = "`admin`=1";
            }
            else{
                $sql_array[] = "`admin`=0";
            }
            if (isset($alerts) && !empty($alerts)){
                $sql_array[] = "`receive_alerts`=1";
            }
            else{
                $sql_array[] = "`receive_alerts`=0";
            }
            if (isset($display_name) && !empty($display_name)){
                $sql_array[] = "`display_name`='$display_name'";
            }
            if (isset($email) && !empty($email)){
                $sql_array[] = "`email`='$email'";
            }
            $replacement_parts = implode(", ", $sql_array);
            $sql = "UPDATE `users` SET $replacement_parts WHERE `user_id`='$username' AND id=$id LIMIT 1;";
            $link = mysql_connect(DB_HOST,DB_USER,DB_PASS);
            mysql_select_db(DB_NAME,$link);
            mysql_query($sql);
            mysql_close($link);
            $_SESSION['good_notice'] = "$username modified! That wasn't so bad, now was it?";
	    sleep(1);
            header('location:'.BASE_PATH."edit_user?id=$id");
        }
        else{
            $_SESSION['error_notice'] = "A required field was not filled in";
        }
    }
    else{
        header('location:'.BASE_PATH."edit_user?id=$id");
        exit();
    }
}
else{
    $_SESSION['error_notice'] = "You do not have permission to add users. This even thas been logged, and the admin has been notified.";
    header('location:'.BASE_PATH);
    exit();
}
?>

