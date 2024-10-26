<?php
if (basename(getcwd()) == 'mods') {
    $rootdir = "../../";
} else {
    $rootdir = "../";
}

include ($rootdir . 'common/config.inc.php');

global $OVERRIDES;
$CONFIG = include $config_file;

$menulist = get_section_menu_array();
$linklist = file($config_dir . "links.conf", FILE_IGNORE_NEW_LINES);

echo '<meta charset="utf-8">';

// Set tzo if possible
?>
   <script>
     if (navigator.cookieEnabled)
       document.cookie = "tzo="+ (- new Date().getTimezoneOffset())+"; path=/";
       var tzid = new Intl.DateTimeFormat().resolvedOptions().timeZone;
       document.cookie = "tzid=" + tzid + "; path=/";
   </script>
<?php

if (isset($_COOKIE['mail_name']) && isset($_COOKIE['pkey'])) {
    $user = strtolower($_COOKIE['mail_name']);
    if (! isset($_SESSION['theme']) && file_exists($config_dir . '/userconfig/' . $user . '.config')) {
        $user_config = unserialize(file_get_contents($config_dir . '/userconfig/' . $user . '.config'));
        $_SESSION['theme'] = $user_config['theme'];
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

if ((isset($_SESSION['theme'])) && file_exists($rootdir . '/common/themes/' . $do_theme . '/images/rocksolidlight.png')) {
    $header_image = $rootdir . '/common/themes/' . $do_theme . '/images/rocksolidlight.png';
} else {
    $header_image = $rootdir . 'common/images/rocksolidlight.png';
}

echo '</head><body>';
?>

	<table class="np_header_bar_top" valign="middle">
		<tr>
			<td class="np_td_header_bar_logo_image"><a href="<?php echo $CONFIG['default_content'];?>"><img
					src="<?php echo $header_image ?>" alt="Rocksolid Light"
					class="responsive_image"></a></td>
			<td class="header_page_title_top">
	<?php echo $CONFIG['rslight_title']; ?>	

				</p>
			</td>
			<td align="right">
<?php
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
        echo '<a class="np_header_links" href="' . trim($linkitem[1]) . '">' . trim(strtoupper($linkitem[0])) . '</a>&nbsp;&nbsp;';
        echo '</strong>';
    } else {
        echo '<a class="np_header_links" href="' . trim($linkitem[1]) . '">' . trim($linkitem[0]) . '</a>&nbsp;&nbsp;';
    }
}
echo '<a class="np_header_links" href="../spoolnews/user.php">';
if (isset($user)) {
    echo '(' . $_COOKIE['mail_name'] . ')';
} else {
    echo 'login';
}
echo '</a>';
echo '</td></tr>';
echo '</table>';

include ($config_dir . '/fortunes.conf');

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
echo '</tr></table>';

$config_name = basename(getcwd());

if (!isset($OVERRIDES['disable_msgid_search']) || $OVERRIDES['disable_msgid_search'] == false) {
    if ($config_name != "common" && $config_name != 'spoolnews') {
        echo '<table align="right">';
        echo '<form name="form1" method="get" action="article-flat.php">';
        echo '<tr>';
        echo '<td>Message-ID: ';
        echo '<input name="id" type="text" id="id" size="40" maxlength="120">&nbsp;';
        echo '<input type="submit" name="Submit" value="Lookup"></form></td>';
        echo '</tr>';
        echo '</table><br />';
    }
}

// Soup...Uh, Message of the Day
if ($unread) {
    $motd = '<center>*** You have unread mail. <a href="../spoolnews/mail.php">Click Here</a> ***</center>';
}
echo '<div class="np_display_motd">' . $motd . '</div>';

function check_unread_mail()
{
    global $CONFIG, $spooldir;
    if (isset($_COOKIE['mail_name'])) {
        $name = strtolower($_COOKIE['mail_name']);
        $database = $spooldir . '/mail.db3';
        if (is_file($database)) {
            $dbh = head_mail_db_open($database);
            $query = $dbh->prepare('SELECT * FROM messages where rcpt_to=:rcpt_to');
            $query->execute([
                'rcpt_to' => $name
            ]);
            $newmail = false;
            while (($row = $query->fetch()) !== false) {
                if (($row['rcpt_viewed'] != 'true') && ($row['to_hide'] != 'true')) {
                    $newmail = true;
                }
            }
            $dbh = null;
            return $newmail;
        } else {
            return false;
        }
    }
}

function head_mail_db_open($database, $table = 'messages')
{
    try {
        $dbh = new PDO('sqlite:' . $database);
    } catch (PDOException $e) {
        echo 'Connection failed: ' . $e->getMessage();
        exit();
    }
    $dbh->exec("CREATE TABLE IF NOT EXISTS messages(
     id INTEGER PRIMARY KEY,
     msgid TEXT UNIQUE,
     mail_from TEXT,
     mail_viewed TEXT,
     rcpt_to TEXT,
     rcpt_viewed TEXT,
     rcpt_target TEXT,
     date TEXT,
     subject TEXT,
     message TEXT,
     from_hide TEXT,
     to_hide TEXT)");
    return ($dbh);
}