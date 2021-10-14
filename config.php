<?php
error_reporting(E_ALL);
ini_set('display_errors', 'Off');

ob_start();
session_start();

$dir_root = $_SERVER['SCRIPT_FILENAME'];
$fileNameExt = basename($dir_root);
$dir_root = explode('/'.$fileNameExt, $dir_root)[0];
$_SERVER['DOCUMENT_ROOT'] = $dir_root;
$session_id = session_id();

$host = '0.0.0.0';
$port = 8090;
?>