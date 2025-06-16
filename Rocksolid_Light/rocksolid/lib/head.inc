<?php
$backtrace = debug_backtrace();
$parent = isset($backtrace[0]['file']) ? $backtrace[0]['file'] : 'Direct execution';
echo "[ERROR rocksolid/lib/head.inc (STUB) included by: " . basename($parent) . "]<br>\n";
die("legacy code!");

/*
 * Add 'rsactive' to differentiate rslight sessions from
 * sessions from other applications
 */

// Include essential functions needed by head.inc
if (file_exists('../common/head_functions.inc.php')) {
    include_once '../common/head_functions.inc.php';
} elseif (file_exists(__DIR__ . '/../common/head_functions.inc.php')) {
    include_once __DIR__ . '/../common/head_functions.inc.php';
} else {
    die("debug include error head_functions in rocksolid/lib/head.inc");
}


//require_once(__DIR__.'/security.inc.php');

$_SESSION['rsactive'] = true;
$client_device = get_client_user_agent_info();

// throttle_hits MUST be called before any data is sent
throttle_hits($client_device);
write_access_log();

echo '<!DOCTYPE html>';
echo '<html><head>';
echo '<title>DEBUG#8ff4' . htmlspecialchars($title) . '</title>';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';

include "config.inc.php";

if (file_exists($config_dir . '/googleanalytics.conf')) {
    include $config_dir . '/googleanalytics.conf';
}

include "../common/header.php";

echo '<hr>';
// TODO FIXME: crazy things happen if you add a php close tag here!!
