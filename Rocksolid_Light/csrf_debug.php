<?php
/**
 * Debug CSRF token functionality
 */

error_reporting(E_ALL & ~E_WARNING);
ini_set('display_errors', 1);

// Suppress session warnings in CLI mode
ini_set('session.use_cookies', '0');
ini_set('session.use_trans_sid', '0');
ini_set('session.cache_limiter', '');

require_once(__DIR__ . '/rocksolid/security.inc.php');

echo "CSRF Debug Test\n";
echo "===============\n";
echo "PHP Version: " . PHP_VERSION . "\n";
echo "Session status before: " . session_status() . "\n";

// Start session explicitly
if (session_status() != PHP_SESSION_ACTIVE) {
    @session_start();
}

echo "Session status after start: " . session_status() . "\n";
echo "Session ID: " . session_id() . "\n";

// Test token generation
echo "\nGenerating first token...\n";
$token1 = generate_csrf_token();
echo "Token1: " . $token1 . "\n";
echo "Session csrf_token: " . (isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : 'NOT SET') . "\n";

echo "\nGenerating second token...\n";
$token2 = generate_csrf_token();
echo "Token2: " . $token2 . "\n";
echo "Session csrf_token: " . (isset($_SESSION['csrf_token']) ? $_SESSION['csrf_token'] : 'NOT SET') . "\n";

echo "\nTokens match: " . ($token1 === $token2 ? 'YES' : 'NO') . "\n";

echo "\nVerifying token1...\n";
$verify1 = verify_csrf_token($token1);
echo "Verification result: " . ($verify1 ? 'TRUE' : 'FALSE') . "\n";

echo "\nVerifying invalid token...\n";
$verify2 = verify_csrf_token('invalid_token');
echo "Verification result: " . ($verify2 ? 'TRUE' : 'FALSE') . "\n";
