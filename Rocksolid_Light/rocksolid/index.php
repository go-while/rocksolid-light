<?php
/**
 * LEGACY INDEX FILE - CONSOLIDATED INTO ROUTER SYSTEM
 *
 * This file now redirects to the consolidated router-based index page
 * All functionality has been moved to pages/index.php via the secure router
 *
 * Date: June 15, 2025 - Part of header migration consolidation
 */

// Quick redirect to router-based index page
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$current_path = dirname($_SERVER['REQUEST_URI']);

// Build redirect URL - go up one directory level and use page parameter
$redirect_url = $protocol . '://' . $host . $current_path . '/?page=index';

// Add any query parameters (like subscribe, unsub, mark_read)
if (!empty($_SERVER['QUERY_STRING'])) {
    $redirect_url .= '&' . $_SERVER['QUERY_STRING'];
}

header("Location: $redirect_url", true, 302);
exit("Redirecting to consolidated index page...");

// NOTE: The rest of this file is kept for reference but should not execute
// All functionality has been moved to pages/index.php

if (! isset($_SESSION['last_access']) || (time() - $_SESSION['last_access']) > 60) {
    $_SESSION['last_access'] = time();
}

$title .= ' - ' . basename(getcwd());
include "lib/head.inc";

echo '<h1 class="np_thread_headline">' . basename(getcwd()) . '</h1>';
echo '<table class="np_buttonbar"><tr>';

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
        $user_config = secure_unserialize($config_dir . '/userconfig/' . strtolower($_COOKIE['mail_name']) . '.config');
        if (isset($_POST['hide_unsub'])) {
            $user_config['hide_unsub'] = $_POST['hide_unsub'];
            secure_serialize_file($config_dir . '/userconfig/' . strtolower($_COOKIE['mail_name']) . '.config', $user_config, false);
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

// Subscribe to group
if (isset($_GET['subscribe'])) {
    if (isset($_COOKIE['mail_name'])) {
        if ($userdata = get_user_mail_auth_data($_COOKIE['mail_name'])) {
            $userfile = $spooldir . '/' . strtolower($_COOKIE['mail_name']) . '-articleviews.dat';
            $thisgroup = _rawurldecode($_GET['subscribe']);
            $userdata[$thisgroup] = time();
            file_put_contents($userfile, serialize($userdata));
        }
    }
}
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
            file_put_contents($userfile, serialize($newsubs));
        }
    }
}
// Mark group as read
if (isset($_GET['mark_read'])) {
    if (isset($_COOKIE['mail_name'])) {
        if ($userdata = get_user_mail_auth_data($_COOKIE['mail_name'])) {
            $userfile = $spooldir . '/' . strtolower($_COOKIE['mail_name']) . '-articleviews.dat';
            $user_config = secure_unserialize($config_dir . '/userconfig/' . strtolower($_COOKIE['mail_name']) . '.config');
            $userdata[$_GET['mark_read']] = time();
            file_put_contents($userfile, serialize($userdata));
        }
    }
}

$newsgroups = groups_read($server, $port);
echo '<div class="np_index_groups"><h2>debug newsgroups</h2>';
if (isset($frames_on) && $frames_on === true) {
    groups_show_frames($newsgroups);
} else {
    groups_show($newsgroups); // Show the newsgroups table
}
echo '</div>';
$sessions_data = file_get_contents($spooldir . '/sessions.dat');
echo '<h1 class="np_thread_headline">' . $sessions_data . '</h1>';
include "lib/tail.inc";
?>

