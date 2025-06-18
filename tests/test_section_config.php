<?php
// Test script to debug section configuration lookup
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define cron context to avoid router loading
define('CRON_CONTEXT', true);
define('DEBUG_SECTION_LOOKUP', true); // Enable debug output

// Set up local development paths
$config_dir = __DIR__ . "/rslight";
$spooldir = __DIR__ . "/spool";

// Load required functions
require_once($config_dir . '/inc/functions.inc.php');

echo "=== Section Configuration Debug ===\n";
echo "Config dir: $config_dir\n";

// Test get_section_menu_array
echo "\n=== Menu Configuration ===\n";
$menulist = get_section_menu_array();
foreach ($menulist as $menu) {
    echo "Menu entry: $menu\n";
    $menuitem = explode(':', $menu);
    echo "  Section: {$menuitem[0]}\n";
    echo "  Display: {$menuitem[1]}\n";
    echo "  Spool: {$menuitem[2]}\n";

    $groups_file = $config_dir . '/' . $menuitem[0] . "/groups.txt";
    echo "  Groups file: $groups_file\n";
    echo "  File exists: " . (file_exists($groups_file) ? "YES" : "NO") . "\n";

    if (file_exists($groups_file)) {
        $gldata = file($groups_file);
        echo "  Groups in this section:\n";
        foreach ($gldata as $gl) {
            $group_name = preg_split("/( |\t)/", $gl, 2);
            $group_name = trim($group_name[0]);
            if (!empty($group_name) && substr($group_name, 0, 1) !== ':' && substr($group_name, 0, 1) !== '#') {
                echo "    - $group_name\n";
            }
        }
    }
    echo "\n";
}

// Test specific group lookup
echo "\n=== Testing Specific Group Lookup ===\n";
$test_groups = [
    'rocksolid.shared.encryption',
    'rocksolid.nodes.announce',
    'rocksolid.spam'
];

foreach ($test_groups as $test_group) {
    echo "Testing group: $test_group\n";
    $section = get_section_by_group($test_group, true);
    echo "  Found in section: " . ($section ? $section : "NOT FOUND") . "\n";
    echo "\n";
}

echo "=== Debug Complete ===\n";
?>
