<?php
// Test complete authentication flow after password update
include_once('/etc/rslight/inc/functions.inc.php');

$username = 'devjorge';
$password = 'test1234';

echo "=== Complete Authentication Test ===\n";
echo "Username: $username\n";
echo "Password: $password\n\n";

// Test 1: Direct password verification
echo "1. Testing direct password verification:\n";
$userFile = '/etc/rslight/users/' . strtolower($username);
if (file_exists($userFile)) {
    $hash = trim(file_get_contents($userFile));
    echo "   Hash from file: $hash\n";
    $result = password_verify($password, $hash);
    echo "   password_verify result: " . ($result ? 'TRUE' : 'FALSE') . "\n";
} else {
    echo "   ERROR: User file not found at $userFile\n";
}

// Test 2: check_bbs_auth function
echo "\n2. Testing check_bbs_auth function:\n";
$auth_result = check_bbs_auth($username, $password);
echo "   check_bbs_auth result: " . ($auth_result ? 'TRUE' : 'FALSE') . "\n";

// Test 3: Test cookie authentication (existing cookie)
echo "\n3. Testing existing cookie authentication:\n";
$keyfile = '/var/spool/rslight/keys.dat';
if (file_exists($keyfile)) {
    $keys = secure_unserialize($keyfile, ['stdClass'], false);
    if (is_array($keys)) {
        echo "   Keys loaded successfully\n";

        // Simulate existing cookie
        $name = strtolower($username);
        $encryptionkey = get_user_config($name, 'encryptionkey');
        echo "   Encryption key: " . ($encryptionkey ? 'FOUND' : 'NOT FOUND') . "\n";

        if ($encryptionkey) {
            // Test if existing cookie would work
            if (isset($_COOKIE['mail_auth'])) {
                $cookie_test1 = password_verify($name . $keys[0] . $encryptionkey, $_COOKIE['mail_auth']);
                $cookie_test2 = password_verify($name . $keys[1] . $encryptionkey, $_COOKIE['mail_auth']);
                echo "   Current cookie test (key0): " . ($cookie_test1 ? 'TRUE' : 'FALSE') . "\n";
                echo "   Current cookie test (key1): " . ($cookie_test2 ? 'TRUE' : 'FALSE') . "\n";
            } else {
                echo "   No mail_auth cookie present\n";
            }
        }
    } else {
        echo "   ERROR: Could not load keys\n";
    }
} else {
    echo "   ERROR: Keys file not found\n";
}

echo "\n=== Test Complete ===\n";
?>
