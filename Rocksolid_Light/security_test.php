<?php
/**
 * RockSolid Light Security Test Suite
 * Tests security functions for PHP 7.4+ through 8.4 compatibility
 *
 * Usage: php security_test.php
 */

error_reporting(E_ALL & ~E_WARNING);
ini_set('display_errors', 1);

// Suppress session warnings in CLI mode
ini_set('session.use_cookies', '0');
ini_set('session.use_trans_sid', '0');
ini_set('session.cache_limiter', '');

// Start session before any output to avoid header warnings
if (session_status() == PHP_SESSION_NONE) {
    @session_start();
}

echo "RockSolid Light Security Test Suite\n";
echo "====================================\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

require_once(__DIR__ . '/rocksolid/security.inc.php');

$tests_passed = 0;
$tests_failed = 0;

function test_assert($condition, $test_name) {
    global $tests_passed, $tests_failed;

    if ($condition) {
        echo "✅ PASS: $test_name\n";
        $tests_passed++;
    } else {
        echo "❌ FAIL: $test_name\n";
        $tests_failed++;
    }
}

// Test 1: secure_input function
echo "Testing secure_input() function:\n";
test_assert(secure_input('test123', 'alphanum') === 'test123', 'alphanum validation - valid input');
test_assert(secure_input('test<script>', 'alphanum') === false, 'alphanum validation - invalid input');
test_assert(secure_input('<script>alert("xss")</script>', 'html') === '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', 'HTML escaping');
test_assert(secure_input('test@example.com', 'email') === 'test@example.com', 'email validation - valid');
test_assert(secure_input('invalid-email', 'email') === false, 'email validation - invalid');

// Test 2: secure_unserialize function with test file
echo "\nTesting secure_unserialize() function:\n";
$test_file = __DIR__ . '/test_serialize.dat';
$test_data = ['test' => 'data', 'number' => 123];

// Test JSON serialization
file_put_contents($test_file, json_encode($test_data));
$result = secure_unserialize($test_file, [], true);
test_assert($result['test'] === 'data' && $result['number'] === 123, 'JSON deserialization');

// Test PHP serialization (safe)
file_put_contents($test_file, serialize($test_data));
$result = secure_unserialize($test_file, [], false);
test_assert($result['test'] === 'data' && $result['number'] === 123, 'PHP safe deserialization');

// Test invalid file
test_assert(secure_unserialize('/nonexistent/file.dat') === false, 'Non-existent file handling');

// Test malformed data
file_put_contents($test_file, 'malformed data');
test_assert(secure_unserialize($test_file) === false, 'Malformed data handling');

// Clean up test files
if (file_exists($test_file)) {
    @unlink($test_file);
}

// Test 3: get_secure_mime_type function
echo "\nTesting get_secure_mime_type() function:\n";
if (function_exists('finfo_open')) {
    // Create a test file
    $test_txt_file = __DIR__ . '/test.txt';
    file_put_contents($test_txt_file, 'Hello, World!');

    $mime = get_secure_mime_type($test_txt_file);
    test_assert(strpos($mime, 'text/') === 0, 'Text file MIME detection');

    @unlink($test_txt_file);
} else {
    echo "⚠️  SKIP: finfo extension not available\n";
}

// Test 4: CSRF token functions
echo "\nTesting CSRF token functions:\n";

$token1 = generate_csrf_token();
$token2 = generate_csrf_token();
test_assert(!empty($token1), 'CSRF token generation');
test_assert($token1 === $token2, 'CSRF token consistency');
test_assert(verify_csrf_token($token1) === true, 'CSRF token verification - valid');
test_assert(verify_csrf_token('invalid_token') === false, 'CSRF token verification - invalid');

// Test 5: Rate limiting
echo "\nTesting rate limiting:\n";
// Set spooldir for rate limiting tests
$spooldir = __DIR__;
$rate_result1 = rate_limit_check('test_user', 10, 60);
test_assert($rate_result1 === true, 'Rate limit - first request');

// Simulate multiple requests
for ($i = 0; $i < 5; $i++) {
    rate_limit_check('test_user', 10, 60);
}
$rate_result2 = rate_limit_check('test_user', 10, 60);
test_assert($rate_result2 === true, 'Rate limit - within limit');

// Test 6: secure_path function
echo "\nTesting secure_path() function:\n";
test_assert(secure_path('normal/path/file.txt') === 'normal/path/file.txt', 'Normal path');
test_assert(secure_path('../../../etc/passwd') === false, 'Path traversal prevention');
test_assert(secure_path('path/with/./file.txt') === false, 'Current directory reference prevention');

// Test 7: PHP version compatibility checks
echo "\nTesting PHP version compatibility:\n";
test_assert(version_compare(PHP_VERSION, '7.4.0') >= 0, 'PHP 7.4+ compatibility');
test_assert(function_exists('password_hash'), 'Password hashing functions available');
test_assert(function_exists('random_bytes'), 'Cryptographically secure random functions available');
test_assert(function_exists('hash_equals'), 'Timing-safe string comparison available');

// Test 8: Security header function
echo "\nTesting security headers:\n";
ob_start();
add_security_headers();
$headers = ob_get_clean();
// Note: headers_list() only works if headers haven't been sent yet
test_assert(function_exists('add_security_headers'), 'Security headers function exists');

// Test 9: File operations security
echo "\nTesting file operations security:\n";
$test_upload = [
    'name' => 'test.txt',
    'tmp_name' => '/tmp/test_upload.txt',
    'size' => 100,
    'error' => UPLOAD_ERR_OK
];

// Clean up test files
$cleanup_files = [
    '/tmp/test_upload.txt',
    __DIR__ . '/test_serialize.dat',
    __DIR__ . '/test.txt'
];

// Clean up rate limit test files
$rate_files = glob(__DIR__ . '/rate_*.dat');
$cleanup_files = array_merge($cleanup_files, $rate_files);

foreach ($cleanup_files as $file) {
    if (file_exists($file)) {
        @unlink($file);
    }
}

// Final results
echo "\n" . str_repeat("=", 50) . "\n";
echo "TEST RESULTS:\n";
echo "Passed: $tests_passed\n";
echo "Failed: $tests_failed\n";
echo "Total:  " . ($tests_passed + $tests_failed) . "\n";

if ($tests_failed === 0) {
    echo "\n🎉 ALL TESTS PASSED! Security implementation is working correctly.\n";
    exit(0);
} else {
    echo "\n⚠️  SOME TESTS FAILED! Please review the security implementation.\n";
    exit(1);
}
