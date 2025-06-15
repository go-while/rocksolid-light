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

?>
