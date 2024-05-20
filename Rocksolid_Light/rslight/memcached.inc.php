<?php
/* memcached and php-memcached must be installed */

// Set to true to enable memcache
$enable_memcache = false;

// Server & port details
$memcache_server = '127.0.0.1';
$memcache_port = 11211;
 
// Enable logging to file (log file may be large)
$enable_memcache_logging = false;

// Time in seconds to cache data
$memcache_ttl = 14400;

/*
 * Maximum size of data (in bytes) to save per key in memcache
 * This must be less than or equal to
 * MAXITEMSIZE in memcached, which is 1MiB by default
 * Increasing this here will not work unless it is also
 * increased in memcached configuration
 * You probably do not need to change this
 */
$memcache_maxitemsize = 1024000;

/* 
 * A string to prepend to cached key names
 * Required if using more than one rslight instance
 * with one memcache instance
 */
$memcache_key_prefix = 'rsl';

/* PLEASE DO NOT EDIT BELOW THIS LINE */

if ($enable_memcache) {
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
            if ($enable_memcache_logging) {
                file_put_contents($logdir . '/memcache.log', "\n" . format_log_date() . ' Connected memcache ' . $memcache_server . ':' . $memcache_port, FILE_APPEND);
            }
        }
    }
}

