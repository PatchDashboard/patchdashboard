#!/usr/bin/php
<?php
$password = $_SERVER['argv'][1];
$hash = $_SERVER['argv'][2];
echo hash('sha256',$password.$hash);
?>