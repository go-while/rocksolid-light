<?php
/*
 * Quick Spool Reset Script using existing maintenance functions
 * This script provides an easy way to reset the spool using the built-in functions
 */

// Change to the scripts directory
chdir(__DIR__ . '/rslight/scripts');

// Include the required files
if (!file_exists('config.inc.php')) {
    echo "❌ Error: config.inc.php not found. Please run this from the Rocksolid Light root directory.\n";
    exit(1);
}

include "config.inc.php";
include "rslight-lib.php";
include "maintenance.php";

function print_header() {
    echo "\n";
    echo "============================================\n";
    echo "   Rocksolid Light Quick Spool Reset\n";
    echo "============================================\n";
    echo "\n";
}

function print_menu() {
    echo "Available reset options:\n";
    echo "\n";
    echo "1. Clean orphaned group files (safe)\n";
    echo "2. Clear disk cache\n";
    echo "3. Reset specific group\n";
    echo "4. Reset entire section\n";
    echo "5. Remove specific group completely\n";
    echo "6. Show group list\n";
    echo "7. Exit\n";
    echo "\n";
}

function get_user_input($prompt) {
    echo $prompt;
    return trim(fgets(STDIN));
}

function show_groups() {
    global $config_dir;

    echo "Available groups by section:\n";
    echo "============================\n";

    $menulist = file($config_dir . "menu.conf", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($menulist as $menu) {
        if ($menu[0] == '#' || trim($menu) == "") {
            continue;
        }
        $menuitem = explode(':', $menu);
        if ($menuitem[2] == '0') {
            continue;
        }

        echo "\nSection: " . $menuitem[0] . "\n";
        $groupfile = $config_dir . $menuitem[0] . "/groups.txt";
        if (file_exists($groupfile)) {
            $groups = file($groupfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($groups as $group) {
                if ($group[0] == ':' || trim($group) == "") {
                    continue;
                }
                $group_parts = preg_split("/( |\t)/", $group, 2);
                echo "  - " . trim($group_parts[0]) . "\n";
            }
        }
    }
    echo "\n";
}

function confirm_action($message) {
    $response = get_user_input($message . " (y/N): ");
    return strtolower(trim($response)) === 'y';
}

print_header();

// Check if we're running as the right user
$processUser = posix_getpwuid(posix_geteuid());
echo "Running as user: " . $processUser['name'] . "\n";

if ($processUser['name'] !== 'root' && $processUser['name'] !== 'www-data' && $processUser['name'] !== 'apache') {
    echo "⚠️  Warning: You may need to run this as root or web server user for proper permissions.\n";
}

echo "Spool directory: " . $spooldir . "\n";
echo "Config directory: " . $config_dir . "\n\n";

while (true) {
    print_menu();
    $choice = get_user_input("Enter your choice (1-7): ");

    switch (trim($choice)) {
        case '1':
            echo "\n🔄 Cleaning orphaned group files...\n";
            if (confirm_action("This will remove database files for groups not in any groups.txt. Continue?")) {
                clean_spool();
                echo "✅ Cleanup completed.\n";
            }
            break;

        case '2':
            echo "\n🔄 Clearing disk cache...\n";
            if (confirm_action("This will remove all cache files. Continue?")) {
                clear_disk_cache();
                echo "✅ Cache cleared.\n";
            }
            break;

        case '3':
            $group = get_user_input("\nEnter group name to reset: ");
            if (!empty($group)) {
                echo "\n⚠️  This will reset group '$group' (remove all articles but keep in groups.txt)\n";
                if (confirm_action("Continue?")) {
                    echo "Removing articles for $group...\n";
                    remove_articles($group);
                    echo "Resetting group pointers for $group...\n";
                    reset_group($group, 0);
                    echo "✅ Group '$group' has been reset.\n";
                }
            }
            break;

        case '4':
            $section = get_user_input("\nEnter section name to reset: ");
            if (!empty($section)) {
                echo "\n⚠️ ⚠️  This will reset ALL GROUPS in section '$section'\n";
                if (confirm_action("Are you sure?")) {
                    reset_section($section);
                    echo "✅ Section '$section' has been reset.\n";
                }
            }
            break;

        case '5':
            $group = get_user_input("\nEnter group name to remove completely: ");
            if (!empty($group)) {
                echo "\n⚠️ ⚠️  This will COMPLETELY REMOVE group '$group'\n";
                echo "You will need to manually remove it from groups.txt as well.\n";
                if (confirm_action("Are you sure?")) {
                    remove_articles($group);
                    reset_group($group, 1);
                    echo "✅ Group '$group' has been completely removed.\n";
                    echo "ℹ️  Don't forget to remove '$group' from the appropriate groups.txt file.\n";
                }
            }
            break;

        case '6':
            show_groups();
            break;

        case '7':
            echo "\nℹ️  Exiting...\n";
            exit(0);

        default:
            echo "\n❌ Invalid choice. Please select 1-7.\n";
            break;
    }

    echo "\nPress Enter to continue...";
    fgets(STDIN);
    echo "\n";
}
?>
