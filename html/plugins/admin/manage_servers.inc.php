<?php
/*
 * Fail-safe check. Ensures that they go through the main page (and are authenticated to use this page
 */
if (!isset($index_check) || $index_check != "active"){
    exit();
}
$link = mysql_connect(DB_HOST,DB_USER,DB_PASS);
mysql_select_db(DB_NAME,$link);
$sql = "SELECT * FROM servers;";
$res = mysql_query($sql);
$table = "";
$distro_array = array();
$distro_map_sql = "SELECT d.distro_name as distro_name,dv.version_num as version_num, dv.id as version_id,d.id as distro_id FROM distro_version dv LEFT JOIN distro d on d.id=dv.distro_id;";
$distro_map_res = mysql_query($distro_map_sql);
while ($distro_map_row = mysql_fetch_assoc($distro_map_res)){
    $distro_array[$distro_map_row['distro_id']][$distro_map_row['version_id']] = str_replace("_"," ",$distro_map_row['distro_name']." ".$distro_map_row['version_num']);
}
while ($row = mysql_fetch_assoc($res)){
    $id = $row['id'];
    $server_name = $row['server_name'];
    $server_alias = $row['server_alias'];
    $server_group = $row['server_group'];
    $distro_id = $row['distro_id'];
    $server_ip = $row['server_ip'];
    $distro_version = $row['distro_version'];
    $distro_name = $distro_array[$distro_id][$distro_version];
    $client_key = $row['client_key'];
    $trusted = $row['trusted'];
    if ($trusted == 0){
        $trust = "NO";
    }
    else{
        $trust = "YES";
    }
    $last_seen = $row['last_seen'];
    if ($last_seen == "0000-00-00 00:00:00"){
        $last_seen = "Never";
    }
    
        if ($trusted == 1){
                $active_action = "<a href='".BASE_PATH."plugins/admin/deactivate_server.inc.php?id=$id'>Deactivate/Distrust</a>";
        }
        else{
                $active_action = "<a href='".BASE_PATH."plugins/admin/activate_server.inc.php?id=$id'>Reactivate/Trust</a>";
        }
    $table .="                          <tr>
					<td><span title=$server_name>$server_alias</span></td>
					<td>$server_group</td>
                                        <td>$distro_name</td>
                                        <td>$server_ip</td>
                                        <td>$trust</td>
                                        <td>$last_seen</td>
                                        <td><a href='".BASE_PATH."edit_server?id=$id'>Edit</a> | $active_action | <a href='".BASE_PATH."plugins/admin/delete_server.inc.php?id=$id'>Delete</a></td>
                                </tr>
";
}
?>
          <h1 class="page-header">All Servers</h1>
        <div class="container">
          <div class="table-responsive">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th>Server Name (Alias)</th>
                  <th>Server Group</th>
                  <th>Distro</th>
                  <th>Server IP</th>
                  <th>Trusted?</th>
                  <th>Last Check-in</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
<?php echo $table;?>
              </tbody>
            </table>
          </div>
        </div>
