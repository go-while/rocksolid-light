<?php
header("Expires: " . gmdate("D, d M Y H:i:s", time() + (30)) . " GMT");
header("Cache-Control: max-age=30");
header("Pragma: cache");

include "config.inc.php";
include ("$file_newsportal");

if (! isset($_SESSION['last_access']) || (time() - $_SESSION['last_access']) > 60) {
    $_SESSION['last_access'] = time();
}
$_SESSION['isframed'] = 1;

if (isset($frames_on) && $frames_on === true) {
    ?>
<script>
    var contentURL=window.location.pathname+window.location.search+window.location.hash;
    if ( window.self !== window.top ) {
        /* Great! now we move along */
    } else {
        window.location.href = '../index.php?menu='+encodeURIComponent(contentURL);
    }
    top.history.replaceState({}, 'Title', 'index.php?content='+encodeURIComponent(contentURL));
</script>
<?php
}
$title .= ' - ' . basename(getcwd());
include "head.inc";

echo '<h1 class="np_thread_headline">' . basename(getcwd()) . '</h1>';
echo '<table cellpadding="0" cellspacing="0" class="np_buttonbar"><tr>';

// If logged in: button for new only
if (isset($_COOKIE['mail_name'])) {
    if (isset($OVERRIDES['overboard_disable_new_link']) && $OVERRIDES['overboard_disable_new_link'] === true) {
        $newlink = false;
    } else {
        $newlink = true;
    }
    if ($newlink) {
        if ($userdata = get_user_mail_auth_data($_COOKIE['mail_name'])) {
            if (isset($overboard) && ($overboard == true)) {
                echo '<td>';
                echo '<form target="' . $frame['content'] . '" action="overboard.php">';
                echo '<button class="np_button_link" type="submit">new articles</button>';
                echo '<input name="new" type="hidden" id="new" value="true">';
                echo '</form>';
                echo '</td>';
            }
        }
    }
    if ($userdata = get_user_mail_auth_data($_COOKIE['mail_name'])) {
        $userfile = $spooldir . '/' . strtolower($_COOKIE['mail_name']) . '-articleviews.dat';
        $user_config = unserialize(file_get_contents($config_dir . '/userconfig/' . strtolower($_COOKIE['mail_name']) . '.config'));
        if (isset($_POST['hide_unsub'])) {
            $user_config['hide_unsub'] = $_POST['hide_unsub'];
            file_put_contents($config_dir . '/userconfig/' . strtolower($_COOKIE['mail_name']) . '.config', serialize($user_config));
        }
    }
}

// View Latest button
if (isset($overboard) && ($overboard == true)) {
    echo '<td>';
    echo '<form target="' . $frame['content'] . '" action="overboard.php">';
    echo '<button class="np_button_link" type="submit">' . $text_thread["button_overboard"] . '</button>';
    echo '</form>';
    echo '</td>';
} else {
    // echo htmlspecialchars($CONFIG['title_full']);
}
// Search button
echo '<td>';
echo '<form target="' . $frame['content'] . '" action="search.php">';
echo '<button class="np_button_link" type="submit">' . $text_thread["button_search"] . '</button>';
echo '</form>';
echo '</td>';
echo '<td width=100%></td></tr></table>';

flush();

// Unsubscribe from group
if (isset($_GET['unsub'])) {
    if (isset($_COOKIE['mail_name'])) {
        if ($userdata = get_user_mail_auth_data($_COOKIE['mail_name'])) {
            $userfile = $spooldir . '/' . strtolower($_COOKIE['mail_name']) . '-articleviews.dat';
            $newsubs = array();
            $thisgroup = _rawurldecode($_GET['unsub']);
            foreach ($userdata as $key => $usertime) {
                if ($key !== $thisgroup) {
                    $newsubs[$key] = $usertime;
                }
            }
            $userfile = $spooldir . '/' . strtolower($_COOKIE['mail_name']) . '-articleviews.dat';
            file_put_contents($userfile, serialize($newsubs));
        }
    }
}
// Mark group as read
if (isset($_GET['mark_read'])) {
    if (isset($_COOKIE['mail_name'])) {
        if ($userdata = get_user_mail_auth_data($_COOKIE['mail_name'])) {
            $userfile = $spooldir . '/' . strtolower($_COOKIE['mail_name']) . '-articleviews.dat';
            $user_config = unserialize(file_get_contents($config_dir . '/userconfig/' . strtolower($_COOKIE['mail_name']) . '.config'));
            $userdata[$_GET['mark_read']] = time();
            file_put_contents($userfile, serialize($userdata));
        }
    }
}

$newsgroups = groups_read($server, $port);
echo '<div class="np_index_groups">';
if (isset($frames_on) && $frames_on === true) {
    groups_show_frames($newsgroups);
} else {
    groups_show($newsgroups);
}
echo '</div>';
$sessions_data = file_get_contents($spooldir . '/sessions.dat');
echo '<h1 class="np_thread_headline">' . $sessions_data . '</h1>';
include "tail.inc";
?>

