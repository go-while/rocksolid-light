<?php

$enable_memcache = false;

if(!$enable_memcache) {
    exit;
}

// Server & port details
$server = '127.0.0.1';
$port = 11211;

// Log all hits
$enable_memcache_logging = false;

// Time in seconds to cache data
$memcache_ttl = 14400;

/* PLEASE DO NOT EDIT BELOW THIS LINE */

// Initiate a new object of memcache
$memcacheD = new Memcached();

// Add server
if ($memcacheD->addServer($server, $port)) {
//    file_put_contents($logdir . '/debug.log', "\n".format_log_date() . ' Added memcache ' .$server . ':' . $port, FILE_APPEND);
}
else {
    file_put_contents($logdir . '/debug.log', "\n".format_log_date() . ' Failed to add memcache ' .$server . ':' . $port, FILE_APPEND);
}

