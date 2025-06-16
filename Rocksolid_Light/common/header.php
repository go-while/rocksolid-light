<?php
$backtrace = debug_backtrace();
$parent = isset($backtrace[0]['file']) ? $backtrace[0]['file'] : 'Direct execution';
echo "[common/header.php included by: " . basename($parent) . "]<br>\n";
die("legacy code!");

if (basename(getcwd()) == 'mods') {
    $rootdir = "../../";
} else {
    $rootdir = "../";
}

require_once($rootdir . 'common/config.inc.php');
//require_once(__DIR__ . '/../rocksolid/lib/security.inc.php');

// Add security headers
//add_security_headers();

global $OVERRIDES;
$CONFIG = include $config_file;

$menulist = get_section_menu_array();
$linklist = file($config_dir . "links.conf", FILE_IGNORE_NEW_LINES);

echo '<meta charset="utf-8">';

// Set tzo if possible
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

// Get theme
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
    if ($unread && (strpos($linkitem[1], 'spoolnews/mail.php') !== false)) {
        echo '<strong>';
        echo '<a class="header_links_text" href="' . trim($linkitem[1]) . '">' . trim(strtoupper($linkitem[0])) . '</a>&nbsp;&nbsp;';
        echo '</strong>';
    } else {
        echo '<a class="header_links_text" href="' . trim($linkitem[1]) . '">' . trim($linkitem[0]) . '</a>&nbsp;&nbsp;';
    }
}
echo '<a class="header_links_text" href="../spoolnews/user.php">';
if (isset($user)) {
    echo '(' . $_COOKIE['mail_name'] . ')';
} else {
    echo 'login';
}
echo '</a>&nbsp;&nbsp;';

// Add language selector link
$current_page = $_SERVER['REQUEST_URI'];
echo '<a class="header_links_text" href="../rocksolid/language_selector.php?return=' . urlencode($current_page) . '" title="Change Language">';
$current_lang = isset($_COOKIE['user_language']) ? $_COOKIE['user_language'] : 'english.lang';
$lang_display = ucfirst(str_replace(['_', '.lang'], [' ', ''], $current_lang));
echo '🌐 ' . htmlspecialchars($lang_display) . '</a>';

echo '</div></td></tr>';
echo '</table>';

include($config_dir . '/fortunes.conf');

// If $config_dir/motd.txt is not blank, show it
if (file_exists($config_dir . '/motd.txt')) {
    $motd = file_get_contents($config_dir . '/motd.txt');
}
// If specific <section>-motd.txt exists, use it
if (file_exists($config_dir . '/' . $config_name . '-motd.txt')) {
    $motd = file_get_contents($config_dir . '/' . $config_name . '-motd.txt');
}

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
echo '</td></tr></table>';

if (preg_match("/thread.php|article.php|article-flat.php|overboard.php|search.php/", $_SERVER['REQUEST_URI'])) {
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

echo '</div><div class="scroll">';
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

// For debugging purposes
if (isset($OVERRIDES['log_lang']) && $OVERRIDES['log_lang'] == true) {
    $lang = substr($_SERVER['HTTP_ACCEPT_LANGUAGE'], 0, 2);
    file_put_contents($debug_log, "\n" . logging_prefix() . " Browser Lang: " . $lang, FILE_APPEND);
}

// Soup...Uh, Message of the Day
if ($unread) {
    $motd = '*** You have unread mail. <a href="../spoolnews/mail.php">Click Here</a> ***';
    echo '<div class="np_display_motd_new_mail">';;
} else {
    echo '<div class="np_display_motd">';
}
echo $motd;
echo '</div>';

