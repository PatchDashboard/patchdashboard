<?php
if (!empty($_SERVER['HTTPS'])){
    $protocol = 'https://';
}
else{
    $protocol = 'http://';
}
if ($protocol == 'https://'){
    $advice = "<br /><br /><h3><p style='color:green'> Because you're using HTTPS, we advise using the pull method.</h3></p>";
    $curl_cmd = "curl -s -k";
}
else{
    $advice = "<br /><br /><h3><p style='color:red'> Because you're not using HTTPS, we <strong>HIGHLY</strong> advise against the pull method.</h3></p>";
    $curl_cmd = "curl";
}
include '../lib/db_config.php';
$SERVER_URI = $protocol.$_SERVER['HTTP_HOST'].BASE_PATH;
?>
        <div class="col-sm-9 col-md-9">
          <div class="error-template">
                <h1>Adding a server</h1>
                <h2>Howto:</h2>
                <div class="error-details">
                    <p>To add a server, you have 2 options -- The Push method, and The Pull method.</p>
                    <p>The Push method is a little more in-depth, but more secure if you aren't running this site on HTTPS<br />
                        Simply put, you would merely do this from the PatchDashboard server:</p>
                    <pre>/opt/patch_manager/add_server.sh</pre>
                    <p>It will ask you some questions, then instruct you on how to complete the setup on each node (controlled server).</p>
                    <?php echo $advice;?>
                    <br/><br/><p>The easier method -- the Pull Method, if you are running this via HTTPS, or you implicitly trust all traffic on your network (from each guest machine/node):</p>
                    <pre>sudo -i
<?php echo $curl_cmd;?> <?php echo "${SERVER_URI}client/client_installer.php";?> | bash</pre>
                    <br/><br/><br/><p>We do not ever plan on allowing the addition of servers via the web interface.  We belive that keeping the trust (Pull method) or managing the shared keys (Push method) should <strong><i>only</i></strong> be done via the terminal.</p>
                </div>
                <div class="error-actions">
                    <a href="<?php echo BASE_PATH;?>" class="btn btn-primary btn-lg"><span class="glyphicon glyphicon-home"></span>Take Me Home </a>
                </div>
            </div>
        </div>
