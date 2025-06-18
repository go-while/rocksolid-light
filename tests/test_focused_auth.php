<?php
// Test authentication in a more controlled way
echo "=== Focused Authentication Test ===\n";

// Test 1: Direct password verification (this should work)
echo "\n1. Testing direct password verification:\n";
$hash = file_get_contents('/etc/rslight/users/devjorge');
$hash = trim($hash);
$password = 'test1234';

echo "   Password: $password\n";
echo "   Hash: $hash\n";
$result = password_verify($password, $hash);
echo "   Result: " . ($result ? 'SUCCESS' : 'FAILED') . "\n";

if (!$result) {
    echo "   ERROR: Basic password verification failed!\n";
    exit(1);
}

// Test 2: Check if user config exists
echo "\n2. Testing user config:\n";
$config_file = '/etc/rslight/userconfig/devjorge';
if (file_exists($config_file)) {
    echo "   User config file exists: YES\n";
    $config_content = file_get_contents($config_file);
    echo "   Config content preview: " . substr($config_content, 0, 50) . "...\n";
} else {
    echo "   User config file exists: NO\n";
}

// Test 3: Check if keys.dat exists
echo "\n3. Testing keys file:\n";
$keys_file = '/var/spool/rslight/keys.dat';
if (file_exists($keys_file)) {
    echo "   Keys file exists: YES\n";
    echo "   Keys file size: " . filesize($keys_file) . " bytes\n";
} else {
    echo "   Keys file exists: NO\n";
    echo "   Trying alternative location: /home/fed/GO/src/github.com/go-while/rocksolid-light/Rocksolid_Light/spool/keys.dat\n";
    $keys_file_alt = '/home/fed/GO/src/github.com/go-while/rocksolid-light/Rocksolid_Light/spool/keys.dat';
    if (file_exists($keys_file_alt)) {
        echo "   Alternative keys file exists: YES\n";
        echo "   Alternative keys file size: " . filesize($keys_file_alt) . " bytes\n";
    }
}

echo "\n=== Test Summary ===\n";
echo "Basic password verification: " . ($result ? 'WORKING' : 'BROKEN') . "\n";
echo "\nIf password verification works, the main issue is likely in the web context setup.\n";
echo "Try accessing the login page directly in a browser at:\n";
echo "http://your-server/?page=login\n";
?>
