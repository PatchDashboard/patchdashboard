<?php

session_start();
include '../../lib/db_config.php';
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] == true) {
    if (isset($_POST)) {
        $username = $_SESSION['user_id'];
        $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_SPECIAL_CHARS);
        $confirmPassword = filter_input(INPUT_POST, 'confirmPassword', FILTER_SANITIZE_SPECIAL_CHARS);
        $display_name = filter_input(INPUT_POST, 'display_name', FILTER_SANITIZE_SPECIAL_CHARS);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_SPECIAL_CHARS);
        $alerts = filter_input(INPUT_POST, 'alerts', FILTER_SANITIZE_SPECIAL_CHARS);
        $sql_array = array();
        if (isset($password) && !empty($password) && $password == $confirmPassword) {
            $encrypted_pass = hash("sha256", $password . PW_SALT);
            $sql_array[] = "`password`='$encrypted_pass'";
        }
        if (isset($alerts) && !empty($alerts)) {
            $sql_array[] = "`receive_alerts`=1";
        } else {
            $sql_array[] = "`receive_alerts`=0";
        }
        if (isset($display_name) && !empty($display_name)) {
            $sql_array[] = "`display_name`='$display_name'";
        }
        if (isset($email) && !empty($email)) {
            $sql_array[] = "`email`='$email'";
        }
        if (count($sql_array) > 0) {
            $replacement_parts = implode(", ", $sql_array);
            $sql = "UPDATE `users` SET $replacement_parts WHERE `user_id`='$username' AND id=$id LIMIT 1;";
            $link = mysql_connect(DB_HOST, DB_USER, DB_PASS);
            mysql_select_db(DB_NAME, $link);
            mysql_query($sql);
            mysql_close($link);
            $_SESSION['good_notice'] = "Profile modified! All your base are belong to us!";
            sleep(1);
            header('location:' . BASE_PATH . "profile");
        } else {
            $_SESSION['error_notice'] = "Nothing modified because no information was posted!";
            sleep(1);
            header('location:' . BASE_PATH . "profile");
        }
    }
}