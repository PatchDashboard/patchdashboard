<?php
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
else{
    $error_html="";
}
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="shortcut icon" href="<?php echo BASE_PATH;?>favicon.ico">
    <script type="text/css">
        .login-error{
            margin:20px;
        }
    </script>
    <title>Patch Management Dashboard</title>

    <!-- Bootstrap core CSS -->
    <link href="<?php echo BASE_PATH;?>css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="<?php echo BASE_PATH;?>css/dashboard.css" rel="stylesheet">
  </head>
    <div class="navbar navbar-inverse navbar-fixed-top" role="navigation">
      <div class="container-fluid">
        <div class="navbar-header">
          <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
            <span class="sr-only">Toggle navigation</span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
            <span class="icon-bar"></span>
          </button>
          <a class="navbar-brand" href="<?php echo BASE_PATH;?>">Patch Management Dashboard</a>
        </div>
        <div class="navbar-collapse collapse">
          <ul class="nav navbar-nav navbar-right">
            <li><a href="<?php echo BASE_PATH; ?>">Login</a></li>
          </ul>
          <!--<form class="navbar-form navbar-right"> -->
                <div class="navbar-form navbar-right">
            &nbsp;</div>
          <!--</form>-->
        </div>
      </div>
    </div>
<?php
if ($requested_page != "patches"){
        $active = "";
}
else{
        $active = "class=\"active\"";
}
?>
    <div class="container-fluid">
      <div class="row">
<div class="col-sm-3 col-md-2 sidebar">
    <p><img src="<?php echo BASE_PATH;?>img/report.png"/></p>
          <ul class="nav nav-sidebar">
            <li class="active"><a href="<?php echo BASE_PATH;?>">Login</a></li>
          </ul>
        </div>
        <div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main">
            <div class="container">
                <?php echo $error_html;?>
                <div class="row">
                    <div class="col-sm-6 col-md-4 col-md-offset-4">
                        <h3 class="text-center login-title">Login</h1>
                            <div class="account-wall">
                            <img class="profile-img" src="https://lh5.googleusercontent.com/-b0-k99FZlyE/AAAAAAAAAAI/AAAAAAAAAAA/eu7opA4byxI/photo.jpg?sz=120"
                            alt="">
                            <form method="POST" action="<?php echo BASE_PATH;?>inc/p_login.inc.php">
                                <input type="text" name="username" class="form-control" placeholder="Username" required autofocus >
                                <input type="password" name="pass" class="form-control" placeholder="Password" required>
                                <button class="btn btn-lg btn-primary btn-block" type="submit">Sign in</button>
                                <label class="checkbox pull-left">
                                </label>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
<div id="footer">&copy; 2014 <?php echo YOUR_COMPANY;?></div>
    </div>

    <!-- Bootstrap core JavaScript
    ================================================== -->
    <!-- Placed at the end of the document so the pages load faster -->
    <script src="<?php echo BASE_PATH; ?>js/jquery.min.js"></script>
    <script src="<?php echo BASE_PATH; ?>js/bootstrap.min.js"></script>
            <script type="text/javascript">
        </script>
  </body>
</html>
