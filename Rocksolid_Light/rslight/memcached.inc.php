<?php
/* memcached and php-memcached must be installed */

// Set to true to enable memcache
$enable_memcache = false;

// Server & port details
$memcache_server = '127.0.0.1';
$memcache_port = 11211;
 
// Log all hits (log file may be large)
$enable_memcache_logging = false;

// Time in seconds to cache data
$memcache_ttl = 14400;

/* PLEASE DO NOT EDIT BELOW THIS LINE */
if ($enable_memcache) {

    // Initiate a new object of memcache
    $memcacheD = new Memcached();

    // Add server
    if ($memcacheD->addServer($memcache_server, $memcache_port)) {
        if ($enable_memcache_logging) {
            file_put_contents($logdir . '/memcache.log', "\n" . format_log_date() . ' Connected memcache ' . $memcache_server . ':' . $memcache_port, FILE_APPEND);
        }
    } else {
        file_put_contents($logdir . '/memcache.log', "\n" . format_log_date() . ' Failed to connect memcache ' . $memcache_server . ':' . $memcache_port, FILE_APPEND);
    }
}
