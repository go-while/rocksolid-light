<?php
// Test existing cookie authentication
include_once 'rslight/inc/functions.inc.php';

$username = 'devjorge';
$existing_cookie = '$2y$10$PAuyCh5WMHmDSTU9jUMiXOASi8DHFHp0Ph06kfl89p9EuXPW8dyeC';

echo "=== Testing Existing Cookie Authentication ===\n";

// Load keys
$keyfile = 'spool/keys.dat';
if (file_exists($keyfile)) {
    $keys = secure_unserialize($keyfile, ['stdClass'], false);
    if (is_array($keys)) {
        echo "Keys loaded successfully\n";
        echo "Number of keys: " . count($keys) . "\n";
    } else {
        echo "Failed to load keys or keys not array\n";
        var_dump($keys);
    }
} else {
    echo "Keys file not found: $keyfile\n";
}

// Get user encryption key directly from file
$userconfig_file = '/etc/rslight/userconfig/' . $username;
$encryptionkey = '';
if (file_exists($userconfig_file)) {
    $config_content = file_get_contents($userconfig_file);
    if (preg_match('/encryptionkey:([^\n\r]+)/', $config_content, $matches)) {
        $encryptionkey = trim($matches[1]);
        echo "User encryption key: $encryptionkey\n";
    } else {
        echo "Could not parse encryption key from config\n";
        echo "Config content: $config_content\n";
    }
} else {
    echo "User config file not found: $userconfig_file\n";
}

if (is_array($keys) && $encryptionkey) {
    // Test the old authentication method
    echo "\n=== Testing Cookie Verification ===\n";

    // Test with keys[0]
    $test_string_0 = $username . $keys[0] . $encryptionkey;
    $verify_0 = password_verify($test_string_0, $existing_cookie);
    echo "Test with keys[0]: " . ($verify_0 ? 'SUCCESS' : 'FAILED') . "\n";
    echo "Test string: '$test_string_0'\n";

    // Test with keys[1]
    $test_string_1 = $username . $keys[1] . $encryptionkey;
    $verify_1 = password_verify($test_string_1, $existing_cookie);
    echo "Test with keys[1]: " . ($verify_1 ? 'SUCCESS' : 'FAILED') . "\n";
    echo "Test string: '$test_string_1'\n";

    if ($verify_0 || $verify_1) {
        echo "\n✓ EXISTING COOKIE IS VALID!\n";
        echo "The user should already be authenticated with this cookie.\n";
    } else {
        echo "\n✗ EXISTING COOKIE IS INVALID\n";
        echo "Need to create new authentication.\n";
    }
} else {
    echo "Cannot test - missing keys or encryption key\n";
}

?>
