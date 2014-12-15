<?php
/*
 * Fail-safe check. Ensures that they go through the main page (and are authenticated to use this page
 */
if (!isset($index_check) || $index_check != "active"){
    exit();
}
if(isset($_SESSION['error'])){
    $error = $_SESSION['error'];
    $error_html="<div class='bs-example'>
    <div class='alert alert-danger alert-error'>
        <a href='#' class='close' data-dismiss='alert'>&times;</a>
        <strong>Error!</strong> $error
    </div>
</div>";
    unset($_SESSION['error']);
    unset($error);
}
?>
        <div class="col-sm-9 col-md-9">
            <h3 class="text-center login-title">Login</h1>
            <div class="account-wall">
                <img class="profile-img" src="https://lh5.googleusercontent.com/-b0-k99FZlyE/AAAAAAAAAAI/AAAAAAAAAAA/eu7opA4byxI/photo.jpg?sz=120"
                alt="">
                <form id ="addUser" method="POST" action="<?php echo BASE_PATH;?>plugins/admin/p_add_user.inc.php">
                    <input type="text" name="username" class="form-control" placeholder="Username" required autofocus >
                    <input type="password" name="password" class="form-control" placeholder="Password" required >
                    <input type="password" name="confirmPassword" class="form-control" placeholder="Retype Password" required >
                    <input type="text" name="email" class="form-control" placeholder="E-mail Address" required >
                    <input type="checkbox" name="is_admin" id="switch-state" data-on-text="Admin" data-off-text="User">
                    <input type="checkbox" name="alerts" id="switch-state2" data-on-text="Receive Alerts" data-off-text="No Alerts">
                    <button class="btn btn-lg btn-primary btn-block" type="submit">Add User</button>
                    <label class="checkbox pull-left">
                    </label>
                </form>
            </div>
        </div>