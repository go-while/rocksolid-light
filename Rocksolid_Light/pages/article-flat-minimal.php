<?php
if(!defined('RSLIGHT_CONFIG_LOADED')) {
    die("Access denied.");
}

// Minimal article-flat page for router system testing
echo "<h1>Article Flat Page - Under Development</h1>";
echo "<p>Parameters received:</p>";
echo "<ul>";
echo "<li>ID: " . htmlspecialchars($_GET['id'] ?? 'none') . "</li>";
echo "<li>Group: " . htmlspecialchars($_GET['group'] ?? 'none') . "</li>";
echo "</ul>";
echo "<p>Router system is working!</p>";
?>
