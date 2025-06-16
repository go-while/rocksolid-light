<?php
/**
 * Main Index Page - Rocksolid Light
 *
 * Consolidated from rocksolid/index.php into secure router system
 * Shows newsgroups listing and user management interface
 *
 * Access via: ?page=index or as default page
 */

// Get the current site name (from directory structure)
$site_name = basename(getcwd());
$title = 'Rocksolid Light - ' . $site_name;

echo '<h1 class="np_thread_headline">' . htmlspecialchars($site_name) . '</h1>';
echo '<table class="np_buttonbar"><tr>';

// User management functions
$user_authenticated = false;
$userdata = null;
$user_config = null;

if (isset($_COOKIE['mail_name'])) {
    $userdata = get_user_mail_auth_data($_COOKIE['mail_name']);
    if ($userdata) {
        $user_authenticated = true;
        $userfile = $spooldir . '/' . strtolower($_COOKIE['mail_name']) . '-articleviews.dat';
        $user_config = secure_unserialize($config_dir . '/userconfig/' . strtolower($_COOKIE['mail_name']) . '.config');

        // Handle user config updates
        if (isset($_POST['hide_unsub'])) {
            $user_config['hide_unsub'] = $_POST['hide_unsub'];
            secure_serialize_file($config_dir . '/userconfig/' . strtolower($_COOKIE['mail_name']) . '.config', $user_config, false);
        }
    }
}

// New articles button (if logged in and overboard enabled)
if ($user_authenticated) {
    $show_new_link = true;
    if (isset($OVERRIDES['overboard_disable_new_link']) && $OVERRIDES['overboard_disable_new_link'] === true) {
        $show_new_link = false;
    }

    if ($show_new_link && isset($overboard) && ($overboard == true)) {
        echo '<td>';
        echo '<form target="' . ($frame['content'] ?? '') . '" action="overboard.php">';
        echo '<button class="np_button_link" type="submit">new articles</button>';
        echo '<input name="new" type="hidden" id="new" value="true">';
        echo '</form>';
        echo '</td>';
    }
}

// View Latest button (overboard)
if (isset($overboard) && ($overboard == true)) {
    echo '<td>';
    echo '<form target="' . ($frame['content'] ?? '') . '" action="overboard.php">';
    echo '<button class="np_button_link" type="submit">' . ($text_thread["button_overboard"] ?? 'Latest') . '</button>';
    echo '</form>';
    echo '</td>';
}

// Search button
echo '<td>';
echo '<form target="' . ($frame['content'] ?? '') . '" action="search.php">';
echo '<button class="np_button_link" type="submit">' . ($text_thread["button_search"] ?? 'Search') . '</button>';
echo '</form>';
echo '</td>';
echo '<td width=100%></td></tr></table>';

flush();

// Handle subscription management
if (isset($_GET['subscribe']) && $user_authenticated) {
    $thisgroup = _rawurldecode($_GET['subscribe']);
    $userdata[$thisgroup] = time();
    file_put_contents($userfile, serialize($userdata));
}

if (isset($_GET['unsub']) && $user_authenticated) {
    $thisgroup = _rawurldecode($_GET['unsub']);
    $newsubs = array();
    foreach ($userdata as $key => $usertime) {
        if ($key !== $thisgroup) {
            $newsubs[$key] = $usertime;
        }
    }
    file_put_contents($userfile, serialize($newsubs));
}

if (isset($_GET['mark_read']) && $user_authenticated) {
    $userdata[$_GET['mark_read']] = time();
    file_put_contents($userfile, serialize($userdata));
}

// Display newsgroups
$newsgroups = groups_read($server, $port);
echo '<div class="np_index_groups"><h3>'.count($newsgroups, true).' Available Newsgroups</h3>';
groups_show($newsgroups); // Show the newsgroups table
echo '</div>';
echo "<h3>DEBUG End pages/index.php Newsgroups</h3>";
// Show session debug info (if available)
if (file_exists($spooldir . '/sessions.dat')) {
    $sessions_data = file_get_contents($spooldir . '/sessions.dat');
    if ($sessions_data && strlen(trim($sessions_data)) > 0) {
        echo '<div class="np_debug_sessions">';
        echo '<h3>Session Info</h3>';
        echo '<pre>' . htmlspecialchars($sessions_data) . '</pre>';
        echo '</div>';
    }
}

// Include footer if exists
if (file_exists("lib/tail.inc")) {
    include "lib/tail.inc";
} else {
    echo '</div></body></html>';
}
?>
