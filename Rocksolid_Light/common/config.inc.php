<?php
ini_set('error_reporting', E_ERROR); // show no errors at all, only log them

$backtrace = debug_backtrace();
$parent = isset($backtrace[0]['file']) ? $backtrace[0]['file'] : 'Direct execution';
echo "[common/config.inc.php included by: " . basename($parent) . "]<br>\n";

$config_dir = "/etc/rslight"; // TODO FIXME LATER: NEEDS /!? REMOVE HARDCODED PATH AND REPLACE WITH PLACEHOLDER AFTER TESTING <config_dir>
$spooldir = "/var/spool/rslight"; // TODO FIXME LATER: NEEDS NO /!? REMOVE HARDCODED PATH AND REPLACE WITH PLACEHOLDER AFTER TESTING <spooldir>
$config_file = $config_dir.'/rslight.inc.php';
// For router system, determine the correct section
// Since files were originally in web/spoolnews/, the config is in /etc/rslight/spoolnews/
$config_path = $config_dir . "/spoolnews/";
$script_path = $config_dir . "/scripts/";

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
echo "[common/config.inc.php: language file loaded: $file_language]<br>\n";
$title = $CONFIG['title_full']; // TODO WHY HERE?

define('RSLIGHT_CONFIG_LOADED', true); // Define a constant to indicate that the configuration has been loaded

echo "[common/config.inc.php: Configuration loaded successfully] CRON_CONTEXT=".defined('CRON_CONTEXT')."<br>\n";
/**
 * Hardcoded page mapping - NO USER INPUT PARSING
 * This is the ONLY safe way to map page names to files
 */
if (!defined('CRON_CONTEXT')) {
    echo "[common/config.inc.php: Page routing system enabled]<br>\n";
    $RSLIGHT_PAGE_MAP = [
        // Core article pages
        'article'      => 'article.php',
        'article-flat' => 'article-flat.php',
        'thread'       => 'thread.php',

        // Board and search
        'overboard'    => 'overboard.php',
        'search'       => 'search.php',
        'post'         => 'post.php',

        // User management
        'register'     => 'register.php',
        'user'         => 'user.php',
        'mail'         => 'mail.php',

        // File handling
        'files'        => 'files.php',
        'upload'       => 'upload.php',

        // Language/Demo
        'language_demo'     => 'language_demo.php',
        'language_selector' => 'language_selector.php',
        'faq'              => 'faq.php',

        // Testing/Debug
        'header_test'      => 'header_test.php',

        // Main index page
        'index'            => 'index.php'
    ];
    echo "[common/config.inc.php: Page routing system loaded]<br>\n";

    // Always load the router system when not in cron context
    // Include session/cache setup
    echo "[common/config.inc.php: Including " . $config_dir . "/inc/_session.inc.php]<br>\n";
    require($config_dir . '/inc/_session.inc.php');
    echo "[common/config.inc.php: Session and cache setup included]<br>\n";

    // Include header
    echo "[common/config.inc.php: Including " . $config_dir . "/inc/_header.inc.php]<br>\n";
    include($config_dir . '/inc/_header.inc.php');
    echo "[common/config.inc.php: Header included]<br>\n";

    // Your page routing switch
    $page = $_GET['page'] ?? 'index';
    if (!isset($RSLIGHT_PAGE_MAP[$page])){
        die("Error: Invalid page requested.");
    }
    $page_file = "../pages/" . $RSLIGHT_PAGE_MAP[$page];
    echo "loading page: $page_file<br>\n";
    if (file_exists($page_file)) {
        // Include the requested page file
        include($page_file);
        exit(0);
    } else {
        die("Error: Page file '$page_file' not found.");
    }

    // Include footer
    include($config_dir . '/inc/_footer.inc.php');
    exit(0); // Exit after including the footer
}

// If this is a cron context, we do not load the pages
// but only the configuration and libraries
echo "[rocksolid/lib/config.inc.php: Cron context detected, skipping page loading]<br>\n";
// You can add more cron-specific logic here if needed
// For example, you might want to initialize some cron-specific settings or variables

?>
