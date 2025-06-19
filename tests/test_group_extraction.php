<?php
// Test group name extraction logic from article_db_open

// Set CLI mode to avoid web routing issues
$_SERVER['REQUEST_METHOD'] = 'CLI';
$_SERVER['HTTP_HOST'] = 'localhost';
define('CRON_CONTEXT', true);

// Set up configuration manually like debug_groups.php does
$config_dir = "/etc/rslight";
$spooldir = "/var/spool/rslight";
$config_file = $config_dir . '/rslight.inc.php';
$logdir = $spooldir . '/log';
$debug_log = $logdir . '/debug.log';
$config_name = basename(getcwd());
if (empty($config_name)) {
    $config_name = 'rocksolid';
}

// Load the configuration array like debug_groups.php does
if (!file_exists($config_file)) {
    die("Config file not found: $config_file\n");
}
$CONFIG = include($config_file);

// Load required functions like debug_groups.php does
$lib_files = [
    "security.inc.php",
    "functions.inc.php",
    "logging_control.php"
];

foreach ($lib_files as $lib_file) {
    $lib_path = $config_dir . '/inc/' . $lib_file;
    if (!file_exists($lib_path)) {
        die("Required library file not found: $lib_path\n");
    }
    require_once($lib_path);
}

// Define debug constant to enable debug output (comment out for clean output)
// define('DEBUG_SECTION_LOOKUP', true);

// Test various database paths that might be used
$test_paths = [
    '/var/spool/rslight/alt.anonymous-articles.db3',
    '/var/spool/rslight/alt.binaries.multimedia-articles.db3',
    '/var/spool/rslight/alt.test-articles.db3',
    'alt.anonymous-articles.db3',
    'alt.binaries.multimedia-articles.db3',
    'alt.test-articles.db3',
    '/var/spool/rslight/alt.anonymous-articles.db3-new',
];

echo "Testing group name extraction logic:\n";
echo "Current spooldir: " . $spooldir . "\n";
echo "Function get_section_by_group exists: " . (function_exists('get_section_by_group') ? 'YES' : 'NO') . "\n";
echo "Config directory: " . (isset($config_dir) ? $config_dir : 'NOT SET') . "\n";

// Test function with a known good group
if (function_exists('get_section_by_group')) {
    echo "Testing function with known group 'rocksolid.shared.encryption':\n";
    echo "=== DEBUG OUTPUT START ===\n";
    $test_section = get_section_by_group('rocksolid.shared.encryption', true);
    echo "=== DEBUG OUTPUT END ===\n";
    echo "Result: " . ($test_section ? "YES ($test_section)" : "NO") . "\n";
} else {
    echo "get_section_by_group function not available!\n";
}

echo "\n";

foreach ($test_paths as $database) {
    // Replicate the exact logic from article_db_open
    $spoolpath = "/" . preg_replace("/\//", "\/", $spooldir) . "/";
    $group = preg_replace("/\-articles\.db3/", "", $database);
    $group = preg_replace($spoolpath, "", $group);
    $group = preg_replace("/\//", "", $group);

    echo "Database path: $database\n";
    echo "Spoolpath pattern: $spoolpath\n";
    echo "After removing -articles.db3: " . preg_replace("/\-articles\.db3/", "", $database) . "\n";
    echo "After removing spoolpath: " . preg_replace($spoolpath, "", preg_replace("/\-articles\.db3/", "", $database)) . "\n";
    echo "Final group: '$group'\n";

    // Check if this group exists in section config
    $section = get_section_by_group($group, true);
    echo "Section found: " . ($section ? "YES ($section)" : "NO") . "\n";
    echo "---\n";
}

echo "\nNow testing with actual database files in spool:\n";
$spool_files = glob($spooldir . '/*-articles.db3');
foreach ($spool_files as $database) {
    $spoolpath = "/" . preg_replace("/\//", "\/", $spooldir) . "/";
    $group = preg_replace("/\-articles\.db3/", "", $database);
    $group = preg_replace($spoolpath, "", $group);
    $group = preg_replace("/\//", "", $group);

    echo "Database: $database\n";
    echo "Extracted group: '$group'\n";
    $section = get_section_by_group($group, true);
    echo "Section found: " . ($section ? "YES ($section)" : "NO") . "\n";
    echo "---\n";
}
?>
