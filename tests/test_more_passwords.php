<?php
// Test more passwords that might be the real password
$hash_from_file = '$2y$10$Z1JdvxJl1Lni290sRoDtye1Llj.m0cFtOlXhcSg1TN05Xf/LAn8Ju';

$test_passwords = [
    'test1234',
    'password',
    'admin',
    'devjorge',
    'jorge',
    'dev',
    'dev123',
    'dev1234',
    'jorge123',
    'jorge1234',
    'Password',
    'Password123',
    'test',
    'testing',
    'demo',
    'demo123',
    'demo1234',
    'user',
    'user123',
    'user1234',
    'default',
    'default123',
    '123456',
    'qwerty',
    'rslight',
    'rocksolid',
    ''
];

echo "Testing against hash: $hash_from_file\n\n";

foreach ($test_passwords as $test_pw) {
    $result = password_verify($test_pw, $hash_from_file);
    if ($result) {
        echo "*** FOUND IT! Password '$test_pw': VALID ***\n";
    } else {
        echo "Password '$test_pw': INVALID\n";
    }
}

echo "\nIf none match, we'll need to reset the password.\n";
?>
