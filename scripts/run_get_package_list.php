#!/usr/bin/php
<?php
$server_name = filter_var($_SERVER['argv'][1],FILTER_SANITIZE_MAGIC_QUOTES);
$data = filter_var($_SERVER['argv'][2],FILTER_SANITIZE_MAGIC_QUOTES);
$link = mysql_connect("localhost",'root','*****');
mysql_select_db("reports",$link);
$package_array = explode("\n",$data);
foreach($package_array as $val){
	$tmp_array = explode(":::",$val);
	$package_name = $tmp_array[0];
	$package_version = $tmp_array[1];
	$sql = "INSERT INTO patch_allpackages(server_name,package_name,package_version) VALUES('$server_name','$package_name','$package_version');";
	mysql_query($sql) or die (mysql_error());
}
mysql_close($link);
?>
