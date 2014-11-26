#!/usr/bin/php
<?php
if ($_SERVER['argv'][1] == "odd"){
        $dav = array(11,13,15,17,19);
}
else{
        $dav = array(12,14,16,18,20);
}
$lowest['dav'] = 0;
$lowest['count'] = 0;

foreach ($dav as $val){
        $json = file_get_contents("https://hpAdmin:SzVNYltoV5RT1fHpZbTx@dav{$val}.tappin.com:8443/HomePipe/Communication/HealthInfo");
        $obj = json_decode($json);
        $count = $obj->{"ConnectedAgentsCount"};
        if ($lowest['dav'] == 0){
                $lowest['dav'] = $val;
                $lowest['count'] = $count;
        }
        elseif ($count < $lowest['count']){
                $lowest['dav'] = $val;
                $lowest['count'] = $count;
        }
}
echo $lowest['dav'];
