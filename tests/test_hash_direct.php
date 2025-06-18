<?php
// Test the exact hash from the password file
$hash_from_file = '$2y$10$Z1JdvxJl1Lni290sRoDtye1Llj.m0cFtOlXhcSg1TN05Xf/LAn8Ju';
$password = 'test1234';

echo "Testing password: '$password'\n";
echo "Against hash: $hash_from_file\n";
echo "Result: " . (password_verify($password, $hash_from_file) ? 'VALID' : 'INVALID') . "\n";

// Test a few other common passwords
$test_passwords = ['test1234', 'password', 'admin', 'devjorge', 'jorge', ''];
foreach ($test_passwords as $test_pw) {
    $result = password_verify($test_pw, $hash_from_file);
    echo "Password '$test_pw': " . ($result ? 'VALID' : 'INVALID') . "\n";
}
?>
