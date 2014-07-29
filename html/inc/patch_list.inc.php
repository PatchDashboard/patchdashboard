<?php
 include 'inc/supressed_patches.inc.php';
 $link = mysql_connect(DB_HOST,DB_USER,DB_PASS);
 mysql_select_db(DB_NAME,$link);
 $server_name = filter_var($_GET['server'],FILTER_SANITIZE_MAGIC_QUOTES);
 $sql1 = "select * from patches where server_name='$server_name';";
 $res1 = mysql_query($sql1);
$apt_cmd = "apt-get -y install";
 while ($row1 = mysql_fetch_assoc($res1)){
     $package_name = $row1['package_name'];
     $package_name_orig = $package_name;
     if (in_array($package_name,$supressed)){
	$package_name .= " <strong>(SUPRESSED)</strong>";
     }
     else{
	$apt_cmd .= " $package_name";
     }
     $current = $row1['current'];
     $new = $row1['new'];
     $urgency = $row1['urgency'];
     $bug_url = $row1['bug_url'];
	if ($bug_url != ''){
		if (stristr($bug_url,'debian')){
			$url_array = explode("/",$bug_url);
			$cve = end($url_array);
			$url = "<td><a href='$bug_url' style='color:black'>Debian $cve</a></td>";
		}
		else{
			$url_array = explode("/",$bug_url);
			$bug = end($url_array);
			$url = "<td><a href='$bug_url' style='color:black'>Launchpad Bug #$bug</a></td>";
		}
	}
     if (in_array($urgency,array('high','emergency'))){
		$urgency = "<td style='color:red'><a href='http://www.ubuntuupdates.org/package/core/precise/main/updates/$package_name_orig' style='color:red' target='_blank'>$urgency</a></td>";
     }
     elseif ($urgency == "medium"){
		$urgency = "<td style='color:#FF8C00'><a href='http://www.ubuntuupdates.org/package/core/precise/main/updates/$package_name_orig' style='color:#FF8C00' target='_blank'>medium</a></td>";
     }
     elseif ($urgency == "low") {
		$urgency = "<td><a href='http://www.ubuntuupdates.org/package/core/precise/main/updates/$package_name_orig' style='color:black' target='_blank'>$urgency</a></td>";
     }
     else{
		$urgency = "<td>$urgency</td>";
     }
     $table .= "                <tr>
                  <td><a href='/search/exact/$package_name_orig' style='color:green'>$package_name</a></td>
		  <td>$current</td>
                  <td>$new</td>
		  $urgency
		  $url
                </tr>
";
 }
if ($apt_cmd == "apt-get -y install"){
	$apt_cmd = "";
}
else{
	$apt_cmd = "<code>$apt_cmd</code>";
}
?>
        <div class="col-sm-9 col-sm-offset-3 col-md-10 col-md-offset-2 main">

          <h1 class="page-header">Patch List</h1>
          <h2 class="sub-header"><?php echo $server_name;?>(<a href="/packages/server/<?php echo $server_name;?>">List all installed packages</a>)</h2>
          <div class="table-responsive">
            <table class="table table-striped">
              <thead>
                <tr>
                  <th>Package Name</th>
                  <th>Current Version</th>
                  <th>New Version</th>
                  <th>Urgency Level</th>
		  <th>Bug Report Name/Page</th>
                </tr>
              </thead>
              <tbody>
<?php echo $table;?>
              </tbody>
            </table>
<?php echo $apt_cmd;?>
          </div>
