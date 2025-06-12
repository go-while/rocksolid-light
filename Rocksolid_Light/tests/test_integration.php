<?php
/**
 * Simple database monitor test with correct table structure
 */

require_once __DIR__ . '/database_monitor.php';
require_once __DIR__ . '/../database_optimizer.php';

echo "🧪 Database Optimization Integration Test\n";
echo "==========================================\n\n";

// Test the DatabaseOptimizer directly
echo "🔧 Testing DatabaseOptimizer...\n";
$optimizer = new DatabaseOptimizer();

// Create a test database
$test_db = __DIR__ . '/../spool/integration_test.db3';
if (file_exists($test_db)) {
    unlink($test_db);
}

try {
    $dbh = new PDO('sqlite:' . $test_db);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create test tables
    $dbh->exec("CREATE TABLE articles (
        id INTEGER PRIMARY KEY,
        newsgroup TEXT,
        number TEXT,
        msgid TEXT,
        subject TEXT,
        article TEXT
    )");

    $dbh->exec("CREATE TABLE overview (
        id INTEGER PRIMARY KEY,
        newsgroup TEXT,
        number TEXT,
        subject TEXT,
        date TEXT
    )");

    // Insert test data
    echo "📊 Inserting test data...\n";
    for ($i = 1; $i <= 100; $i++) {
        $dbh->exec("INSERT INTO articles (newsgroup, number, msgid, subject, article) VALUES
            ('test.group', '$i', '<test$i@example.com>', 'Test Subject $i', 'Test article content $i')");

        $dbh->exec("INSERT INTO overview (newsgroup, number, subject, date) VALUES
            ('test.group', '$i', 'Test Subject $i', '2025-01-27')");
    }

    echo "✅ Test data inserted successfully\n\n";

    // Test optimizations
    echo "🚀 Testing optimizations...\n";
    $start_time = microtime(true);

    // Apply optimizations
    $optimizer->optimizeDatabase($dbh, 'article');

    // Test query performance
    $query_start = microtime(true);
    $stmt = $dbh->query("SELECT COUNT(*) FROM articles");
    $count = $stmt->fetchColumn();
    $query_time = (microtime(true) - $query_start) * 1000;

    echo "✅ Database optimized successfully\n";
    echo "📊 Performance: $count records queried in " . round($query_time, 3) . "ms\n\n";

    // Get database stats
    echo "📈 Database Statistics:\n";
    $stats = $optimizer->getDatabaseStats($dbh);
    foreach ($stats as $key => $value) {
        echo "  • $key: $value\n";
    }

    echo "\n🔧 Performance Recommendations:\n";
    $recommendations = $optimizer->getPerformanceRecommendations($dbh);
    foreach ($recommendations as $rec) {
        echo "  • $rec\n";
    }

    $dbh = null;

    echo "\n✅ Integration test completed successfully!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

// Clean up
if (file_exists($test_db)) {
    unlink($test_db);
    echo "🧹 Test database cleaned up\n";
}
?>
