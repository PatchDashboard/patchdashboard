#!/usr/bin/php
<?php
include '/opt/patch_manager/db_config.php';
$server_name = filter_var($_SERVER['argv'][1],FILTER_SANITIZE_MAGIC_QUOTES);
$data = filter_var($_SERVER['argv'][2],FILTER_SANITIZE_MAGIC_QUOTES);
$supression_list = filter_var($_SERVER['argv'][3],FILTER_SANITIZE_MAGIC_QUOTES);
$link = mysql_connect(DB_HOST,DB_USER,DB_PASS);
mysql_select_db(DB_NAME,$link);
$package_array = explode("\n",$data);
$supression_array = explode(" ",$supression_list);
foreach($package_array as $val){
	$tmp_array = explode(":::",$val);
	$package_name = $tmp_array[0];
	$package_from = $tmp_array[1];
	$package_to = $tmp_array[2];
	$bug_curl = shell_exec("bash -c \"curl -s http://www.ubuntuupdates.org/bugs?package_name=$package_name|grep '<td>' 2>/dev/null|head -1\"");
	$url = str_replace("<td><a href='","",$bug_curl);
	$url_array = explode("'",$url);
	$the_url = $url_array[0];
        $urgency_curl = shell_exec("bash -c \"curl http://www.ubuntuupdates.org/package/core/precise/main/updates/$package_name|grep '$package_to'|grep 'urgency='\"");
	if (stristr($urgency_curl,"emergency")){
		$urgency = "emergency";
	}
	elseif (stristr($urgency_curl,"high")){
		$urgency = "high";
	}
	elseif (stristr($urgency_curl,"medium")){
		$urgency = "medium";
	}
	elseif (stristr($urgency_curl,"low")){
                $urgency = "low";
        }
	else{
		$urgency = "unknown";
	}
	if (!in_array($package_name,$supression_array)){
		$sql = "INSERT INTO patches(server_name,package_name,current,new,urgency,bug_url) VALUES('$server_name','$package_name','$package_from','$package_to','$urgency','$the_url');";
		mysql_query($sql);
	}
}
mysql_close($link);
?>
