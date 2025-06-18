<?php
/**
 * Simple Security Function Test
 */

require_once(__DIR__ . '/../rocksolid/security.inc.php');

echo "Testing Security Functions...\n";

// Test 1: Basic input validation
echo "1. Testing secure_input()...\n";
$result1 = secure_input('test123', 'alphanum');
echo "   alphanum test: " . ($result1 === 'test123' ? 'PASS' : 'FAIL') . "\n";

$result2 = secure_input('<script>alert("xss")</script>', 'html');
echo "   HTML escape test: " . (strpos($result2, '&lt;') !== false ? 'PASS' : 'FAIL') . "\n";

// Test 2: File operations
echo "2. Testing secure_unserialize()...\n";
$test_file = '/tmp/test_data.json';
file_put_contents($test_file, json_encode(['test' => 'value']));
$result3 = secure_unserialize($test_file);
echo "   JSON unserialize test: " . (isset($result3['test']) && $result3['test'] === 'value' ? 'PASS' : 'FAIL') . "\n";
unlink($test_file);

// Test 3: MIME type detection
echo "3. Testing get_secure_mime_type()...\n";
if (function_exists('finfo_open')) {
    $test_txt = '/tmp/test.txt';
    file_put_contents($test_txt, 'Hello World');
    $mime = get_secure_mime_type($test_txt);
    echo "   MIME detection test: " . (strpos($mime, 'text/') === 0 ? 'PASS' : 'FAIL') . "\n";
    unlink($test_txt);
} else {
    echo "   MIME detection test: SKIP (finfo not available)\n";
}

// Test 4: Path security
echo "4. Testing secure_path()...\n";
$safe_path = secure_path('normal/path/file.txt');
$unsafe_path = secure_path('../../../etc/passwd');
echo "   Safe path test: " . ($safe_path === 'normal/path/file.txt' ? 'PASS' : 'FAIL') . "\n";
echo "   Unsafe path test: " . ($unsafe_path === false ? 'PASS' : 'FAIL') . "\n";

echo "\nSecurity functions basic test completed!\n";
