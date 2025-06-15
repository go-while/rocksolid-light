<?php
/**
 * RockSolid Light - Secure Page Router
 *
 * Centralizes page routing with hardcoded mapping to prevent
 * path traversal attacks and user input parsing vulnerabilities.
 *
 * Usage: ?page=article (returns article.php)
 *
 * Include from config.inc.php when not in CRON_CONTEXT
 */

$backtrace = debug_backtrace();
$parent = isset($backtrace[0]['file']) ? $backtrace[0]['file'] : 'Direct execution';
echo "[pages/pages.php included by: " . basename($parent) . "]<br>\n";

// Prevent direct access to this file
if (!defined('RSLIGHT_CONFIG_LOADED')) {
    die('Direct access not allowed. Include via config.inc.php');
}

/**
 * Hardcoded page mapping - NO USER INPUT PARSING
 * This is the ONLY safe way to map page names to files
 */
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
    'faq'              => 'faq.php'
];

/**
 * Cache settings per page type
 */
$RSLIGHT_PAGE_CACHE = [
    'article'      => ['expires' => 3600 * 24, 'max_age' => 3600 * 24],  // 24 hours
    'article-flat' => ['expires' => 3600 * 24, 'max_age' => 3600 * 24],  // 24 hours
    'thread'       => ['expires' => 100, 'max_age' => 100],               // 100 seconds
    'overboard'    => ['expires' => 120, 'max_age' => 120],               // 2 minutes
    'search'       => ['expires' => 120, 'max_age' => 120],               // 2 minutes
    'post'         => ['expires' => 30, 'max_age' => 30],                 // 30 seconds
    'register'     => ['expires' => 300, 'max_age' => 300],               // 5 minutes
    'user'         => ['expires' => 60, 'max_age' => 60],                 // 1 minute
    'mail'         => ['expires' => 60, 'max_age' => 60],                 // 1 minute
    'files'        => ['expires' => 300, 'max_age' => 300],               // 5 minutes
    'upload'       => ['expires' => 60, 'max_age' => 60],                 // 1 minute
    'language_demo' => ['expires' => 3600, 'max_age' => 3600],            // 1 hour
    'language_selector' => ['expires' => 3600, 'max_age' => 3600],        // 1 hour
    'faq'          => ['expires' => 3600 * 12, 'max_age' => 3600 * 12]    // 12 hours
];

/**
 * Get valid page filename from request
 *
 * @return string|null Valid filename or null if invalid/not found
 */
function rslight_get_page_file() {
    global $RSLIGHT_PAGE_MAP;

    // Get page parameter safely
    $page = isset($_GET['page']) ? $_GET['page'] : null;

    // Return null if no page requested
    if (!$page) {
        return null;
    }

    // Sanitize input - only allow alphanumeric, dash, underscore
    $page = preg_replace('/[^a-zA-Z0-9_-]/', '', $page);

    // Check if page exists in our hardcoded mapping
    if (!isset($RSLIGHT_PAGE_MAP[$page])) {
        return null;
    }

    // Return the hardcoded filename
    return $RSLIGHT_PAGE_MAP[$page];
}

/**
 * Set appropriate cache headers for page
 *
 * @param string $page Page name
 */
function rslight_set_page_cache_headers($page) {
    global $RSLIGHT_PAGE_CACHE;

    $cache_settings = isset($RSLIGHT_PAGE_CACHE[$page])
        ? $RSLIGHT_PAGE_CACHE[$page]
        : ['expires' => 120, 'max_age' => 120]; // Default 2 minutes

    $expires_time = time() + $cache_settings['expires'];

    header("Expires: " . gmdate("D, d M Y H:i:s", $expires_time) . " GMT");
    header("Cache-Control: max-age=" . $cache_settings['max_age']);
    header("Pragma: cache");
}

/**
 * Initialize common page requirements
 * Handles security, sessions, and basic setup that all pages need
 *
 * @param string $page Page name for cache headers
 */
function rslight_init_page($page) {
    // Set cache headers first (before any output)
    rslight_set_page_cache_headers($page);

    // Session handling for web pages (not CLI/cron)
    if (php_sapi_name() !== 'cli' && !defined('RSLIGHT_NO_SESSION')) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Update last access for active pages
        if (!isset($_SESSION['last_access']) || (time() - $_SESSION['last_access']) > 60) {
            $_SESSION['last_access'] = time();
        }
    }

    // Security headers (already loaded via security.inc.php)
    if (function_exists('add_security_headers')) {
        add_security_headers();
    }
}

/**
 * Route to requested page securely
 * This is the main entry point for page routing
 *
 * @return bool True if page was routed, false if no valid page found
 */
function rslight_route_page() {
    global $RSLIGHT_PAGE_MAP;

    $page_file = rslight_get_page_file();

    if (!$page_file) {
        return false; // No valid page requested
    }

    // Extract page name for initialization
    $page_name = array_search($page_file, $RSLIGHT_PAGE_MAP);

    // Initialize page requirements
    rslight_init_page($page_name);

    // Construct safe path to page file
    $page_path = __DIR__ . '/' . $page_file;

    // Final security check - file must exist and be readable
    if (!file_exists($page_path) || !is_readable($page_path)) {
        error_log("RSLIGHT SECURITY: Attempted access to non-existent page: $page_file");
        return false;
    }

    // Log page access for debugging
    error_log("RSLIGHT ROUTER: Loading page $page_name -> $page_file");

    // Include the page (this executes the page)
    include $page_path;

    return true;
}

/**
 * Debug function - show available pages
 * Only for development/debugging
 */
function rslight_debug_pages() {
    global $RSLIGHT_PAGE_MAP;

    echo "<h3>Available Pages:</h3><ul>";
    foreach ($RSLIGHT_PAGE_MAP as $page => $file) {
        echo "<li><a href='?page=$page'>$page</a> → $file</li>";
    }
    echo "</ul>";
}

// Auto-route if page parameter is present and we're not in CLI mode
if (php_sapi_name() !== 'cli' && isset($_GET['page'])) {
    if (rslight_route_page()) {
        // Page was successfully routed and executed
        exit();
    } else {
        // Invalid page requested - could log this as potential attack
        error_log("RSLIGHT SECURITY: Invalid page request: " . ($_GET['page'] ?? 'null'));

        // Don't give detailed error info to potential attackers
        header("HTTP/1.0 404 Not Found");
        echo "<h1>Page Not Found</h1>";
        exit();
    }
}

?>
