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
while ($row = mysql_fetch_assoc($res)){
    $id = $row['id'];
    $server_name = $row['server_name'];
    $distro_id = $row['distro_id'];
    $server_ip = $row['server_ip'];
    $distro_version = $row['distro_version'];
    $client_key = $row['client_key'];
    $trusted = $row['trusted'];
    $last_seen = $row['last_seen'];
    if ($last_seen == "0000-00-00 00:00:00"){
        $last_seen = "Never";
    }
    
        if ($active == 1){
                $active_action = "<a href='".BASE_PATH."plugins/admin/deactivate_user.inc.php?id=$id'>Deactivate</a>";
        }
        else{
                $active_action = "<a href='".BASE_PATH."plugins/admin/activate_user.inc.php?id=$id'>Reactivate</a>";
        }
    $table .="                          <tr>
                                        <td>$username</td>
                                        <td>$email</td>
                                        <td>$group</td>
                                        <td>$last_seen</td>
                                        <td>$alerts</td>
                                        <td><a href='".BASE_PATH."edit_user?id=$id'>Edit</a> | $active_action | <a href='".BASE_PATH."plugins/admin/delete_user.inc.php?id=$id'>Delete</a></td>
                                </tr>
";
}
?>
          <h1 class="page-header">List Users</h1>
        <div class="container">
          <div class="table-responsive">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th>Username</th>
                  <th>E-mail</th>
                  <th>Group</th>
                  <th>Last Login</th>
                  <th>Alerts?</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
<?php echo $table;?>
              </tbody>
            </table>
          </div>
        </div>