<?php
// Check existing user configuration and cookie
$username = 'devjorge';
$config_dir = '/etc/rslight';

echo "=== User Configuration Check ===\n";

// Check userconfig file
$userconfig_file = $config_dir . '/userconfig/' . $username;
echo "Userconfig file: $userconfig_file\n";
if (file_exists($userconfig_file)) {
    $content = file_get_contents($userconfig_file);
    echo "Content: $content\n";
} else {
    echo "File does not exist\n";
}

// Check keys.dat
$keyfile = '/home/fed/GO/src/github.com/go-while/rocksolid-light/Rocksolid_Light/spool/keys.dat';
echo "\nKeys file: $keyfile\n";
if (file_exists($keyfile)) {
    echo "Keys file exists\n";
    // Try to read it
    $keys_content = file_get_contents($keyfile);
    echo "Keys content length: " . strlen($keys_content) . "\n";
} else {
    echo "Keys file does not exist\n";
}

// Check the current mail_auth cookie value
$current_cookie = '$2y$10$PAuyCh5WMHmDSTU9jUMiXOASi8DHFHp0Ph06kfl89p9EuXPW8dyeC';
echo "\nCurrent mail_auth cookie: $current_cookie\n";
echo "Cookie length: " . strlen($current_cookie) . "\n";
echo "Cookie starts with: " . substr($current_cookie, 0, 7) . "\n";

?>
