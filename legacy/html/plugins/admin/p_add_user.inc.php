<?php
session_start();
include '../../lib/db_config.php';
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == true) {
    if (isset($_POST)) {
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS);
        $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_SPECIAL_CHARS);
        $confirmPassword = filter_input(INPUT_POST, 'confirmPassword', FILTER_SANITIZE_SPECIAL_CHARS);
        $display_name = filter_input(INPUT_POST, 'display_name', FILTER_SANITIZE_SPECIAL_CHARS);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_SPECIAL_CHARS);
        $is_admin = filter_input(INPUT_POST, 'is_admin', FILTER_SANITIZE_SPECIAL_CHARS);
        $alerts = filter_input(INPUT_POST, 'alerts', FILTER_SANITIZE_SPECIAL_CHARS);
        if (isset($username) && !empty($username) && isset($password) && !empty($password) && $password == $confirmPassword && isset($email) && !empty($email) && isset($display_name) && !empty($display_name)){
            $encrypted_pass =  hash("sha256", $password.PW_SALT);
            if (isset($is_admin) && !empty($is_admin)){
                $admin = 1;
            }
            else{
                $admin = 0;
            }
            if (isset($alerts) && !empty($alerts)){
                $alert = 1;
            }
            else{
                $alert = 0;
            }
            $sql = "INSERT INTO `users`(`user_id`,`display_name`,`email`,`admin`,`password`,`receive_alerts`) VALUES('$username','$display_name','$email','$admin','$encrypted_pass','$alert');";
            $link = mysql_connect(DB_HOST,DB_USER,DB_PASS);
            mysql_select_db(DB_NAME,$link);
            mysql_query($sql);
            mysql_close($link);
            $_SESSION['good_notice'] = "$username added! Now, I hope you aren't paying them too much...";
            header('location:'.BASE_PATH.'add_user');
        }
        else{
            $_SESSION['error_notice'] = "A required field was not filled in";
        }
    }
    else{
        header('location:'.BASE_PATH."add_user");
        exit();
    }
}
else{
    $_SESSION['error_notice'] = "You do not have permission to add users. This even thas been logged, and the admin has been notified.";
    header('location:'.BASE_PATH);
    exit();
}
?>

