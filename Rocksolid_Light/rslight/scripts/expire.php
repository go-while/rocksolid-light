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

$webserver_group = $CONFIG['webserver_user'];
$logfile = $logdir . '/expire.log';

$grouplist = file($config_dir . '/' . $config_name . '/groups.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($grouplist as $groupline) {
    $groupname = explode(' ', $groupline);
    $group = $groupname[0];
    if ($group[0] == ':') {
        continue;
    }
    // Delete over $max_articles_per_group if so configured in $OVERRIDES
    $expire_conf = $CONFIG['expire_days'];
    $override_days = convert_max_articles_to_days($group);
    $expire_user = get_config_value('expire.conf', $group);
    /*
     * Order of preference is:
     * 1. value in $config_dir/expire.conf
     * 2. value in section config file OR $config_dir/overrides.inc.php
     * whichever is lower
     */
    $expire = $expire_conf;
    if ($override_days) {
        $expire = $override_days;
    }
    if ($expire_user !== false) {
        $expire = $expire_user;
    }
    $expire = trim($expire);
    if ($expire < 1) {
        vacuum_group_database($group);
        continue;
    }
    $expireme = time() - ($expire * 86400);
    $showme = date('d M, Y', $expireme);

    echo "Expire $group articles before " . $showme . " (" . $expire . ") days\n";
    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " " . $group . " Expiring articles before " . $showme . " (" . $expire . ") days", FILE_APPEND);
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
    $query = $dbh->prepare('SELECT number FROM overview WHERE newsgroup=:newsgroup AND CAST(date AS int)<:expireme');
    $query->execute([
        ':newsgroup' => $group,
        ':expireme' => $expireme
    ]);
    $get_row = array();
    while ($query_row = $query->fetch()) {
        $get_row[] = $query_row;
    }
    $stmt = $dbh->prepare('DELETE FROM overview WHERE newsgroup=:newsgroup AND CAST(date AS int)<:expireme');
    $grouppath = preg_replace('/\./', '/', $group);
    $status = "deleted";
    $statusdate = time();
    $statusreason = "expired";
    $i = 0;
    foreach ($get_row as $row) {
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
        // Delete any extraneous articles from group-articles database
        $articles_stmt = $articles_dbh->prepare('DELETE FROM articles WHERE newsgroup=:newsgroup AND CAST(date AS int)<:expireme');
        $articles_stmt->execute([
            ':newsgroup' => $group,
            ':expireme' => $expireme
        ]);
        $articles_dbh = null;
    }
    unlink($lockfile);
    touch($spooldir . '/' . $config_name . '-expire-timer');
    if ($i > 50) {
        echo "Rebuilding threads for " . $group . "...\n";
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " " . $group . " Rebuilding threads...", FILE_APPEND);
        unlink($spooldir . '/' . $group . '/-info.txt');
        $ns = nntp_open();
        if (! $ns) {
            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Failed to connect to " . $CONFIG['remote_server'] . ":" . $CONFIG['remote_port'], FILE_APPEND);
            exit();
        }
        thread_load_newsserver($ns, $group, 0);
    }
    vacuum_group_database($group);
    echo "Expired " . $i . " articles for " . $group . "\n";
    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " " . $group . " Expired " . $i . " articles", FILE_APPEND);
}

function vacuum_group_database($group)
{
    global $spooldir, $logfile, $config_name, $OVERRIDES, $CONFIG;
    if ($CONFIG['article_database'] == '1') {
        $database = $spooldir . '/' . $group . '-articles.db3';
        if ($article_dbh = article_db_open($database)) {
            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " " . $group . " VACUUM article database...", FILE_APPEND);
            $article_stmt = $article_dbh->prepare('VACUUM');
            $article_stmt->execute();
            $article_dbh = null;
        }
    }
    $database = $spooldir . '/' . $group . '-data.db3';
    if ($data_dbh = threads_db_open($database)) {
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " " . $group . " VACUUM threads database...", FILE_APPEND);
        $data_stmt = $data_dbh->prepare('VACUUM');
        $data_stmt->execute();
        $data_dbh = null;
    }
    // Check for moderation flag here. Yes, in vacuum.
    is_moderated($group);
    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " " . $group . " Checked for moderation flag", FILE_APPEND);
}

function convert_max_articles_to_days($group)
{
    global $spooldir, $OVERRIDES, $CONFIG;
    if ($OVERRIDES['max_articles_per_group'] > 0) {
        $count = $OVERRIDES['max_articles_per_group'];
    } else {
        return false;
    }
    $database = $spooldir . '/articles-overview.db3';
    $overview_dbh = overview_db_open($database);
    $overview_query = $overview_dbh->prepare('SELECT * FROM overview WHERE newsgroup=:newsgroup ORDER BY CAST(date AS int) DESC LIMIT :count');
    $overview_query->execute([
        ':newsgroup' => $group,
        ':count' => $count
    ]);
    $i = 0;
    while ($row = $overview_query->fetch()) {
        $i ++;
        if ($i == $count) {
            $found = $row;
        }
    }
    $overview_dbh = null;
    if ($found) {
        $days = ((time() - $found['date']) / 86400);
        return (round($days));
    } else {
        return false;
    }
}