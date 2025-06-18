<?php
// Test the complete login flow using the same logic as the login page
echo "=== Testing Complete Login Flow ===\n";

// Simulate the web environment
$_POST['username'] = 'devjorge';
$_POST['password'] = 'test1234';
$_POST['command'] = 'Login';

// Set up basic paths (adjust these to match your config)
$config_dir = '/etc/rslight';
$spooldir = '/var/spool/rslight';

// Include the functions
require_once('/etc/rslight/inc/functions.inc.php');

echo "Testing check_bbs_auth function:\n";
$auth_result = check_bbs_auth($_POST['username'], $_POST['password']);
echo "check_bbs_auth result: " . ($auth_result ? 'TRUE' : 'FALSE') . "\n";

if ($auth_result) {
    echo "\n✓ Password authentication successful!\n";

    // Test cookie generation
    echo "\nTesting cookie generation:\n";
    $keyfile = $spooldir . '/keys.dat';
    if (file_exists($keyfile)) {
        $keys = unserialize(file_get_contents($keyfile));
        if (is_array($keys)) {
            echo "Keys loaded successfully\n";

            // Test user config
            $username_lc = strtolower(trim($_POST['username']));
            $encryption_key = get_user_config($username_lc, 'encryptionkey');
            echo "Encryption key for user: " . ($encryption_key ? 'FOUND' : 'NOT FOUND') . "\n";

            if ($encryption_key) {
                echo "\n✓ All components ready for cookie generation!\n";
                echo "\nAuthentication system is working correctly.\n";
            } else {
                echo "\n⚠ Missing encryption key - this will be created on first login\n";
            }
        } else {
            echo "Error: Keys file exists but contains invalid data\n";
        }
    } else {
        echo "Error: Keys file not found at $keyfile\n";
    }
} else {
    echo "\n✗ Password authentication failed!\n";
}
?>
