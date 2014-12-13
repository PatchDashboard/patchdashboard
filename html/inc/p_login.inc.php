<?php
session_start();
if (isset($_POST) && !empty($_POST['username']) && !empty($_POST['pass'])){
    include '../lib/db_config.php';
    $username = filter_input(INPUT_POST, 'username');
    $password = hash("sha256", $_POST['pass'].PW_SALT);
    $sql = "SELECT * FROM users where user_id='$username' AND password='$password' LIMIT 1;";
    $link = mysql_connect(DB_HOST,DB_USER,DB_PASS);
    mysql_select_db(DB_NAME,$link);
    $res = mysql_query($sql);
    if (mysql_num_rows($res) > 0){
        while ($row = mysql_fetch_assoc($res)){
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['is_admin'] = $row['admin'];
            $_SESSION['display_name'] = $row['display_name'];
	    $_SESSION['logged_in'] = true;
        }
    }
    else{
        $_SESSION['error'] = "Invalid username/password combination";
    }
}
else{
    $_SESSION['error'] = "Please be sure to enter both your username AND password";
}
mysql_close($link);
header("location:".BASE_PATH);
