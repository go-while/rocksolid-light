<?php
/*
 * spoolnews NNTP news spool creator
 * Download: https://news.novabbs.com/getrslight
 *
 * E-Mail: retroguy@novabbs.com
 * Web: https://news.novabbs.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */
include "config.inc.php";
include("$file_newsportal");
include "spool-lib.php";
include $config_dir . '/gpg.conf';

if (isset($OVERRIDES['save_nocem_messages']) && $OVERRIDES['save_nocem_messages'] == true) {
    $save_nocem_messages = true;
    $nocem_dir = $spooldir . "/saved_nocem";
    @mkdir($nocem_dir . '/done', 0755, true);
} else {
    $save_nocem_messages = false;
}

$remote_groups_array_file = $spooldir . "/" . $config_name . "/" . $CONFIG['remote_server'] . ":" . $CONFIG['remote_port'] . "-remote_groups.dat";

$file_groups = $config_path . "groups.txt";
$logfile = $logdir . '/spoolnews.log';
$spamlog = $logdir . '/spam.log';

# END MAIN CONFIGURATION
@mkdir($spooldir . "/" . $config_name, 0755, 'recursive');

# Put this here for version 0.9.157 for a while to clean up dir
if (file_exists($spooldir . '/' . $config_name . '/local_groups.txt')) {
    $section_dir = $spooldir . '/' . $config_name . '/';
    @mkdir($section_dir . 'OLD');
    $files = scandir($section_dir);
    foreach ($files as $file) {
        $file_name = $section_dir . $file;
        if (is_file($file_name) && str_ends_with($file, ".txt")) {
            copy($file_name, $section_dir . 'OLD/' . $file);
            unlink($file_name);
        }
    }
}

// Defaults
$maxarticles_per_run = 100;
$maxfirstrequest = 100;

// Overrides
if ($OVERRIDES['maxarticles_per_run'] > 0) {
    $maxarticles_per_run = $OVERRIDES['maxarticles_per_run'];
}
if ($OVERRIDES['maxfirstrequest'] > 0) {
    $maxfirstrequest = $OVERRIDES['maxfirstrequest'];
}

if (! isset($CONFIG['enable_nntp']) || $CONFIG['enable_nntp'] != true) {
    $maxfirstrequest = $maxarticles;
    $maxarticles_per_run = $maxfetch;
}

$workpath = $spooldir . "/";
$path = $workpath . "articles/";

if ($low_spool_disk_space) {
    print "Low Disk Space (less than " . $min_spool_disk_space . " available)\n";
    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Low Disk Space (less than " . $min_spool_disk_space . "Gb available for spool). Pausing spoolnews", FILE_APPEND);

    $subject = "LOW DISK SPACE ON " . $CONFIG['server_path'];
    $body = "LOW DISK SPACE ON " . $CONFIG['server_path'] . "\n";
    $body .= "Space has fallen below " . $min_spool_disk_space . "GB\n";
    $body .= "Space remaining: " . round($free_spool_disk_space) . "GB\n";

    exit();
}

$lockfile = $lockdir . '/' . $config_name . '-spoolnews.lock';
$pid = file_get_contents($lockfile);
if (posix_getsid($pid) === false || ! is_file($lockfile)) {
    print "Starting Spoolnews...\n";
    file_put_contents($lockfile, getmypid()); // create lockfile
} else {
    print "Spoolnews currently running\n";
    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Spoolnews currently running...", FILE_APPEND);
    exit();
}

$sem = $spooldir . "/" . $config_name . ".reload";
if (is_file($sem)) {
    unlink($remote_groups_array_file);
    unlink($sem);
    $maxfirstrequest = 200;
}

# Iterate through groups
$enable_rslight = 0;
# Refresh group list
$menulist = get_section_menu_array();
foreach ($menulist as $menu) {
    if (($menu[0] == '#') || (trim($menu) == "")) {
        continue;
    }
    $menuitem = explode(':', $menu);
    if (($menuitem[0] == $config_name) && ($menuitem[1] == '1')) {
        groups_read($server, $port, 1, true); // 'true' forces a refresh of the group list
        $enable_rslight = 1;
        echo "\nLoaded groups";
    }
}

# Clean outgoing directory for LOCAL sections
if ($CONFIG['remote_server'] == '') {
    $outgoing_dir = $spooldir . "/" . $config_name . "/outgoing/";
    $files = scandir($outgoing_dir);
    foreach ($files as $file) {
        $file_name = $outgoing_dir . $file;
        if (is_file($file_name) && (filemtime($file_name) < (time() - 3600))) {
            unlink($file_name);
        }
    }
}
if ($CONFIG['remote_server'] != '') {
    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Connecting: " . $CONFIG['remote_server'] . ":" . $CONFIG['remote_port'], FILE_APPEND);
    $ns = nntp2_open($CONFIG['remote_server'], $CONFIG['remote_port']);
    if ($ns == false) {
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Failed to connect to " . $CONFIG['remote_server'] . ":" . $CONFIG['remote_port'], FILE_APPEND);
        exit();
    }
    $grouplist = file($config_dir . '/' . $config_name . '/groups.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($grouplist as $findgroup) {
        if ($findgroup[0] == ":") {
            continue;
        }
        $name = preg_split("/( |\t)/", $findgroup, 2);
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Retrieving articles for: " . $name[0] . "...", FILE_APPEND);
        echo "\nRetrieving articles for: " . $name[0] . "...";
        if ($ns == false) {
            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Lost connection to: " . $CONFIG['local_server'] . ":" . $CONFIG['local_port'], FILE_APPEND);
            break;
        }
        $groupstat = get_articles($ns, $name[0]);

        if ($enable_rslight == 1 && $groupstat != false) {
            $timer_file = $spooldir . '/tmp/' . $name[0] . '-thread-timer';
            if (filemtime($timer_file) + 600 < time()) {

                $ns_local = nntp_open();
                echo "\nOPENING Local server: " . $ns_local . "\n";
                file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " OPENING Local server: " . $ns_local, FILE_APPEND);

                if ($ns_local == false) {
                    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Failed to connect to " . $CONFIG['local_server'] . ":" . $CONFIG['local_port'], FILE_APPEND);
                    // exit();
                } else {
                    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Updating threads for: " . $name[0] . "...", FILE_APPEND);
                    try {
                        thread_load_newsserver($ns_local, $name[0], 0);
                    } catch (Exception $exc) {
                        echo "\nFatal exception caught: " . $exc->getMessage();
                        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Fatal exception caught: " . $exc->getMessage() . " trying to run thread_load_newsserver", FILE_APPEND);
                    } catch (Error $err) {
                        echo "\nFatal error caught: " . $err->getMessage();
                        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Fatal error caught: " . $err->getMessage() . " trying to run thread_load_newsserver", FILE_APPEND);
                    }
                    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Threads updated for: " . $name[0], FILE_APPEND);
                    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " CLOSING Local server: " . $ns_local, FILE_APPEND);
                    if ($ns_local != false) {
                        nntp_close($ns_local);
                    }
                }
                touch($timer_file);
            }
        }
    }
    nntp_close($ns);
}
unlink($lockfile);
echo "\nSpoolnews Done\n";