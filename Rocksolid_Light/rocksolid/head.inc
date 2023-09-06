<?php
/*
 * Add 'rsactive' to differentiate rslight sessions from
 * sessions from other applications
 */
$_SESSION['rsactive'] = true;
$client_device = get_client_user_agent_info();

// throttle_hits MUST be called before any data is sent
throttle_hits($client_device);
write_access_log();

echo '<html><head>';
echo '<title>' . htmlspecialchars($title) . '</title>';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<META HTTP-EQUIV="Content-type" CONTENT="text/html; charset=<?=$www_charset?>">';
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
echo '<hr></head>';
echo '<body textcolor="black" bgcolor="white">';