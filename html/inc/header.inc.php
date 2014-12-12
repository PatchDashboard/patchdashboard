<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="shortcut icon" href="/patch_manager2/patchdashboard/html/favicon.ico">
    <title>Patch Management Dashboard</title>
    <!-- Bootstrap core CSS -->
    <link href="/patch_manager2/patchdashboard/html/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom styles for this template -->
    <link href="/patch_manager2/patchdashboard/html/css/jquery.easy-pie-chart.css" rel="stylesheet">
    <link href="/patch_manager2/patchdashboard/html/css/dashboard.css" rel="stylesheet">
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
          <a class="navbar-brand" href="/patch_manager2/patchdashboard/html/">Patch Management Dashboard</a>
        </div>
        <div id="navbar" class="navbar-collapse collapse">
          <ul class="nav navbar-nav navbar-right">
            <li><a href="/patch_manager2/patchdashboard/html/patches">Patch List</a></li>
          </ul>
          <div class="navbar-form navbar-right">
            <input type="text" class="form-control" id="search" placeholder="Find Servers With Package..." onkeydown="if (event.keyCode == 13) NewURL(this.value);"></div>
        </div>
      </div>
    </nav>