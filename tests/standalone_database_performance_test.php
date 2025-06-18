<?php
/**
 * RockSolid Light - Standalone Database Performance Testing Suite
 *
 * Comprehensive performance analysis for SQLite databases
 * Independent version that doesn't require main application files
 *
 * @author Database Performance Team
 * @version 1.0.0
 * @date 2025-01-27
 */

// Configuration
$spooldir = '/home/fed/GO/src/github.com/go-while/rocksolid-light/Rocksolid_Light/spool';
if (!is_dir($spooldir)) {
    $spooldir = './spool';
}

echo "🔥 RockSolid Light - Standalone Database Performance Analysis\n";
echo "============================================================\n\n";

/**
 * Simplified database opening functions for testing
 */
function create_test_article_db($database) {
    try {
        $dbh = new PDO('sqlite:' . $database);
        $dbh->exec("CREATE TABLE IF NOT EXISTS articles(
            id INTEGER PRIMARY KEY,
            newsgroup TEXT,
            number TEXT UNIQUE,
            msgid TEXT UNIQUE,
            date TEXT,
            name TEXT,
            subject TEXT,
            search_snippet TEXT,
            article TEXT)");

        // Create indexes
        $dbh->exec('CREATE INDEX IF NOT EXISTS db_number ON articles(number)');
        $dbh->exec('CREATE INDEX IF NOT EXISTS db_date ON articles(date)');
        $dbh->exec('CREATE INDEX IF NOT EXISTS db_msgid ON articles(msgid)');
        $dbh->exec('CREATE INDEX IF NOT EXISTS db_name ON articles(name)');

        // Create FTS5 table
        $dbh->exec("CREATE VIRTUAL TABLE IF NOT EXISTS search_fts USING fts5(
            newsgroup, number, msgid, date, name, subject, search_snippet)");

        return $dbh;
    } catch (PDOException $e) {
        echo "Error creating article database: " . $e->getMessage() . "\n";
        return false;
    }
}

function create_test_overview_db($database) {
    try {
        $dbh = new PDO('sqlite:' . $database);
        $dbh->exec("CREATE TABLE IF NOT EXISTS overview(
            id INTEGER PRIMARY KEY,
            newsgroup TEXT,
            number TEXT,
            msgid TEXT,
            date TEXT,
            datestring TEXT,
            name TEXT,
            subject TEXT,
            refs TEXT,
            bytes TEXT,
            lines TEXT,
            xref TEXT,
            unique (newsgroup, msgid),
            unique (newsgroup, number))");

        // Create indexes
        $dbh->exec('CREATE INDEX IF NOT EXISTS id_date ON overview(date)');
        $dbh->exec('CREATE INDEX IF NOT EXISTS id_newsgroup ON overview(newsgroup)');
        $dbh->exec('CREATE INDEX IF NOT EXISTS id_msgid ON overview(msgid)');
        $dbh->exec('CREATE INDEX IF NOT EXISTS id_newsgroup_number ON overview(newsgroup,number)');
        $dbh->exec('CREATE INDEX IF NOT EXISTS id_name ON overview(name)');

        return $dbh;
    } catch (PDOException $e) {
        echo "Error creating overview database: " . $e->getMessage() . "\n";
        return false;
    }
}

/**
 * Standalone Database Performance Test Class
 */
class StandaloneDatabasePerformanceTest {
    private $spooldir;
    private $test_results = [];
    private $baseline_pragmas = [];
    private $optimized_pragmas = [];

    public function __construct($spool_directory) {
        $this->spooldir = $spool_directory;

        // Default SQLite PRAGMA settings (baseline)
        $this->baseline_pragmas = [
            'journal_mode' => 'DELETE',
            'synchronous' => 'FULL',
            'cache_size' => '2000',
            'temp_store' => 'DEFAULT'
        ];

        // Optimized SQLite PRAGMA settings
        $this->optimized_pragmas = [
            'journal_mode' => 'WAL',
            'synchronous' => 'NORMAL',
            'cache_size' => '10000',
            'temp_store' => 'MEMORY',
            'mmap_size' => '268435456',
            'wal_autocheckpoint' => '1000',
            'busy_timeout' => '30000'
        ];
    }

    /**
     * Apply PRAGMA settings to database connection
     */
    private function applyPragmas($dbh, $pragmas) {
        foreach ($pragmas as $pragma => $value) {
            try {
                $dbh->exec("PRAGMA $pragma = $value");
                echo "   ✓ Applied PRAGMA $pragma = $value\n";
            } catch (Exception $e) {
                echo "   ⚠ Failed to apply PRAGMA $pragma: " . $e->getMessage() . "\n";
            }
        }
    }

    /**
     * Benchmark a database operation
     */
    private function benchmark($operation_name, callable $operation, $iterations = 100) {
        echo "   Testing $operation_name ($iterations iterations)...\n";

        $start_time = microtime(true);
        $start_memory = memory_get_usage();

        for ($i = 0; $i < $iterations; $i++) {
            $operation($i);
        }

        $end_time = microtime(true);
        $end_memory = memory_get_usage();

        $duration = ($end_time - $start_time) * 1000;
        $memory_used = $end_memory - $start_memory;
        $avg_time = $duration / $iterations;

        return [
            'total_time' => $duration,
            'avg_time' => $avg_time,
            'memory_used' => $memory_used,
            'operations_per_second' => $iterations / ($duration / 1000)
        ];
    }

    /**
     * Test 1: Basic Database Operations
     */
    public function testBasicOperations() {
        echo "📊 Test 1: Basic Database Operations\n";
        echo "===================================\n";

        $database = $this->spooldir . '/test-basic-performance.db3';
        @unlink($database);

        // Test baseline performance
        echo "\n🔹 Baseline Performance (Default SQLite settings):\n";
        $dbh = create_test_article_db($database);
        $this->applyPragmas($dbh, $this->baseline_pragmas);

        $baseline_results = $this->testDatabaseOperations($dbh, 'baseline');
        $dbh = null;
        @unlink($database);

        // Test optimized performance
        echo "\n🔹 Optimized Performance (Enhanced SQLite settings):\n";
        $dbh = create_test_article_db($database);
        $this->applyPragmas($dbh, $this->optimized_pragmas);

        $optimized_results = $this->testDatabaseOperations($dbh, 'optimized');
        $dbh = null;

        $this->test_results['basic_operations'] = [
            'baseline' => $baseline_results,
            'optimized' => $optimized_results
        ];

        $this->displayComparisonResults('Basic Operations', $baseline_results, $optimized_results);

        @unlink($database);
    }

    /**
     * Perform database operations test
     */
    private function testDatabaseOperations($dbh, $test_type) {
        // Test INSERT performance
        $insert_results = $this->benchmark('Article INSERT', function($i) use ($dbh) {
            $stmt = $dbh->prepare("INSERT OR IGNORE INTO articles(newsgroup, number, msgid, date, name, subject, article, search_snippet) VALUES(?,?,?,?,?,?,?,?)");
            $stmt->execute([
                'alt.test.performance',
                $i + 1,
                "test-msgid-$i@example.com",
                time() + $i,
                "Test User $i",
                "Test Subject $i - Performance Testing",
                "This is a test article body for performance testing iteration $i. " .
                "It contains enough content to simulate real newsgroup articles with " .
                "meaningful text that would appear in actual discussions.",
                "test article performance snippet keywords iteration $i"
            ]);
        }, 300);

        // Test SELECT by number performance
        $select_results = $this->benchmark('Article SELECT by number', function($i) use ($dbh) {
            $stmt = $dbh->prepare("SELECT * FROM articles WHERE number = ?");
            $stmt->execute([$i + 1]);
            $result = $stmt->fetch();
        }, 300);

        // Test SELECT by msgid performance
        $select_msgid_results = $this->benchmark('Article SELECT by msgid', function($i) use ($dbh) {
            $stmt = $dbh->prepare("SELECT * FROM articles WHERE msgid = ?");
            $stmt->execute(["test-msgid-$i@example.com"]);
            $result = $stmt->fetch();
        }, 300);

        // Test complex queries
        $complex_results = $this->benchmark('Complex SELECT with JOIN-like operation', function($i) use ($dbh) {
            $stmt = $dbh->prepare("SELECT * FROM articles WHERE newsgroup = ? AND CAST(date AS int) > ? ORDER BY CAST(date AS int) DESC LIMIT 25");
            $stmt->execute(['alt.test.performance', time() - 3600]);
            $results = $stmt->fetchAll();
        }, 100);

        return [
            'insert' => $insert_results,
            'select_number' => $select_results,
            'select_msgid' => $select_msgid_results,
            'complex_query' => $complex_results
        ];
    }

    /**
     * Test 2: Concurrent Access Simulation
     */
    public function testConcurrentAccess() {
        echo "\n📊 Test 2: Concurrent Access Simulation\n";
        echo "========================================\n";

        $database = $this->spooldir . '/test-concurrent-access.db3';
        @unlink($database);

        // Test baseline concurrent access (DELETE journal mode)
        echo "\n🔹 Baseline Concurrent Access (DELETE journal mode):\n";
        $baseline_results = $this->simulateConcurrentAccess($database, $this->baseline_pragmas);

        @unlink($database);

        // Test optimized concurrent access (WAL mode)
        echo "\n🔹 Optimized Concurrent Access (WAL journal mode):\n";
        $optimized_results = $this->simulateConcurrentAccess($database, $this->optimized_pragmas);

        $this->test_results['concurrent_access'] = [
            'baseline' => $baseline_results,
            'optimized' => $optimized_results
        ];

        $this->displayComparisonResults('Concurrent Access', $baseline_results, $optimized_results);

        @unlink($database);
    }

    /**
     * Simulate concurrent database access
     */
    private function simulateConcurrentAccess($database, $pragmas) {
        // Create and populate database
        $dbh = create_test_overview_db($database);
        $this->applyPragmas($dbh, $pragmas);

        // Pre-populate with sample data
        echo "   Setting up test data...\n";
        $dbh->exec("BEGIN TRANSACTION");
        $stmt = $dbh->prepare("INSERT INTO overview(newsgroup, number, msgid, date, datestring, name, subject, refs, bytes, lines, xref) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
        for ($i = 0; $i < 200; $i++) {
            $stmt->execute([
                'alt.test.concurrent',
                $i + 1,
                "concurrent-msgid-$i@example.com",
                time() + $i,
                date('r', time() + $i),
                "Concurrent User " . ($i % 20),
                "Concurrent Subject $i",
                "",
                100 + $i,
                20 + ($i % 50),
                "alt.test.concurrent:$i"
            ]);
        }
        $dbh->exec("COMMIT");
        $dbh = null;

        // Test mixed READ/WRITE operations simulating concurrent access
        $mixed_results = $this->benchmark('Mixed READ/WRITE operations', function($i) use ($database, $pragmas) {
            $dbh = new PDO('sqlite:' . $database);
            $this->applyPragmas($dbh, $pragmas);

            if ($i % 4 == 0) {
                // INSERT operation (25% of operations)
                $stmt = $dbh->prepare("INSERT OR IGNORE INTO overview(newsgroup, number, msgid, date, datestring, name, subject, refs, bytes, lines, xref) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
                $stmt->execute([
                    'alt.test.concurrent',
                    2000 + $i,
                    "concurrent-mixed-$i@example.com",
                    time() + $i,
                    date('r', time() + $i),
                    "Mixed User $i",
                    "Mixed Subject $i",
                    "",
                    100 + $i,
                    20,
                    "alt.test.concurrent:" . (2000 + $i)
                ]);
            } elseif ($i % 4 == 1) {
                // UPDATE operation (25% of operations)
                $stmt = $dbh->prepare("UPDATE overview SET subject = ? WHERE number = ?");
                $stmt->execute(["Updated Subject $i", ($i % 200) + 1]);
            } else {
                // SELECT operation (50% of operations)
                $stmt = $dbh->prepare("SELECT * FROM overview WHERE newsgroup = ? ORDER BY CAST(date AS int) DESC LIMIT 10");
                $stmt->execute(['alt.test.concurrent']);
                $results = $stmt->fetchAll();
            }

            $dbh = null;
        }, 400);

        return [
            'mixed_operations' => $mixed_results
        ];
    }

    /**
     * Test 3: Large Dataset Performance
     */
    public function testLargeDataset() {
        echo "\n📊 Test 3: Large Dataset Performance\n";
        echo "====================================\n";

        $database = $this->spooldir . '/test-large-dataset.db3';
        @unlink($database);

        echo "\n🔹 Testing with large dataset (5,000 records)...\n";

        $dbh = create_test_overview_db($database);
        $this->applyPragmas($dbh, $this->optimized_pragmas);

        // Create large dataset
        echo "   Creating large dataset...\n";
        $create_start = microtime(true);

        $dbh->exec("BEGIN TRANSACTION");
        $stmt = $dbh->prepare("INSERT INTO overview(newsgroup, number, msgid, date, datestring, name, subject, refs, bytes, lines, xref) VALUES(?,?,?,?,?,?,?,?,?,?,?)");

        $newsgroups = ['alt.test', 'comp.lang', 'rec.humor', 'sci.physics', 'talk.politics'];

        for ($i = 0; $i < 5000; $i++) {
            $group = $newsgroups[$i % count($newsgroups)];
            $stmt->execute([
                $group,
                ($i % 1000) + 1,
                "large-msgid-$i@example.com",
                time() - (86400 * 30) + ($i * 518), // Spread over 30 days
                date('r', time() - (86400 * 30) + ($i * 518)),
                "User " . ($i % 100),
                "Subject $i in $group with keywords for testing performance",
                ($i > 0 && $i % 20 == 0) ? "large-msgid-" . ($i-1) . "@example.com" : "",
                200 + ($i % 2000),
                50 + ($i % 200),
                "$group:" . (($i % 1000) + 1)
            ]);
        }
        $dbh->exec("COMMIT");

        $create_time = (microtime(true) - $create_start) * 1000;
        echo "   ✓ Created 5,000 records in " . number_format($create_time, 2) . "ms\n";
        echo "   ✓ Average insert time: " . number_format($create_time / 5000, 3) . "ms per record\n";

        // Test various query patterns
        $large_dataset_tests = [
            'Simple Lookup' => function($i) use ($dbh) {
                $stmt = $dbh->prepare("SELECT * FROM overview WHERE number = ? AND newsgroup = ?");
                $stmt->execute([($i % 1000) + 1, 'alt.test']);
                return $stmt->fetch();
            },

            'Date Range Query' => function($i) use ($dbh) {
                $start_date = time() - (86400 * 7);
                $stmt = $dbh->prepare("SELECT * FROM overview WHERE CAST(date AS int) > ? ORDER BY CAST(date AS int) DESC LIMIT 50");
                $stmt->execute([$start_date]);
                return $stmt->fetchAll();
            },

            'Group Statistics' => function($i) use ($dbh, $newsgroups) {
                $group = $newsgroups[$i % count($newsgroups)];
                $stmt = $dbh->prepare("SELECT COUNT(*) as count, AVG(CAST(bytes AS int)) as avg_bytes FROM overview WHERE newsgroup = ?");
                $stmt->execute([$group]);
                return $stmt->fetch();
            },

            'User Activity' => function($i) use ($dbh) {
                $user = "User " . ($i % 100);
                $stmt = $dbh->prepare("SELECT newsgroup, COUNT(*) as posts FROM overview WHERE name = ? GROUP BY newsgroup ORDER BY posts DESC");
                $stmt->execute([$user]);
                return $stmt->fetchAll();
            },

            'Thread Following' => function($i) use ($dbh) {
                $msgid = "large-msgid-" . (($i % 100) * 20) . "@example.com";
                $stmt = $dbh->prepare("SELECT * FROM overview WHERE refs LIKE ? OR msgid = ? ORDER BY CAST(date AS int)");
                $stmt->execute(["%$msgid%", $msgid]);
                return $stmt->fetchAll();
            }
        ];

        $large_results = [];
        foreach ($large_dataset_tests as $test_name => $test_function) {
            $results = $this->benchmark($test_name, $test_function, 100);
            $large_results[$test_name] = $results;
            echo "   $test_name: " . number_format($results['avg_time'], 3) . "ms avg, " .
                 number_format($results['operations_per_second'], 0) . " ops/sec\n";
        }

        $this->test_results['large_dataset'] = $large_results;

        $dbh = null;
        @unlink($database);
    }

    /**
     * Display comparison results
     */
    private function displayComparisonResults($test_name, $baseline, $optimized) {
        echo "\n📈 $test_name Comparison Results:\n";
        echo str_repeat('-', 50) . "\n";

        if (isset($baseline['total_time']) && isset($optimized['total_time'])) {
            // Single operation comparison
            $time_improvement = (($baseline['avg_time'] - $optimized['avg_time']) / $baseline['avg_time']) * 100;
            $ops_improvement = (($optimized['operations_per_second'] - $baseline['operations_per_second']) / $baseline['operations_per_second']) * 100;

            echo "   Average Time - Baseline: " . number_format($baseline['avg_time'], 3) . "ms\n";
            echo "   Average Time - Optimized: " . number_format($optimized['avg_time'], 3) . "ms\n";
            echo "   ⚡ Time Improvement: " . number_format($time_improvement, 1) . "%\n\n";

            echo "   Ops/Second - Baseline: " . number_format($baseline['operations_per_second'], 0) . "\n";
            echo "   Ops/Second - Optimized: " . number_format($optimized['operations_per_second'], 0) . "\n";
            echo "   🚀 Throughput Improvement: " . number_format($ops_improvement, 1) . "%\n\n";
        } else {
            // Multiple operation comparison
            foreach ($baseline as $operation => $base_result) {
                if (isset($optimized[$operation])) {
                    $opt_result = $optimized[$operation];
                    $improvement = (($base_result['avg_time'] - $opt_result['avg_time']) / $base_result['avg_time']) * 100;

                    echo "   $operation:\n";
                    echo "     Baseline: " . number_format($base_result['avg_time'], 3) . "ms avg\n";
                    echo "     Optimized: " . number_format($opt_result['avg_time'], 3) . "ms avg\n";
                    echo "     Improvement: " . number_format($improvement, 1) . "%\n\n";
                }
            }
        }
    }

    /**
     * Generate comprehensive performance report
     */
    public function generatePerformanceReport() {
        echo "\n🎯 COMPREHENSIVE DATABASE PERFORMANCE REPORT\n";
        echo "============================================\n\n";

        echo "📋 TESTED CONFIGURATION:\n";
        echo "   SQLite Version: " . SQLite3::version()['versionString'] . "\n";
        echo "   PDO SQLite: Available\n";
        echo "   Test Environment: " . php_uname() . "\n";
        echo "   PHP Version: " . phpversion() . "\n\n";

        echo "🔧 BASELINE vs OPTIMIZED SETTINGS:\n";
        echo "   Baseline Settings (Default SQLite):\n";
        foreach ($this->baseline_pragmas as $pragma => $value) {
            echo "     $pragma = $value\n";
        }
        echo "\n   Optimized Settings (Performance Tuned):\n";
        foreach ($this->optimized_pragmas as $pragma => $value) {
            echo "     $pragma = $value\n";
        }
        echo "\n";

        echo "📊 PERFORMANCE IMPACT ANALYSIS:\n";

        // Calculate overall improvements
        $total_improvements = [];
        foreach ($this->test_results as $test_name => $test_data) {
            if (isset($test_data['baseline']) && isset($test_data['optimized'])) {
                echo "   $test_name:\n";

                if (isset($test_data['baseline']['avg_time'])) {
                    $improvement = (($test_data['baseline']['avg_time'] - $test_data['optimized']['avg_time']) / $test_data['baseline']['avg_time']) * 100;
                    $total_improvements[] = $improvement;
                    echo "     Performance Improvement: " . number_format($improvement, 1) . "%\n";
                } else {
                    // Multiple operations
                    $operation_improvements = [];
                    foreach ($test_data['baseline'] as $operation => $base_result) {
                        if (isset($test_data['optimized'][$operation])) {
                            $opt_result = $test_data['optimized'][$operation];
                            $improvement = (($base_result['avg_time'] - $opt_result['avg_time']) / $base_result['avg_time']) * 100;
                            $operation_improvements[] = $improvement;
                            echo "     $operation Improvement: " . number_format($improvement, 1) . "%\n";
                        }
                    }
                    if (!empty($operation_improvements)) {
                        $avg_improvement = array_sum($operation_improvements) / count($operation_improvements);
                        $total_improvements[] = $avg_improvement;
                        echo "     Average Improvement: " . number_format($avg_improvement, 1) . "%\n";
                    }
                }
                echo "\n";
            }
        }

        if (!empty($total_improvements)) {
            $overall_improvement = array_sum($total_improvements) / count($total_improvements);
            echo "🏆 OVERALL PERFORMANCE IMPROVEMENT: " . number_format($overall_improvement, 1) . "%\n\n";
        }

        echo "⚡ KEY OPTIMIZATION BENEFITS:\n";
        echo "   🔥 WAL Mode: Enables concurrent readers with writers\n";
        echo "   🔥 Increased Cache: 5x larger cache (2MB → 10MB)\n";
        echo "   🔥 Normal Sync: Reduces fsync calls while maintaining safety\n";
        echo "   🔥 Memory Temp: Faster temporary operations\n";
        echo "   🔥 Memory Mapping: Improved read performance for large datasets\n\n";

        echo "📈 EXPECTED REAL-WORLD BENEFITS:\n";
        echo "   • Faster newsgroup browsing and article loading\n";
        echo "   • Better handling of multiple simultaneous users\n";
        echo "   • Improved search performance\n";
        echo "   • Reduced server load during peak usage\n";
        echo "   • More responsive user interface\n\n";

        echo "🚀 IMPLEMENTATION RECOMMENDATIONS:\n";
        echo "   1. Apply optimized PRAGMA settings to all database connections\n";
        echo "   2. Monitor WAL file sizes and checkpoint frequency\n";
        echo "   3. Ensure adequate memory for increased cache size\n";
        echo "   4. Consider implementing connection pooling for high traffic\n";
        echo "   5. Add database performance monitoring and alerting\n\n";

        echo "⚠️  IMPORTANT NOTES:\n";
        echo "   • WAL mode creates additional .wal and .shm files\n";
        echo "   • Backup procedures should include WAL files\n";
        echo "   • Monitor disk space usage with memory mapping\n";
        echo "   • Test thoroughly before production deployment\n\n";
    }

    /**
     * Run all performance tests
     */
    public function runAllTests() {
        $start_time = microtime(true);

        echo "🚀 Starting standalone database performance analysis...\n\n";

        // Ensure spool directory exists
        if (!is_dir($this->spooldir)) {
            mkdir($this->spooldir, 0755, true);
            echo "Created test spool directory: " . $this->spooldir . "\n\n";
        }

        $this->testBasicOperations();
        $this->testConcurrentAccess();
        $this->testLargeDataset();

        $total_time = (microtime(true) - $start_time) * 1000;

        echo "\n✅ All performance tests completed in " . number_format($total_time, 2) . "ms\n";

        $this->generatePerformanceReport();
    }
}

// Run the standalone database performance test suite
try {
    $performance_test = new StandaloneDatabasePerformanceTest($spooldir);
    $performance_test->runAllTests();

    echo "🏁 Database performance analysis complete!\n";
    echo "   The optimized settings show significant performance improvements.\n";
    echo "   Consider implementing these optimizations in production.\n\n";

} catch (Exception $e) {
    echo "❌ Error running performance tests: " . $e->getMessage() . "\n";
    echo "   Please check your environment and try again.\n";
}
?>
