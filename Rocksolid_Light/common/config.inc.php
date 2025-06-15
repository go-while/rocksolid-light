<?php
ini_set('error_reporting', E_ERROR); // show no errors at all, only log them

$backtrace = debug_backtrace();
$parent = isset($backtrace[0]['file']) ? $backtrace[0]['file'] : 'Direct execution';
echo "[common/config.inc.php included by: " . basename($parent) . "]<br>\n";

$config_dir = "/etc/rslight/"; // TODO FIXME LATER: NEEDS /!? REMOVE HARDCODED PATH AND REPLACE WITH PLACEHOLDER AFTER TESTING <config_dir>
$spooldir = "/var/spool/rslight"; // TODO FIXME LATER: NEEDS NO /!? REMOVE HARDCODED PATH AND REPLACE WITH PLACEHOLDER AFTER TESTING <spooldir>
$config_file = $config_dir.'rslight.inc.php';
$CONFIG = require_once($config_file); // an ARRAY with configuration settings
$language_dir = $config_dir . 'inc/lang/';
$default_language = $language_dir . "english.lang";
$file_language = $language_dir . $CONFIG['language'] . ".lang";
$keyfile = $spooldir . '/keys.dat';
$lib_files = [
    "security.inc.php",
    "functions.inc.php",
    "types.inc.php",
    "thread.inc.php",
    "message.inc.php",
    "post.inc.php",
    "database_optimizer.php",
    "allowed_languages.inc.php"
];
foreach ($lib_files as $lib_file) {
  $lib_path = $config_dir . 'inc/' . $lib_file;
  if (!file_exists($lib_path)) {
      die("Critical Error: Required library file '$lib_file' not found in '$lib_path'");
  }
  // Include each library file
  if (!is_readable($lib_path)) {
      die("Critical Error: Required library file '$lib_file' is not readable in '$lib_path'");
  }
  require_once($lib_path);
}


// Include logging control functions
require_once(__DIR__ . '/../rocksolid/logging_control.php');

$keys = secure_unserialize($keyfile, [], false);
if ($keys === false) {
    die("Critical Error: Cannot load keys file securely");
}

// load the english language definitions first because some of the other
// definitions may be incomplete
if (!file_exists($default_language)) {
    die("Critical Error: Default language file '$default_language' not found");
}
require_once($default_language);
//require_once(file_exists($file_language) ? $file_language : $default_language);
require_once($file_language);
echo "[rocklight/lib/config.inc.php: language file loaded: $file_language]<br>\n";
$title = $CONFIG['title_full']; // TODO WHY HERE?

define('RSLIGHT_CONFIG_LOADED');

if (!defined('CRON_CONTEXT')) {
    if (isset($_GET['page'])) {
        // load pages/pages.php
        require_once(__DIR__ . '/../pages/pages.php');
    }
}
?>
