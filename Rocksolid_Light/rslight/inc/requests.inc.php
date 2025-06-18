<?php
/*
 * Centralized Authentication Gate for RockSolid Light
 * Handles page access control and authentication requirements
 */

// Get current page
if(isset($_GET['page'])) {
    $page = $_GET['page'];
} else {
    $page = 'index'; // Default page
}

// Define pages that require authentication
$auth_required_pages = [
    'upload',
    'mail',
    'user',
    'files'
];

// Define pages that should redirect authenticated users elsewhere
$login_pages = ['login'];

// Check if current page requires authentication
$needs_auth = in_array($page, $auth_required_pages);

// Check current authentication status
$is_authenticated = false;
$current_user = '';

// Primary authentication check - use the existing verify_logged_in system
if (isset($_COOKIE['mail_name']) && !empty($_COOKIE['mail_name'])) {
    $current_user = trim($_COOKIE['mail_name']);
    $is_authenticated = verify_logged_in(trim(strtolower($current_user)));

    // Fallback authentication check using mail_auth cookie
    if (!$is_authenticated && isset($_COOKIE['mail_auth'])) {
        $keyfile = $spooldir . '/keys.dat';
        $keys = secure_unserialize($keyfile, ['stdClass'], false);
        if (is_array($keys)) {
            $username_lc = strtolower(trim($current_user));
            $encryptionkey = get_user_config($username_lc, 'encryptionkey');

            if ($encryptionkey) {
                $key0_check = password_verify($username_lc . $keys[0] . $encryptionkey, $_COOKIE['mail_auth']);
                $key1_check = password_verify($username_lc . $keys[1] . $encryptionkey, $_COOKIE['mail_auth']);

                if ($key0_check || $key1_check) {
                    $is_authenticated = true;
                    // Refresh session variables if they're missing
                    $_SESSION['pass'] = true;
                    $_SESSION['username'] = $username_lc;
                    if (!isset($_SESSION['start_address'])) {
                        $_SESSION['start_address'] = $_SERVER['REMOTE_ADDR'];
                    }
                }
            }
        }
    }
}

// Handle authentication requirements
if ($needs_auth && !$is_authenticated) {
    // User needs to log in - redirect to login page with return URL
    $current_url = $_SERVER['REQUEST_URI'] ?? ('?page=' . $page);
    $login_url = '?page=login&redirect_url=' . urlencode($current_url);

    header("Location: $login_url");
    exit;
}

// Handle login page access for already authenticated users
if (in_array($page, $login_pages) && $is_authenticated) {
    // User is already logged in but accessing login page
    // Check if there's a redirect URL to send them to
    if (isset($_GET['redirect_url']) && !empty($_GET['redirect_url'])) {
        $redirect_url = $_GET['redirect_url'];
        // Basic validation of redirect URL
        if (strpos($redirect_url, '?page=') === 0 || strpos($redirect_url, '/') === 0) {
            header("Location: $redirect_url");
            exit;
        }
    }
    // If no valid redirect, let them stay on login page to see status/logout
}

// Set global authentication variables for use in pages
$GLOBALS['is_authenticated'] = $is_authenticated;
$GLOBALS['current_user'] = $current_user;
$GLOBALS['current_page'] = $page;

?>