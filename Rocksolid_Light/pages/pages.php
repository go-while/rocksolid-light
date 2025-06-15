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
    'faq'              => 'faq.php',

    // Testing/Debug
    'header_test'      => 'header_test.php',

    // Main index page
    'index'            => 'index.php'
];

/**
 * Cache settings per page type
 */
$RSLIGHT_PAGE_CACHE = [
    'article'      => ['expires' => 3600 * 24, 'max_age' => 3600 * 24],  // 24 hours
    'article-flat' => ['expires' => 100, 'max_age' => 100],               // 100 seconds
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
    'faq'          => ['expires' => 3600 * 12, 'max_age' => 3600 * 12],   // 12 hours
    'header_test'  => ['expires' => 60, 'max_age' => 60],                 // 1 minute
    'index'        => ['expires' => 30, 'max_age' => 30]                  // 30 seconds
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
 * Render HTML document head section
 * Consolidates all the scattered HTML generation from head.inc and header.php
 *
 * @param string $page_title Page title
 * @param string $page_name Page name for specific styling
 */
function rslight_render_html_head($page_title, $page_name = '') {
    global $CONFIG, $config_dir, $config_name;

    // Ensure we have required globals
    if (!isset($CONFIG)) {
        global $config_file;
        $CONFIG = include $config_file;
    }

    // Session setup (extracted from head.inc)
    $_SESSION['rsactive'] = true;

    // Security and throttling (from head.inc)
    if (function_exists('get_client_user_agent_info')) {
        $client_device = get_client_user_agent_info();
        if (function_exists('throttle_hits')) {
            throttle_hits($client_device);
        }
    }
    if (function_exists('write_access_log')) {
        write_access_log();
    }

    // Start HTML output
    echo '<!DOCTYPE html>';
    echo '<html><head>';
    echo '<title>' . htmlspecialchars($page_title) . '</title>';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<meta charset="utf-8">';

    // Timezone JavaScript (from header.php)
    echo '<script>';
    echo 'if (navigator.cookieEnabled) {';
    echo '    document.cookie = "tzo=" + (-new Date().getTimezoneOffset()) + "; path=/";';
    echo '    var tzid = new Intl.DateTimeFormat().resolvedOptions().timeZone;';
    echo '    document.cookie = "tzid=" + tzid + "; path=/";';
    echo '}';
    echo '</script>';

    // Theme and CSS (from header.php)
    rslight_render_theme_css();

    // Google Analytics if configured (from head.inc)
    if (file_exists($config_dir . '/googleanalytics.conf')) { // TODO FIXME: should be declared variable before in rocksolid/config.inc.php
        include $config_dir . '/googleanalytics.conf';
    }

    echo '</head><body>';
}

/**
 * Render theme CSS and favicon
 * Extracted from header.php theme handling
 */
function rslight_render_theme_css() {
    global $CONFIG, $config_dir;

    // Determine root directory for CSS paths
    $rootdir = "../";

    // Handle user theme selection (from header.php)
    $default_theme = "Default Theme";
    if (isset($_COOKIE['mail_name']) && isset($_COOKIE['pkey'])) {
        $user = strtolower($_COOKIE['mail_name']);
        if (!isset($_SESSION['theme']) && file_exists($config_dir . '/userconfig/' . $user . '.config')) {
            $user_config = secure_unserialize($config_dir . '/userconfig/' . $user . '.config');
            if ($user_config !== false && isset($user_config['theme'])) {
                $_SESSION['theme'] = $user_config['theme'];
            }
        }
    }

    // Select theme
    if (isset($_SESSION['theme'])) {
        $do_theme = preg_replace("/ /", "%20", $_SESSION['theme']);
    } else {
        $do_theme = preg_replace("/ /", "%20", $default_theme);
    }

    // Output CSS link and favicon
    echo '<link rel="stylesheet" type="text/css" href="' . $rootdir . '/common/themes/' . $do_theme . '/style.css">';
    echo '<link rel="icon" type="image/x-icon" href="/common/images/favicon.ico">';
}

/**
 * Debug function to log browser language (from header.php)
 */
function rslight_debug_browser_language() {
    global $OVERRIDES, $debug_log;

    if (isset($OVERRIDES['log_lang']) && $OVERRIDES['log_lang'] == true) {
        $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
        if (function_exists('logging_prefix')) {
            file_put_contents($debug_log, "\n" . logging_prefix() . " Browser Lang: " . $lang, FILE_APPEND);
        }
    }
}

/**
 * Render site header with navigation
 * Consolidates header.php HTML generation
 */
function rslight_render_site_header() {
    global $CONFIG, $config_dir, $config_name;

    // Determine root directory and header image
    $rootdir = "../";
    $do_theme = isset($_SESSION['theme']) ? preg_replace("/ /", "%20", $_SESSION['theme']) : "Default%20Theme";

    if (isset($_SESSION['theme']) && file_exists($rootdir . '/common/themes/' . $do_theme . '/images/rocksolidlight.png')) {
        $header_image = $rootdir . '/common/themes/' . $do_theme . '/images/rocksolidlight.png';
    } else {
        $header_image = $rootdir . 'common/images/rocksolidlight.png';
    }

    echo '<div class="header_top">';
    echo '<table class="np_header_table_top">';
    echo '<tr class="np_header_bar_top">';
    echo '<td class="np_td_header_bar_logo_image"><a href="' . $CONFIG['default_content'] . '">';
    echo '<img src="' . $header_image . '" alt="Rocksolid Light" class="responsive_image"></a></td>';
    echo '<td class="header_page_title_top">';
    echo '<p class="header_page_title_top">' . $CONFIG['rslight_title'] . '</p></td>';
    echo '<td class="header_links">';

    // Render navigation links
    rslight_render_navigation_links();

    echo '</td></tr></table>';

    // Render menu buttons
    rslight_render_menu_buttons();

    // Render group breadcrumb if applicable
    rslight_render_group_breadcrumb();

    echo '</div><div class="scroll">';

    // Message ID search form
    rslight_render_msgid_search();

    // MOTD
    rslight_render_motd();
}

/**
 * Render navigation links in header
 */
function rslight_render_navigation_links() {
    global $config_dir;

    echo '<div class="header_links_text">';

    // Check for unread mail
    $user = (isset($_COOKIE['mail_name']) && isset($_COOKIE['pkey'])) ? strtolower($_COOKIE['mail_name']) : null;
    $unread = ($user && function_exists('check_unread_mail') && check_unread_mail() == true);

    // Load and display links
    $linklist = file($config_dir . "links.conf", FILE_IGNORE_NEW_LINES);
    foreach ($linklist as $link) {
        if ($link[0] == '#') continue;

        $linkitem = explode(':', $link, 2);
        if ($linkitem[1] == '0') continue;

        if ($unread && (strpos($linkitem[1], 'spoolnews/mail.php') !== false)) {
            echo '<strong><a class="header_links_text" href="' . trim($linkitem[1]) . '">' . trim(strtoupper($linkitem[0])) . '</a>&nbsp;&nbsp;</strong>';
        } else {
            echo '<a class="header_links_text" href="' . trim($linkitem[1]) . '">' . trim($linkitem[0]) . '</a>&nbsp;&nbsp;';
        }
    }

    // Login/user link
    echo '<a class="header_links_text" href="../spoolnews/user.php">';
    echo isset($user) ? '(' . $_COOKIE['mail_name'] . ')' : 'login';
    echo '</a>&nbsp;&nbsp;';

    // Language selector
    $current_page = $_SERVER['REQUEST_URI'];
    $current_lang = isset($_COOKIE['user_language']) ? $_COOKIE['user_language'] : 'english.lang';
    $lang_display = ucfirst(str_replace(['_', '.lang'], [' ', ''], $current_lang));
    echo '<a class="header_links_text" href="../rocksolid/language_selector.php?return=' . urlencode($current_page) . '" title="Change Language">';
    echo '🌐 ' . htmlspecialchars($lang_display) . '</a>';

    echo '</div>';
}

/**
 * Render menu buttons
 */
function rslight_render_menu_buttons() {
    global $config_dir, $frame;

    $menulist = get_section_menu_array();
    $rootdir = "../";

    echo '<table class="np_header_button_bar"><tr>';
    foreach ($menulist as $menu) {
        $menuitem = explode(':', $menu);
        if ($menuitem[1] == '0') continue;

        $frame_target = isset($frame['menu']) ? $frame['menu'] : '';
        echo '<td>';
        echo '<form target="' . $frame_target . '" action="' . $rootdir . $menuitem[0] . '">';
        echo '<button class="np_header_button_link" type="submit">' . $menuitem[0] . '</button>';
        echo '</form>';
        echo '</td>';
    }
    echo '</tr></table>';
}

/**
 * Render group breadcrumb navigation
 */
function rslight_render_group_breadcrumb() {
    global $config_name, $file_thread, $frame;

    if (preg_match("/thread.php|article.php|article-flat.php|overboard.php|search.php/", $_SERVER['REQUEST_URI'])) {
        $display_group = $_REQUEST['group'] ?? $_REQUEST['thisgroup'] ?? null;
        if ($display_group) {
            echo '<table class="header_display_group">';
            echo '<tr><td>';
            echo '<span><a href="/' . $config_name . '">' . $config_name . '</a> / ';
            echo '<a href="' . $file_thread . '?group=' . rawurlencode($display_group) . '" target="' . ($frame["content"] ?? '') . '">';
            echo htmlspecialchars(function_exists('group_display_name') ? group_display_name($display_group) : $display_group);
            echo '</a>';
            echo '</td></tr></table>';
        }
    }
}

/**
 * Render Message ID search form
 */
function rslight_render_msgid_search() {
    global $OVERRIDES, $config_name;

    if (!isset($OVERRIDES['disable_msgid_search']) || $OVERRIDES['disable_msgid_search'] == false) {
        if ($config_name != "common" && $config_name != 'spoolnews') {
            echo '<form name="form1" method="get" action="?">';
            echo '<input type="hidden" name="page" value="article-flat">';
            echo '<table class="header_message_id_search">';
            echo '<tr><td class="header_message_id_search_prompt">Message-ID: ';
            echo '<input name="id" type="text" id="id" size="40" maxlength="120">&nbsp;';
            echo '<input type="submit" name="Submit" value="Lookup">';
            echo '</td></tr></table>';
            echo '</form>';
        }
    }
}

/**
 * Render MOTD (Message of the Day)
 */
function rslight_render_motd() {
    global $config_dir, $config_name;

    // Check for unread mail
    $user = (isset($_COOKIE['mail_name']) && isset($_COOKIE['pkey'])) ? strtolower($_COOKIE['mail_name']) : null;
    $unread = ($user && function_exists('check_unread_mail') && check_unread_mail() == true);

    // Load MOTD
    $motd = '';
    if (file_exists($config_dir . '/motd.txt')) {
        $motd = file_get_contents($config_dir . '/motd.txt');
    }
    if (file_exists($config_dir . '/' . $config_name . '-motd.txt')) {
        $motd = file_get_contents($config_dir . '/' . $config_name . '-motd.txt');
    }

    // Override MOTD for unread mail
    if ($unread) {
        $motd = '*** You have unread mail. <a href="../spoolnews/mail.php">Click Here</a> ***';
        echo '<div class="np_display_motd_new_mail">';
    } else {
        echo '<div class="np_display_motd">';
    }

    echo $motd;
    echo '</div>';
}

/**
 * Complete page header rendering
 * This is the main function to call instead of including head.inc + header.php
 *
 * @param string $page_title Page title to display
 * @param string $page_name Page identifier for styling
 */
function rslight_render_complete_header($page_title, $page_name = '') {
    // Include fortunes config (from header.php)
    global $config_dir;
    if (file_exists($config_dir . '/fortunes.conf')) {
        include($config_dir . '/fortunes.conf');
    }

    // Render HTML head
    rslight_render_html_head($page_title, $page_name);

    // Render site header
    rslight_render_site_header();

    // Debug browser language if enabled
    rslight_debug_browser_language();

    // Add separator (from head.inc)
    echo '<hr>';
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

    // Log page access only in debug mode
    global $config_dir;
    if (file_exists($config_dir . '/DEBUG')) {
        error_log("RSLIGHT ROUTER: Loading page $page_name -> $page_file");
    }

    // Include the page (this executes the page)
    include $page_path;

    return true;
}

/**
 * Serve default index page when no page parameter is provided
 * This replaces the functionality of rocksolid/index.php
 *
 * @return bool True if default page was served
 */
function rslight_serve_default_page() {
    // Check if we should serve the default page
    if (php_sapi_name() === 'cli' || defined('RSLIGHT_NO_DEFAULT_PAGE')) {
        return false;
    }

    // Only serve default if no page parameter and we're in a web context
    if (!isset($_GET['page']) || $_GET['page'] === '') {
        // Initialize page requirements for index page
        rslight_init_page('index');

        // Load the index page
        $index_path = __DIR__ . '/index.php';
        if (file_exists($index_path) && is_readable($index_path)) {
            // Only log in debug mode to avoid cluttering Apache error logs
            global $config_dir;
            if (file_exists($config_dir . '/DEBUG')) {
                error_log("RSLIGHT ROUTER: Loading default index page");
            }
            include $index_path;
            return true;
        } else {
            error_log("RSLIGHT ERROR: Default index page not found at $index_path");
            return false;
        }
    }

    return false;
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

// Auto-route based on request
if (php_sapi_name() !== 'cli') {
    if (isset($_GET['page'])) {
        // Specific page requested
        if (rslight_route_page()) {
            // Page was successfully routed and executed
            exit();
        } else {
            // Invalid page requested - could log this as potential attack
            error_log("RSLIGHT SECURITY: Invalid page request: " . ($_GET['page'] ?? 'null') . " | Query: " . http_build_query($_GET));

            // Don't give detailed error info to potential attackers
            header("HTTP/1.0 404 Not Found");
            echo "<h1>Page Not Found</h1>";
            exit();
        }
    } else {
        // No page specified - serve default index page
        if (rslight_serve_default_page()) {
            exit();
        }
        // If default page fails, continue with normal flow (don't exit)
    }
}

?>
