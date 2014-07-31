<?php
 include 'inc/supressed_patches.inc.php';
 $supressed = array("nadda");
 foreach($supressed as $val){
	$supressed_list .= " '$val'";
 }
	$supressed_list = str_replace("' '","', '",$supressed_list);
 $link = mysql_connect(DB_HOST,DB_USER,DB_PASS);
 mysql_select_db(DB_NAME,$link);
 $nsupressed_sql = "select count(distinct(server_name)) as total from patches where package_name NOT IN($supressed_list) and package_name != '';";
 $nsupressed_res = mysql_query($nsupressed_sql);
 $nsupressed_row = mysql_fetch_array($nsupressed_res);
 $nsupressed_total = $nsupressed_row['total'];
 $sql1 = "select * from servers;";
 $res1 = mysql_query($sql1);
 $table = "";
 $total_count = 0;
 $server_count = 0;
 while ($row1 = mysql_fetch_assoc($res1)){
     $server_count++;
     $server_name = $row1['server_name'];
	 $distro_id = $row1['distro_id'];
	 $dist_sql = "SELECT * FROM distro WHERE id='$distro_id';";
	 $dist_res = mysql_query($dist_sql);
	 $dist_row = mysql_fetch_array($dist_res);
	 $dist_img = BASE_PATH.$dist_row['icon_path'];
     $sql2 = "SELECT COUNT(*) as `total` FROM patches where server_name='$server_name' and package_name NOT IN($supressed_list) and package_name != '';";
     $res2 = mysql_query($sql2);
     $row2 = mysql_fetch_array($res2);
     $count = $row2['total'];
     $total_count = $total_count + $count;
     $table .= "                <tr>
                  <td><a href='/patches/server/$server_name'><img src='$dist_img' height='32' width='32' border='0'>&nbsp;$server_name</a></td>
                  <td>$count</td>
                </tr>
";
 }
$percent_needing_upgrade = round((($nsupressed_total / $server_count)*100));
$percent_good_to_go = 100 - $percent_needing_upgrade;
?>
        <div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main">

          <h1 class="page-header">Patch List</h1>
        <div class="container">
            <div class="chart">
                <div class="percentage" data-percent="<?php echo $percent_good_to_go;?>"><span><?php echo $percent_good_to_go;?></span>%</div>
                <div class="label" style="color:#0000FF">Percent of servers not needing upgrades/patches</div>
            </div>
          <div class="table-responsive">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th>Server Name (<?php echo $server_count;?> servers)</th>
                  <th>Patch Count (<?php echo $total_count;?> total patches available)</th>
                </tr>
              </thead>
              <tbody>
<?php echo $table;?>
              </tbody>
            </table>
          </div>
        </div>
