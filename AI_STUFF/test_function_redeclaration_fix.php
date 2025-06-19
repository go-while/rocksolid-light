#!/usr/bin/php
<?php
/**
 * Test script to verify that function redeclaration is fixed
 */

echo "=== FUNCTION REDECLARATION TEST ===\n";

// Test 1: Include the file multiple times
echo "Test 1: Including allowed_languages.inc.php multiple times...\n";

include 'rocksolid/allowed_languages.inc.php';
echo "   ✅ First include successful\n";

include 'rocksolid/allowed_languages.inc.php';
echo "   ✅ Second include successful (no redeclaration error)\n";

include 'rocksolid/allowed_languages.inc.php';
echo "   ✅ Third include successful (no redeclaration error)\n";

// Test 2: Check that functions exist and work
echo "\nTest 2: Testing function availability...\n";

if (function_exists('is_language_allowed')) {
    echo "   ✅ is_language_allowed() function exists\n";

    // Test the function
    $test_valid = is_language_allowed('english.lang');
    $test_invalid = is_language_allowed('nonexistent.lang');

    if ($test_valid === true && $test_invalid === false) {
        echo "   ✅ is_language_allowed() works correctly\n";
    } else {
        echo "   ❌ is_language_allowed() not working correctly\n";
        exit(1);
    }
} else {
    echo "   ❌ is_language_allowed() function not found\n";
    exit(1);
}

if (function_exists('get_language_display_name')) {
    echo "   ✅ get_language_display_name() function exists\n";

    $display_name = get_language_display_name('english.lang');
    if ($display_name === 'English') {
        echo "   ✅ get_language_display_name() works correctly\n";
    } else {
        echo "   ❌ get_language_display_name() returned: '$display_name'\n";
        exit(1);
    }
} else {
    echo "   ❌ get_language_display_name() function not found\n";
    exit(1);
}

if (function_exists('get_allowed_languages')) {
    echo "   ✅ get_allowed_languages() function exists\n";

    $languages = get_allowed_languages();
    if (is_array($languages) && count($languages) > 100) {
        echo "   ✅ get_allowed_languages() returns " . count($languages) . " languages\n";
    } else {
        echo "   ❌ get_allowed_languages() not working correctly\n";
        exit(1);
    }
} else {
    echo "   ❌ get_allowed_languages() function not found\n";
    exit(1);
}

// Test 3: Simulate web environment includes
echo "\nTest 3: Simulating web environment includes...\n";

// This simulates what happens when both rocksolid and spoolnews configs are loaded
include 'spoolnews/allowed_languages.inc.php'; // This is a symlink to rocksolid version
echo "   ✅ Symlink include successful (no redeclaration error)\n";

echo "\n🎉 ALL TESTS PASSED!\n";
echo "\nThe function redeclaration error has been fixed.\n";
echo "The production server should now work without PHP fatal errors.\n";
?>
