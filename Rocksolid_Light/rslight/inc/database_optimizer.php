<?php
/**
 * RockSolid Light - Database Performance Optimization Module
 *
 * Implements optimized SQLite PRAGMA settings and database performance enhancements
 * for RockSolid Light newsgroup server
 *
 * @author Database Performance Team
 * @version 1.0.0
 * @date 2025-01-27
 */

/**
 * Database Performance Optimization Class
 */
class DatabaseOptimizer {

    private $optimized_pragmas;
    private $monitoring_enabled;
    private $performance_log;

    public function __construct($enable_monitoring = true) {
        global $spooldir;

        $this->monitoring_enabled = $enable_monitoring;
        $this->performance_log = $spooldir . '/log/database_performance.log';

        // Optimized SQLite PRAGMA settings for newsgroup server workload
        $this->optimized_pragmas = [
            // Performance Settings
            'journal_mode' => 'WAL',           // Write-Ahead Logging for better concurrency
            'synchronous' => 'NORMAL',         // Balanced safety vs performance
            'cache_size' => '10000',           // 10MB cache (10000 pages * 1KB each)
            'temp_store' => 'MEMORY',          // Store temporary tables/indexes in memory
            'mmap_size' => '268435456',        // 256MB memory mapping for read performance

            // WAL Mode Settings
            'wal_autocheckpoint' => '1000',    // Checkpoint every 1000 pages
            'wal_checkpoint' => 'TRUNCATE',    // Truncate WAL file during checkpoint

            // Query Optimization
            'query_optimizer' => 'ON',         // Enable query optimizer
            'optimize' => '',                  // Analyze database for better query plans

            // Concurrency Settings
            'busy_timeout' => '30000',         // 30 second timeout for busy database
            'timeout' => '30000',              // Connection timeout

            // Memory and Storage Settings
            'page_size' => '4096',             // 4KB page size (optimal for most systems)
            'max_page_count' => '1073741824',  // Allow large databases (4TB max)
            'auto_vacuum' => 'INCREMENTAL',    // Incremental auto-vacuum to prevent bloat
        ];
    }

    /**
     * Apply optimized PRAGMA settings to a database connection
     */
    public function optimizeDatabase($dbh, $database_type = 'general') {
        $applied_pragmas = [];
        $failed_pragmas = [];

        foreach ($this->optimized_pragmas as $pragma => $value) {
            try {
                // Skip certain pragmas for specific database types
                if ($this->shouldSkipPragma($pragma, $database_type)) {
                    continue;
                }

                if ($value === '') {
                    // Special case for pragmas without values (like OPTIMIZE)
                    $dbh->exec("PRAGMA $pragma");
                } else {
                    $dbh->exec("PRAGMA $pragma = $value");
                }

                $applied_pragmas[$pragma] = $value;

                if ($this->monitoring_enabled) {
                    $this->logPerformance("Applied PRAGMA $pragma = $value to $database_type database");
                }

            } catch (Exception $e) {
                $failed_pragmas[$pragma] = $e->getMessage();

                if ($this->monitoring_enabled) {
                    $this->logPerformance("Failed to apply PRAGMA $pragma: " . $e->getMessage(), 'WARNING');
                }
            }
        }

        return [
            'applied' => $applied_pragmas,
            'failed' => $failed_pragmas
        ];
    }

    /**
     * Check if a PRAGMA should be skipped for certain database types
     */
    private function shouldSkipPragma($pragma, $database_type) {
        // Skip page_size for existing databases (can only be set on empty databases)
        if ($pragma === 'page_size' && $database_type !== 'new') {
            return true;
        }

        return false;
    }

    /**
     * Get current database performance statistics
     */
    public function getDatabaseStats($dbh) {
        $stats = [];

        try {
            // Get current PRAGMA settings
            $pragma_queries = [
                'journal_mode', 'synchronous', 'cache_size', 'temp_store',
                'mmap_size', 'page_size', 'wal_autocheckpoint', 'busy_timeout'
            ];

            foreach ($pragma_queries as $pragma) {
                $stmt = $dbh->query("PRAGMA $pragma");
                if ($stmt) {
                    $result = $stmt->fetch(PDO::FETCH_NUM);
                    $stats['pragmas'][$pragma] = $result[0] ?? 'N/A';
                }
            }

            // Get database file information
            $stmt = $dbh->query("PRAGMA database_list");
            if ($stmt) {
                $stats['databases'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Get index statistics
            $stmt = $dbh->query("PRAGMA index_list");
            if ($stmt) {
                $stats['indexes'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Get cache statistics
            $cache_stats = [
                'cache_hit' => 'PRAGMA cache_hit',
                'cache_miss' => 'PRAGMA cache_miss',
                'cache_size' => 'PRAGMA cache_size'
            ];

            foreach ($cache_stats as $stat_name => $query) {
                try {
                    $stmt = $dbh->query($query);
                    if ($stmt) {
                        $result = $stmt->fetch(PDO::FETCH_NUM);
                        $stats['cache'][$stat_name] = $result[0] ?? 0;
                    }
                } catch (Exception $e) {
                    // Some PRAGMA commands may not be available in all SQLite versions
                    $stats['cache'][$stat_name] = 'N/A';
                }
            }

        } catch (Exception $e) {
            $this->logPerformance("Error getting database stats: " . $e->getMessage(), 'ERROR');
        }

        return $stats;
    }

    /**
     * Perform database maintenance and optimization
     */
    public function performMaintenance($dbh, $database_name = 'unknown') {
        $maintenance_results = [];
        $start_time = microtime(true);

        try {
            // Analyze the database for better query planning
            $dbh->exec("ANALYZE");
            $maintenance_results['analyze'] = 'completed';

            // Perform incremental vacuum if enabled
            $stmt = $dbh->query("PRAGMA auto_vacuum");
            $auto_vacuum = $stmt->fetch(PDO::FETCH_NUM)[0];

            if ($auto_vacuum == 2) { // INCREMENTAL
                $dbh->exec("PRAGMA incremental_vacuum");
                $maintenance_results['incremental_vacuum'] = 'completed';
            }

            // Get database size information
            $stmt = $dbh->query("PRAGMA page_count");
            $page_count = $stmt->fetch(PDO::FETCH_NUM)[0];

            $stmt = $dbh->query("PRAGMA page_size");
            $page_size = $stmt->fetch(PDO::FETCH_NUM)[0];

            $database_size_mb = ($page_count * $page_size) / (1024 * 1024);
            $maintenance_results['database_size_mb'] = round($database_size_mb, 2);

            // WAL checkpoint if in WAL mode
            $stmt = $dbh->query("PRAGMA journal_mode");
            $journal_mode = $stmt->fetch(PDO::FETCH_NUM)[0];

            if (strtoupper($journal_mode) === 'WAL') {
                $stmt = $dbh->query("PRAGMA wal_checkpoint(TRUNCATE)");
                $checkpoint_result = $stmt->fetch(PDO::FETCH_NUM);
                $maintenance_results['wal_checkpoint'] = [
                    'result' => $checkpoint_result[0] ?? 'unknown',
                    'pages_written' => $checkpoint_result[1] ?? 0,
                    'pages_checkpointed' => $checkpoint_result[2] ?? 0
                ];
            }

            $maintenance_time = (microtime(true) - $start_time) * 1000;
            $maintenance_results['maintenance_time_ms'] = round($maintenance_time, 2);

            if ($this->monitoring_enabled) {
                $this->logPerformance("Database maintenance completed for $database_name in " .
                                    number_format($maintenance_time, 2) . "ms");
            }

        } catch (Exception $e) {
            $maintenance_results['error'] = $e->getMessage();

            if ($this->monitoring_enabled) {
                $this->logPerformance("Database maintenance error for $database_name: " .
                                    $e->getMessage(), 'ERROR');
            }
        }

        return $maintenance_results;
    }

    /**
     * Monitor query performance
     */
    public function monitorQuery($query, callable $query_function, $params = []) {
        if (!$this->monitoring_enabled) {
            return $query_function();
        }

        $start_time = microtime(true);
        $start_memory = memory_get_usage();

        try {
            $result = $query_function();

            $end_time = microtime(true);
            $end_memory = memory_get_usage();

            $execution_time = ($end_time - $start_time) * 1000;
            $memory_used = $end_memory - $start_memory;

            // Log slow queries (> 100ms)
            if ($execution_time > 100) {
                $this->logPerformance("SLOW QUERY: $query executed in " .
                                    number_format($execution_time, 2) . "ms, memory: " .
                                    number_format($memory_used / 1024, 2) . "KB", 'WARNING');
            }

            return $result;

        } catch (Exception $e) {
            $end_time = microtime(true);
            $execution_time = ($end_time - $start_time) * 1000;

            $this->logPerformance("QUERY ERROR: $query failed after " .
                                number_format($execution_time, 2) . "ms - " . $e->getMessage(), 'ERROR');

            throw $e;
        }
    }

    /**
     * Get performance recommendations for a database
     */
    public function getPerformanceRecommendations($dbh) {
        $recommendations = [];
        $stats = $this->getDatabaseStats($dbh);

        // Check journal mode
        if (isset($stats['pragmas']['journal_mode']) &&
            strtoupper($stats['pragmas']['journal_mode']) !== 'WAL') {
            $recommendations[] = [
                'priority' => 'HIGH',
                'type' => 'PRAGMA',
                'setting' => 'journal_mode',
                'current' => $stats['pragmas']['journal_mode'],
                'recommended' => 'WAL',
                'reason' => 'WAL mode provides better concurrency and performance for newsgroup servers'
            ];
        }

        // Check cache size
        if (isset($stats['pragmas']['cache_size']) &&
            intval($stats['pragmas']['cache_size']) < 5000) {
            $recommendations[] = [
                'priority' => 'HIGH',
                'type' => 'PRAGMA',
                'setting' => 'cache_size',
                'current' => $stats['pragmas']['cache_size'],
                'recommended' => '10000',
                'reason' => 'Larger cache improves query performance significantly'
            ];
        }

        // Check synchronous setting
        if (isset($stats['pragmas']['synchronous']) &&
            strtoupper($stats['pragmas']['synchronous']) === 'FULL') {
            $recommendations[] = [
                'priority' => 'MEDIUM',
                'type' => 'PRAGMA',
                'setting' => 'synchronous',
                'current' => $stats['pragmas']['synchronous'],
                'recommended' => 'NORMAL',
                'reason' => 'NORMAL synchronous setting provides good balance of safety and performance'
            ];
        }

        // Check memory mapping
        if (isset($stats['pragmas']['mmap_size']) &&
            intval($stats['pragmas']['mmap_size']) === 0) {
            $recommendations[] = [
                'priority' => 'MEDIUM',
                'type' => 'PRAGMA',
                'setting' => 'mmap_size',
                'current' => $stats['pragmas']['mmap_size'],
                'recommended' => '268435456',
                'reason' => 'Memory mapping improves read performance for large databases'
            ];
        }

        // Check temp_store setting
        if (isset($stats['pragmas']['temp_store']) &&
            strtoupper($stats['pragmas']['temp_store']) !== 'MEMORY') {
            $recommendations[] = [
                'priority' => 'LOW',
                'type' => 'PRAGMA',
                'setting' => 'temp_store',
                'current' => $stats['pragmas']['temp_store'],
                'recommended' => 'MEMORY',
                'reason' => 'Storing temporary data in memory improves query performance'
            ];
        }

        return $recommendations;
    }

    /**
     * Log performance information
     */
    private function logPerformance($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $log_entry = "[$timestamp] [$level] $message\n";

        @file_put_contents($this->performance_log, $log_entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Generate performance report
     */
    public function generatePerformanceReport($database_connections) {
        $report = [];
        $report['timestamp'] = date('Y-m-d H:i:s');
        $report['databases'] = [];

        foreach ($database_connections as $db_name => $dbh) {
            $stats = $this->getDatabaseStats($dbh);
            $recommendations = $this->getPerformanceRecommendations($dbh);

            $report['databases'][$db_name] = [
                'stats' => $stats,
                'recommendations' => $recommendations,
                'optimized' => count($recommendations) === 0
            ];
        }

        return $report;
    }
}

/**
 * Enhanced database opening functions with optimization
 */

/**
 * Open article database with optimization
 */
function article_db_open_optimized($database, $table = 'articles') {
    global $spooldir, $logdir, $config_name;

    // Use original function to create/open database
    $dbh = article_db_open($database, $table);

    if ($dbh) {
        // Apply optimizations
        $optimizer = new DatabaseOptimizer();
        $optimization_result = $optimizer->optimizeDatabase($dbh, 'article');

        // Log optimization results
        if (!empty($optimization_result['applied'])) {
            $logfile = $logdir . '/debug.log';
            file_put_contents($logfile, "\n" . logging_prefix() . " " . $config_name .
                            " Applied " . count($optimization_result['applied']) .
                            " database optimizations to $database", FILE_APPEND);
        }
    }

    return $dbh;
}

/**
 * Open overview database with optimization
 */
function overview_db_open_optimized($database, $table = 'overview') {
    global $logdir, $config_name;

    // Use original function to create/open database
    $dbh = overview_db_open($database, $table);

    if ($dbh) {
        // Apply optimizations
        $optimizer = new DatabaseOptimizer();
        $optimization_result = $optimizer->optimizeDatabase($dbh, 'overview');

        // Log optimization results
        if (!empty($optimization_result['applied'])) {
            $logfile = $logdir . '/debug.log';
            file_put_contents($logfile, "\n" . logging_prefix() . " " . $config_name .
                            " Applied " . count($optimization_result['applied']) .
                            " database optimizations to $database", FILE_APPEND);
        }
    }

    return $dbh;
}

/**
 * Open history database with optimization
 */
function history_db_open_optimized($database, $table = 'history') {
    global $logdir, $config_name;

    // Use original function to create/open database
    $dbh = history_db_open($database, $table);

    if ($dbh) {
        // Apply optimizations
        $optimizer = new DatabaseOptimizer();
        $optimization_result = $optimizer->optimizeDatabase($dbh, 'history');

        // Log optimization results
        if (!empty($optimization_result['applied'])) {
            $logfile = $logdir . '/debug.log';
            file_put_contents($logfile, "\n" . logging_prefix() . " " . $config_name .
                            " Applied " . count($optimization_result['applied']) .
                            " database optimizations to $database", FILE_APPEND);
        }
    }

    return $dbh;
}

/**
 * Open mail database with optimization
 */
function mail_db_open_optimized($database, $table = 'messages') {
    global $logdir, $config_name;

    // Use original function to create/open database
    $dbh = mail_db_open($database, $table);

    if ($dbh) {
        // Apply optimizations
        $optimizer = new DatabaseOptimizer();
        $optimization_result = $optimizer->optimizeDatabase($dbh, 'mail');

        // Log optimization results
        if (!empty($optimization_result['applied'])) {
            $logfile = $logdir . '/debug.log';
            file_put_contents($logfile, "\n" . logging_prefix() . " " . $config_name .
                            " Applied " . count($optimization_result['applied']) .
                            " database optimizations to $database", FILE_APPEND);
        }
    }

    return $dbh;
}

/**
 * Database maintenance function
 */
function perform_database_maintenance() {
    global $spooldir, $logdir, $config_name;

    $optimizer = new DatabaseOptimizer();
    $maintenance_results = [];
    $logfile = $logdir . '/debug.log';

    try {
        // Maintain overview database
        $overview_db = $spooldir . '/articles-overview.db3';
        if (file_exists($overview_db)) {
            $dbh = overview_db_open($overview_db);
            if ($dbh) {
                $optimizer->optimizeDatabase($dbh, 'overview');
                $maintenance_results['overview'] = $optimizer->performMaintenance($dbh, 'overview');
                $dbh = null;
            }
        }

        // Maintain history database
        $history_db = $spooldir . '/history.db3';
        if (file_exists($history_db)) {
            $dbh = history_db_open($history_db);
            if ($dbh) {
                $optimizer->optimizeDatabase($dbh, 'history');
                $maintenance_results['history'] = $optimizer->performMaintenance($dbh, 'history');
                $dbh = null;
            }
        }

        // Maintain mail database
        $mail_db = $spooldir . '/mail.db3';
        if (file_exists($mail_db)) {
            $dbh = mail_db_open($mail_db);
            if ($dbh) {
                $optimizer->optimizeDatabase($dbh, 'mail');
                $maintenance_results['mail'] = $optimizer->performMaintenance($dbh, 'mail');
                $dbh = null;
            }
        }

        // Maintain article databases (sample a few largest ones)
        $article_databases = glob($spooldir . '/*-articles.db3');
        $article_count = 0;

        foreach ($article_databases as $article_db) {
            if ($article_count >= 5) break; // Limit maintenance to prevent long execution

            $dbh = article_db_open($article_db);
            if ($dbh) {
                $group_name = basename($article_db, '-articles.db3');
                $optimizer->optimizeDatabase($dbh, 'article');
                $maintenance_results['articles'][$group_name] = $optimizer->performMaintenance($dbh, $group_name);
                $dbh = null;
                $article_count++;
            }
        }

        file_put_contents($logfile, "\n" . logging_prefix() . " " . $config_name .
                        " Database maintenance completed for " . count($maintenance_results) . " databases",
                        FILE_APPEND);

    } catch (Exception $e) {
        file_put_contents($logfile, "\n" . logging_prefix() . " " . $config_name .
                        " Database maintenance error: " . $e->getMessage(), FILE_APPEND);
    }

    return $maintenance_results;
}

/**
 * Generate database performance monitoring report
 */
function generate_database_performance_report() {
    global $spooldir;

    $optimizer = new DatabaseOptimizer();
    $database_connections = [];

    // Collect current database connections for analysis
    $overview_db = $spooldir . '/articles-overview.db3';
    if (file_exists($overview_db)) {
        $database_connections['overview'] = overview_db_open($overview_db);
    }

    $history_db = $spooldir . '/history.db3';
    if (file_exists($history_db)) {
        $database_connections['history'] = history_db_open($history_db);
    }

    $mail_db = $spooldir . '/mail.db3';
    if (file_exists($mail_db)) {
        $database_connections['mail'] = mail_db_open($mail_db);
    }

    // Generate comprehensive report
    $report = $optimizer->generatePerformanceReport($database_connections);

    // Close connections
    foreach ($database_connections as $dbh) {
        $dbh = null;
    }

    return $report;
}
?>
