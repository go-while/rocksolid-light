<?php
/*
 * Authentication and Login Functions for RockSolid Light
 * Extracted from functions.inc.php to avoid conflicts
 */

/**
 * Set user authentication cookies securely without JavaScript
 * This is the preferred method over set_user_logged_in_cookies()
 */
function set_user_auth_cookies_secure($name, $keys, $redirect_after = false)
{
    global $debug_log, $CONFIG, $spooldir;
    $name = trim($name);
    $name_lc = strtolower($name);

    if ($name == $CONFIG['anonusername']) {
        return false;
    }

    // Ensure user has encryption key
    if (!get_user_config($name_lc, 'encryptionkey')) {
        $key = openssl_random_pseudo_bytes(44);
        set_user_config($name_lc, 'encryptionkey', base64_encode($key));
        if (function_exists('debug_log')) {
            debug_log("Created encryptionkey for: " . $name, $debug_log);
        }
    }

    // Set session variables
    $_SESSION['pass'] = true;
    $_SESSION['username'] = $name_lc;
    $_SESSION['start_address'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['start_stamp'] = time();

    // Generate cookie values
    $auth_expire = 14400; // 4 hours
    $name_expire = 7776000; // 90 days
    $authkey = password_hash($name_lc . $keys[0] . get_user_config($name_lc, 'encryptionkey'), PASSWORD_DEFAULT);
    $pkey = hash('crc32', get_user_config($name_lc, 'encryptionkey'));

    // Set user config
    set_user_config($name_lc, "pkey", $pkey);

    // Cookie options
    $use_ssl = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $cookie_options = [
        'expires' => time() + $auth_expire,
        'path' => '/',
        'domain' => '',
        'secure' => $use_ssl,
        'httponly' => false, // Allow JavaScript access for compatibility
        'samesite' => 'Strict'
    ];

    $name_cookie_options = $cookie_options;
    $name_cookie_options['expires'] = time() + $name_expire;

    // Set cookies using PHP (more reliable than JavaScript)
    $auth_result = setcookie('mail_auth', $authkey, $cookie_options);
    $name_result = setcookie('mail_name', $name, $name_cookie_options);
    $pkey_result = setcookie('pkey', $pkey, $name_cookie_options);

    // Set cookies in current request for immediate availability
    if ($auth_result && $name_result && $pkey_result) {
        $_COOKIE['mail_auth'] = $authkey;
        $_COOKIE['mail_name'] = $name;
        $_COOKIE['pkey'] = $pkey;

        if (function_exists('debug_log')) {
            debug_log("Set secure auth cookies for: " . $name, $debug_log);
        }
        return true;
    } else {
        if (function_exists('debug_log')) {
            debug_log("Failed to set auth cookies for: " . $name, $debug_log);
        }
        return false;
    }
}

/**
 * Enhanced authentication check with fallback methods
 * Combines session and cookie-based authentication
 */
function enhanced_verify_logged_in($name)
{
    global $spooldir;

    $name_lc = strtolower(trim($name));

    // Primary check: use existing verify_logged_in function
    if (function_exists('verify_logged_in')) {
        $logged_in = verify_logged_in($name_lc);
        if ($logged_in) {
            return true;
        }
    }

    // Fallback check: direct cookie verification
    if (isset($_COOKIE['mail_auth']) && !empty($_COOKIE['mail_auth'])) {
        $keyfile = $spooldir . '/keys.dat';
        if (function_exists('secure_unserialize')) {
            $keys = secure_unserialize($keyfile, ['stdClass'], false);
            if (is_array($keys) && function_exists('get_user_config')) {
                $key0_check = password_verify($name_lc . $keys[0] . get_user_config($name_lc, 'encryptionkey'), $_COOKIE['mail_auth']);
                $key1_check = password_verify($name_lc . $keys[1] . get_user_config($name_lc, 'encryptionkey'), $_COOKIE['mail_auth']);

                if ($key0_check || $key1_check) {
                    // Refresh session variables if they're missing
                    $_SESSION['pass'] = true;
                    $_SESSION['username'] = $name_lc;
                    if (!isset($_SESSION['start_address'])) {
                        $_SESSION['start_address'] = $_SERVER['REMOTE_ADDR'];
                    }
                    return true;
                }
            }
        }
    }

    return false;
}

/**
 * Clear all authentication cookies and session data
 */
function clear_authentication()
{
    // Clear authentication cookies
    $past = time() - 3600;
    $auth_cookies = ['mail_auth', 'mail_name', 'pkey'];

    foreach ($auth_cookies as $cookie) {
        if (isset($_COOKIE[$cookie])) {
            setcookie($cookie, '', $past, '/', '', false, false);
            unset($_COOKIE[$cookie]);
        }
    }

    // Clear session data
    if (isset($_SESSION)) {
        unset($_SESSION['pass']);
        unset($_SESSION['username']);
        unset($_SESSION['start_address']);
        unset($_SESSION['start_stamp']);
    }

    return true;
}

?>
