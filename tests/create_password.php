<?php
// Generate new password hash for devjorge
$username = 'devjorge';
$new_password = 'test1234';

echo "Creating new password hash for user: $username\n";
echo "New password: $new_password\n\n";

// Generate new hash
$new_hash = password_hash($new_password, PASSWORD_DEFAULT);
echo "New hash: $new_hash\n";
echo "Hash length: " . strlen($new_hash) . "\n\n";

// Verify the new hash works
$verify_result = password_verify($new_password, $new_hash);
echo "Verification test: " . ($verify_result ? 'SUCCESS' : 'FAILED') . "\n\n";

if ($verify_result) {
    $userfile = '/etc/rslight/users/' . $username;
    echo "To update the password, run:\n";
    echo "echo '$new_hash' > $userfile\n\n";

    echo "Or copy this hash and manually replace the content in $userfile:\n";
    echo "$new_hash\n";
} else {
    echo "ERROR: Hash verification failed!\n";
}
?>
