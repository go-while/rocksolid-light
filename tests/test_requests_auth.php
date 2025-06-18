<?php
// Test the requests.inc.php authentication logic directly
session_start();

// Simulate having the cookie
$_COOKIE['mail_name'] = 'devjorge';
$_COOKIE['mail_auth'] = '$2y$10$PAuyCh5WMHmDSTU9jUMiXOASi8DHFHp0Ph06kfl89p9EuXPW8dyeC';

// Set up required globals
$spooldir = '/var/spool/rslight';
$config_dir = '/etc/rslight';

// Include required files
include_once '/etc/rslight/inc/functions.inc.php';

echo "=== Testing requests.inc.php Authentication Logic ===\n";

$current_user = trim($_COOKIE['mail_name']);
echo "Current user: $current_user\n";

$is_authenticated = verify_logged_in(trim(strtolower($current_user)));
echo "verify_logged_in result: " . ($is_authenticated ? 'TRUE' : 'FALSE') . "\n";

if (!$is_authenticated && isset($_COOKIE['mail_auth'])) {
    echo "Attempting fallback cookie authentication...\n";

    $keyfile = $spooldir . '/keys.dat';
    $keys = secure_unserialize($keyfile, ['stdClass'], false);
    if (is_array($keys)) {
        echo "Keys loaded: " . count($keys) . " keys\n";

        $username_lc = strtolower(trim($current_user));
        $encryptionkey = get_user_config($username_lc, 'encryptionkey');
        echo "Encryption key: " . ($encryptionkey ? 'FOUND' : 'NOT FOUND') . "\n";

        if ($encryptionkey) {
            $key0_check = password_verify($username_lc . $keys[0] . $encryptionkey, $_COOKIE['mail_auth']);
            $key1_check = password_verify($username_lc . $keys[1] . $encryptionkey, $_COOKIE['mail_auth']);

            echo "Key0 check: " . ($key0_check ? 'SUCCESS' : 'FAILED') . "\n";
            echo "Key1 check: " . ($key1_check ? 'SUCCESS' : 'FAILED') . "\n";

            if ($key0_check || $key1_check) {
                $is_authenticated = true;
                echo "✓ Cookie authentication SUCCESSFUL\n";
            } else {
                echo "✗ Cookie authentication FAILED\n";
            }
        }
    } else {
        echo "Failed to load keys\n";
    }
}

echo "\nFinal authentication status: " . ($is_authenticated ? 'AUTHENTICATED' : 'NOT AUTHENTICATED') . "\n";
?>
