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
            <li <?php echo $active;?>><a href="<?php echo BASE_PATH;?>/patches">Patch list</a></li>
          </ul>
        </div>
