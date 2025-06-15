<?php
ini_set('error_reporting', E_ERROR); // show no errors at all, only log them

$backtrace = debug_backtrace();
$parent = isset($backtrace[0]['file']) ? $backtrace[0]['file'] : 'Direct execution';
echo "[common/config.inc.php included by: " . basename($parent) . "]<br>\n";

$config_dir = "/etc/rslight/"; // TODO FIXME LATER: NEEDS /!? REMOVE HARDCODED PATH AND REPLACE WITH PLACEHOLDER AFTER TESTING <config_dir>
$spooldir = "/var/spool/rslight"; // TODO FIXME LATER: NEEDS NO /!? REMOVE HARDCODED PATH AND REPLACE WITH PLACEHOLDER AFTER TESTING <spooldir>
$config_file = $config_dir.'rslight.inc.php';
require_once($config_dir."functions.inc.php");

$CONFIG = require_once($config_file);
$keyfile = $spooldir . '/keys.dat';

// Include logging control functions
require_once(__DIR__ . '/../rocksolid/logging_control.php');

// Handle lib includes with flexible path resolution
$lib_files = [
    "types.inc.php",
    "thread.inc.php",
    "message.inc.php",
    "post.inc.php",
    "database_optimizer.php"
];

// Calculate lib directory path relative to this file
//$newsportal_dir = __DIR__;
//$lib_dir = $newsportal_dir . '/lib';

foreach ($lib_files as $lib_file) {
    $lib_path = $lib_dir . '/' . $lib_file;
    if (file_exists($lib_path)) {
        include $lib_path;
    } elseif (file_exists("../rocksolid/lib/$lib_file")) {
        include "../rocksolid/lib/$lib_file";
    } elseif (file_exists("rocksolid/lib/$lib_file")) {
        include "rocksolid/lib/$lib_file";
    } elseif (file_exists("lib/$lib_file")) {
        include "lib/$lib_file";
    }
}


$keys = secure_unserialize($keyfile, [], false);
if ($keys === false) {
    die("Critical Error: Cannot load keys file securely");
}

$title = $CONFIG['title_full']; // TODO WHY HERE?
?>
