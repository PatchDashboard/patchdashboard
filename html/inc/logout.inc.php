<?php
session_start();
session_unset();
include '../lib/db_config.php';
header("location:".BASE_PATH);