<?php
// Test script to verify override loading
echo "Testing override loading...\n";

// Set up minimal required global variables
global $OVERRIDES, $debug_log;
$debug_log = "/tmp/test_debug.log";

// Simulate the config.inc.php loading process
$OVERRIDES = array();

// Load overrides from local directory (runtime application config)
if (file_exists(__DIR__ . '/overrides.inc.php')) {
    $OVERRIDES = include (__DIR__ . '/overrides.inc.php');
    echo "✅ Loaded overrides from local directory\n";
} else {
    echo "❌ No overrides file found\n";
}

echo "OVERRIDES variable type: " . gettype($OVERRIDES) . "\n";
echo "OVERRIDES contents:\n";
print_r($OVERRIDES);

// Test logging control
require_once('logging_control.php');

echo "\nTesting logging control functions:\n";
echo "is_debug_logging_enabled(): " . (is_debug_logging_enabled() ? "true" : "false") . "\n";

// Test debug logging
echo "\nTesting debug_log function:\n";
debug_log("This is a test debug message\n", $debug_log);
echo "Debug message written (check if it was actually written based on settings)\n";

// Test important logging
echo "\nTesting important_log function:\n";
important_log("This is a test important message\n", $debug_log);
echo "Important message written (should always be written)\n";

// Check if log file was created
if (file_exists($debug_log)) {
    echo "\n📁 Log file created at: $debug_log\n";
    echo "Log file contents:\n";
    echo file_get_contents($debug_log);
} else {
    echo "\n❌ No log file created (this is expected in production mode)\n";
}

echo "\n✅ Test completed\n";
?>
