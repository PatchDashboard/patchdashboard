<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="shortcut icon" href="<?php echo BASE_PATH;?>favicon.ico">

    <title>Patch Management Dashboard</title>

    <!-- Bootstrap core CSS -->
    <link href="<?php echo BASE_PATH;?>css/bootstrap.min.css" rel="stylesheet">

    <!-- Custom styles for this template -->
    <link href="<?php echo BASE_PATH;?>css/dashboard.css" rel="stylesheet">
    <link href="<?php echo BASE_PATH; ?>css/jquery.easy-pie-chart.css" rel="stylesheet">
  </head>

  <body onload="initPieChart();">

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
            <li><a href="<?php echo BASE_PATH; ?>patches">Patch List</a></li>
          </ul>
          <!--<form class="navbar-form navbar-right"> -->
		<div class="navbar-form navbar-right">
            <input type="text" class="form-control" id="search" placeholder="Find Servers With Package..." onkeydown="if (event.keyCode == 13) NewURL(this.value);"></div>
          <!--</form>-->
        </div>
      </div>
    </div>
