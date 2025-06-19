<?php
/**
 * Test database monitor with existing test database
 */

require_once __DIR__ . '/../database_monitor.php';

echo "🧪 Testing Database Monitor\n";
echo "===========================\n\n";

// Create monitor instance
$monitor = new DatabaseMonitor();

// Create a fake directory structure for testing
$test_spool = __DIR__ . '/../spool';
if (!is_dir($test_spool . '/articles')) {
    mkdir($test_spool . '/articles', 0755, true);
}
if (!is_dir($test_spool . '/articles/test')) {
    mkdir($test_spool . '/articles/test', 0755, true);
}

// Copy test database to simulate article database
$test_db = $test_spool . '/test-large-dataset.db3';
$article_db = $test_spool . '/articles/test/articles.db3';

if (file_exists($test_db)) {
    copy($test_db, $article_db);
    echo "✅ Test database copied to articles directory\n\n";

    // Run health check
    $results = $monitor->quickHealthCheck();

    echo "\n🧪 Test completed successfully!\n";
} else {
    echo "❌ Test database not found at: $test_db\n";
}
?>
