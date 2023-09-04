<?php
echo '<html><head>';
echo '<title>' . htmlspecialchars($title) . '</title>';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<META HTTP-EQUIV="Content-type" CONTENT="text/html; charset=<?=$www_charset?>">';
include "config.inc.php";

// Try to get browser info to use for extra formatting of page
$ua = strtolower($_SERVER["HTTP_USER_AGENT"]);
$devices = array(
    "bot",
    "spider",
    "mobile",
    "lynx",
    "w3m",
    "links",
    "ipad",
    "tablet"
);
$client_device = "desktop";
foreach ($devices as $device) {
    if (strpos($ua, $device) !== false) {
        $client_device = $device;
        break;
    }
}
if($client_device == "spider") {
    $client_device = "bot";
}
// Log client device if enabled by semaphore
if (file_exists($config_dir . '/devicelog.enable')) {
    $client_ip = getenv("REMOTE_ADDR");
    $logfile = $logdir . '/device.log';
    file_put_contents($logfile, "\n" . date('M d H:i:s') . " " . $config_name . " Client: " . $client_ip . " browser: " . $client_device, FILE_APPEND);
    file_put_contents($logfile, "\nFull UA: ".$ua, FILE_APPEND);
}

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