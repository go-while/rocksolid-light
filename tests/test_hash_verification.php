<?php
// Test the actual hash from the password file
$hash = '$2y$10$Z1JdvxJl1Lni290sRoDtye1Llj.m0cFtOlXhcSg1TN05Xf/LAn8Ju';
$password = 'test1234';

echo "Testing password verification:\n";
echo "Password: '$password'\n";
echo "Hash: $hash\n";
echo "Result: " . (password_verify($password, $hash) ? 'TRUE' : 'FALSE') . "\n";

// Also test some variations
$variations = [
    'test1234',
    'Test1234',
    'TEST1234',
    'devjorge',
    'password',
    'admin',
    '1234'
];

echo "\nTesting variations:\n";
foreach ($variations as $test_pwd) {
    $result = password_verify($test_pwd, $hash) ? 'TRUE' : 'FALSE';
    echo "  '$test_pwd' -> $result\n";
}
?>
