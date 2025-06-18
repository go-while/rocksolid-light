<?php
/*
 * Session Name Test - Verify ROCKSOLID_BY_RETROGUY session name is working
 */

echo "<h2>RockSolid Light - Session Name Test</h2>\n";

// Test 1: Check if session is already started
if (session_status() === PHP_SESSION_ACTIVE) {
    echo "⚠️ Session already started with name: <strong>" . session_name() . "</strong><br>\n";
    echo "Session ID: " . session_id() . "<br>\n";
    echo "Session status: ACTIVE<br><br>\n";
} else {
    echo "✅ No session started yet<br><br>\n";
}

// Test 2: Load config and start secure session
echo "<h3>Loading Configuration and Starting Secure Session</h3>\n";

try {
    require_once('rocksolid/lib/config.inc.php');
    require_once('rslight/inc/security.inc.php');

    echo "✅ Configuration loaded<br>\n";

    // Check session name variable
    if (isset($session_name)) {
        echo "✅ Session name variable: <strong>$session_name</strong><br>\n";
    } else {
        echo "❌ Session name variable not set<br>\n";
    }

    // Start secure session
    if (session_status() !== PHP_SESSION_ACTIVE) {
        secure_session_start();
        echo "✅ Secure session started<br>\n";
    }

    // Test results
    echo "<h3>Session Test Results</h3>\n";
    echo "Current session name: <strong>" . session_name() . "</strong><br>\n";
    echo "Current session ID: <strong>" . session_id() . "</strong><br>\n";

    if (session_name() === 'ROCKSOLID_BY_RETROGUY') {
        echo "✅ <strong>SUCCESS:</strong> Session name is correctly set to ROCKSOLID_BY_RETROGUY<br>\n";
    } else {
        echo "❌ <strong>FAILED:</strong> Session name should be ROCKSOLID_BY_RETROGUY but is " . session_name() . "<br>\n";
    }

    // Check cookie
    echo "<h3>Cookie Information</h3>\n";
    if (isset($_COOKIE['ROCKSOLID_BY_RETROGUY'])) {
        echo "✅ ROCKSOLID_BY_RETROGUY cookie found<br>\n";
    } else {
        echo "⚠️ ROCKSOLID_BY_RETROGUY cookie not found<br>\n";
    }

    if (isset($_COOKIE['PHPSESSID'])) {
        echo "❌ PHPSESSID cookie found (should not exist)<br>\n";
    } else {
        echo "✅ PHPSESSID cookie not found (correct)<br>\n";
    }

    // Show all session-related cookies
    echo "<h3>All Session Cookies</h3>\n";
    foreach ($_COOKIE as $name => $value) {
        if (strpos($name, 'SESS') !== false || strpos($name, 'ROCKSOLID') !== false || $name === 'PHPSESSID') {
            echo "Cookie: <strong>$name</strong> = " . substr($value, 0, 20) . "...<br>\n";
        }
    }

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>\n";
}

echo "<hr>\n";
echo "<h3>Browser Instructions</h3>\n";
echo "<p>1. Clear all cookies for this site</p>\n";
echo "<p>2. Refresh this page</p>\n";
echo "<p>3. Check if ROCKSOLID_BY_RETROGUY cookie is set (not PHPSESSID)</p>\n";
echo "<p>4. Navigate to other pages and verify session persistence</p>\n";

echo "<h3>Important Notes</h3>\n";
echo "<p><strong>Warning:</strong> Some test files in /tests/ directory call session_start() directly and may interfere with session naming.</p>\n";
echo "<p>For accurate testing, avoid running test files before this session name test.</p>\n";
echo "<p>If session name shows as PHPSESSID instead of ROCKSOLID_BY_RETROGUY, clear all cookies and avoid test files.</p>\n";

?>
