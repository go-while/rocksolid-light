<?php
/**
 * RockSolid Light - Simple Database Performance Monitor
 *
 * Real-time database performance monitoring and alerting
 *
 * @author Database Performance Team
 * @version 1.0.0
 * @date 2025-01-27
 */

require_once __DIR__ . '/../database_optimizer.php';

class DatabaseMonitor {
    private $spooldir;
    private $optimizer;

    public function __construct($spooldir = null) {
        $this->spooldir = $spooldir ?: __DIR__ . '/../spool';
        $this->optimizer = new DatabaseOptimizer();
    }

    /**
     * Quick health check of all databases
     */
    public function quickHealthCheck() {
        echo "🔍 RockSolid Light Database Health Check\n";
        echo "==========================================\n\n";

        $results = [];

        // Check article databases
        $article_dirs = glob($this->spooldir . '/articles/*', GLOB_ONLYDIR);
        echo "📊 Article Databases: " . count($article_dirs) . " found\n";

        foreach (array_slice($article_dirs, 0, 5) as $dir) { // Check first 5
            $db_file = $dir . '/articles.db3';
            if (file_exists($db_file)) {
                $stats = $this->checkDatabase($db_file, 'article');
                $results[] = $stats;
                echo "  • " . basename($dir) . ": " . $this->formatHealth($stats) . "\n";
            }
        }

        // Check overview databases
        $overview_dirs = glob($this->spooldir . '/overview/*', GLOB_ONLYDIR);
        echo "\n📊 Overview Databases: " . count($overview_dirs) . " found\n";

        foreach (array_slice($overview_dirs, 0, 5) as $dir) { // Check first 5
            $db_file = $dir . '/overview.db3';
            if (file_exists($db_file)) {
                $stats = $this->checkDatabase($db_file, 'overview');
                $results[] = $stats;
                echo "  • " . basename($dir) . ": " . $this->formatHealth($stats) . "\n";
            }
        }

        // Overall summary
        $this->printSummary($results);

        return $results;
    }

    /**
     * Check individual database performance
     */
    private function checkDatabase($db_file, $type) {
        $stats = [
            'file' => $db_file,
            'type' => $type,
            'size' => filesize($db_file),
            'readable' => is_readable($db_file),
            'writable' => is_writable($db_file),
            'performance' => null,
            'health' => 'unknown'
        ];

        try {
            $start_time = microtime(true);
            $dbh = new PDO('sqlite:' . $db_file);
            $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Apply optimizations
            $this->optimizer->optimizeDatabase($dbh, $type);

            // Simple performance test
            $test_start = microtime(true);
            if ($type === 'article') {
                $stmt = $dbh->query("SELECT COUNT(*) FROM articles");
            } else {
                $stmt = $dbh->query("SELECT COUNT(*) FROM overview");
            }
            $count = $stmt->fetchColumn();
            $query_time = (microtime(true) - $test_start) * 1000;

            $stats['record_count'] = $count;
            $stats['query_time_ms'] = round($query_time, 3);
            $stats['connection_time_ms'] = round((microtime(true) - $start_time) * 1000, 3);

            // Determine health
            if ($query_time < 1) {
                $stats['health'] = 'excellent';
            } elseif ($query_time < 5) {
                $stats['health'] = 'good';
            } elseif ($query_time < 20) {
                $stats['health'] = 'fair';
            } else {
                $stats['health'] = 'poor';
            }

            $dbh = null;

        } catch (Exception $e) {
            $stats['error'] = $e->getMessage();
            $stats['health'] = 'error';
        }

        return $stats;
    }

    /**
     * Format health status with emoji
     */
    private function formatHealth($stats) {
        $health_icons = [
            'excellent' => '🟢',
            'good' => '🟡',
            'fair' => '🟠',
            'poor' => '🔴',
            'error' => '❌',
            'unknown' => '⚪'
        ];

        $icon = $health_icons[$stats['health']] ?? '⚪';
        $size = $this->formatFileSize($stats['size']);

        if (isset($stats['query_time_ms'])) {
            return sprintf("%s %s (%d records, %.1fms query, %s)",
                $icon,
                ucfirst($stats['health']),
                $stats['record_count'],
                $stats['query_time_ms'],
                $size
            );
        } else {
            return sprintf("%s %s (%s)", $icon, ucfirst($stats['health']), $size);
        }
    }

    /**
     * Format file size in human readable format
     */
    private function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 1) . ' ' . $units[$pow];
    }

    /**
     * Print overall summary
     */
    private function printSummary($results) {
        echo "\n📋 Summary:\n";
        echo "==========\n";

        $health_counts = ['excellent' => 0, 'good' => 0, 'fair' => 0, 'poor' => 0, 'error' => 0];
        $total_size = 0;
        $total_records = 0;
        $avg_query_time = 0;
        $valid_queries = 0;

        foreach ($results as $stats) {
            $health_counts[$stats['health']]++;
            $total_size += $stats['size'];

            if (isset($stats['record_count'])) {
                $total_records += $stats['record_count'];
            }

            if (isset($stats['query_time_ms'])) {
                $avg_query_time += $stats['query_time_ms'];
                $valid_queries++;
            }
        }

        if ($valid_queries > 0) {
            $avg_query_time /= $valid_queries;
        }

        echo "🟢 Excellent: {$health_counts['excellent']}  ";
        echo "🟡 Good: {$health_counts['good']}  ";
        echo "🟠 Fair: {$health_counts['fair']}  ";
        echo "🔴 Poor: {$health_counts['poor']}  ";
        echo "❌ Errors: {$health_counts['error']}\n";

        echo "\n📈 Performance Metrics:\n";
        echo "• Total database size: " . $this->formatFileSize($total_size) . "\n";
        echo "• Total records: " . number_format($total_records) . "\n";
        echo "• Average query time: " . round($avg_query_time, 2) . "ms\n";

        // Performance recommendations
        if ($avg_query_time > 10) {
            echo "\n⚠️  Recommendation: Query times are high. Consider running database maintenance.\n";
        } elseif ($avg_query_time < 1) {
            echo "\n✅ Performance is excellent! Optimizations are working well.\n";
        }
    }

    /**
     * Run database maintenance on slow databases
     */
    public function runMaintenance($health_threshold = 'fair') {
        echo "🔧 Running Database Maintenance\n";
        echo "===============================\n\n";

        $results = $this->quickHealthCheck();
        $maintained = 0;

        foreach ($results as $stats) {
            if ($this->needsMaintenance($stats['health'], $health_threshold)) {
                echo "\n🔧 Maintaining: " . basename($stats['file']) . "\n";
                $this->maintainDatabase($stats['file']);
                $maintained++;
            }
        }

        echo "\n✅ Maintenance completed on $maintained databases.\n";
    }

    /**
     * Check if database needs maintenance
     */
    private function needsMaintenance($health, $threshold) {
        $health_order = ['excellent', 'good', 'fair', 'poor', 'error'];
        $current_index = array_search($health, $health_order);
        $threshold_index = array_search($threshold, $health_order);

        return $current_index !== false && $threshold_index !== false && $current_index >= $threshold_index;
    }

    /**
     * Perform maintenance on a database
     */
    private function maintainDatabase($db_file) {
        try {
            $dbh = new PDO('sqlite:' . $db_file);
            $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            echo "  • Applying optimizations...\n";
            $this->optimizer->optimizeDatabase($dbh);

            echo "  • Running VACUUM...\n";
            $dbh->exec('VACUUM');

            echo "  • Analyzing tables...\n";
            $dbh->exec('ANALYZE');

            echo "  • Maintenance completed.\n";

        } catch (Exception $e) {
            echo "  ❌ Error: " . $e->getMessage() . "\n";
        }
    }
}

// CLI usage
if (php_sapi_name() === 'cli') {
    $monitor = new DatabaseMonitor();

    $command = $argv[1] ?? 'check';

    switch ($command) {
        case 'check':
            $monitor->quickHealthCheck();
            break;

        case 'maintain':
            $threshold = $argv[2] ?? 'fair';
            $monitor->runMaintenance($threshold);
            break;

        case 'help':
        default:
            echo "RockSolid Light Database Monitor\n";
            echo "================================\n\n";
            echo "Usage: php database_monitor.php [command] [options]\n\n";
            echo "Commands:\n";
            echo "  check                  - Run health check on databases\n";
            echo "  maintain [threshold]   - Run maintenance on slow databases\n";
            echo "                          threshold: excellent|good|fair|poor (default: fair)\n";
            echo "  help                   - Show this help message\n\n";
            echo "Examples:\n";
            echo "  php database_monitor.php check\n";
            echo "  php database_monitor.php maintain good\n";
            break;
    }
}
?>
