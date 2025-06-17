<?php
if (!defined('PRE_LOAD_DONE')) {
    ini_set('error_reporting', E_ERROR); // show no errors at all, only log them

    $backtrace = debug_backtrace();
    $parent = isset($backtrace[0]['file']) ? $backtrace[0]['file'] : 'Direct execution';
    //echo "[common/config.inc.php included by: " . basename($parent) . "]<br>\n";

    $config_dir = "/etc/rslight"; // TODO FIXME LATER: NEEDS /!? REMOVE HARDCODED PATH AND REPLACE WITH PLACEHOLDER AFTER TESTING <config_dir>
    $spooldir = "/var/spool/rslight"; // TODO FIXME LATER: NEEDS NO /!? REMOVE HARDCODED PATH AND REPLACE WITH PLACEHOLDER AFTER TESTING <spooldir>
    $config_file = $config_dir.'/rslight.inc.php';
    // For router system, determine the correct section
    // Since files were originally in web/spoolnews/, the config is in /etc/rslight/spoolnews/
    $config_path = $config_dir . "/spoolnews/";
    $script_path = $config_dir . "/scripts/";
    $rootdir = "../";
    // Initialize logging paths early - needed for cron and debug logging
    $logdir = $spooldir . '/log';
    $debug_log = $logdir . '/debug.log';
    $abuse_log = $logdir . '/abuse.log';
    $auth_log = $logdir . '/auth.log';
    $mail_log = $logdir . '/mail.log';

    $auth_inc = "/inc/auth.inc.php";
    $session_inc = $config_dir . '/inc/_session.inc.php';
    $header_inc = $config_dir . '/inc/_header.inc.php';
    $footer_inc = $config_dir . '/inc/_footer.inc.php';


    // Initialize config name for logging - used by many scripts
    //$config_name = basename(getcwd()); //  TODO FIXME SECTIONS
    if (empty($config_name)) {
        $config_name = 'rocksolid'; // fallback for cases where getcwd() fails
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
       die("Critical Error: Language file '$file_language' cfg='".$CONFIG['language']."'='".strlen($CONFIG['language'])."' not found<br>\n");
    }
    require_once($file_language);
    //echo "[common/config.inc.php: language file loaded: $file_language]<br>\n";
    $title = $CONFIG['title_full']; // TODO WHY HERE?

    define('RSLIGHT_CONFIG_LOADED', true); // Define a constant to indicate that the configuration has been loaded

    //echo "[common/config.inc.php: Configuration loaded successfully] CRON_CONTEXT=".defined('CRON_CONTEXT')."<br>\n";
} // if !defined('PRE_LOAD_DONE')

/**
 * Hardcoded page mapping - NO USER INPUT PARSING
 * This is the ONLY safe way to map page names to files
 */
$cron_context = 0;
$is_pre_load = 0;
if (defined('CRON_CONTEXT')) { $cron_context = true; }
if (defined('PRE_LOAD_CONF')) { $is_pre_load = true; }
if (defined('PRE_LOAD_CONF') && defined('PRE_LOAD_DONE') && $is_pre_load){ $is_pre_load = false; }
$noheader = false;
if (!$cron_context && !$is_pre_load && defined('RSLIGHT_CONFIG_LOADED')) {
    //echo "[common/config.inc.php: Page routing system enabled]<br>\n";
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
        'attachment'       => 'attachment.php',
        'decrypt'       => 'decrypt.php',

        // Language/Demo
        'language_demo'     => 'language_demo.php',
        'language_selector' => 'language_selector.php',
        'faq'              => 'faq.php',

        // Testing/Debug
        'header_test'      => 'header_test.php',

        // Main index page
        'index'            => 'index.php'
    ];
    // Cache settings per page type
    $cache_settings = [
        'article'           => ['expires' => 3600 * 24, 'max_age' => 3600 * 24],    // 24 hours
        'article-flat'      => ['expires' => 100, 'max_age' => 100],                // 100 seconds
        'thread'            => ['expires' => 100, 'max_age' => 100],                // 100 seconds
        'overboard'         => ['expires' => 120, 'max_age' => 120],                // 2 minutes
        'search'            => ['expires' => 120, 'max_age' => 120],                // 2 minutes
        'post'              => ['expires' => 30, 'max_age' => 30],                  // 30 seconds
        'register'          => ['expires' => 300, 'max_age' => 300],                // 5 minutes
        'user'              => ['expires' => 60, 'max_age' => 60],                  // 1 minute
        'mail'              => ['expires' => 60, 'max_age' => 60],                  // 1 minute
        'files'             => ['expires' => 300, 'max_age' => 300],                // 5 minutes
        'upload'            => ['expires' => 60, 'max_age' => 60],                  // 1 minute
        'language_demo'     => ['expires' => 3600, 'max_age' => 3600],              // 1 hour
        'language_selector' => ['expires' => 3600, 'max_age' => 3600],              // 1 hour
        'faq'               => ['expires' => 3600 * 12, 'max_age' => 3600 * 12],    // 12 hours
        'header_test'       => ['expires' => 60, 'max_age' => 60],                  // 1 minute
        'index'             => ['expires' => 30, 'max_age' => 30],                   // 30 seconds
        'attachement'       => ['expires' => 30*86400, 'max_age' => 30*86400]                   // 30 seconds
    ];
    $no_headfoot = [
        'attachment' => true, // No header for attachment page. checks only for isset. so false works like true
    ];
    //echo "[common/config.inc.php: Page routing system loaded]<br>\n";

    // Always load the router system when not in cron context
    // Include session/cache setup
    //echo "[common/config.inc.php: Including " . $config_dir . "/inc/_session.inc.php]<br>\n";


    //include_once($config_dir . '/inc/_session.inc.php');
    //echo "[common/config.inc.php: Session and cache setup included]<br>\n";

    // Include header
    //echo "[common/config.inc.php: Including " . $config_dir . "/inc/_header.inc.php]<br>\n";
    //include_once($config_dir . '/inc/_header.inc.php');
    //echo "[common/config.inc.php: Header included]<br>\n";

    // Your page routing switch
    $page = $_REQUEST['page'] ?? 'index';
    if (!isset($RSLIGHT_PAGE_MAP[$page])){
        die("Error: Invalid page requested.");
    }
    if (!file_exists($session_inc) || !is_readable($session_inc)) {
        die("Error: Session include file '$session_inc' not found.");
    }
    include_once($session_inc); // Ensure session is started before header

    if (!isset($no_headfoot[$page])) {
        // Include header if not already done
        if (!file_exists($header_inc) || !is_readable($header_inc)) {
            die("Error: Header include file '$header_inc' not found.");
        }
        include_once($header_inc);
    }
    $page_file = "../pages/" . $RSLIGHT_PAGE_MAP[$page];
    //echo "[common/lib/config.inc.php: loading page: $page_file]<br>\n";
    if (!file_exists($page_file) || !is_readable($page_file)) {
        // If the page file does not exist, we can return an error
        // or redirect to a 404 page, depending on your application design
        // For now, we will just die with an error message
        die("Error: Page file '$page_file' not found.");
    }
    // Include the requested page file
    include_once($page_file);

    // Include footer
    if (!isset($no_headfoot[$page])) {

        if (!file_exists($footer_inc) || !is_readable($footer_inc)) {
            die("Error: Footer include file '$footer_inc' not found.");
        }
        include_once($footer_inc);
    }
    exit(0); // Exit after including the footer
}

// If this is a cron context, we do not load the pages
// but only the configuration and libraries
echo "<!-- [common/lib/config.inc.php: Context cron_context=$cron_context pre_load=$pre_load detected, skipping page loading]<br> -->\n";
// You can add more cron-specific logic here if needed
// For example, you might want to initialize some cron-specific settings or variables

?>
