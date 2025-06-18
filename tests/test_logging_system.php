<?php
/**
 * Test script to verify logging system works in both web and cron contexts
 */

echo "=== Logging System Test ===\n";

// Test 1: Web context (default)
echo "\n1. Testing WEB context:\n";
include "common/config.inc.php";

echo "   config_name: " . (isset($config_name) ? $config_name : 'NOT SET') . "\n";
echo "   debug_log: " . (isset($debug_log) ? $debug_log : 'NOT SET') . "\n";
echo "   logdir: " . (isset($logdir) ? $logdir : 'NOT SET') . "\n";

// Test logging functions
if (function_exists('debug_log')) {
    debug_log("Test debug message from web context", $debug_log);
    echo "   ✅ debug_log() function available\n";
} else {
    echo "   ❌ debug_log() function NOT available\n";
}

if (function_exists('error_log_always')) {
    error_log_always("Test error message from web context", $debug_log);
    echo "   ✅ error_log_always() function available\n";
} else {
    echo "   ❌ error_log_always() function NOT available\n";
}

// Test 2: Cron context
echo "\n2. Testing CRON context:\n";

// Reset all variables to simulate clean cron environment
unset($config_name, $debug_log, $logdir, $CONFIG, $OVERRIDES);

// Define cron context
define('CRON_CONTEXT_TEST', true);
$_SERVER['HTTP_HOST'] = null; // Simulate no web environment

include "common/config.inc.php";

echo "   config_name: " . (isset($config_name) ? $config_name : 'NOT SET') . "\n";
echo "   debug_log: " . (isset($debug_log) ? $debug_log : 'NOT SET') . "\n";
echo "   logdir: " . (isset($logdir) ? $logdir : 'NOT SET') . "\n";

// Test logging functions in cron context
if (function_exists('debug_log')) {
    debug_log("Test debug message from cron context", $debug_log);
    echo "   ✅ debug_log() function available in cron context\n";
} else {
    echo "   ❌ debug_log() function NOT available in cron context\n";
}

if (function_exists('error_log_always')) {
    error_log_always("Test error message from cron context", $debug_log);
    echo "   ✅ error_log_always() function available in cron context\n";
} else {
    echo "   ❌ error_log_always() function NOT available in cron context\n";
}

echo "\n3. Checking log file:\n";
if (file_exists($debug_log)) {
    echo "   ✅ Log file exists: $debug_log\n";
    echo "   Last 5 lines:\n";
    $lines = file($debug_log);
    $last_lines = array_slice($lines, -5);
    foreach ($last_lines as $line) {
        echo "     " . trim($line) . "\n";
    }
} else {
    echo "   ❌ Log file does not exist: $debug_log\n";
}

echo "\n=== Test Complete ===\n";
?>
