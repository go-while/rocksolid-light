<?php
include "config.inc.php";
include("$file_newsportal");
include $config_dir . "/gpg.conf";

if (! isset($CONFIG['enable_nocem']) || $CONFIG['enable_nocem'] != true) {
    exit();
}

$lockfile = $lockdir . '/rslight-spoolnews.lock';
$pid = file_get_contents($lockfile);
if (posix_getsid($pid) === false || ! is_file($lockfile)) {
    print "Starting nocem...\n";
    file_put_contents($lockfile, getmypid()); // create lockfile
} else {
    print "nocem currently running\n";
    exit();
}

putenv("GNUPGHOME=" . $rslight_gpg['gnupghome']);
$res = gnupg_init();

$webserver_group = $CONFIG['webserver_user'];
$logfile = $logdir . '/nocem.log';
@mkdir($spooldir . "/nocem/processed", 0755, 'recursive');
@mkdir($spooldir . "/nocem/failed", 0755, 'recursive');

$nocem_path = $spooldir . "/nocem/";
$messages = scandir($nocem_path);
$begin = "@@BEGIN NCM BODY";
$end = "@@END NCM BODY";

foreach ($messages as $message) {
    $nocem_file = $nocem_path . $message;
    if (! is_file($nocem_file)) {
        continue;
    }
    if (check_nocem_config($nocem_file) == true) {
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Good Issuer and Type for: " . $message, FILE_APPEND);
    } else {
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Bad Issuer or Type for: " . $message, FILE_APPEND);
        rename($nocem_file, $nocem_path . "failed/" . $message);
        continue;
    }
    $signed_text = file_get_contents($nocem_file);
    if (verify_gpg_signature($res, $signed_text) == 1) {
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Good signature in: " . $message, FILE_APPEND);
        echo "Good signature in: " . $message . "\r\n";
    } else {
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Bad signature in: " . $message, FILE_APPEND);
        echo "Bad signature in: " . $message . "\r\n";
        rename($nocem_file, $nocem_path . "failed/" . $message);
        continue;
    }
    $nocem_list = file($nocem_file, FILE_IGNORE_NEW_LINES);
    $start = 0;

    // Open overview database and process list
    $database = $spooldir . '/articles-overview.db3';
    $overview_dbh = overview_db_open($database);
    foreach ($nocem_list as $nocem_line) {
        if (strpos($nocem_line, $begin) !== false) {
            $start = 1;
            continue;
        }
        if (strpos($nocem_line, $end) !== false) {
            break;
        }
        if ($nocem_line[0] == '#') {
            continue;
        }

        if (($nocem_line[0] == '<') && $start == 1) {
            $found = preg_split("/[ \t]/", $nocem_line, 2);
            $allgroups = preg_split("/\ |\,/", $found[1]);
            foreach ($allgroups as $group_item) {
                if ($status = get_history_status($found[0], $group_item)) {
                    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " " . $found[0] . " appears as " . $status['status'] . ":" . $status['statusreason'] . " in history. Trying anyway...", FILE_APPEND);
                } else {
                    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " " . $found[0] . " not found in history database (this is not an error)", FILE_APPEND);
                }
                file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " TRYING: " . $found[0] . " IN: " . $group_item, FILE_APPEND);
                delete_message($found[0], trim($group_item), $overview_dbh);
            }
        }
    }
    $overview_dbh = null;

    rename($nocem_file, $nocem_path . "processed/" . $message);
    prune_dir_by_days($nocem_path . "processed/", 30);
    prune_dir_by_days($nocem_path . "failed/", 30);
}
unlink($lockfile);

function check_nocem_config($nocem_file)
{
    global $config_dir;
    $nocem_config = $config_dir . '/nocem.conf';
    $name_ok = false;
    $type_ok = false;
    $ncmhead = '@@BEGIN NCM HEADERS';
    $nocem_list = file($nocem_file, FILE_IGNORE_NEW_LINES);
    $headers = 0;
    foreach ($nocem_list as $nocem_line) {
        if (stripos($nocem_line, $ncmhead) == 0) {
            $headers = 1;
        }
        if ($headers != 1) {
            continue;
        }
        if (stripos($nocem_line, "Issuer: ") === 0) {
            $issuer = explode(': ', $nocem_line);
            $issuer = $issuer[1];
        }
        if (stripos($nocem_line, "Type: ") === 0) {
            $type = explode(': ', $nocem_line);
            $type = $type[1];
        }
    }
    $config_val = get_config_file_value($nocem_config, $issuer);
    if ($config_val === false) {
        return false;
    } else {
        $name_ok = true;
    }
    $all_types = explode(',', $config_val);
    foreach ($all_types as $one_type) {
        if (trim($type) == trim($one_type)) {
            echo $issuer . ':' . $type . " Good Type \n";
            $type_ok = true;
        } else {
            echo $issuer . ':' . $type . ' : ' . $one_type . " Bad Type \n";
        }
    }
    if ($type_ok && $name_ok) {
        return true;
    } else {
        return false;
    }
}
