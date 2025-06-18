<?php
/*
 * RockSolid Light - Upload Security Test
 *
 * This script tests the optional upload security features.
 * Run this script to verify security settings are working correctly.
 */

// Include the configuration
require_once('rocksolid/lib/config.inc.php');

echo "<h2>RockSolid Light - Upload Security Test</h2>\n";
echo "<p>Testing optional security configurations...</p>\n";

// Test 1: Check if security validation is enabled
echo "<h3>1. Upload Validation Status</h3>\n";
if (isset($CONFIG['validate_file_uploads']) && $CONFIG['validate_file_uploads']) {
    echo "✅ Upload validation: <strong>ENABLED</strong><br>\n";

    if (isset($CONFIG['max_upload_size'])) {
        echo "📏 Max upload size: <strong>" . number_format($CONFIG['max_upload_size']/1024/1024, 1) . " MB</strong><br>\n";
    }

    if (isset($CONFIG['allowed_file_types']) && is_array($CONFIG['allowed_file_types'])) {
        echo "📄 Allowed file types: <strong>" . implode(', ', $CONFIG['allowed_file_types']) . "</strong><br>\n";
    }
} else {
    echo "⚠️ Upload validation: <strong>DISABLED</strong> (default)<br>\n";
}

// Test 2: Check logging settings
echo "<h3>2. Logging Configuration</h3>\n";
if (isset($CONFIG['log_file_uploads']) && $CONFIG['log_file_uploads']) {
    echo "✅ Upload logging: <strong>ENABLED</strong><br>\n";
} else {
    echo "⚠️ Upload logging: <strong>DISABLED</strong> (default)<br>\n";
}

if (isset($CONFIG['track_uploads']) && $CONFIG['track_uploads']) {
    echo "✅ Upload tracking: <strong>ENABLED</strong><br>\n";
} else {
    echo "⚠️ Upload tracking: <strong>DISABLED</strong> (default)<br>\n";
}

// Test 3: Check directory permissions
echo "<h3>3. Directory Security</h3>\n";

$upload_dir = $spooldir . '/upload';
if (is_dir($upload_dir)) {
    echo "✅ Upload directory exists: <strong>$upload_dir</strong><br>\n";

    if (file_exists($upload_dir . '/.htaccess')) {
        echo "✅ Security .htaccess file: <strong>PRESENT</strong><br>\n";
    } else {
        echo "⚠️ Security .htaccess file: <strong>MISSING</strong><br>\n";
    }
} else {
    echo "❌ Upload directory: <strong>NOT FOUND</strong><br>\n";
}

$logs_dir = $spooldir . '/logs';
if (is_dir($logs_dir)) {
    echo "✅ Logs directory exists: <strong>$logs_dir</strong><br>\n";
} else {
    echo "⚠️ Logs directory: <strong>NOT FOUND</strong> (will be created when needed)<br>\n";
}

// Test 4: PHP Security Functions
echo "<h3>4. PHP Security Functions</h3>\n";

if (function_exists('finfo_open')) {
    echo "✅ File info functions: <strong>AVAILABLE</strong><br>\n";
} else {
    echo "❌ File info functions: <strong>NOT AVAILABLE</strong> (file type validation won't work)<br>\n";
}

if (function_exists('move_uploaded_file')) {
    echo "✅ File upload functions: <strong>AVAILABLE</strong><br>\n";
} else {
    echo "❌ File upload functions: <strong>NOT AVAILABLE</strong><br>\n";
}

// Test 5: Recommendations
echo "<h3>5. Security Recommendations</h3>\n";
echo "<ul>\n";

if (!isset($CONFIG['validate_file_uploads']) || !$CONFIG['validate_file_uploads']) {
    echo "<li>⚠️ Consider enabling upload validation for enhanced security</li>\n";
}

if (!isset($CONFIG['log_file_uploads']) || !$CONFIG['log_file_uploads']) {
    echo "<li>📊 Consider enabling upload logging for monitoring</li>\n";
}

if (!file_exists($upload_dir . '/.htaccess')) {
    echo "<li>🔒 Install .htaccess file in upload directory to prevent script execution</li>\n";
}

echo "<li>🛡️ Regularly monitor upload logs for suspicious activity</li>\n";
echo "<li>🔄 Keep allowed file types as restrictive as practical</li>\n";
echo "<li>📝 Test security settings in development before production deployment</li>\n";
echo "</ul>\n";

echo "<hr>\n";
echo "<p><strong>Note:</strong> All security features are optional and disabled by default for backward compatibility.</p>\n";
echo "<p>Edit <code>rocksolid/lib/config.inc.php</code> to enable desired security features.</p>\n";
echo "<p>See <code>UPLOAD_SECURITY.md</code> for detailed configuration instructions.</p>\n";

?>
