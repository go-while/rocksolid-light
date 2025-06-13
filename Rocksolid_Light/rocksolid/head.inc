<?php
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
    // Fallback - try direct path
    include_once '/home/fed/GO/src/github.com/go-while/rocksolid-light/Rocksolid_Light/common/head_functions.inc.php';
}

$_SESSION['rsactive'] = true;
$client_device = get_client_user_agent_info();

// throttle_hits MUST be called before any data is sent
throttle_hits($client_device);
write_access_log();

echo '<!DOCTYPE html>';
echo '<html><head>';
echo '<title>' . htmlspecialchars($title) . '</title>';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';

include "config.inc.php";

if (file_exists($config_dir . '/googleanalytics.conf')) {
    include $config_dir . '/googleanalytics.conf';
}

if (isset($frames_on) && $frames_on === false) {
    if (file_exists('../common/mods/header.php')) {
        include "../common/mods/header.php";
    } else {
        include "../common/header.php";
    }
}
echo '<hr>';
