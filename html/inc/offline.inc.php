<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="">
    <link rel="shortcut icon" href="/patch_manager2/patchdashboard/html/favicon.ico">
    <title>|| OFFLINE || Patch Management Dashboard</title>
    <link href="/patch_manager2/patchdashboard/html/css/bootstrap.min.css" rel="stylesheet">
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

    <div class="container-fluid">
      <div class="row">
        <div class="col-sm-3 col-md-3">
            <div class="panel-group" id="accordion">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <a data-toggle="collapse" data-parent="#accordion" href="#collapseOne"><span class="glyphicon glyphicon-home">
                            &nbsp;&nbsp;</span>Main</a>
                        </h4>
                    </div>
                    <div id="collapseOne" class="panel-collapse collapse in">
                        <div class="panel-body">
                            <table class="table">
                                <tr>
                                    <td>
                                        <span class="glyphicon glyphicon-warning-sign text-primary"></span>&nbsp;&nbsp;<a href="#">Patches</a>
										<span class="badge">42</span>
                                    </td>
                                </tr>
								<tr>
                                    <td>
                                        <span class="glyphicon glyphicon-user text-primary"></span>&nbsp;&nbsp;<a href="#">Manage Profile</a>
                                    </td>
                                </tr>
						   </table>
                        </div>
                    </div>
                </div>
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h4 class="panel-title">
                            <a data-toggle="collapse" data-parent="#accordion" href="#collapseTwo"><span class="glyphicon glyphicon-wrench">
                            &nbsp;&nbsp;</span>Admin</a>
                        </h4>
                    </div>
                    <div id="collapseTwo" class="panel-collapse collapse">
                        <div class="panel-body">
                            <table class="table">
                                <tr>
                                    <td>
                                        <span class="glyphicon glyphicon-eye-open text-primary"></span>&nbsp;&nbsp;<a href="#">Add User</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <span class="glyphicon glyphicon-star text-primary"></span>&nbsp;&nbsp;<a href="#">Manage Users</a>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <span class="glyphicon glyphicon-hdd text-primary"></span>&nbsp;&nbsp;<a href="#">Manage Servers</a>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-9 col-md-9">
          <h1 class="page-header">Patches</h1>
          <h2 class="sub-header">Servers Needing patches</h2>
          <div class="error-template">
                <img src='<?php echo BASE_PATH;?>img/offline.png' title="Site offline" alt="The site is offline. Try again later, or contact the admin."/>
                <h1>
                    OFFLINE</h1>
                <h2>
                    PatchDashboard is currently offline</h2>
                <div class="error-details">
                    I'm sorry, but right now, the site is offline.  If you continue to experience issues, please contact the site administrator.
                </div>
            </div>
        </div>
      </div>
	  <div id="footer" align="center">&&copy; 2014 <?php echo YOUR_COMPANY;?></div>
    </div>
    <script src="<?php echo BASE_PATH; ?>js/jquery.min.js"></script>
    <script src="<?php echo BASE_PATH; ?>js/bootstrap.min.js"></script>
  </body>
</html>