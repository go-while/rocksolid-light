<?php
// This file runs maintenance scripts and should be executed by cron regularly
include "config.inc.php";
include "newsportal.php";
include $config_dir . "/scripts/rslight-lib.php";
include $config_dir . "/gpg.conf";

$pid = getmypid();
$logfile = $logdir . '/cron.log';
if (file_exists($config_dir . '/cron.disable') || file_exists($spooldir . '/cron.disable')) {
    file_put_contents($logfile, "\n" . date('M d H:i:s') . " " . $config_name . " cron.php disabled by semaphore: cron.disable Exiting...", FILE_APPEND);
    chown($logfile, $CONFIG['webserver_user']);
    exit();
} else {
    file_put_contents($logfile, "\n" . date('M d H:i:s') . " " . $config_name . " cron ".$pid." started...", FILE_APPEND);
    chown($logfile, $CONFIG['webserver_user']);
}

$menulist = file($config_dir . "menu.conf", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

# Start or verify NNTP server
if (isset($CONFIG['enable_nntp']) && $CONFIG['enable_nntp'] == true) {
    # Create group list for nntp.php
    $fp1 = $spooldir . "/" . $config_name . "/groups.txt";
    unlink($fp1);
    touch($fp1);
    foreach ($menulist as $menu) {
        if (($menu[0] == '#') || trim($menu) == "") {
            continue;
        }
        $menuitem = explode(':', $menu);
        if ($menuitem[2] == '1') {
            $in_gl = file($config_dir . $menuitem[0] . "/groups.txt");
            foreach ($in_gl as $ok_group) {
                if (($ok_group[0] == ':') || (trim($ok_group) == "")) {
                    continue;
                }
                $ok_group = preg_split("/( |\t)/", trim($ok_group), 2);
                file_put_contents($fp1, $ok_group[0] . "\r\n", FILE_APPEND);
            }
        }
    }
    exec($CONFIG['php_exec'] . " " . $config_dir . "/scripts/nntp.php > /dev/null 2>&1");
    if (is_numeric($CONFIG['local_ssl_port'])) {
        exec($CONFIG['php_exec'] . " " . $config_dir . "/scripts/nntp-ssl.php > /dev/null 2>&1");
    }
}
# Generate user count file (must be root)
exec($CONFIG['php_exec'] . " " . $config_dir . "/scripts/count_users.php");
echo "Updated user count\n";

$uinfo = posix_getpwnam($CONFIG['webserver_user']);
$cwd = getcwd();
$webtmp = preg_replace('/spoolnews/', 'tmp/', $cwd);
$keydir = preg_replace('/spoolnews/', 'pubkey/', $cwd);

@mkdir($webtmp, 0755, 'recursive');
@chown($webtmp, $uinfo["uid"]);
@chgrp($webtmp, $uinfo["gid"]);
@mkdir($keydir, 0755, 'recursive');
@chown($keydir, $uinfo["uid"]);
@chgrp($keydir, $uinfo["gid"]);
@mkdir($ssldir, 0755);
@chown($ssldir, $uinfo["uid"]);
@chgrp($ssldir, $uinfo["gid"]);

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

reset($menulist);
foreach ($menulist as $menu) {
    if (($menu[0] == '#') || (trim($menu) == "")) {
        continue;
    }
    $menuitem = explode(':', $menu);
    chdir("../" . $menuitem[0]);
    if ($CONFIG['remote_server'] !== '') {
        # Send articles
        echo "Sending articles\n";
        echo exec($CONFIG['php_exec'] . " " . $config_dir . "/scripts/send.php");
        # Refresh spool
        if (isset($spoolnews) && ($spoolnews == true)) {
            exec($CONFIG['php_exec'] . " " . $config_dir . "/scripts/spoolnews.php");
            echo "\nRefreshed spoolnews\n";
        }
    }
    # Expire articles
    exec($CONFIG['php_exec'] . " " . $config_dir . "/scripts/expire.php");
    echo "Expired articles\n";
}

# Run RSS Feeds
exec($CONFIG['php_exec'] . " " . $config_dir . "/scripts/rss-feeds.php");
echo "RSS Feeds updated\n";
# Rotate log files
log_rotate();
echo "Log files rotated\n";
# Rotate keys
rotate_keys();
echo "Keys rotated\n";
# Expire files
expire_files();
echo "Removed old files\n";
file_put_contents($logfile, "\n" . date('M d H:i:s') . " " . $config_name . " cron ".$pid." completed...", FILE_APPEND);

function expire_files() {
    global $spooldir, $logdir;
    $now = time();
    // Days to prune
    $nocemdays = 7;
    // Days to seconds from now
    $nocem = $now - ($nocemdays * 86400);
    // Dirs to prune
    $nocem_processed = $spooldir . "/nocem/processed/";
    $nocem_failed = $spooldir . "/nocem/failed/";
    
    // $nocem_processed
    $filenames = array_diff(scandir($nocem_processed), array(
        '..',
        '.'
    ));
    foreach($filenames as $one) {
        if(filemtime($nocem_processed.$one) < $nocem) {
            unlink($nocem_processed.$one);
        }
    }
    
    // $nocem_failed
    $filenames = array_diff(scandir($nocem_failed), array(
        '..',
        '.'
    ));
    foreach($filenames as $one) {
        if(filemtime($nocem_failed.$one) < $nocem) {
            unlink($nocem_failed.$one);
        }
    }
    
}

function log_rotate()
{
    global $logdir;
    $rotate = filemtime($logdir . '/rotate');
    if ((time() - $rotate) > 86400) {
        $log_files = scandir($logdir);
        foreach ($log_files as $logfile) {
            if (substr($logfile, - 4) != '.log') {
                continue;
            }
            $logfile = $logdir . '/' . $logfile;
            @unlink($logfile . '.5');
            @rename($logfile . '.4', $logfile . '.5');
            @rename($logfile . '.3', $logfile . '.4');
            @rename($logfile . '.2', $logfile . '.3');
            @rename($logfile . '.1', $logfile . '.2');
            @rename($logfile, $logfile . '.1');
            echo 'Rotated: ' . $logfile . "\n";
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
            $keys = unserialize(file_get_contents($keyfile));
            $new = false;
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

function change_identity($uid, $gid)
{
    if (! posix_setgid($gid)) {
        print "Unable to setgid to " . $gid . "!\n";
        exit();
    }

    if (! posix_setuid($uid)) {
        print "Unable to setuid to " . $uid . "!\n";
        exit();
    }
}
?>
