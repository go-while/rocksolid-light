<?php
header("Expires: " . gmdate("D, d M Y H:i:s", time() + (100)) . " GMT");
header("Cache-Control: max-age=100");
header("Pragma: cache");

include "config.inc.php";
include "$file_newsportal";

if (! isset($_SESSION['last_access']) || (time() - $_SESSION['last_access']) > 60) {
    $_SESSION['last_access'] = time();
}

$logfile = $logdir . '/newsportal.log';
if (isset($_COOKIE['mail_name'])) {
    $cookie_mail_name = trim(strtolower($_COOKIE['mail_name']));
    if ($_COOKIE['mail_name'] == $CONFIG['anonusername']) {
        unset($cookie_mail_name);
    }
    if ($userdata = get_user_mail_auth_data($cookie_mail_name)) {
        $userfile = $spooldir . '/' . strtolower($cookie_mail_name) . '-articleviews.dat';
    }
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
        $newurl = 'article-flat.php?id=' . $id . '&group=' . urlencode($row['newsgroup']) . '#' . $id;
        header("Location: $newurl");
        die();
    }
}

// Switch to correct section in case group has been moved and link is to old section
$findsection = get_section_by_group($group);
if (($findsection) && trim($findsection) !== $config_name) {
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on')
        $link = "https";
    else
        $link = "http";
    $link .= "://";
    $link .= $_SERVER['HTTP_HOST'];
    $link .= $_SERVER['REQUEST_URI'];

    // May need to add more characters to escape for regex here
    $configregex = '|/' . preg_replace('/\+/', '\+', addslashes($config_name)) . '/|';
    $newurl = preg_replace($configregex, "/$findsection/", $link);
    header("Location:$newurl");
    die();
}

if (isset($_REQUEST["first"]))
    $first = $_REQUEST["first"];

if (! isset($_SERVER['REQUEST_STRING'])) {
    $_SERVER['REQUEST_STRING'] = '';
}
$location = $_SERVER['REQUEST_URI'] . $_SERVER['REQUEST_STRING'];
$_SESSION['return_page'] = $location . '#' . $id;

// file_put_contents($accessfile, "\n".format_log_date()." ".$config_name." ".$group.":".$id, FILE_APPEND);
if ($userdata) {
    $userdata[$group] = time();
    file_put_contents($userfile, serialize($userdata));
}

if (isset($frames_on) && $frames_on === true) {
?>
    <script>
        var contentURL = window.location.pathname + window.location.search + window.location.hash;
        if (window.self !== window.top) {
            /* Great! now we move along */
        } else {
            window.location.href = '../index.php?content=' + encodeURIComponent(contentURL);
        }
        top.history.replaceState({}, 'Title', 'index.php?content=' + encodeURIComponent(contentURL));
    </script>
<?php
}

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
include "head.inc";

echo '<h1 class="np_thread_headline">';
echo '<a href="' . $file_index . '" target=' . $frame['menu'] . '>' . basename(getcwd()) . '</a> / ';
echo '<a href="' . $file_thread . '?group=' . rawurlencode($group) . '" target=' . $frame["content"] . '>' . htmlspecialchars(group_display_name($group)) . '</a> / ' . $subject . '</h1>';

if (! $message) {
    echo "Article not found";
    include "tail.inc";
    exit(0);
}

if ($message) {
    // load thread-data and get IDs of the actual subthread
    $thread = thread_load($group);
    $subthread = thread_getsubthreadids($message->header->id, $thread);
    if (! $subthread) {
        echo '<center>Group is rebuilding... Please try again later</center>';
        repair_broken_group($group);
        exit();
    }
    if ($thread_articles == false) {
        sort($subthread);
    }
    // If no page is set, lets look, if we can calculate the page by
    // the message-number
    if (! isset($first)) {
        $first = intval(array_search($id, $subthread) / $articleflat_articles_per_page) * $articleflat_articles_per_page + 1;
    }

    // which articles are exactly on this page?
    $pageids = array();
    for ($i = $first - 1; (($i < count($subthread)) && ($i < $first + $articleflat_articles_per_page - 1)); $i++) {
        $pageids[] = $subthread[$i];
    }

    // display the thread on top
    // change some of the default threadstyle-values
    $thread_show["replies"] = true;
    $thread_show["threadsize"] = false;
    $thread_show["lastdate"] = false;
    $thread_show["latest"] = false;
    $thread_show["author"] = true;
    if (isset($OVERRIDES['show_thread_tree']) && $OVERRIDES['show_thread_tree'] == true) {
        message_thread($message->header->id, $group, $thread, false);
    }
    echo '<br>';
    // navigation line
    echo '<form action="' . $file_thread . '">';
    echo '<table id="start" class="np_buttonbar"><tr>';
    // Article List button
    echo '<td>';
    echo '<input type="hidden" name="group" value="' . rawurlencode($group) . '">';
    echo '<button class="np_button_link" type="submit">' . htmlspecialchars(group_display_name($group)) . '</button>';
    echo '</td>';
    // Pages
    echo '<td class="np_pages">';
    echo articleflat_pageselect($group, $id, count($subthread), $first);
    echo '</td></tr></table>';
    echo '</form>';

    foreach ($pageids as $subid) {
        flush();
        $message = message_read($subid, 0, $group);
        echo '<section id="' . $subid . '">';
        $is_blocked = message_show($group, $subid, 0, $message, $articleflat_chars_per_articles);
        if (((! $CONFIG['readonly']) && ($message)) && $is_blocked != "blocked") {
            echo '<form action="' . $file_post . '">' . '<input type="hidden" name="id" value="' . urlencode($subid) . '">' . '<input type="hidden" name="type" value="reply">' . '<input type="hidden" name="group" value="' . urlencode($group) . '">' . '<input type="submit" value="' . $text_article["button_answer"] . '">' . '</form>';
        }
        echo ' </section>';
    }
    // Display section/group/subject
    echo '<hr><h1 class="np_thread_headline">';
    echo '<a href="' . $file_index . '" target=' . $frame['menu'] . '>' . basename(getcwd()) . '</a> / ';
    echo '<a href="' . $file_thread . '?group=' . rawurlencode($group) . '" target=' . $frame["content"] . '>' . htmlspecialchars(group_display_name($group)) . '</a> / ' . $subject . '</h1>';
    // navigation line
    echo '<form action="' . $file_thread . '">';
    echo '<table class="np_buttonbar"><tr>';
    // Article List button
    echo '<td>';
    echo '<input type="hidden" name="group" value="' . rawurlencode($group) . '">';
    echo '<button class="np_button_link" type="submit">' . htmlspecialchars(group_display_name($group)) . '</button>';
    echo '</td>';
    // Pages
    echo '<td class="np_pages">';
    echo articleflat_pageselect($group, $id, count($subthread), $first);
    echo '</td></tr></table>';
    echo '</form>';
}
include "tail.inc";
?>