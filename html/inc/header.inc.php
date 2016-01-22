<?php
/*
 * Fail-safe check. Ensures that they go through the main page (and are authenticated to use this page
 */
if (!isset($index_check) || $index_check != "active") {
    exit();
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
        <link rel="shortcut icon" href="<?php echo BASE_PATH; ?>favicon.ico">
        <title>Patch Management Dashboard</title>
        <link href="<?php echo BASE_PATH; ?>css/bootstrap.min.css" rel="stylesheet">
        <link href="<?php echo BASE_PATH; ?>css/jquery.easy-pie-chart.css" rel="stylesheet">
        <link href="<?php echo BASE_PATH; ?>css/dashboard.css" rel="stylesheet">
        <link href="<?php echo BASE_PATH; ?>css/bootstrap-switch.min.css" rel="stylesheet">
        <link href="<?php echo BASE_PATH; ?>css/bootstrapValidator.min.css" rel="stylesheet">
        <style>
            .alert {
                width:100%;
                margin-left: auto;
                margin-right: auto;
            }
            select {
                margin: 5px;
                width: 200px !important;
            }
            select.custom {
                padding: 0px;
            }
        </style>
    </head>
    <body onload="initPieChart();">
        <nav class="navbar navbar-inverse navbar-fixed-top" role="navigation">
            <div class="container-fluid">
                <div class="navbar-header">
                    <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar" aria-expanded="false" aria-controls="navbar">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </button>
                    <a class="navbar-brand" href="<?php echo BASE_PATH; ?>">Patch Management Dashboard</a>
                </div>
                <div id="navbar" class="navbar-collapse collapse">
                    <ul class="nav navbar-nav navbar-right">
                        <li><a href="<?php echo BASE_PATH; ?>">Patch List</a></li>
                        <li><a href="<?php echo BASE_PATH; ?>inc/logout.inc.php">Logout</a></li>
                    </ul>
                    <div class="navbar-form navbar-right">
                        <input type="text" class="form-control" id="search" placeholder="Find Servers With Package..." onkeydown="if (event.keyCode == 13)
                        NewURL(this.value);"></div>
                </div>
            </div>
        </nav>
