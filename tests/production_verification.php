<?php
/**
 * RockSolid Light Production Verification Script
 * Final verification for deployment readiness
 */

echo "=== RockSolid Light Production Verification ===" . PHP_EOL;
echo "Generated: " . date('Y-m-d H:i:s') . PHP_EOL . PHP_EOL;

// Check if security.inc.php exists and is accessible
$security_file = __DIR__ . '/../rocksolid/security.inc.php';
if (!file_exists($security_file)) {
    echo "❌ CRITICAL: security.inc.php not found!" . PHP_EOL;
    exit(1);
}

require_once($security_file);

// 1. PHP Syntax Check
echo "1. PHP SYNTAX CHECK:" . PHP_EOL;
$php_files = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/..'));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'php') {
        $path = $file->getPathname();
        if (strpos($path, '/tests/') === false && strpos($path, '/spool/') === false) {
            $php_files[] = $path;
        }
    }
}

$syntax_errors = 0;
foreach ($php_files as $file) {
    $output = [];
    $return_var = 0;
    exec("php -l " . escapeshellarg($file) . " 2>&1", $output, $return_var);
    if ($return_var !== 0) {
        echo "❌ Syntax error in: $file" . PHP_EOL;
        echo "   " . implode("\n   ", $output) . PHP_EOL;
        $syntax_errors++;
    }
}

if ($syntax_errors === 0) {
    echo "✅ All " . count($php_files) . " PHP files passed syntax check" . PHP_EOL;
} else {
    echo "❌ $syntax_errors files have syntax errors" . PHP_EOL;
}

// 2. Security Integration Check
echo PHP_EOL . "2. SECURITY INTEGRATION:" . PHP_EOL;

$security_includes = 0;
$security_headers = 0;
$secure_unserialize_usage = 0;
$unsafe_unserialize = 0;

foreach ($php_files as $file) {
    $content = file_get_contents($file);

    if (strpos($content, 'security.inc.php') !== false) {
        $security_includes++;
    }

    if (strpos($content, 'add_security_headers') !== false) {
        $security_headers++;
    }

    if (strpos($content, 'secure_unserialize') !== false) {
        $secure_unserialize_usage++;
    }

    // Check for unsafe unserialize (not in security.inc.php and not secure_unserialize)
    if (strpos($file, 'security.inc.php') === false && strpos($file, 'production_verification.php') === false) {
        // Look for actual unsafe unserialize calls, excluding secure_unserialize and comments
        $lines = explode("\n", $content);
        foreach ($lines as $line_num => $line) {
            $line = trim($line);
            // Skip comments and secure_unserialize calls
            if (strpos($line, '//') === 0 || strpos($line, '*') === 0 || strpos($line, 'secure_unserialize') !== false) {
                continue;
            }
            // Look for actual unserialize( calls
            if (preg_match('/[^a-zA-Z_]unserialize\s*\(/', $line)) {
                $unsafe_unserialize++;
                echo "❌ Unsafe unserialize found in: $file (line " . ($line_num + 1) . ")" . PHP_EOL;
                echo "   $line" . PHP_EOL;
                break; // Only report first occurrence per file
            }
        }
    }
}

echo "✅ Files with security includes: $security_includes" . PHP_EOL;
echo "✅ Files with security headers: $security_headers" . PHP_EOL;
echo "✅ Secure unserialize usage: $secure_unserialize_usage" . PHP_EOL;

if ($unsafe_unserialize === 0) {
    echo "✅ No unsafe unserialize calls found" . PHP_EOL;
} else {
    echo "❌ $unsafe_unserialize unsafe unserialize calls found" . PHP_EOL;
}

// 3. Security Functions Check
echo PHP_EOL . "3. SECURITY FUNCTIONS:" . PHP_EOL;
$required_functions = [
    'secure_unserialize',
    'add_security_headers',
    'secure_input',
    'generate_csrf_token',
    'verify_csrf_token',
    'rate_limit_check',
    'secure_path',
    'get_secure_mime_type'
];

$missing_functions = 0;
foreach ($required_functions as $function) {
    if (function_exists($function)) {
        echo "✅ $function - Available" . PHP_EOL;
    } else {
        echo "❌ $function - Missing" . PHP_EOL;
        $missing_functions++;
    }
}

// 4. File Permissions Check
echo PHP_EOL . "4. FILE PERMISSIONS:" . PHP_EOL;
$writable_files = [];
foreach ($php_files as $file) {
    if (is_writable($file)) {
        $perms = substr(sprintf('%o', fileperms($file)), -4);
        if ($perms !== '0644' && $perms !== '0664') {
            $writable_files[] = "$file ($perms)";
        }
    }
}

if (empty($writable_files)) {
    echo "✅ File permissions look good" . PHP_EOL;
} else {
    echo "⚠️  Check permissions for: " . PHP_EOL;
    foreach ($writable_files as $file) {
        echo "   $file" . PHP_EOL;
    }
}

// 5. Final Status
echo PHP_EOL . "=== FINAL STATUS ===" . PHP_EOL;
$total_issues = $syntax_errors + $unsafe_unserialize + $missing_functions;

if ($total_issues === 0) {
    echo "🎉 PRODUCTION READY! No critical issues found." . PHP_EOL;
    echo "   - All PHP files have correct syntax" . PHP_EOL;
    echo "   - Security integration is complete" . PHP_EOL;
    echo "   - No unsafe unserialize calls detected" . PHP_EOL;
    echo "   - All security functions are available" . PHP_EOL;
    exit(0);
} else {
    echo "❌ DEPLOYMENT BLOCKED: $total_issues critical issues found" . PHP_EOL;
    echo "   Please fix the issues listed above before deployment." . PHP_EOL;
    exit(1);
}
?>
