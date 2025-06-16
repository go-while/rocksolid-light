<?php
echo "[inc/_header.inc.php: start]<br>\n";

/**
 * RockSolid Light - HTML Header Include
 * Extracted from pages/pages.php for simple include usage
 */

// Prevent direct access
if (!defined('RSLIGHT_CONFIG_LOADED')) {
    die('Direct access not allowed. Include via config.inc.php');
}

// Ensure we have required globals
if (!isset($CONFIG)) {
    $CONFIG = array();
}

// throttle_hits MUST be called before any data is sent
$client_device = get_client_user_agent_info();
throttle_hits($client_device);
write_access_log();


$menulist = get_section_menu_array();
$linklist = file($config_dir . "links.conf", FILE_IGNORE_NEW_LINES);

// Start HTML output
echo '<!DOCTYPE html>';
echo '<html><head>';
echo '<title>' . htmlspecialchars($title ?? 'RockSolid Light') . '</title>';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
echo '<meta charset="utf-8">';



if(file_exists($config_dir.'/googleanalytics.conf')) {
  include $config_dir.'/googleanalytics.conf';
}
// Timezone JavaScript Set tzo if possible
?>
<script>
    if (navigator.cookieEnabled)
        document.cookie = "tzo=" + (-new Date().getTimezoneOffset()) + "; path=/";
    var tzid = new Intl.DateTimeFormat().resolvedOptions().timeZone;
    document.cookie = "tzid=" + tzid + "; path=/";
</script>
<?php
if (isset($_COOKIE['mail_name']) && isset($_COOKIE['pkey'])) {
    $user = strtolower($_COOKIE['mail_name']);
    if (! isset($_SESSION['theme']) && file_exists($config_dir . '/userconfig/' . $user . '.config')) {
        $user_config = secure_unserialize($config_dir . '/userconfig/' . $user . '.config');
        if ($user_config === false) {
            $user_config = [];
        }
        if (isset($user_config['theme'])) {
            $_SESSION['theme'] = $user_config['theme'];
        }
    }
} else {
    unset($user);
}

// Theme and CSS
$rootdir = "../";
$default_theme = "Default Theme";
if (isset($_SESSION['theme'])) {
    $do_theme = preg_replace("/ /", "%20", $_SESSION['theme']);
} else {
    $do_theme = preg_replace("/ /", "%20", $default_theme);
}
echo '<link rel="stylesheet" type="text/css" href="' . $rootdir . '/common/themes/' . $do_theme . '/style.css">';
echo '<link rel="icon" type="image/x-icon" href="/common/images/favicon.ico">';

if ((isset($_SESSION['theme'])) && file_exists($rootdir . '/common/themes/' . $do_theme . '/images/rocksolidlight.png')) {
    $header_image = $rootdir . '/common/themes/' . $do_theme . '/images/rocksolidlight.png';
} else {
    $header_image = $rootdir . 'common/images/rocksolidlight.png';
}

echo '</head><body>';
echo '<div class="header_top">';

echo '<table class="np_header_table_top">';
echo '<tr class="np_header_bar_top">';
echo '<td class="np_td_header_bar_logo_image"><a href="' . $CONFIG['default_content'] . '">';
echo '<img src="' . $header_image . '" alt="Rocksolid Light"';
echo ' class="responsive_image"></a></td>';
echo '<td class="header_page_title_top">';
echo '<p class="header_page_title_top">';
echo $CONFIG['rslight_title'];
echo '</p></td>';
echo '<td class="header_links">';
echo '<div class="header_links_text">';

if (isset($user) && $user && check_unread_mail() == true) {
    $unread = true;
} else {
    $unread = false;
}
foreach ($linklist as $link) {
    if ($link[0] == '#') {
        continue;
    }
    $linkitem = explode(':', $link, 2);
    if ($linkitem[1] == '0') {
        continue;
    }
    if ($unread && isset($_GET['page']) && $_GET['page'] == 'mail') {
        echo '<strong>';
        echo '<a class="header_links_text" href="' . trim($linkitem[1]) . '">' . trim(strtoupper($linkitem[0])) . '</a>&nbsp;&nbsp;';
        echo '</strong>';
    } else {
        echo '<a class="header_links_text" href="' . trim($linkitem[1]) . '">' . trim($linkitem[0]) . '</a>&nbsp;&nbsp;';
    }
}
echo '<a class="header_links_text" href="?page=user">';
if (isset($user)) {
    echo '(' . $_COOKIE['mail_name'] . ')';
} else {
    echo 'login';
}
echo '</a>&nbsp;&nbsp;';

// Add language selector link
$current_page = $_SERVER['REQUEST_URI'];
echo '<a class="header_links_text" href="?page=language_selector&return=' . urlencode($current_page) . '" title="Change Language">';
$current_lang = isset($_COOKIE['user_language']) ? $_COOKIE['user_language'] : 'english.lang';
$lang_display = ucfirst(str_replace(['_', '.lang'], [' ', ''], $current_lang));
echo '🌐 ' . htmlspecialchars($lang_display) . '</a>';

echo '</div></td></tr>';
echo '</table>';

include($config_dir . '/fortunes.conf');

// If $config_dir/motd.txt is not blank, show it
if (file_exists($config_dir . '/motd.txt')) {
    $motd = file_get_contents($config_dir . '/motd.txt');
} else {
    $motd = ''; // Default to empty if motd.txt does not exist
}
/* TODO FIXME
 * If motd.txt is not found, we can use a default message or leave it empty.
 * This is currently set to an empty string if no motd.txt is found.
 */
/*
// If specific <section>-motd.txt exists, use it
if (file_exists($config_dir . '/' . $config_name . '-motd.txt')) {
    $motd = file_get_contents($config_dir . '/' . $config_name . '-motd.txt');
}
*/

echo '<table class="np_header_button_bar"><tr>';

foreach ($menulist as $menu) {
    $menuitem = explode(':', $menu);
    if ($menuitem[1] == '0') {
        continue;
    }
    if (! isset($frame['menu'])) {
        $frame['menu'] = null;
    }
    echo '<td>';
    echo '<form target="' . $frame['menu'] . '" action="' . $rootdir . $menuitem[0] . '">';
    echo '<button class="np_header_button_link" type="submit">' . $menuitem[0] . '</button>';
    echo '</form>';
    echo '</td>';
}
echo '</tr></table>';

// breadcrumbs for thread/article pages
$show_breadcrumbs = array("thread", "article", "article-flat", "overboard", "search");
if (isset($_GET['page']) && in_array($_GET['page'], $show_breadcrumbs)) {
    $show_breadcrumbs = true;
} else {
    $show_breadcrumbs = false;
}
// Determine the file for thread/article links

if ($show_breadcrumbs) {

    if (isset($_REQUEST["group"]) || isset($_REQUEST['thisgroup'])) {
        if (isset($_REQUEST["group"])) {
            $display_group = $_REQUEST['group'];
        } else {
            $display_group = $_REQUEST['thisgroup'];
        }
        echo '<table class="header_display_group">';
        echo '<tr><td>';
        echo '<span><a href="/' . $config_name . '">' . $config_name . '</a> /  <a href="' . $file_thread . '&group=' . rawurlencode($display_group) . '" target=' . $frame["content"] . '>' . htmlspecialchars(group_display_name($display_group)) . '</a>';
        echo '</td></tr></table>';
    }
}

echo '</div><div Xclass="scroll">';

/* TODO FIXME
 * Message-ID search form
 * This is currently commented out, but can be enabled if needed.
 * It allows users to search for articles by their Message-ID.
 * The form will only be displayed if the 'disable_msgid_search' override is not set or is false.
 */
/*
$config_name = basename(getcwd());

if (!isset($OVERRIDES['disable_msgid_search']) || $OVERRIDES['disable_msgid_search'] == false) {
    if ($config_name != "common" && $config_name != 'spoolnews') {
        echo '<form name="form1" method="get" action="?">';
        echo '<input type="hidden" name="page" value="article-flat">';
        echo '<table class="header_message_id_search">';
        echo '<tr>';
        echo '<td class="header_message_id_search_prompt">Message-ID: ';
        echo '<input name="id" type="text" id="id" size="40" maxlength="120">&nbsp;';
        echo '<input type="submit" name="Submit" value="Lookup">';
        echo '</td></tr></table>';
        echo '</form>';
    }
}
*/


// For debugging purposes
if (isset($OVERRIDES['log_lang']) && $OVERRIDES['log_lang'] == true) {
    $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
    file_put_contents($debug_log, "\n" . logging_prefix() . " Browser Lang: " . $lang, FILE_APPEND);
}

// Soup...Uh, Message of the Day
if ($unread) {
    $motd = '*** You have unread mail. <a href="?page=mail">Click Here</a> ***';
    echo '<div class="np_display_motd_new_mail">';;
} else {
    echo '<div class="np_display_motd">';
}
echo $motd;
echo '</div>';



// Include fortunes config
if (file_exists($config_dir . '/fortunes.conf')) {
    // Fortune handling would go here
}

// Add separator
echo '<hr>';

?>