<?php
/**
 * Debug script to diagnose section/group configuration issues
 * This script will help identify why get_section_by_group() is failing
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Define cron context to avoid router loading
define('CRON_CONTEXT', true);

// Set paths for production environment
$config_dir = "/etc/rslight";
$spooldir = "/var/spool/rslight";
$config_file = $config_dir . '/rslight.inc.php';

// Initialize logging paths
$logdir = $spooldir . '/log';
$debug_log = $logdir . '/debug.log';
$config_name = basename(getcwd());
if (empty($config_name)) {
    $config_name = 'rslight';
}

// Load basic config
if (!file_exists($config_file)) {
    die("Config file not found: $config_file\n");
}
$CONFIG = include($config_file);

// Load required functions
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

echo "=== Group Section Configuration Debug ===\n";
echo "Config directory: $config_dir\n";
echo "Spool directory: $spooldir\n\n";

// Test groups to check
$test_groups = [
    'rocksolid.shared.encryption',
    'rocksolid.shared.entertainment',
    'rocksolid.nodes.announce',
    'rocksolid.spam'
];

echo "=== 1. Testing menu.conf loading ===\n";
$menu_file = $config_dir . '/menu.conf';
if (file_exists($menu_file)) {
    echo "✅ menu.conf exists\n";
    $menulist = get_section_menu_array();
    echo "Found " . count($menulist) . " menu entries:\n";
    foreach ($menulist as $menu) {
        echo "  - $menu";
    }
} else {
    echo "❌ menu.conf not found at: $menu_file\n";
    exit(1);
}

echo "\n=== 2. Testing section directories and groups.txt files ===\n";
foreach ($menulist as $menu) {
    $menuitem = explode(':', trim($menu));
    if (count($menuitem) < 3) {
        echo "⚠️  Invalid menu format: $menu\n";
        continue;
    }

    $section_name = $menuitem[0];
    $section_enabled = $menuitem[1];

    echo "Section: $section_name (enabled: $section_enabled)\n";

    $groups_file = $config_dir . '/' . $section_name . '/groups.txt';
    echo "  Looking for: $groups_file\n";

    if (file_exists($groups_file)) {
        echo "  ✅ groups.txt exists\n";
        $groups_data = file($groups_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        echo "  Found " . count($groups_data) . " lines in groups.txt:\n";
        foreach ($groups_data as $idx => $group_line) {
            $clean_line = trim($group_line);
            if (!empty($clean_line) && !preg_match('/^#/', $clean_line)) {
                echo "    [$idx] '$clean_line'\n";
            }
        }
    } else {
        echo "  ❌ groups.txt not found\n";
    }
    echo "\n";
}

echo "=== 3. Testing get_section_by_group() function ===\n";

// Test each group individually with detailed debugging
foreach ($test_groups as $test_group) {
    echo "Testing group: '$test_group'\n";

    // Call the function with debug output
    $section = get_section_by_group_debug($test_group, true);

    if ($section) {
        echo "  ✅ Found in section: $section\n";
    } else {
        echo "  ❌ Not found in any section\n";
    }
    echo "\n";
}

echo "=== 4. Manual string comparison test ===\n";
// Test manual string comparison to see if there are encoding/whitespace issues
$test_group = 'rocksolid.shared.encryption';
$groups_file = $config_dir . '/rocksolid/groups.txt';

if (file_exists($groups_file)) {
    $groups_data = file($groups_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    echo "Manual comparison for '$test_group':\n";

    foreach ($groups_data as $idx => $group_line) {
        $clean_line = trim($group_line);
        if (empty($clean_line) || preg_match('/^#/', $clean_line)) {
            continue;
        }

        // Split line to get just the group name (before space/tab)
        $group_parts = preg_split("/( |\t)/", $clean_line, 2);
        $group_name = trim($group_parts[0]);

        // Test various comparison methods
        $exact_match = ($test_group === $group_name);
        $case_insensitive = (strtolower($test_group) === strtolower($group_name));

        echo "  Line $idx: '$group_name' ";
        echo "exact:" . ($exact_match ? "✅" : "❌") . " ";
        echo "case_insensitive:" . ($case_insensitive ? "✅" : "❌") . " ";
        echo "lengths: " . strlen($test_group) . " vs " . strlen($group_name) . "\n";

        if ($case_insensitive) {
            echo "    ✅ MATCH FOUND on line $idx!\n";
            break;
        }
    }
}

echo "\n=== Debug Complete ===\n";

/**
 * Debug version of get_section_by_group with verbose output
 */
function get_section_by_group_debug($groupname, $all_sections = false)
{
    global $config_dir;

    echo "  DEBUG: Looking for group '$groupname'\n";

    $menulist = get_section_menu_array();
    echo "  DEBUG: Got " . count($menulist) . " menu items\n";

    // Get first group in Newsgroups (handle comma-separated)
    $groupname_parts = preg_split("/( |\,)/", $groupname, 2);
    $groupname = $groupname_parts[0];
    echo "  DEBUG: Cleaned group name: '$groupname'\n";

    foreach ($menulist as $menu) {
        $menuitem = explode(':', trim($menu));
        echo "  DEBUG: Checking menu item: " . trim($menu) . "\n";

        if (count($menuitem) < 2) {
            echo "  DEBUG: Invalid menu format, skipping\n";
            continue;
        }

        if ($menuitem[1] == '0') {
            if (!$all_sections) {
                echo "  DEBUG: Section {$menuitem[0]} disabled, skipping\n";
                continue;
            }
        }

        $section = "";
        $groups_file = $config_dir . '/' . $menuitem[0] . "/groups.txt";
        echo "  DEBUG: Checking groups file: $groups_file\n";

        if (!file_exists($groups_file)) {
            echo "  DEBUG: Groups file does not exist, skipping\n";
            continue;
        }

        $gldata = file($groups_file);
        if ($gldata === false) {
            echo "  DEBUG: Could not read groups file, skipping\n";
            continue;
        }

        echo "  DEBUG: Found " . count($gldata) . " lines in groups file\n";

        foreach ($gldata as $line_num => $gl) {
            $group_name_parts = preg_split("/( |\t)/", $gl, 2);
            $file_group_name = trim($group_name_parts[0]);

            // Skip empty lines and comments
            if (empty($file_group_name) || preg_match('/^#/', $file_group_name)) {
                continue;
            }

            echo "  DEBUG: Comparing '$groupname' with '$file_group_name' (line $line_num)\n";

            if (strtolower(trim($groupname)) == strtolower(trim($file_group_name))) {
                echo "  DEBUG: ✅ MATCH FOUND! Returning section: {$menuitem[0]}\n";
                $section = $menuitem[0];
                return $section;
            }
        }
    }

    echo "  DEBUG: ❌ No match found in any section\n";
    return false;
}
?>
