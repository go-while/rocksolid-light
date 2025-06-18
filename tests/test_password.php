<?php
// Comprehensive password verification test
$username = 'devjorge';
$test_password = 'test1234';

$userfile = '/etc/rslight/users/' . $username;
if (file_exists($userfile)) {
    $stored_hash = trim(file_get_contents($userfile));
    echo "User: $username\n";
    echo "Stored hash: $stored_hash\n";
    echo "Hash length: " . strlen($stored_hash) . "\n";
    echo "Hash starts with: " . substr($stored_hash, 0, 7) . "\n";

    echo "\nTesting primary password:\n";
    $verify_result = password_verify($test_password, $stored_hash);
    echo "Password '$test_password' verification: " . ($verify_result ? 'SUCCESS' : 'FAILED') . "\n";

    echo "\nTesting common variations:\n";
    $variations = [
        $test_password,
        'test1234',
        'Test1234',
        'TEST1234',
        'devjorge',
        'password',
        'admin',
        '123456',
        'test',
        'letmein',
        'qwerty',
        'devjorge123'
    ];

    foreach ($variations as $pwd) {
        $result = password_verify($pwd, $stored_hash);
        if ($result) {
            echo "✓ FOUND MATCHING PASSWORD: '$pwd'\n";
        } else {
            echo "✗ '$pwd' - FAILED\n";
        }
    }

    echo "\nHash info:\n";
    $info = password_get_info($stored_hash);
    echo "Algorithm: " . $info['algoName'] . "\n";
    echo "Options: " . print_r($info['options'], true) . "\n";

} else {
    echo "User file not found: $userfile\n";
}
?>
