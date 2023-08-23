<?php
include "config.inc.php";
include ("$file_newsportal");

if (filemtime($spooldir . '/' . $config_name . '-expire-timer') + 86400 > time()) {
    exit();
}
$lockfile = $lockdir . '/' . $config_name . '-spoolnews.lock';
$pid = file_get_contents($lockfile);
if (posix_getsid($pid) === false || ! is_file($lockfile)) {
    print "Starting expire...\n";
    file_put_contents($lockfile, getmypid()); // create lockfile
} else {
    print "expire currently running\n";
    exit();
}

// pcntl_setpriority(0);

$webserver_group = $CONFIG['webserver_user'];
$logfile = $logdir . '/expire.log';

$grouplist = file($config_dir . '/' . $config_name . '/groups.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($grouplist as $groupline) {
    $groupname = explode(' ', $groupline);
    $group = $groupname[0];
    if ($group[0] == ':') {
        continue;
    }
    $expire_conf = $CONFIG['expire_days'];
    $expire_user = get_config_value('expire.conf', $group);

    if ($expire_user !== false) {
        $expire = $expire_user;
    } else {
        $expire = $expire_conf;
    }
    if ($expire < 1) {
        continue;
    }
    $expireme = time() - ($expire * 86400);
    $showme = date('d M, Y', $expireme);

    echo "Expire $group articles before $showme\n";
    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " " . $group . " Expiring articles before " . $showme, FILE_APPEND);
    if ($CONFIG['article_database'] == '1') {
        $database = $spooldir . '/' . $group . '-articles.db3';
        if (is_file($database)) {
            $articles_dbh = article_db_open($database);
            $articles_stmt = $articles_dbh->prepare('DELETE FROM articles WHERE newsgroup=:newsgroup AND number=:number');
        }
    }
    // Expire tradspool and remove from newsportal
    echo "Expiring articles database, overview database and writing history...\n";
    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " " . $group . " Expiring articles database, overview database and writing history...", FILE_APPEND);

    $database = $spooldir . '/articles-overview.db3';
    $dbh = overview_db_open($database);
    $query = $dbh->prepare('SELECT number FROM overview WHERE newsgroup=:newsgroup AND date<:expireme');
    $query->execute([
        ':newsgroup' => $group,
        ':expireme' => $expireme
    ]);
    $stmt = $dbh->prepare('DELETE FROM overview WHERE newsgroup=:newsgroup AND date<:expireme');
    $grouppath = preg_replace('/\./', '/', $group);
    $status = "deleted";
    $statusdate = time();
    $statusreason = "expired";
    $i = 0;
    while ($row = $query->fetch()) {
        if (is_file($spooldir . '/articles/' . $grouppath . '/' . $row['number'])) {
            unlink($spooldir . '/articles/' . $grouppath . '/' . $row['number']);
        }
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " " . $group . " Expiring:" . $row['number'], FILE_APPEND);
        if ($CONFIG['article_database'] == '1') {
            try {
                $articles_dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $articles_stmt->execute([
                    ':newsgroup' => $group,
                    ':number' => $row['number']
                ]);
            } catch (Exception $e) {
                echo 'Caught exception: ' . $e->getMessage();
                file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " " . $group . " Caught exception: " . $e->getMessage(), FILE_APPEND);
            }
        }
        add_to_history($group, $row['number'], $row['msgid'], $status, $statusdate, $statusreason, $statusnotes);
        $i ++;
    }
    $stmt->execute([
        ':newsgroup' => $group,
        ':expireme' => $expireme
    ]);
    $dbh = null;
    if ($articles_dbh) {
        $articles_dbh = null;
    }
    unlink($lockfile);
    touch($spooldir . '/' . $config_name . '-expire-timer');
    echo "Expired " . $i . " articles for " . $group . "\n";
    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " " . $group . " Expired " . $i . " articles", FILE_APPEND);
}
?>
