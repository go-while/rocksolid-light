<?php

$backtrace = debug_backtrace();
$parent = isset($backtrace[0]['file']) ? $backtrace[0]['file'] : 'Direct execution';
echo "rocksolid/common/head_functions.inc.php included by: " . basename($parent) . "\n";

/**
 * Essential functions needed by head.inc
 * These functions are extracted from newsportal.php so they can be used
 * independently by scripts that include head.inc without newsportal.php
 */

/*

if (!function_exists('get_client_user_agent_info')) {
    function get_client_user_agent_info()
    {
        global $config_dir, $logdir;

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
        if ($client_device == "spider" || $client_device == "crawler") {
            $client_device = "bot";
        }
        // Log client device if enabled by semaphore
        if (file_exists($config_dir . '/devicelog.enable')) {
            $client_ip = getenv("REMOTE_ADDR");
            $logfile = $logdir . '/device.log';
            file_put_contents($logfile, "\n" . format_log_date() . " " . $client_ip . " " . $client_device, FILE_APPEND);
        }
        return $client_device;
    }
}

if (!function_exists('throttle_hits')) {
    function throttle_hits($client_device = null)
    {
        global $CONFIG, $OVERRIDES, $logdir, $abuse_log, $config_name, $spooldir;

        $rdns_file = $spooldir . '/rdns.dat';
        $rdns = array();
        if (file_exists($rdns_file)) {
            try {
                $rdns = secure_unserialize(file_get_contents($rdns_file));
                if (!is_array($rdns)) {
                    $rdns = array();
                }
            } catch (Exception $e) {
                $rdns = array();
            }
        }

        if (! $client_device) {
            $client_device = get_client_user_agent_info();
        }
        $client_device = strtolower($client_device);

        $_SESSION['rsactive'] = true;

        // Block by user-agent
        if (isset($OVERRIDES['block_by_user_agent'])) {
            $this_ua = strtolower($_SERVER["HTTP_USER_AGENT"]);
            foreach ($OVERRIDES['block_by_user_agent'] as $block_user_agent) {
                if (stripos($this_ua, $block_user_agent) !== false) {
                    file_put_contents($abuse_log, "\n" . logging_prefix() . " (blocking) '" . $block_user_agent . "' found in User-Agent block list", FILE_APPEND);
                    $_SESSION['throttled'] = true;
                    header("HTTP/1.0 403 Forbidden");
                    exit();
                }
            }
        }

        // Block by rdns
        if (isset($OVERRIDES['block_by_rdns'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
            if (isset($rdns[$ip])) {
                $this_rdns = $rdns[$ip];
            } else {
                $this_rdns = gethostbyaddr($ip);
                $rdns[$ip] = $this_rdns;
                file_put_contents($rdns_file, serialize($rdns));
            }
            foreach ($OVERRIDES['block_by_rdns'] as $block_rdns) {
                if (stripos($this_rdns, $block_rdns) !== false) {
                    file_put_contents($abuse_log, "\n" . logging_prefix() . " (blocking) '" . $block_rdns . "' found in RDNS block list", FILE_APPEND);
                    $_SESSION['throttled'] = true;
                    header("HTTP/1.0 403 Forbidden");
                    exit();
                }
            }
        }

        // $loadrate = allowed article request per second
        $loadrate = .15;
        if ($client_device == "bot") {
            $_SESSION['bot'] = 'true';
            if (isset($OVERRIDES['throttle_hits_bot_loadrate']) && trim($OVERRIDES['throttle_hits_bot_loadrate']) != '') {
                $loadrate = $OVERRIDES['throttle_hits_bot_loadrate'];
            }
        }

        if (! isset($_SESSION['starttime'])) {
            $_SESSION['starttime'] = time();
            $_SESSION['views'] = 0;
        }
        $_SESSION['views']++;
        // $rate = current hits / seconds since start of session
        $rate = fdiv($_SESSION['views'], (time() - $_SESSION['starttime']));
        // if $rate > greater than $loadrate, throttle hits
        // but allow 50 hits at start of session to allow loading everything
        if (($rate > $loadrate) && ($_SESSION['views'] > 50)) {
            header("HTTP/1.0 429 Too Many Requests");
            if (! isset($_SESSION['throttled'])) {
                file_put_contents($abuse_log, "\n" . logging_prefix() . " (throttling) too many requests" . " (" . $rate . " > " . $loadrate . ")", FILE_APPEND);
                $_SESSION['throttled'] = true;
            }
            exit(0);
        }
        if (isset($_SESSION['throttled'])) {
            unset($_SESSION['throttled']);
        }
    }
}

if (!function_exists('write_access_log')) {
    function write_access_log()
    {
        global $logdir;
        $accessfile = $logdir . '/access.log';
        $currentPageUrl = $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
        file_put_contents($accessfile, "\n" . logging_prefix() . " " . $currentPageUrl, FILE_APPEND);
    }
}

if (!function_exists('logging_prefix')) {
    function logging_prefix($sockip = null)
    {
        global $client_ip_address;
        if ($sockip) {
            if (preg_match("/\./", $sockip)) {
                $client_ip_address = $sockip;
            } else {
                $client_ip_address = 'invalid';
            }
        } else {
            $client_ip_address = getenv("REMOTE_ADDR");
        }
        return format_log_date() . " " . $client_ip_address;
    }
}

if (!function_exists('format_log_date')) {
    function format_log_date()
    {
        return date('M d H:i:s');
    }
}

if (!function_exists('secure_unserialize')) {
    die("debug: include error !function_exists 'secure_unserialize' in head_functions.inc.php");
}
*/

/*
if (!function_exists('secure_unserialize')) {
//    function secure_unserialize($filename, $allowed_classes = ['stdClass'], $array_fallback = true)
    {
        if (!file_exists($filename)) {
            return $array_fallback ? [] : false;
        }

        try {
            $content = file_get_contents($filename);
            if ($content === false) {
                return $array_fallback ? [] : false;
            }

            $data = unserialize($content, ['allowed_classes' => $allowed_classes]);
            if ($data === false) {
                return $array_fallback ? [] : false;
            }

            return $data;
        } catch (Exception $e) {
            return $array_fallback ? [] : false;
        }
    }
}
*/
?>
