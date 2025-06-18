<?php


//$_SESSION['group'] = $_SERVER['REQUEST_URI'];

if (! isset($_SESSION['last_access']) || (time() - $_SESSION['last_access']) > 60) {
    $_SESSION['last_access'] = time();
}

// register parameters
$group = _rawurldecode($_REQUEST["group"]);
if (isset($_REQUEST["first"]))
    $first = intval($_REQUEST["first"]);
if (isset($_REQUEST["last"]))
    $last = intval($_REQUEST["last"]);

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

if (isset($_COOKIE['mail_name'])) {
    if ($userdata = get_user_mail_auth_data($_COOKIE['mail_name'])) {
        $userfile = $spooldir . '/' . strtolower($_COOKIE['mail_name']) . '-articleviews.dat';
    }
}

$thread_show["latest"] = true;
$title .= ' - ' . $group;


$logfile = $logdir . '/newsportal.log';
//$CONFIG = include ($config_file);

$noaccess = false; // TODO or deprecated. legacy npgroup function
if($noaccess){
    echo $text_register["no_access_group"];
    include($config_dir . '/footer.inc.php');
    die();
}

if ($userdata) {
    $userdata[$group] = time();
    file_put_contents($userfile, serialize($userdata));
}
if (! isset($_SERVER['REQUEST_STRING'])) {
    $_SERVER['REQUEST_STRING'] = '';
}
$_SESSION['return_page'] = $_SERVER['REQUEST_URI'] . $_SERVER['REQUEST_STRING'];

//   echo '<a name="top"></a>';
echo '<h1 id="top" class="np_thread_headline">';

echo '<a href="' . $file_index . '">' . basename(getcwd()) . '</a> / ';
echo htmlspecialchars(group_display_name($group)) . '</h1>';

echo '<table class="np_buttonbar"><tr>';
// View Latest button
if (isset($overboard) && ($overboard == true)) {
    echo '<td>';
    echo '<form action="" method="get">';
    echo '<input type="hidden" name="page" value="overboard">';
    echo '<input type="hidden" name="thisgroup" value="' . urlencode($group) . '">';
    echo '<button class="np_button_link" type="submit">' . $text_thread["button_latest"] . '</button>';
    echo '</form>';
    echo '</td>';
}
if (!$CONFIG['readonly']) {
    // New Thread button
    echo '<td>';
    echo '<form action="" method="get">';
    echo '<input type="hidden" name="page" value="post">';
    echo '<input type="hidden" name="group" value="' . urlencode($group) . '">';
    echo '<button class="np_button_link" type="submit">' . $text_thread["button_write"] . '</button>';
    echo '</form>';
    echo '</td>';
}
// Search button
echo '<td>';
echo '<form action="" method="get">';
echo '<input type="hidden" name="page" value="search">';
echo '<button class="np_button_link" type="submit">' . $text_thread["button_search"] . '</button>';
echo '<input type="hidden" name="group" value="' . urlencode($group) . '">';
echo '</form>';
echo '</td>';

// $ns=nntp_open($server,$port);
flush();
$headers = thread_load($group);
if ($headers) {
    $article_count = count($headers);
}
if ($articles_per_page != 0) {
    if ((! isset($first)) || (! isset($last))) {
        if ($startpage == "first") {
            $first = 1;
            $last = $articles_per_page;
        } else {
            $first = $article_count - (($article_count - 1) % $articles_per_page);
            $last = $article_count;
        }
    }
    echo '<td class="np_pages">';
    // Show the replies to an article in the thread view?
    if ($thread_show["replies"]) {
        // yes, so the counting of the shown articles is very easy
        $pagecount = count($headers);
    } else {
        // oh no, the replies will not be shown, this makes life hard...
        $pagecount = 0;
        if (($headers) && (count($headers) > 0 && is_array($headers))) {
            foreach ($headers as $h) {
                if ($h->isAnswer == false)
                    $pagecount ++;
            }
        }
    }

    thread_pageselect($group, $pagecount, $first);
    echo '</td>';
} else {
    $first = 0;
    $last = $article_count;
}
echo '</tr></table>';
thread_show($headers, $group, $first, $last);
echo '<table class="np_buttonbar"><tr>';
echo '<td class="np_pages">';
thread_pageselect($group, $pagecount, $first);
echo '</td></tr></table>';

$sessions_data = file_get_contents($spooldir . '/sessions.dat');
echo '<h1 class="np_thread_headline">' . $sessions_data . '</h1>';
?>
