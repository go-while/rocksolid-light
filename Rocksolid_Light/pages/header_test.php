<?php
/**
 * TEST PAGE: Header Migration Verification
 *
 * This page tests the new consolidated header system
 * Access via: ?page=header_test
 */

// Use new router-based header system
if (function_exists('rslight_render_complete_header')) {
    rslight_render_complete_header('Header Migration Test', 'header_test');
    echo '<div style="background: #e8f5e8; padding: 20px; margin: 20px; border-radius: 8px; border: 1px solid #4CAF50;">';
    echo '<h2>✅ SUCCESS: New Header System Active</h2>';
    echo '<p>This page is using the new consolidated header system from <code>pages/pages.php</code></p>';
    echo '<ul>';
    echo '<li>HTML document structure: <code>rslight_render_html_head()</code></li>';
    echo '<li>Theme and CSS: <code>rslight_render_theme_css()</code></li>';
    echo '<li>Site navigation: <code>rslight_render_site_header()</code></li>';
    echo '<li>All sub-components: Navigation links, menu buttons, breadcrumb, MOTD</li>';
    echo '</ul>';
    echo '</div>';
} else {
    echo '<!DOCTYPE html><html><head><title>Header Migration Test</title></head><body>';
    echo '<div style="background: #ffebee; padding: 20px; margin: 20px; border-radius: 8px; border: 1px solid #f44336;">';
    echo '<h2>❌ FALLBACK: Old Header System</h2>';
    echo '<p>The new header functions are not available. Router may not be loaded.</p>';
    echo '</div>';
}
?>

<div style="background: #fff3e0; padding: 20px; margin: 20px; border-radius: 8px; border: 1px solid #ff9800;">
    <h3>🔍 Header Migration Status</h3>

    <h4>Available Functions:</h4>
    <ul>
        <li>rslight_render_complete_header: <?php echo function_exists('rslight_render_complete_header') ? '✅ Available' : '❌ Missing'; ?></li>
        <li>rslight_render_html_head: <?php echo function_exists('rslight_render_html_head') ? '✅ Available' : '❌ Missing'; ?></li>
        <li>rslight_render_site_header: <?php echo function_exists('rslight_render_site_header') ? '✅ Available' : '❌ Missing'; ?></li>
        <li>rslight_render_theme_css: <?php echo function_exists('rslight_render_theme_css') ? '✅ Available' : '❌ Missing'; ?></li>
    </ul>

    <h4>Global Variables:</h4>
    <ul>
        <li>$CONFIG: <?php echo isset($CONFIG) && is_array($CONFIG) ? '✅ Loaded (' . count($CONFIG) . ' keys)' : '❌ Missing'; ?></li>
        <li>$config_dir: <?php echo isset($config_dir) ? '✅ Set to: ' . htmlspecialchars($config_dir) : '❌ Missing'; ?></li>
        <li>$config_name: <?php echo isset($config_name) ? '✅ Set to: ' . htmlspecialchars($config_name) : '❌ Missing'; ?></li>
    </ul>

    <h4>Session Status:</h4>
    <ul>
        <li>Session Status: <?php echo session_status() === PHP_SESSION_ACTIVE ? '✅ Active' : '❌ Inactive'; ?></li>
        <li>rsactive Flag: <?php echo isset($_SESSION['rsactive']) ? '✅ Set' : '❌ Missing'; ?></li>
    </ul>
</div>

<div style="background: #f3e5f5; padding: 20px; margin: 20px; border-radius: 8px; border: 1px solid #9c27b0;">
    <h3>🧭 Navigation Test</h3>
    <p>Test the router with different pages:</p>
    <ul>
        <li><a href="?page=faq">FAQ Page (updated)</a></li>
        <li><a href="?page=language_demo">Language Demo (updated)</a></li>
        <li><a href="?page=article">Article Page</a></li>
        <li><a href="?page=search">Search Page</a></li>
    </ul>
</div>

</body>
</html>
