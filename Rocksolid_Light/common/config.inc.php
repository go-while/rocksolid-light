<?php
ini_set('error_reporting', 0); // show no errors at all, only log them
$backtrace = debug_backtrace();
$parent = isset($backtrace[0]['file']) ? $backtrace[0]['file'] : 'Direct execution';
echo "[common/config.inc.php included by: " . basename($parent) . "]<br>\n";

$config_dir = "/etc/rslight/"; // TODO FIXME LATER: NEEDS /!? REMOVE HARDCODED PATH AND REPLACE WITH PLACEHOLDER AFTER TESTING <config_dir>
$spooldir = "/var/spool/rslight"; // TODO FIXME LATER: NEEDS NO /!? REMOVE HARDCODED PATH AND REPLACE WITH PLACEHOLDER AFTER TESTING <spooldir>
$config_file = $config_dir.'rslight.inc.php';
$CONFIG = require_once($config_file);
$title = $CONFIG['title_full']; // TODO WHY HERE?
?>
