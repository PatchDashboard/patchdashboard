<?php
/* 
 * @copyright 2014 GlobalScape
 * @author Jon Harris
 * All rights reserved.
 */
#echo "test";
#include 'lib/config.inc.php';
#if (OFFLINE == true){
#    include 'inc/offline_header.inc.php';
#    include 'inc/offline_navbar.inc.php';
#    include 'inc/offline_body.inc.php';
#    include 'inc/offline_footer.inc.php';
#}
$allowed_pages = array("main","print_report","gen_report", "list_reports","preferences","delete_report","graph_view","patches","patch_list","packages","search");
#$requested_page = filter_var($_GET['page'],FILTER_SANITIZE_MAGIC_QUOTES)
$requested_page = filter_input(INPUT_GET,'page',FILTER_SANITIZE_STRING);
if (!isset($requested_page) || is_null($requested_page) || empty($requested_page)){
    $requested_page = "patches";
}
if (!in_array($requested_page, $allowed_pages)){
    include 'inc/404_header.inc.php';
    include 'inc/404_navbar.inc.php';
    include 'inc/404_body.inc.php';
    include 'inc/404_footer.inc.php';
}
else{
    include 'lib/db.inc.php';
    include 'inc/header.inc.php';
    include 'inc/navbar.inc.php';
    include "inc/$requested_page.inc.php";
    include 'inc/footer.inc.php';
}
/** For testing
 * Remove once complete, and uncomment the code above
 */
/*include 'inc/header.inc.php';
include 'inc/navbar.inc.php';
include 'inc/reports.inc.php';
include 'inc/footer.inc.php';*/
