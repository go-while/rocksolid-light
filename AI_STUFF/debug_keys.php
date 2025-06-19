#!/usr/bin/php
<?php
/**
 * Debug script to test keys.dat file compatibility
 */

// Look for security.inc.php
$security_paths = [
    '/var/www/html/rslight/common/security.inc.php',
    '/var/www/html/rslight/rocksolid/security.inc.php',
    '/var/www/html/rslight/spoolnews/security.inc.php'
];

$security_file = null;
foreach ($security_paths as $path) {
    if (file_exists($path)) {
        $security_file = $path;
        break;
    }
}

if ($security_file) {
    echo "Found security.inc.php: $security_file\n";
    include_once $security_file;
} else {
    echo "No security.inc.php found\n";
}

$keyfile = '/var/spool/rslight/keys.dat';

if (!file_exists($keyfile)) {
    die("Keys file not found: $keyfile\n");
}

echo "Testing keys file: $keyfile\n";
echo "File size: " . filesize($keyfile) . " bytes\n";

$content = file_get_contents($keyfile);
echo "Content length: " . strlen($content) . " bytes\n";
echo "First 50 chars: " . substr($content, 0, 50) . "\n";

// Test 1: Standard unserialize
echo "\n=== Test 1: Standard unserialize ===\n";
try {
    $keys1 = unserialize($content);
    echo "Type: " . gettype($keys1) . "\n";
    if (is_array($keys1)) {
        echo "Array count: " . count($keys1) . "\n";
        foreach ($keys1 as $i => $key) {
            echo "Key $i: " . substr($key, 0, 10) . "... (length: " . strlen($key) . ")\n";
        }
    }
    echo "Result: SUCCESS\n";
} catch (Exception $e) {
    echo "Result: FAILED - " . $e->getMessage() . "\n";
}

// Test 2: secure_unserialize if available
if (function_exists('secure_unserialize')) {
    echo "\n=== Test 2: secure_unserialize ===\n";
    try {
        $keys2 = secure_unserialize($content);
        echo "Type: " . gettype($keys2) . "\n";
        if (is_array($keys2)) {
            echo "Array count: " . count($keys2) . "\n";
            foreach ($keys2 as $i => $key) {
                echo "Key $i: " . substr($key, 0, 10) . "... (length: " . strlen($key) . ")\n";
            }
        }
        echo "Result: SUCCESS\n";
    } catch (Exception $e) {
        echo "Result: FAILED - " . $e->getMessage() . "\n";
    }
} else {
    echo "\n=== Test 2: secure_unserialize ===\n";
    echo "Function not available\n";
}

// Test 3: Check what secure_unserialize expects
if (function_exists('secure_unserialize')) {
    echo "\n=== Test 3: Alternative serialization ===\n";

    // Create test data
    $test_keys = [
        base64_encode(openssl_random_pseudo_bytes(44)),
        base64_encode(openssl_random_pseudo_bytes(44))
    ];

    // Try to figure out what secure_unserialize expects
    $test_content = serialize($test_keys);
    echo "Test serialized length: " . strlen($test_content) . "\n";

    try {
        $test_result = secure_unserialize($test_content);
        echo "secure_unserialize works with fresh data: YES\n";
    } catch (Exception $e) {
        echo "secure_unserialize works with fresh data: NO - " . $e->getMessage() . "\n";
    }
}

echo "\n=== CONCLUSION ===\n";
echo "The keys file should work with the web interface if standard unserialize works.\n";
echo "If secure_unserialize fails, it may be due to additional security restrictions.\n";
?>
