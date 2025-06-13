<?php
/**
 * Test script to verify head.inc functions work correctly
 */

// Start session like other scripts do
session_start();

// Set some required globals
$title = "Test Title";
$config_dir = "/var/www/html/rslight";
$logdir = "/var/log/rslight";
$spooldir = "/var/spool/rslight";
$config_name = "test";
$abuse_log = $logdir . "/abuse.log";

// Create directories if they don't exist
@mkdir($logdir, 0755, true);
@mkdir($spooldir, 0755, true);

// Mock minimal config
$CONFIG = array();
$OVERRIDES = array();

echo "Testing head.inc functions...\n\n";

// Test head.inc inclusion
echo "1. Testing head.inc inclusion...\n";
try {
    // Capture output to avoid HTML display
    ob_start();
    include "head.inc";
    $output = ob_get_clean();
    echo "   ✓ head.inc included successfully\n";
    echo "   ✓ HTML output captured (length: " . strlen($output) . " bytes)\n";
} catch (Exception $e) {
    echo "   ✗ Error including head.inc: " . $e->getMessage() . "\n";
}

echo "\n2. Testing individual functions...\n";

// Test get_client_user_agent_info
try {
    $client_device = get_client_user_agent_info();
    echo "   ✓ get_client_user_agent_info() returned: $client_device\n";
} catch (Exception $e) {
    echo "   ✗ get_client_user_agent_info() failed: " . $e->getMessage() . "\n";
}

// Test logging_prefix
try {
    $prefix = logging_prefix();
    echo "   ✓ logging_prefix() returned: $prefix\n";
} catch (Exception $e) {
    echo "   ✗ logging_prefix() failed: " . $e->getMessage() . "\n";
}

// Test format_log_date
try {
    $date = format_log_date();
    echo "   ✓ format_log_date() returned: $date\n";
} catch (Exception $e) {
    echo "   ✗ format_log_date() failed: " . $e->getMessage() . "\n";
}

// Test write_access_log
try {
    write_access_log();
    echo "   ✓ write_access_log() executed successfully\n";
} catch (Exception $e) {
    echo "   ✗ write_access_log() failed: " . $e->getMessage() . "\n";
}

echo "\n3. Testing function availability before and after head.inc...\n";

// Check if functions are available
$functions_to_check = [
    'get_client_user_agent_info',
    'throttle_hits', 
    'write_access_log',
    'logging_prefix',
    'format_log_date',
    'secure_unserialize'
];

foreach ($functions_to_check as $func) {
    if (function_exists($func)) {
        echo "   ✓ Function $func is available\n";
    } else {
        echo "   ✗ Function $func is NOT available\n";
    }
}

echo "\nTest completed!\n";
?>
