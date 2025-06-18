<?php
/**
 * Comprehensive Security Audit for RockSolid Light
 * Verifies security includes and proper function usage across the codebase
 */

error_reporting(E_ALL & ~E_WARNING);
ini_set('display_errors', 1);

echo "RockSolid Light Comprehensive Security Audit\n";
echo str_repeat("=", 50) . "\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

$security_issues = [];
$files_checked = 0;
$security_includes_count = 0;
$unserialize_issues = 0;
$missing_headers = 0;

/**
 * Scan a directory for PHP files
 */
function scanForPhpFiles($dir) {
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }
    return $files;
}

/**
 * Check if file has security includes
 */
function hasSecurityInclude($file) {
    $content = file_get_contents($file);
    return strpos($content, 'security.inc.php') !== false;
}

/**
 * Check if file uses add_security_headers()
 */
function hasSecurityHeaders($file) {
    $content = file_get_contents($file);
    return strpos($content, 'add_security_headers()') !== false;
}

/**
 * Check for unsafe unserialize usage
 */
function hasUnsafeUnserialize($file) {
    $content = file_get_contents($file);
    // Look for unserialize that's not secure_unserialize
    $pattern = '/(?<!secure_)unserialize\s*\(/';
    return preg_match($pattern, $content);
}

/**
 * Check if file uses secure_unserialize
 */
function usesSecureUnserialize($file) {
    $content = file_get_contents($file);
    return strpos($content, 'secure_unserialize') !== false;
}

/**
 * Get files that should have security includes (web-facing files)
 */
function shouldHaveSecurityInclude($file) {
    $webFacingDirs = ['rocksolid', 'spoolnews', 'common'];
    $excludeFiles = ['config.inc.php', 'security.inc.php', 'newsportal.php'];

    $basename = basename($file);
    if (in_array($basename, $excludeFiles)) {
        return false;
    }

    foreach ($webFacingDirs as $dir) {
        if (strpos($file, $dir . '/') !== false) {
            return true;
        }
    }
    return false;
}

// Get all PHP files in key directories
$directories = [
    __DIR__ . '/../rocksolid',
    __DIR__ . '/../spoolnews',
    __DIR__ . '/../common'
];

$allFiles = [];
foreach ($directories as $dir) {
    if (is_dir($dir)) {
        $allFiles = array_merge($allFiles, scanForPhpFiles($dir));
    }
}

echo "Scanning " . count($allFiles) . " PHP files...\n\n";

// Analyze each file
foreach ($allFiles as $file) {
    $files_checked++;
    $relativePath = str_replace(__DIR__ . '/../', '', $file);

    // Check for security includes
    if (shouldHaveSecurityInclude($file)) {
        if (hasSecurityInclude($file)) {
            $security_includes_count++;
        } else {
            $security_issues[] = "MISSING SECURITY INCLUDE: $relativePath";
            $missing_headers++;
        }

        // Check for security headers
        if (!hasSecurityHeaders($file) && shouldHaveSecurityInclude($file)) {
            $security_issues[] = "MISSING SECURITY HEADERS: $relativePath";
        }
    }

    // Check for unsafe unserialize
    if (hasUnsafeUnserialize($file)) {
        $security_issues[] = "UNSAFE UNSERIALIZE: $relativePath";
        $unserialize_issues++;
    }

    // Report files using secure_unserialize (good)
    if (usesSecureUnserialize($file)) {
        echo "✓ SECURE: $relativePath uses secure_unserialize()\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "SECURITY AUDIT RESULTS\n";
echo str_repeat("=", 50) . "\n";

echo "Files checked: $files_checked\n";
echo "Files with security includes: $security_includes_count\n";
echo "Missing security headers: $missing_headers\n";
echo "Unsafe unserialize issues: $unserialize_issues\n";
echo "Total security issues: " . count($security_issues) . "\n\n";

if (count($security_issues) > 0) {
    echo "SECURITY ISSUES FOUND:\n";
    echo str_repeat("-", 30) . "\n";
    foreach ($security_issues as $issue) {
        echo "⚠️  $issue\n";
    }
    echo "\n";
} else {
    echo "🎉 NO SECURITY ISSUES FOUND!\n\n";
}

// Check specific security functions
echo "SECURITY FUNCTION AVAILABILITY:\n";
echo str_repeat("-", 30) . "\n";

require_once(__DIR__ . '/../rocksolid/security.inc.php');

$securityFunctions = [
    'secure_unserialize',
    'secure_serialize_file',
    'secure_input',
    'add_security_headers',
    'generate_csrf_token',
    'verify_csrf_token',
    'rate_limit_check',
    'secure_path',
    'get_secure_mime_type'
];

foreach ($securityFunctions as $func) {
    if (function_exists($func)) {
        echo "✓ $func() - Available\n";
    } else {
        echo "❌ $func() - Missing\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "SECURITY INTEGRATION STATUS\n";
echo str_repeat("=", 50) . "\n";

// Check if security.inc.php exists and is readable
$securityFile = __DIR__ . '/rocksolid/security.inc.php';
if (file_exists($securityFile) && is_readable($securityFile)) {
    echo "✓ Security include file exists and is readable\n";
} else {
    echo "❌ Security include file missing or not readable\n";
}

// Check key files for security integration
$keyFiles = [
    'rocksolid/newsportal.php',
    'rocksolid/index.php',
    'rocksolid/search.php',
    'rocksolid/overboard.php',
    'rocksolid/auth.inc.php',
    'spoolnews/user.php',
    'spoolnews/upload.php',
    'spoolnews/mail.php',
    'common/register.php'
];

echo "\nKey files security integration:\n";
foreach ($keyFiles as $file) {
    $fullPath = __DIR__ . '/' . $file;
    if (file_exists($fullPath)) {
        $hasInclude = hasSecurityInclude($fullPath);
        $hasHeaders = hasSecurityHeaders($fullPath);
        $usesSecure = usesSecureUnserialize($fullPath);

        echo "- $file:\n";
        echo "  Security include: " . ($hasInclude ? "✓" : "❌") . "\n";
        echo "  Security headers: " . ($hasHeaders ? "✓" : "❌") . "\n";
        echo "  Secure functions: " . ($usesSecure ? "✓" : "N/A") . "\n";
    }
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "AUDIT COMPLETE\n";
echo str_repeat("=", 50) . "\n";

if (count($security_issues) == 0) {
    echo "🎉 SECURITY AUDIT PASSED!\n";
    echo "RockSolid Light security implementation is complete.\n";
    exit(0);
} else {
    echo "⚠️  SECURITY AUDIT FAILED!\n";
    echo "Please address the issues listed above.\n";
    exit(1);
}
?>
