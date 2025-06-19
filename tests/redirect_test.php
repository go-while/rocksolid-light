<?php
/**
 * REDIRECT TEST PAGE
 *
 * Simple test to verify redirect logic works correctly
 * This will simulate different redirect scenarios
 */

echo "<h1>Redirect Logic Test</h1>";

echo "<h2>Test Cases:</h2>";
echo "<ul>";
echo "<li><a href='rocksolid/'>Test 1: Basic redirect</a> - Should go to /?page=index</li>";
echo "<li><a href='rocksolid/?subscribe=test.group'>Test 2: With subscribe param</a> - Should preserve subscribe parameter</li>";
echo "<li><a href='rocksolid/?page=index'>Test 3: Loop detection</a> - Should show error message</li>";
echo "<li><a href='rocksolid/?mark_read=test.group'>Test 4: With mark_read param</a> - Should preserve mark_read parameter</li>";
echo "</ul>";

echo "<h2>Current URL Analysis:</h2>";
echo "<p><strong>REQUEST_URI:</strong> " . htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'N/A') . "</p>";
echo "<p><strong>QUERY_STRING:</strong> " . htmlspecialchars($_SERVER['QUERY_STRING'] ?? 'N/A') . "</p>";
echo "<p><strong>GET Parameters:</strong></p>";
echo "<pre>" . print_r($_GET, true) . "</pre>";

echo "<h2>Router Status:</h2>";
echo "<p>Router functions available: " . (function_exists('rslight_route_page') ? 'YES' : 'NO') . "</p>";
echo "<p>Current page: " . ($_GET['page'] ?? 'No page parameter') . "</p>";

echo "<h2>🔧 Problem Analysis:</h2>";
echo "<div style='background: #ffebee; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<p><strong>Issue Found:</strong> Root index.php was redirecting to default_content</p>";
echo "<p><strong>Redirect Chain:</strong></p>";
echo "<ol>";
echo "<li>/rocksolid/ → /?page=index</li>";
echo "<li>/?page=index → /index.php (Apache default)</li>";
echo "<li>/index.php → /rocksolid/index.php (default_content redirect)</li>";
echo "<li>/rocksolid/index.php → Loop detected!</li>";
echo "</ol>";
echo "</div>";

echo "<h2>✅ Solution Applied:</h2>";
echo "<div style='background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 10px 0;'>";
echo "<p>1. Modified root index.php to handle router requests</p>";
echo "<p>2. Modified rocksolid/index.php to serve content directly</p>";
echo "<p>3. Eliminated redirect loops by using direct content serving</p>";
echo "</div>";

?>
