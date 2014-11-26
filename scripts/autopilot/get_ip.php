#!/usr/bin/php
<?php
$dav = array();
$dav[11] = 197;
$dav[12] = 198;
$dav[13] = 199;
$dav[14] = 200;
$dav[15] = 201;
$dav[16] = 202;
$dav[17] = 208;
$dav[18] = 209;
$dav[19] = 213;
$dav[20] = 214;
if (isset($_SERVER['argv'][1]) && is_numeric($_SERVER['argv'][1]) && isset($dav[$_SERVER['argv'][1]])){
        $server = $_SERVER['argv'][1];
        echo "208.89.184.".$dav[$server];
}