<?php
session_start();
$index_check = "active";
include 'lib/db_config.php';
/*
 * @author Jon Harris
 * @license Apache License v2.0
 */
# Will add Offline feature later
#define("OFFLINE", false);
#if (OFFLINE == true){
#    include 'inc/offline.inc.php';
#}

$plugin_dir = ltrim(BASE_PATH."plugins",'/');
$link = mysql_connect(DB_HOST,DB_USER,DB_PASS);
mysql_select_db(DB_NAME,$link);
$plugin_installed_sql = "SELECT pm.page_name as name, p.name as plugin_name,p.glyph as plugin_glyph,pm.glyph as page_glyph,pm.on_navbar as on_navbar FROM page_maps pm LEFT JOIN plugins p ON pm.plugin_parent = p.id where p.installed = 1 and p.disabled = 0 and p.is_admin=0";
$plugin_installed_sql_res = mysql_query($plugin_installed_sql);
$allowed_pages = array();
$navbar_array = array();
while ($plugin_row = mysql_fetch_assoc($plugin_installed_sql_res)){
   # include "$plugin_dir";
    $plugin_name = $plugin_row['plugin_name'];
    $navbar_array[$plugin_name]['glyph'] = $plugin_row['plugin_glyph'];
    $is_on_navbar = $plugin_row['on_navbar'];
    if ($is_on_navbar == "1"){
        $navbar_array[$plugin_name]['page_and_glyph'][] = $plugin_row['name'].",".$plugin_row['page_glyph'];
    }
    $allowed_pages[] = $plugin_row['name'];
}
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == true){
#    $allowed_pages[] = "admin";
    $admin_plugins_sql = "SELECT pm.page_name as name, p.name as plugin_name,p.glyph as plugin_glyph,pm.glyph as page_glyph,pm.on_navbar as on_navbar FROM page_maps pm LEFT JOIN plugins p ON pm.plugin_parent = p.id where p.installed = 1 and p.disabled = 0 and p.is_admin=1";
    $admin_plugins_res = mysql_query($admin_plugins_sql);
    while ($admin_plugins_row = mysql_fetch_assoc($admin_plugins_res)){
        $plugin_name = $admin_plugins_row['plugin_name'];
        $navbar_array[$plugin_name]['glyph'] = $admin_plugins_row['plugin_glyph'];
        $is_on_navbar = $admin_plugins_row['on_navbar'];
        if ($is_on_navbar == "1"){
            $navbar_array[$plugin_name]['page_and_glyph'][] = $admin_plugins_row['name'].",".$admin_plugins_row['page_glyph'];
        }
        $allowed_pages[] = $admin_plugins_row['name'];
    }
}
$requested_page = filter_input(INPUT_GET,'page',FILTER_SANITIZE_STRING);
if (!isset($requested_page) || is_null($requested_page) || empty($requested_page)){
    $requested_page = "patches";
}
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] != true){
    include 'inc/login.inc.php';
    exit();
}
if (!in_array($requested_page, $allowed_pages)){
    include 'inc/404_header.inc.php';
    include 'inc/navbar.inc.php';
    include 'inc/404_body.inc.php';
    include 'inc/404_footer.inc.php';
}
else{
    $url_sql = "SELECT p.name as plugin_name,pm.real_file as plugin_file FROM page_maps pm LEFT JOIN plugins p ON p.id = pm.plugin_parent WHERE pm.page_name = '$requested_page' LIMIT 1;";
    $url_res = mysql_query($url_sql);
    if (mysql_num_rows($url_res) == 0){
        include 'inc/404_header.inc.php';
        include 'inc/navbar.inc.php';
        include 'inc/404_body.inc.php';
        include 'inc/404_footer.inc.php';
    }
    else{
        while ($url_row = mysql_fetch_assoc($url_res)){
        $final_plugin = $url_row['plugin_name'];
        $file = $url_row['plugin_file'];
        }
        #print_r($url_row);
        #$plugin = $url_row['plugin_name'];
        #$file = $url_row['plugin_file'];
        mysql_close($link);
        include 'inc/header.inc.php';
        include 'inc/navbar.inc.php';
        include "plugins/${final_plugin}/$file";
        include 'inc/footer.inc.php';
    }
}
