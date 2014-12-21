<?php
/*
 * Fail-safe check. Ensures that they go through the main page (and are authenticated to use this page
 */
if (!isset($index_check) || $index_check != "active") {
    exit();
}
$id = filter_input(INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT);
if (!isset($id) || empty($id) || !is_numeric($id)) {
    $_SESSION['error_message'] = "Invalid user ID";
    ?>
    <div class="col-sm-9 col-md-9">
        <h3 class="text-center login-title">INVALID SERVER</h3>
        <div class="account-wall">
            Please try again. <a href="javascript:history.back()">Back</a>
        </div>
    </div>
    <?php
} else {
    $link_edit_user = mysql_connect(DB_HOST, DB_USER, DB_PASS);
    mysql_select_db(DB_NAME, $link_edit_user);
    $distro_map_sql = "SELECT d.distro_name as distro_name,dv.version_num as version_num, dv.id as version_id,d.id as distro_id FROM distro_version dv LEFT JOIN distro d on d.id=dv.distro_id;";
    $distro_map_res = mysql_query($distro_map_sql);
    $select_html = "<select class='form-control custom' name='distro_ver_id'>";

    $sql_edit_server = "SELECT * FROM `servers` WHERE id=$id limit 1;";
    $res_edit_server = mysql_query($sql_edit_server);
    $row_edit_server = mysql_fetch_array($res_edit_server);
    $id = $row['id'];
    $server_name = $row['server_name'];
    $distro_id_main = $row['distro_id'];
    $server_ip = $row['server_ip'];
    $distro_version_main = $row['distro_version'];
        while ($distro_map_row = mysql_fetch_assoc($distro_map_res)){
        $distro_id = $distro_map_row['distro_id'];
        $distro_ver_id = $distro_map_row['verson_id'];
        $distro_ver_name = str_replace("_"," ",$distro_map_row['distro_name']." ".$distro_map_row['version_num']);
        if ("${distro_id}-${distro_ver_id}" == "${distro_id_main}-${distro_ver_main}"){
            $select_html .= "\t\t\t\t\t<option value='${distro_id}-${distro_ver_id}' selected='selected'>$distro_ver_name</option>\n";
        }
        else{
            $select_html .= "\t\t\t\t\t<option value='${distro_id}-${distro_ver_id}'>$distro_ver_name</option>\n";
        }
        $distro_array[$distro_map_row['distro_id']][$distro_map_row['version_id']] = $distro_ver_name;
    }
    $select_html .= "\t\t\t\t</select>";
    $distro_name = $distro_array[$distro_id_main][$distro_version_main];
    $client_key = $row['client_key'];
    $trusted = $row['trusted'];
    if ($seen == "0000-00-00 00:00:00") {
        $last_seen = "Never";
    } else {
        $last_seen = $seen;
    }
    ?>
    <div class="col-sm-9 col-md-9">
        <h3 class="text-center login-title">Edit User (<?php echo $username; ?>)</h3>
        <div class="account-wall">
            <form id ="editUser" method="POST" action="<?php echo BASE_PATH; ?>plugins/admin/p_edit_user.inc.php"><input type="hidden" name="id" value="<?php print $id; ?>" />
                <div class="form-group"><label class="col-sm-5 control-label">Last Login</label><div class="col-sm-5"><input type="text" value="<?php echo $last_seen; ?>" class="form-control" readonly /></div></div>
                <div class="form-group"><label class="col-sm-5 control-label">Username</label><div class="col-sm-5"><input type="text" name="username" value="<?php echo $username; ?>" class="form-control" readonly /></div></div>
                <div class="form-group"><label class="col-sm-5 control-label">Display Name</label><div class="col-sm-5"><input value="<?php echo $display_name; ?>" type="text" name="display_name" class="form-control" placeholder="Nickname/Real Name" required autofocus ></div></div>
                <div class="form-group"><label class="col-sm-5 control-label">Password (Leave blank for no change)</label><div class="col-sm-5"><input type="password" name="password" class="form-control" placeholder="Password" /></div></div>
                <div class="form-group"><label class="col-sm-5 control-label">Confirm Password (Leave blank for no change)</label><div class="col-sm-5"><input type="password" name="confirmPassword" class="form-control" placeholder="Retype Password" /></div></div>
                <div class="form-group"><label class="col-sm-5 control-label">E-Mail Address</label><div class="col-sm-5"><input value="<?php echo $email_address; ?>" type="text" name="email" class="form-control" placeholder="E-mail Address" required ></div></div>
                <div class="form-group"><label class="col-sm-5 control-label">Are they an Admin?</label><div class="col-sm-5"><input type="checkbox" name="is_admin" class="form-control" <?php echo $admin_checked; ?>> </div></div>
                <div class="form-group"><label class="col-sm-5 control-label">Receive Alerts?</label><div class="col-sm-5"><input type="checkbox" name="alerts" class="form-control" <?php echo $alerts_checked; ?>></div></div>
                <div class="form-group"><label class="col-sm-5 control-label"></label><div class="col-sm-5"><button class="btn btn-lg btn-primary btn-block" type="submit">Edit User</button></div></div>
                <div class="form-group"><label class="col-sm-5 control-label"></label><div class="col-sm-5"><label class="checkbox pull-left"></label></div></div>
            </form>
        </div>
    </div>
    <?php
}