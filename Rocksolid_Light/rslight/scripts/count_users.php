<?php
include "config.inc.php";
include ("$file_newsportal");
if (trim($CONFIG['tac'] == '')) {
    if (is_file($spooldir . '/sessions.dat')) {
        unlink($spooldir . '/sessions.dat');
    }
    exit(0);
}
count_users();

function count_articles()
{
    GLOBAL $CONFIG, $spooldir;
    $database = $spooldir . '/articles-overview.db3';
    $dbh = overview_db_open($database);
    $count = $dbh->query('SELECT COUNT(DISTINCT msgid) FROM overview')->fetchColumn();
    $dbh = null;
    return $count;
}

function count_users()
{
    GLOBAL $CONFIG, $spooldir;
    $session_age = 600;
    $session_save_file = $spooldir . '/sessions.dat';
    $session_dir = $CONFIG['tac'];
    $session_files = scandir($session_dir);
    $count = 0;
    $bot_count = 0;
    foreach ($session_files as $session_file) {
        if (filemtime($session_dir . '/' . $session_file) < time() - $session_age) {
            continue;
        }
        if (strpos($session_file, 'sess_') === 0) {
            $contents = file_get_contents($session_dir . '/' . $session_file);
            if (strpos($contents, 'rsactive') !== false) {
                $count ++;
            }
            if (strpos($contents, 'bot') !== false) {
                $bot_count ++;
            }
        }
    }
    if ($count == 1) {
        $are = 'is';
        $users = 'user';
    } else {
        $are = 'are';
        $users = 'users';
    }
    if ($bot_count == 1) {
        $bot_are = 'is';
        $bot_users = 'bot';
    } else {
        $bot_are = 'are';
        $bot_users = 'bots';
    }
    $session_info = '<h1 class="np_thread_headline">There ' . $are . ' currently ' . $count . ' ' . $users . ' online / plus ' . $bot_count . ' ' . $bot_users . '<br />Total messages: ' . number_format(count_articles()) . '</h1>' . "\r\n";
    file_put_contents($session_save_file, $session_info);
}
?>
