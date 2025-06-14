<?php
/**
 * Test script to verify cron.php can find all its dependencies
 * when run from the rocksolid directory
 */

echo "🧪 Testing Cron Dependencies\n";
echo "============================\n\n";

// Simulate running from rocksolid directory
chdir('rocksolid');
echo "📁 Changed to rocksolid directory: " . getcwd() . "\n\n";

echo "🔍 Checking if cron.php can find its dependencies...\n";

// Test the includes that cron.php needs
$dependencies = [
    'lib/config.inc.php' => 'Configuration file',
    'newsportal.php' => 'Main newsportal functions',
    '../common/config.inc.php' => 'Common configuration (via config.inc.php)',
];

$success = true;

foreach ($dependencies as $file => $description) {
    if (file_exists($file)) {
        echo "✅ Found: $file ($description)\n";
    } else {
        echo "❌ Missing: $file ($description)\n";
        $success = false;
    }
}

echo "\n🔧 Testing actual includes...\n";

// Test if config.inc.php loads without errors
try {
    ob_start();
    if (file_exists('lib/config.inc.php')) {
        include_once 'lib/config.inc.php';
        echo "✅ lib/config.inc.php loaded successfully\n";
    } else {
        echo "❌ lib/config.inc.php not found\n";
        $success = false;
    }
    ob_end_clean();
} catch (Exception $e) {
    echo "❌ Error loading lib/config.inc.php: " . $e->getMessage() . "\n";
    $success = false;
    ob_end_clean();
}

// Test if we can at least syntax-check the cron.php script
$cron_script = '../rslight/scripts/cron.php';
if (file_exists($cron_script)) {
    $output = shell_exec("php -l $cron_script 2>&1");
    if (strpos($output, 'No syntax errors') !== false) {
        echo "✅ Cron script syntax is valid\n";
    } else {
        echo "❌ Cron script has syntax errors:\n$output\n";
        $success = false;
    }
} else {
    echo "❌ Cron script not found at $cron_script\n";
    $success = false;
}

echo "\n📋 Test Summary:\n";
echo "===============\n";

if ($success) {
    echo "✅ All dependencies found - cron job should work correctly!\n";
    echo "🚀 The fixed cron command will work:\n";
    echo "   */5 * * * * cd \$webroot/rocksolid ; bash -lc \"php \$configpath/scripts/cron.php\"\n";
} else {
    echo "❌ Some dependencies missing - cron job may fail\n";
    echo "🔧 Check the installation process and file locations\n";
}

echo "\n";
