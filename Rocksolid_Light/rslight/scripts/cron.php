<?php
define('CRON_CONTEXT', true); // Global constant to indicate down the line: this is a cron job context

// This file runs maintenance scripts and should be executed by cron regularly
include "../lib/config.inc.php";

// Include newsportal with dynamic path calculation
$web_root = null;
if (file_exists("../rslight.inc.php")) {
    $rslight_path = readlink("../rslight.inc.php");

    if ($rslight_path) {
        $web_root = dirname(dirname(dirname($rslight_path))); // Go up 3 levels
    }
}

echo "[cron.php rslight_path: " . $rslight_path . " web_root=$web_root]<br>\n";

if ($web_root && file_exists($web_root . "/rocksolid/newsportal.php")) {
    echo "[cron.php included newsportal.php from web_root=$web_root]<br>\n";
    include $web_root . "/rocksolid/newsportal.php";
    echo "[cron.php included newsportal.php from web_root<br>]\n";
} else {
    die("Error: Could not locate newsportal.php web_root=$web_root");
}

echo "[CRON] Debug 0 cron config_name: " . $config_name . "\n";

include $config_dir . "/scripts/rslight-lib.php";
include $config_dir . "/gpg.conf";

// Ensure critical variables are set with fallbacks
if (!isset($logdir) || empty($logdir)) {
    $logdir = $spooldir . '/log';
    @mkdir($logdir, 0755, true);
}
if (!isset($config_name) || empty($config_name)) {
    $config_name = 'rslight';
}

echo "[CRON] Debug 1 cron config_name: " . $config_name . "\n";

$pid = getmypid();
$logfile = $logdir . '/cron.log';
if (file_exists($config_dir . '/cron.disable') || file_exists($spooldir . '/cron.disable')) {
    file_put_contents($logfile, "\n" . date('M d H:i:s') . " " . $config_name . " cron.php disabled by semaphore: cron.disable Exiting...", FILE_APPEND);
    chown($logfile, $CONFIG['webserver_user']);
    exit();
} else {
    file_put_contents($logfile, "\n" . date('M d H:i:s') . " " . $config_name . " cron " . $pid . " started...", FILE_APPEND);
    chown($logfile, $CONFIG['webserver_user']);
}

echo "[CRON] DEBUG 2 cron\n";

$menulist = get_section_menu_array();
# Start or verify NNTP server
if (isset($CONFIG['enable_nntp']) && $CONFIG['enable_nntp'] == true) {
    # Create group list for nntp.php
    $fp1 = $spooldir . "/" . $config_name . "/groups.txt";
    unlink($fp1);
    touch($fp1);
    $group_exists = array();
    foreach ($menulist as $menu) {
        $menuitem = explode(':', $menu);
        if ($menuitem[2] == '1') {
            $in_gl = file($config_dir . $menuitem[0] . "/groups.txt");
            foreach ($in_gl as $ok_group) {
                if (($ok_group[0] == ':') || (trim($ok_group) == "")) {
                    continue;
                }
                $entry = preg_split("/( |\t)/", trim($ok_group), 2);
                if (!isset($group_exists[$entry[0]])) {
                    file_put_contents($fp1, $entry[0] . "\n", FILE_APPEND);
                }
                $group_exists[$entry[0]] = true;
            }
        }
    }

    $disabled_php = ini_get('disable_functions');
    echo $disabled_php;
    if (strpos($disabled_php, 'pcntl_fork') !== false) {
        echo "\nERROR: pcntl_fork() disabled in php ini file, cannot fork (nntp server will not start).";
        file_put_contents($logfile, "\n" . format_log_date() . " ERROR: pcntl_fork() disabled in php ini file, cannot fork (nntp server will not start).", FILE_APPEND);
    } else {
        exec($CONFIG['php_exec'] . " " . $config_dir . "/scripts/nntp.php > /dev/null 2>&1");
        if (is_numeric($CONFIG['local_ssl_port'])) {
            exec($CONFIG['php_exec'] . " " . $config_dir . "/scripts/nntp-ssl.php > /dev/null 2>&1");
        }
    }
}

// Create paths in $config_dir/scripts path file
$config_path_file = $config_dir . '/scripts/paths.inc.php';
if (!file_exists($config_path_file)) {
    file_put_contents($config_path_file, '<?php' . "\n");
    file_put_contents($config_path_file, '$spoolnews_path = "' . getcwd() . '";', FILE_APPEND);
}

# Generate user count file (must be root)
exec($CONFIG['php_exec'] . " " . $config_dir . "/scripts/count_users.php");
echo "[CRON] Updated user count\n";

$uinfo = posix_getpwnam($CONFIG['webserver_user']);
$cwd = getcwd();

// Check permissions on some files
$webtmp = preg_replace('/spoolnews/', 'tmp/', $cwd);
$keydir = preg_replace('/spoolnews/', 'pubkey/', $cwd);

$banfile = $config_dir . '/banned_users.conf';
@chown($banfile, $uinfo["uid"]);

@mkdir($webtmp, 0755, 'recursive');
@chown($webtmp, $uinfo["uid"]);
@chgrp($webtmp, $uinfo["gid"]);
@mkdir($keydir, 0755, 'recursive');
@chown($keydir, $uinfo["uid"]);
@chgrp($keydir, $uinfo["gid"]);
@mkdir($ssldir, 0755);
@chown($ssldir, $uinfo["uid"]);
@chgrp($ssldir, $uinfo["gid"]);

$alias_file = $config_dir . '/aliases.conf';
if (! file_exists($alias_file)) {
    touch($alias_file);
}
@chown($alias_file, $uinfo["uid"]);
@chgrp($alias_file, $uinfo["gid"]);

$pemfile = $ssldir . '/server.pem';
create_node_ssl_cert($pemfile);

$overview = $spooldir . '/articles-overview.db3';
touch($overview);
@chown($overview, $uinfo["uid"]);
@chgrp($overview, $uinfo["gid"]);

if ($rslight_gpg['enable'] == '1') {
    $gnupg = $rslight_gpg['gnupghome'];
    if (! is_dir($gnupg)) {
        mkdir($gnupg, 0700);
        chown($gnupg, $uinfo["uid"]);
        chgrp($gnupg, $uinfo["gid"]);
    }
}
/* Change to non root user */
change_identity($uinfo["uid"], $uinfo["gid"]);
/* Everything below runs as $CONFIG['webserver_user'] */

@mkdir($logdir, 0755, 'recursive');
@mkdir($lockdir, 0755, 'recursive');

if (isset($CONFIG['enable_nocem']) && $CONFIG['enable_nocem'] == true) {
    @mkdir($spooldir . "nocem", 0755, 'recursive');
    exec($CONFIG['php_exec'] . " " . $config_dir . "/scripts/nocem.php");
}
// Set up server gpg keys
if ($rslight_gpg['enable'] == '1') {
    if (! is_file($keydir . '/server_pubkey.txt')) {
        $domain = 'rslight@' . $rslight_gpg['domain_name'];
        $pubkey = $keydir . '/server_pubkey.txt';
        $fingerprint = $keydir . '/server_fingerprint.txt';
        $create_gpg_keys = $config_dir . '/scripts/create_gpg_keys.sh "' . $gnupg . '" "' . $pubkey . '" "' . $fingerprint . '" "' . $domain . '"';
        exec($create_gpg_keys);
    }
    exec($CONFIG['php_exec'] . " " . $config_dir . "/scripts/interBBS_mail.php");
}

check_disk_space();

reset($menulist);
foreach ($menulist as $menu) {
    if (($menu[0] == '#') || (trim($menu) == "")) {
        continue;
    }
    $menuitem = explode(':', $menu);
    chdir("../" . $menuitem[0]);

    $this_config_name = $menuitem[0];
    if(file_exists($config_dir.$this_config_name.'.inc.php')) {
      $config_file = $config_dir.$this_config_name.'.inc.php';
    } else {
      $config_file = $config_dir.'rslight.inc.php';
    }

    $this_CONFIG = include($config_file);
    file_put_contents($debug_log, "\n" . format_log_date() . " Using " . $config_file . " for " . $menuitem[0], FILE_APPEND);

    if ($this_CONFIG['remote_server'] !== '') {
        # Send articles
        echo "[CRON] Sending articles\n";
        echo exec($CONFIG['php_exec'] . " " . $config_dir . "/scripts/send.php");
        # Refresh spool
        if (isset($spoolnews) && ($spoolnews == true)) {
            file_put_contents($debug_log, "\n" . format_log_date() . " DEBUG: Starting spoolnews for " . $menuitem[0], FILE_APPEND);
            exec($CONFIG['php_exec'] . " " . $config_dir . "/scripts/spoolnews.php");
            file_put_contents($debug_log, "\n" . format_log_date() . " DEBUG: Completed spoolnews for " . $menuitem[0], FILE_APPEND);
            echo "\n[CRON] Refreshed spoolnews\n";
        }
    } else {
        file_put_contents($debug_log, "\n" . format_log_date() . " Remote disabled for " . $menuitem[0] . " (no remote server)", FILE_APPEND);
    }
    # Expire articles
    exec($CONFIG['php_exec'] . " " . $config_dir . "/scripts/expire.php");
    echo "[CRON] Expired articles\n";
}

# Expire diskcache
if (file_exists($config_dir . '/cache.inc.php')) {
    include $config_dir . '/cache.inc.php';
    if ($enable_cache == 'diskcache') {
        prune_dir_by_days($cache_dir, $cache_ttl / 86400);
    }
}

# Run RSS Feeds
exec($CONFIG['php_exec'] . " " . $config_dir . "/scripts/rss-feeds.php");
echo "[CRON] RSS Feeds updated\n";
# Reload grouplist
if ((filemtime($grouplist_cache_filename) < (time() - 14400) || ! file_exists($grouplist_cache_filename))) {
    exec($CONFIG['php_exec'] . " ../common/grouplist.php .RELOAD");
    echo "[CRON] Refreshed grouplist\n";
}
# Rotate log files
log_rotate();
echo "[CRON] Log files rotated\n";
# Rotate keys
rotate_keys();
echo "[CRON] Keys rotated\n";
# Expire files
expire_files();
echo "[CRON] Removed old files\n";
file_put_contents($logfile, "\n" . date('M d H:i:s') . " " . $config_name . " cron " . $pid . " completed...", FILE_APPEND);

function check_disk_space()
{
    global $CONFIG, $OVERRIDES, $logdir, $spooldir;
    global $low_spool_disk_space, $min_spool_disk_space, $free_spool_disk_space;
    $logfile = $logdir . '/spoolnews.log';

    $warning_spool_disk_space = $min_spool_disk_space * 1.1;

    if ($free_spool_disk_space < $warning_spool_disk_space) {
        $nearing_low_spool_disk_space = true;
    } else {
        $nearing_low_spool_disk_space = false;
    }

    if ($nearing_low_spool_disk_space) {
        if ($low_spool_disk_space) { // Disk space low. Spooling will pause
            print "Low Disk Space (less than " . $min_spool_disk_space . " available)\n";
            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Low Disk Space (less than " . $min_spool_disk_space . "Gb available for spool). Pausing spoolnews", FILE_APPEND);

            $subject = "LOW DISK SPACE ON " . $CONFIG['server_path'];
            $body = "LOW DISK SPACE ON " . $CONFIG['server_path'] . "\n";
            $body .= "Space has fallen below " . $min_spool_disk_space . "GB\n";
            $body .= "Space remaining: " . round($free_spool_disk_space) . "GB\n";
            $timer_type = "low_spool_disk_space";
        } else { // Disk space approaching low (within 10%) This is a warning only
            print "Nearing Low Disk Space (less than " . round($warning_spool_disk_space, 1) . "Gb available for spool)\n";
            file_put_contents($logfile, "\n" . format_log_date() . " Nearing Low Disk Space (less than " . round($warning_spool_disk_space, 1) . "Gb available for spool)", FILE_APPEND);

            $subject = "NEARING LOW DISK SPACE ON " . $CONFIG['server_path'];
            $body = "NEARING LOW DISK SPACE ON " . $CONFIG['server_path'] . "\n";
            $body .= "Space has fallen below " . round($warning_spool_disk_space) . "GB\n";
            $body .= "Space remaining: " . round($free_spool_disk_space) . "GB\n\n";
            $body .= "Spooling will pause when space below " . $min_spool_disk_space . "GB\n";
            $timer_type = "nearing_low_spool_disk_space";
        }

        if ($OVERRIDES['send_admin_debug_messages'] === true) {
            $date_window = 86400;
            $send_email_timer_file = $spooldir . '/email_send_timer.dat';
            if (file_exists($send_email_timer_file)) {
                try {
                    $send_email_timer = secure_unserialize($send_email_timer_file);
                    if (!is_array($send_email_timer)) {
                        $send_email_timer = array();
                    }
                } catch (Exception $e) {
                    $send_email_timer = array();
                }
            } else {
                $send_email_timer = array();
            }
            if (! isset($send_email_timer[$timer_type])) {
                $send_email_timer[$timer_type] = 0;
            }
            if ($send_email_timer[$timer_type] < (time() - $date_window)) {
                if ($send_email_timer[$timer_type] != 0) {
                    $send_email_timer[$timer_type] = 0;
                } else {
                    $send_email_timer[$timer_type] = time();
                }
                send_internet_email($subject, $body);
            }
            file_put_contents($send_email_timer_file, serialize($send_email_timer));
        }
    }
}

function expire_files()
{
    global $spooldir, $logdir, $uinfo;
    $now = time();
    // Days to prune
    $nocemdays = 7;
    // Days to seconds from now
    $nocem = $now - ($nocemdays * 86400);
    // Dirs to prune
    $nocem_processed = $spooldir . "/nocem/processed/";
    $nocem_failed = $spooldir . "/nocem/failed/";
    if (! is_dir($nocem_processed)) {
        @mkdir($nocem_processed, 0755, 'recursive');
        @chown($nocem_processed, $uinfo["uid"]);
        @chgrp($nocem_processed, $uinfo["gid"]);
    }
    if (! is_dir($nocem_failed)) {
        @mkdir($nocem_failed, 0755, 'recursive');
        @chown($nocem_failed, $uinfo["uid"]);
        @chgrp($nocem_failed, $uinfo["gid"]);
    }

    // $nocem_processed
    $filenames = array_diff(scandir($nocem_processed), array(
        '..',
        '.'
    ));
    foreach ($filenames as $one) {
        if (filemtime($nocem_processed . $one) < $nocem) {
            unlink($nocem_processed . $one);
        }
    }

    // $nocem_failed
    $filenames = array_diff(scandir($nocem_failed), array(
        '..',
        '.'
    ));
    foreach ($filenames as $one) {
        if (filemtime($nocem_failed . $one) < $nocem) {
            unlink($nocem_failed . $one);
        }
    }
}

function log_rotate()
{
    global $logdir;
    $rotate_days = 7;
    $rotate = filemtime($logdir . '/rotate');
    if ((time() - $rotate) > ($rotate_days * 86400)) {
        $log_files = scandir($logdir);
        foreach ($log_files as $logfile) {
            if (substr($logfile, -4) != '.log') {
                continue;
            }
            $logfile = $logdir . '/' . $logfile;
            @unlink($logfile . '.5');
            @rename($logfile . '.4', $logfile . '.5');
            @rename($logfile . '.3', $logfile . '.4');
            @rename($logfile . '.2', $logfile . '.3');
            @rename($logfile . '.1', $logfile . '.2');
            file_put_contents($logfile, "\nLog file rotated", FILE_APPEND);
            @rename($logfile, $logfile . '.1');
            echo '[CRON] Rotated: ' . $logfile . "\n";
        }
        unlink($logdir . '/rotate');
        touch($logdir . '/rotate');
    }
}

function rotate_keys()
{
    global $spooldir;
    $keyfile = $spooldir . '/keys.dat';
    $newkeys = array();
    if (filemtime($keyfile) + 14400 > time()) {
        return;
    } else {
        $new = true;
        if (is_file($keyfile)) {
            try {
                $keys = secure_unserialize($keyfile);
                if (!is_array($keys)) {
                    $keys = array();
                    $new = true;
                } else {
                    $new = false;
                }
            } catch (Exception $e) {
                $keys = array();
                $new = true;
            }
        }
        if ($new !== true) {
            $newkeys[0] = base64_encode(openssl_random_pseudo_bytes(44));
            $newkeys[1] = $keys[0];
        } else {
            $newkeys[0] = base64_encode(openssl_random_pseudo_bytes(44));
            $newkeys[1] = base64_encode(openssl_random_pseudo_bytes(44));
        }
    }
    file_put_contents($keyfile, serialize($newkeys));
    touch($keyfile);
}
