<?php
include "config.inc.php";
include ("$file_newsportal");
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
    foreach ($nocem_list as $nocem_line) {
        if (strpos($nocem_line, $begin) !== false) {
            $start = 1;
            continue;
        }
        if (strpos($nocem_line, $end) !== false) {
            break;
        }
        if ((isset($nocem_line[0]) && $nocem_line[0] == '<') && $start == 1) {
            $found = explode(' ', $nocem_line);
            $msgid = $found[0];
            foreach ($found as $found_group) {
                delete_message($msgid, $found_group);
            }
        }
    }
    rename($nocem_file, $nocem_path . "processed/" . $message);
}
prune_dir_by_days($nocem_path . "processed/", 30);
prune_dir_by_days($nocem_path . "failed/", 30);
unlink($lockfile);
exit();

function delete_message($messageid, $group)
{
    global $logfile, $config_dir, $spooldir, $CONFIG, $webserver_group;

    /* Find section */
    $menulist = file($config_dir . "menu.conf", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($menulist as $menu) {
        if ($menu[0] == '#') {
            continue;
        }
        $menuitem = explode(':', $menu);
        $glfp = fopen($config_dir . $menuitem[0] . "/groups.txt", 'r');
        $section = "";
        while ($gl = fgets($glfp)) {
            $group_name = preg_split("/( |\t)/", $gl, 2);
            if (strtolower(trim($group)) == strtolower(trim($group_name[0]))) {
                $config_name = $menuitem[0];
                file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " FOUND: " . $messageid . " IN: " . $config_name . '/' . $group, FILE_APPEND);
                break 2;
            }
        }
    }

    if ($CONFIG['article_database'] == '1') {
        $database = $spooldir . '/' . $group . '-articles.db3';
        if (is_file($database)) {
            $articles_dbh = article_db_open($database);
            $articles_query = $articles_dbh->prepare('DELETE FROM articles WHERE msgid=:messageid');
            $articles_query->execute([
                'messageid' => $messageid
            ]);
            $articles_dbh = null;
        }
    }

    // Handle overview and history
    $database = $spooldir . '/articles-overview.db3';
    $dbh = overview_db_open($database);
    $stmt_del = $dbh->prepare('DELETE FROM overview WHERE newsgroup=:newsgroup AND msgid=:msgid');
    $query = $dbh->prepare('SELECT * FROM overview WHERE newsgroup=:newsgroup AND msgid=:msgid');
    $query->execute([
        ':newsgroup' => $group,
        ':msgid' => $messageid
    ]);
    $grouppath = preg_replace('/\./', '/', $group);
    $status = "deleted";
    $statusdate = time();
    $statusreason = "nocem";
    $statusnotes = null;
    while ($row = $query->fetch()) {
        if (is_file($spooldir . '/articles/' . $grouppath . '/' . $row['number'])) {
            unlink($spooldir . '/articles/' . $grouppath . '/' . $row['number']);
        }
        delete_message_from_overboard($config_name, $group, $messageid);
        add_to_history($group, $row['number'], $row['msgid'], $status, $statusdate, $statusreason, $statusnotes);
        thread_cache_removearticle($group, $row['number']);
    }
    $stmt_del->execute([
        ':newsgroup' => $group,
        ':msgid' => $messageid
    ]);
    $dbh = null;
    return;
}

function delete_message_from_overboard($config_name, $group, $messageid)
{
    GLOBAL $spooldir;
    $cachefile = $spooldir . "/" . $config_name . "-overboard.dat";
    if (is_file($cachefile)) {
        $cached_overboard = unserialize(file_get_contents($cachefile));
        if ($target = $cached_overboard['msgids'][$messageid]) {
            unset($cached_overboard['threads'][$target['date']]);
            unset($cached_overboard['msgids'][$messageid]);
            unset($cached_overboard['threadlink'][$messageid]);
            file_put_contents($cachefile, serialize($cached_overboard));
        }
    }
    $cachefile = $spooldir . "/" . $group . "-overboard.dat";
    if (is_file($cachefile)) {
        $cached_overboard = unserialize(file_get_contents($cachefile));
        if ($target = $cached_overboard['msgids'][$messageid]) {
            unset($cached_overboard['threads'][$target['date']]);
            unset($cached_overboard['msgids'][$messageid]);
            unset($cached_overboard['threadlink'][$messageid]);
            file_put_contents($cachefile, serialize($cached_overboard));
        }
    }
}
?>
