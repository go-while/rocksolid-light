<?php
/*
$config_dir = "/etc/rslight/"; // TODO FIXME LATER: NEEDS /!? REMOVE HARDCODED PATH AND REPLACE WITH PLACEHOLDER AFTER TESTING <config_dir>
$spooldir = "/var/spool/rslight"; // TODO FIXME LATER: NEEDS NO /!? REMOVE HARDCODED PATH AND REPLACE WITH PLACEHOLDER AFTER TESTING <spooldir>
$config_file = $config_dir.'rslight.inc.php';
$CONFIG = include $config_file;
$title = $CONFIG['title_full']; // TODO WHY HERE?
*/
//if(isset($config_name)) {die("common/config.inc.php loading alternate config file: $config_file<br>\n");}

/* OLD config.inc.php KEEP FOR REFERENCE */
/* Location of configuration and spool */

$config_dir = "/etc/rslight/"; // TODO FIXME LATER: NEEDS /!? REMOVE HARDCODED PATH AND REPLACE WITH PLACEHOLDER AFTER TESTING <config_dir>
$spooldir = "/var/spool/rslight"; // TODO FIXME LATER: NEEDS NO /!? REMOVE HARDCODED PATH AND REPLACE WITH PLACEHOLDER AFTER TESTING <spooldir>

if(isset($config_name) && file_exists($config_dir.$config_name.'.inc.php')) {
  $config_file = $config_dir.$config_name.'.inc.php';
  die("alternate config file: $config_file\n");

} else {
  $config_file = $config_dir.'rslight.inc.php';
  echo "Using default config file: $config_file\n";
}
// Include main config file for rslight
$CONFIG = include $config_file;

$title = $CONFIG['title_full'];

if(!file_exists($config_dir.'/DEBUG')) {
  ini_set('error_reporting', E_ERROR );
}

?>
