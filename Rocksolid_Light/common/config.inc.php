<?php
ini_set('error_reporting', E_ERROR); // show no errors at all, only log them

$backtrace = debug_backtrace();
$parent = isset($backtrace[0]['file']) ? $backtrace[0]['file'] : 'Direct execution';
echo "[common/config.inc.php included by: " . basename($parent) . "]<br>\n";

$config_dir = "/etc/rslight"; // TODO FIXME LATER: NEEDS /!? REMOVE HARDCODED PATH AND REPLACE WITH PLACEHOLDER AFTER TESTING <config_dir>
$spooldir = "/var/spool/rslight"; // TODO FIXME LATER: NEEDS NO /!? REMOVE HARDCODED PATH AND REPLACE WITH PLACEHOLDER AFTER TESTING <spooldir>

// For development/testing, check if local rslight directory exists
if (!is_dir($config_dir) && is_dir(__DIR__ . '/../rslight')) {
    $config_dir = __DIR__ . '/../rslight';
    $spooldir = __DIR__ . '/../spool';
    echo "[common/config.inc.php: Using development paths - config_dir: $config_dir, spooldir: $spooldir]<br>\n";
}

$config_file = $config_dir.'/rslight.inc.php';

// Initialize logging paths early - needed for cron and debug logging
$logdir = $spooldir . '/log';
$debug_log = $logdir . '/debug.log';
$abuse_log = $logdir . '/abuse.log';
$auth_log = $logdir . '/auth.log';
$mail_log = $logdir . '/mail.log';

// Initialize config name for logging - used by many scripts
$config_name = basename(getcwd());
if (empty($config_name)) {
    $config_name = 'rslight'; // fallback for cases where getcwd() fails
}

// Ensure log directory exists
@mkdir($logdir, 0755, true);

// Check if the configuration file exists and is readable
if (!file_exists($config_file)) {
    die("Critical Error: Configuration file '$config_file' not found");
}
if (!is_readable($config_file)) {
    die("Critical Error: Configuration file '$config_file' is not readable");
}
$CONFIG = include($config_file); // an ARRAY with configuration settings
if(!is_array($CONFIG)) {
    die("Critical Error: Configuration file '$config_file' does not return an array");
}

$language_dir = $config_dir . '/inc/lang/';
$keyfile = $spooldir . '/keys.dat';
$lib_files = [
    "security.inc.php",
    "functions.inc.php",
    "types.inc.php",
    "thread.inc.php",
    "message.inc.php",
    "post.inc.php",
    "database_optimizer.php",
    "allowed_languages.inc.php",
    "logging_control.php",
    "overrides.inc.php"
];
$OVERRIDES = array();

// load all library files from the inc/ directory
if (!is_dir($config_dir . '/inc/')) {
    die("Critical Error: Library directory '$config_dir/inc/' does not exist");
}
if (!is_readable($config_dir . '/inc/')) {
    die("Critical Error: Library directory '$config_dir/inc/' is not readable");
}
foreach ($lib_files as $lib_file) {
  $lib_path = $config_dir . '/inc/' . $lib_file;
  if (!file_exists($lib_path)) {
      if ($lib_file === 'overrides.inc.php') {
          echo "[rocksolid/lib/config.inc.php: not found '$lib_file']<br>\n";
          // If overrides.inc.php is not found, we can skip it
          continue;
      }
      die("Critical Error: Required library file '$lib_file' not found in '$lib_path'");
  }
  // Include each library file
  if (!is_readable($lib_path)) {
      die("Critical Error: Required library file '$lib_file' is not readable in '$lib_path'");
  }
  require_once($lib_path);
}

$keys = secure_unserialize($keyfile, [], false);
if ($keys === false) {
    die("Critical Error: Cannot load keys file securely");
}

$languages = get_allowed_languages();
// Check if the configured language is in the allowed languages
if (!in_array($CONFIG['language'], $languages)) {
    // If not, use the default language
    $CONFIG['language'] = 'english';
    $file_language = $default_language;
}
$file_language = $language_dir . $CONFIG['language'] . '.lang';
if (!file_exists($file_language)) {
    // If the specific language file does not exist, fall back to English
    $file_language = $language_dir . 'english.lang';
}

if(!file_exists($file_language)) {
    echo "Critical Error: Language file '$file_language' cfg='".$CONFIG['language']."'='".strlen($CONFIG['language'])."' not found<br>\n";
}
require_once($file_language);
echo "[rocklight/lib/config.inc.php: language file loaded: $file_language]<br>\n";
$title = $CONFIG['title_full']; // TODO WHY HERE?

define('RSLIGHT_CONFIG_LOADED', true); // Define a constant to indicate that the configuration has been loaded



if (!defined('CRON_CONTEXT')||CRON_CONTEXT === false) {
  // Always load the router system when not in cron context
  require_once(__DIR__ . '/../pages/pages.php');

  if (isset($_GET['page'])) {
      // Router will handle the page automatically
      exit(0); // Exit after loading the page
  }

  // If no page parameter, serve default index page
  if (function_exists('rslight_serve_default_page')) {
    if (rslight_serve_default_page()) {
      exit(); // Default page served successfully
    }
  }

  // If serving fails, we fail hard. no more legacy code support!
  die("Error: common/config.inc.php: No page parameter provided and default page serving failed. Please check your configuration.");
}

// If this is a cron context, we do not load the pages
// but only the configuration and libraries
echo "[rocksolid/lib/config.inc.php: Cron context detected, skipping page loading]<br>\n";
// You can add more cron-specific logic here if needed
// For example, you might want to initialize some cron-specific settings or variables

?>
