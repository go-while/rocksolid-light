<?php
// Handle config include with flexible path resolution
if (file_exists("../lib/config.inc.php")) {
    include "../lib/config.inc.php";
} elseif (file_exists("config.inc.php")) {
    include "config.inc.php";
} elseif (file_exists("../config.inc.php")) {
    include "../config.inc.php";
} elseif (file_exists("common/config.inc.php")) {
    include "common/config.inc.php";
}

// Include newsportal with dynamic path calculation
$web_root = null;
if (file_exists("../rslight.inc.php")) {
    $rslight_path = readlink("../rslight.inc.php");
    if ($rslight_path) {
        $web_root = dirname(dirname(dirname($rslight_path))); // Go up 3 levels from /var/www/html/rocksolid/lib/
    }
}

// Try to include newsportal.php using calculated web root
if ($web_root && file_exists($web_root . "/rocksolid/newsportal.php")) {
    include $web_root . "/rocksolid/newsportal.php";
} elseif (isset($file_newsportal) && file_exists($file_newsportal)) {
    include $file_newsportal;
} elseif (file_exists("rocksolid/newsportal.php")) {
    include "rocksolid/newsportal.php";
} elseif (file_exists("newsportal.php")) {
    include "newsportal.php";
}
if (trim($CONFIG['tac'] == '')) {
    if (is_file($spooldir . '/sessions.dat')) {
        unlink($spooldir . '/sessions.dat');
    }
    exit(0);
}

if (isset($OVERRIDES['count_bots'])) {
    $count_bots = $OVERRIDES['count_bots'];
} else {
    $count_bots = false;
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
    GLOBAL $CONFIG, $spooldir, $count_bots;
    $session_age = 600;
    $session_save_file = $spooldir . '/sessions.dat';
    $session_dir = $CONFIG['tac'];
    $session_files = scandir($session_dir);
    $count = 0;
    $bot_count = 0;
    $throttled_count = 0;
    foreach ($session_files as $session_file) {
        if (filemtime($session_dir . '/' . $session_file) < time() - $session_age) {
            continue;
        }
        if (strpos($session_file, 'sess_') === 0) {
            $contents = file_get_contents($session_dir . '/' . $session_file);
            if (strpos($contents, 'rsactive') !== false) {
                $count ++;
                if (strpos($contents, 'bot') !== false) {
                    $bot_count ++;
                }
                if (strpos($contents, 'throttled') !== false) {
                    $throttled_count ++;
                }
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
        $bot_users = 'bot';
    } else {
        $bot_users = 'bots';
    }
    if($count_bots) {
        $throttled_users = 'throttled';
        $session_info = 'There ' . $are . ' currently ' . $count . ' ' . $users . ' online (including ' . $bot_count . ' ' . $bot_users . ' and ' . $throttled_count . ' ' . $throttled_users . ')<br>Total messages: ' . number_format(count_articles()) . "\r\n";
    } else {
        $session_info = 'There ' . $are . ' currently ' . $count . ' ' . $users . ' online <br>Total messages: ' . number_format(count_articles()) . "\r\n";
    }
    file_put_contents($session_save_file, $session_info);
}
?>
