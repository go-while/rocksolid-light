<?php
if(!defined('RSLIGHT_CONFIG_LOADED')) {
    die("Access denied.");
}

// register parameters
$id = $_REQUEST["id"];
$group = _rawurldecode($_REQUEST["group"]);

if (strpos($id, '@') !== false) {
    $id = '<' . trim($id, '<> ') . '>';
    $database = $spooldir . '/articles-overview.db3';
    $overview_dbh = overview_db_open($database);
    $overview_query = $overview_dbh->prepare('SELECT * FROM overview WHERE msgid=:messageid');
    $overview_query->execute([
        'messageid' => $id
    ]);
    $found = 0;
    while ($row = $overview_query->fetch()) {
        $id = $row['number'];
        $group = $row['newsgroup'];
        $found = 1;
        break;
    }
    $overview_dbh = null;
    if ($found) {
        $newurl = '?page=article-flat&id=' . $id . '&group=' . $row['newsgroup'] . '#' . $id;
        header("Location: $newurl");
        die();
    }
}

$thread_show["replies"] = true;
$thread_show["lastdate"] = false;
$thread_show["threadsize"] = false;

$location = $_SERVER['REQUEST_URI'] . $_SERVER['REQUEST_STRING'];
preg_match('/id=(.*)&/', $location, $hash);
$_SESSION['return_page'] = $location . '#' . $hash[1];

$message = message_read($id, 0, $group);
if (! $message) {
    header("HTTP/1.0 404 Not Found");
    $subject = $title;
    $title .= ' - Article not found';
    if ($ns != false)
        nntp_close($ns);
} else {
    $subject = htmlspecialchars($message->header->subject);
    header("Last-Modified: " . date("r", $message->header->date));
    $title .= ' - ' . $group . ' - ' . $subject;
}
include "lib/head.inc";
throttle_hits($client_device);

// has the user read-rights on this article?
if ((function_exists("npreg_group_has_read_access") && ! npreg_group_has_read_access($group)) || (function_exists("npreg_group_is_visible") && ! npreg_group_is_visible($group))) {
    die("access denied");
}

echo '<h1 class="np_thread_headline">';
echo '<a href="' . $file_index . '">' . basename(getcwd()) . '</a> / ';
echo '<a href="' . $file_thread . '&group=' . rawurlencode($group) . '" target=' . $frame["content"] . '>' . htmlspecialchars(group_display_name($group)) . '</a> / ' . $subject . '</h1>';
echo '<table cellpadding="0" cellspacing="0" width="100%" class="np_buttonbar"><tr>';
// Article List button
echo '<td>';
echo '<form action="" method="get">';
echo '<input type="hidden" name="page" value="thread">';
echo '<input type="hidden" name="group" value="' . rawurlencode($group) . '"/>';
echo '<button class="np_button_link" type="submit">' . htmlspecialchars(group_display_name($group)) . '</button>';
echo '</form>';
echo '</td>';
echo '</tr></table>';

if (! $message)
    // article not found
    echo $text_error["article_not_found"];
else {
    if ($article_showthread) {
        $thread = thread_cache_load($group);
    }
    $is_blocked = message_show($group, $id, 0, $message);
    if (((! $CONFIG['readonly']) && ($message)) && $is_blocked != "blocked") {
        echo '<form action="" method="get">' . '<input type="hidden" name="page" value="post">' . '<input type="hidden" name="id" value="' . urlencode($id) . '">' . '<input type="hidden" name="type" value="reply">' . '<input type="hidden" name="group" value="' . urlencode($group) . '">' . '<input type="submit" value="TODO' . $text_article["button_answer"] . '">' . '</form>';
    }
    if ($article_showthread) {
        message_thread($message->header->id, $group, $thread);
    }
}
include "lib/tail.inc";
