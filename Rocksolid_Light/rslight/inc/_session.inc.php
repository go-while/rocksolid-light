<?php
echo "<!-- [inc/_session.inc.php: Including session management]<br> -->\n";
/**
 * RockSolid Light - Session Management Include
 * Extracted from pages/pages.php for simple include usage
 */

// Prevent direct access
if (!defined('RSLIGHT_CONFIG_LOADED')) {
    die('Direct access not allowed.');
}

// Session handling for web pages (not CLI/cron)
if (php_sapi_name() !== 'cli' && (!defined('RSLIGHT_NO_SESSION') && !defined('CRON_CONTEXT'))) { // REVIEW
    // Start session if not already started
    echo "<!-- [inc/_session.inc.php: Starting secure session] -->\n";
    secure_session_start();
    echo "<!-- [inc/_session.inc.php: Secure session started] -->\n";
    // Set session as active
}

// Security headers
add_security_headers();

// Set appropriate cache headers based on page type
$cache_page = isset($_GET['page']) ? $_GET['page'] : 'index';

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
    'index'             => ['expires' => 30, 'max_age' => 30]                   // 30 seconds
];

// Default cache settings
$current_cache = isset($cache_settings[$cache_page]) ? $cache_settings[$cache_page] : ['expires' => 300, 'max_age' => 300];

$expires_time = time() + $current_cache['expires'];

header("Expires: " . gmdate("D, d M Y H:i:s", $expires_time) . " GMT");
header("Cache-Control: max-age=" . $current_cache['max_age']);
header("Pragma: cache");

?>