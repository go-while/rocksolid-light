<?php
global $enable_cache, $rslight_version, $spooldir, $logdir, $cache_dir, $cache_log;
 /* 
 * Set $enable_cache to the cache type you want to use
 * memcached and php-memcached must be installed
 * if using memcached.
 * $enable_cache = 'memcached';
 * 
 * This will use a directory for caching, no memcache
 * $enable_cache = 'diskcache';
 * 
 * or set to false (no quotes) to disable caching:
 * $enable_cache = false;
 */
// $enable_cache = 'memcached';
// $enable_cache = 'diskcache';
$enable_cache = false;

// Enable logging to file (log file may be large)
$enable_cache_logging = false;

// Server & port details if using memcached
$memcache_server = '127.0.0.1';
$memcache_port = 11211;
/*
 * Maximum size of data (in bytes) to save per key in memcache
 * 
 * If using memcached This must be less than or equal to
 * MAXITEMSIZE in memcached, which is 1MiB by default
 * Increasing this here will not work unless it is also
 * increased in memcached configuration
 * 
 * If using diskcache, pruning by size is only done daily
 * 
 * You probably do not need to change this
 */
$cache_maxitemsize = 1024000;

// Time in seconds to cache data
$cache_ttl = 14400;

/*
 * A string to prepend to cached key names
 * Necessary if using more than one rslight instance
 * with one memcache instance
 */
$cache_key_prefix = 'mysite';

// Directory to cache data if using diskcache
$cache_dir = $spooldir . '/cache/';

/* PLEASE DO NOT EDIT BELOW THIS LINE */

$cache_log = $logdir . '/cache.log';
@mkdir($cache_dir);

// Add version to prefix to avoid errors if upgrading
// and not restarting memcached
$cache_key_prefix .= trim(preg_replace('/\./', '', $rslight_version));

/* IF MEMCACHED */
if ($enable_cache == 'memcached') {
    $memcacheD = new Memcached('memcacheD');
    $memcacheD->setOption(Memcached::OPT_LIBKETAMA_COMPATIBLE, true);
    $memcacheD->setOption(Memcached::OPT_CONNECT_TIMEOUT, 1000);
    if (! count($memcacheD->getServerList())) {
        if (! $memcacheD->addServers(array(
            array(
                $memcache_server,
                $memcache_port
            )
        ))) {
            file_put_contents($logdir . '/memcache.log', "\n" . format_log_date() . ' Failed to connect memcache ' . $memcache_server . ':' . $memcache_port, FILE_APPEND);
        } else {
            file_put_contents($logdir . '/memcache.log', "\n" . format_log_date() . ' Connected memcache ' . $memcache_server . ':' . $memcache_port, FILE_APPEND);
        }
    }
} else {
    $memcacheD = null;
}
/* END IF MEMCACHED */
