<?php
session_start();
if (! isset($_SESSION['last_access']) || (time() - $_SESSION['last_access']) > 60) {
    $_SESSION['last_access'] = time();
}

if (isset($_POST['command']) && $_POST['command'] == 'Logout') {
    $past = time() - 3600;
    foreach ($_COOKIE as $key => $value) {
        setcookie($key, $value, $past, '/');
    }
    $_SESSION = array();
    session_destroy();
    unset($_COOKIE['mail_name']);
    setcookie('mail_name', '', -1, '/');
    $logmeout = true;
} else {
    $logmeout = false;
}

include("config.inc.php");
include("newsportal.php");

$ip_pass = false;
if (! isset($_SESSION['remote_address'])) {
    $_SESSION['remote_address'] = $_SERVER['REMOTE_ADDR'];
    $_SESSION['start_address'] = $_SESSION['remote_address'];
    $ip_pass = true;
} else {
    if ($_SERVER['REMOTE_ADDR'] != $_SESSION['start_address']) {
        $ip_pass = false;
    } else {
        $ip_pass = true;
    }
}

if ($logmeout) {
    include "head.inc";
    echo "<center>";
    echo "<hr><p>You have been logged out</p>";
    echo '</center>';
    echo '<br >';
    include "tail.inc";
    exit(0);
}

if (isset($_COOKIE['tzo'])) {
    $offset = $_COOKIE['tzo'];
} else {
    $offset = $CONFIG['timezone'];
}
if (! isset($_POST['command'])) {
    $_POST['command'] = null;
}

$keyfile = $spooldir . '/keys.dat';
$keys = unserialize(file_get_contents($keyfile));

$title .= ' - User Configuration';
include "head.inc";

if (disable_page_by_user_agent($client_device, "bot", "User")) {
    echo "<center>Page Disabled</center>";
    include "tail.inc";
    exit();
}

$logged_in = false;
if (! isset($_POST['username'])) {
    $_POST['username'] = $_COOKIE['mail_name'];
}
$name = trim(strtolower($_POST['username']));
if (! isset($_POST['password'])) {
    $_POST['password'] = null;
}
if (! isset($_COOKIE['mail_auth'])) {
    $_COOKIE['mail_auth'] = null;
}
$logged_in = verify_logged_in(trim(strtolower($_POST['username'])));
if (!$logged_in) {
    if ((password_verify($name . $keys[0] . get_user_config($name, 'encryptionkey'), $_COOKIE['mail_auth'])) || (password_verify($name . $keys[1] . get_user_config($name, 'encryptionkey'), $_COOKIE['mail_auth']))) {
        $logged_in = true;
    } else {
        if (check_bbs_auth($_POST['username'], $_POST['password'])) {
            if ($ip_pass) {
                $_SESSION['pass'] = true;
            }
            set_user_logged_in_cookies(trim($_POST['username']), $keys);
            $logged_in = true;
        } else {
            echo 'Authentication Required';
        }
    }
}

if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'Configuration') {
    echo '<h1 class="np_thread_headline">';
    echo '<a href="user.php" target=' . $frame['menu'] . '>Configuration</a> / ';
    echo htmlspecialchars($_POST['username']) . '</h1>';
} else {
    echo '<h1 class="np_thread_headline">';
    echo '<a href="user.php" target=' . $frame['menu'] . '>user login</a> / ';
    echo htmlspecialchars($_POST['username']) . '</h1>';
}
echo '<table cellpadding="0" cellspacing="0" class="np_buttonbar"><tr>';
// Mail button
if ($logged_in == true) {
    echo '<td>';
    echo '<form target="' . $frame['content'] . '" method="post" action="mail.php">';
    echo '<input name="command" type="hidden" id="command" value="Mail" readonly="readonly">';
    echo "<input type='hidden' name='username' value='" . $_POST['username'] . "' >";
    echo '<button class="np_button_link" type="submit">Mail</button>';
    echo '</form>';
    echo '</td>';
    // Files button
    echo '<td>';
    echo '<form target="' . $frame['content'] . '" method="post" action="files.php">';
    echo '<input name="command" type="hidden" id="command" value="Files" readonly="readonly">';
    echo "<input type='hidden' name='username' value='" . $_POST['username'] . "' >";
    echo '<button class="np_button_link" type="submit">Files</button>';
    echo '</form>';
    echo '</td>';
    // Configuration button
    echo '<td>';
    echo '<form target="' . $frame['content'] . '" method="post" action="user.php">';
    echo '<input name="command" type="hidden" id="command" value="Configuration" readonly="readonly">';
    echo "<input type='hidden' name='username' value='" . $_POST['username'] . "' >";
    echo '<button class="np_button_link" type="submit">Configuration</button>';
    echo '</form>';
    echo '</td>';
}
if ((isset($_COOKIE["mail_name"]))) {
    // Logout button
    echo '<td>';
    echo '<form target="' . $frame['content'] . '" method="post" action="user.php">';
    echo '<input name="command" type="hidden" id="command" value="Logout" readonly="readonly">';
    echo "<input type='hidden' name='username' value='" . $_POST['username'] . "' >";
    echo '<button class="np_button_link" type="submit">Logout</button>';
    echo '</form>';
    echo '</td>';
}
echo '<td width=100%></td></tr></table>';

if (isset($_POST['username'])) {
    $name = $_POST['username'];
    // Save name in cookie
    if ($setcookies == true) {
        setcookie("mail_name", stripslashes($name), time() + (3600 * 24 * 90), '/');
    }
} else {
    if ($setcookies) {
        if ((isset($_COOKIE["mail_name"])) && (! isset($name))) {
            $name = $_COOKIE["mail_name"];
        } else {
            $name = '';
        }
    }
}

if ($logged_in !== true) {
    echo '<form name="form1" method="post" action="user.php" enctype="multipart/form-data">';
    echo '<table class="mail_table_login">';
    echo '<tr><td><strong>Please Login</strong></td></tr>';
    echo '<tr><td>Username:</td><td><input name="username" type="text" id="username" value="' . $_POST['username'] . '"></td></tr>';
    echo '<tr><td>Password:</td><td><input name="password" type="password" id="password"></td></tr>';
    echo '<input name="command" type="hidden" value="Login">';
    echo '<input type="hidden" name="key" value="' . password_hash($CONFIG['thissitekey'] . $name, PASSWORD_DEFAULT) . '">';

    echo '<tr>';
    echo '<td><input type="submit" name="Submit" value="Login"></td>';
    echo '</tr>';
    echo '</table>';
    echo '</form>';
    exit(0);
}

$user = strtolower($_POST['username']);
$_SESSION['username'] = $user;
unset($user_config);
$userfile = $spooldir . '/' . $user . '-articleviews.dat';
if (is_file($userfile)) {
    $userdata = unserialize(file_get_contents($userfile));
}
if (!file_exists($config_dir . '/userconfig/' . $user . '.config')) {
    $user_config = array();
    file_put_contents($config_dir . '/userconfig/' . $user . '.config', serialize($user_array));
}

// Show Logged-In Message
if ($_POST['command'] != 'Configuration' && $_POST['command'] != 'SaveConfig') {
    if (isset($_POST['source'])) {
        $link = explode(':', $_POST['source']);
        $golink = '<a href="' . $link[1] . '">Continue to ' . $link[0] . '</a>';
    } else {
        $golink = '';
    }
    echo "<center>";
    echo "<hr><p>You are logged in as " . $_POST['username'] . "</p>";
    echo "<p>" . $golink . "</p>";
    echo '</center>';
}

// Apply Config
if (isset($_POST['command']) && $_POST['command'] == 'SaveConfig') {
    // Confirm password
    if (! check_bbs_auth($user, $_POST['confirm_password'])) {
        $message = '<b>Password Incorrect</b><br >Please try again';
        retry_configuration($message);
    }
    if ($OVERRIDES['disable_change_name'] != true) {
        if (trim($_POST['display_name']) == '') {
            $_POST['display_name'] = $user;
        }
        if (trim($_POST['display_email']) == '') {
            $_POST['display_email'] = get_user_config($user, 'email');
        }
        // Don't allow using already existing username or alias
        $value = get_user_config($_POST['display_name'], 'encryptionkey');
        if (! $value) {
            $value = get_config_file_value($config_dir . '/aliases.conf', strtolower($_POST['display_name']));
            // Alias exists if $value is true
            if (strtolower($value) == $user) {
                // But it's our alias so it's ok to use
                $value = false;
            }
        }
        if (isset($OVERRIDES['reserved_names'])) {
            $reserved_names = $OVERRIDES['reserved_names'];
        } else {
            $reserved_names = array(
                "admin",
                "sysop"
            );
        }
        if (isset($OVERRIDES['duplicate_aliases'])) {
            $dupe_ok = $OVERRIDES['duplicate_aliases'];
        } else {
            $dupe_ok = false;
        }
        foreach ($reserved_names as $name) {
            if (strtolower($_POST['display_name']) == strtolower($name)) {
                // It's a reserved alias
                $message = '<b>' . $_POST['display_name'] . "</b> is unavailable.<br >Please try again";
                retry_configuration($message);
            }
        }
        if ($value && (strtolower($_POST['display_name']) != $user)) {
            // It's someone else's username or alias
            $message = '<b>' . $_POST['display_name'] . "</b> is unavailable.<br >Please try again";
            retry_configuration($message);
        }
        // Validate email format
        if (filter_var($_POST['display_email'], FILTER_VALIDATE_EMAIL) == false) {
            // Email address format invalid. Format is important but does not need to be a real address
            $message = '</b> Display email format appears incorrect:<br><b>' . $_POST['display_email'] . '</b><br >Please try again';
            retry_configuration($message);
        }
        // Check if email already exists in user database
        if ($founduser = check_registered_email_addresses(trim($_POST['display_email']))) {
            // Email exists in database
            if (strtolower($user) != strtolower($founduser)) {
                // It's someone else's email
                $message = '<b>' . $_POST['display_email'] . "</b> is unavailable.<br >Please try again";
                retry_configuration($message);
            }
        }
        // New passwords do not match
        if ($_POST['password'] !== $_POST['password2']) {
            $message = '<b> New password entries do not match</b><br >Please try again';
            retry_configuration($message);
        }
        $user_config['display_name'] = trim($_POST['display_name']);
        $user_config['display_email'] = trim($_POST['display_email']);
        // Apply alias into $config_dir/aliases_conf
        if (strtolower($user_config['display_name'] != strtolower($_POST['username']))) {
            $value_unique = true;
            if ($dupe_ok) {
                foreach ($dupe_ok as $dupe) {
                    if ($dupe == strtolower($_POST['username'])) {
                        $value_unique = false;
                        break;
                    }
                }
            }
            save_config_value($config_dir . '/aliases.conf', strtolower($user_config['display_name']), strtolower($_POST['username']), $value_unique);
        }
    }
    $user_config['signature'] = $_POST['signature'];
    $user_config['xface'] = preg_replace("/[\n\r]/", "", $_POST['xface']);
    $user_config['timezone'] = $_POST['timezone'];
    $user_config['theme'] = $_POST['theme'];
    $user_config['hide_unsub'] = $_POST['hide_unsub'];
    $user_config['send_mail_to_email'] = $_POST['send_mail_to_email'];
    file_put_contents($config_dir . '/userconfig/' . $user . '.config', serialize($user_config));
    $_SESSION['theme'] = $user_config['theme'];
    $mysubs = explode("\n", $_POST['subscribed']);
    foreach ($mysubs as $sub) {
        $sub = trim($sub);
        if ($sub == '') {
            continue;
        }
        if (! isset($userdata[$sub])) {
            $userdata[$sub] = 0;
        }
        $newsubs[$sub] = $userdata[$sub];
    }
    file_put_contents($spooldir . '/' . $user . '-articleviews.dat', serialize($newsubs));

    // Block posters
    $blockfile = $spooldir . '/' . strtolower($_COOKIE['mail_name']) . '-blocked_posters.dat';
    if (file_exists($blockfile)) {
        $blocked_saved_config = unserialize(file_get_contents($blockfile));
    } else {
        $blocked_saved_config = null;
    }
    $block = preg_split("/\r\n|\n|\r/", $_POST['blocked_users_config']);
    foreach ($block as $blocked_user) {
        foreach ($blocked_saved_config as $key => $value) {
            if ($key == $blocked_user) {
                $newblocks[$key] = $value;
                break;
            }
        }
    }
    file_put_contents($blockfile, serialize($newblocks));
    // End Block posters

    $userdata = unserialize(file_get_contents($userfile));
    if ($userdata) {
        ksort($userdata);
    }

    // Save new password
    if ((trim($_POST['password']) != '') && ($_POST['password'] == $_POST['password2'])) {
        $userFilename = $config_dir . '/users/' . strtolower($user);
        file_put_contents($userFilename, password_hash($_POST['password'], PASSWORD_DEFAULT));
    }

    echo '<center>Configuration Saved for ' . $_POST['username'] . '</center>';
} else {
    $user_config = unserialize(file_get_contents($config_dir . '/userconfig/' . $user . '.config'));
}
// Get themes
$themedir = $rootdir . '/common/themes';
if (is_dir($themedir)) {
    if ($theme_list = opendir($themedir)) {
        while (($theme_dir = readdir($theme_list)) !== false) {
            if ($theme_dir == '.' || $theme_dir == '..' || ! is_dir($themedir . '/' . $theme_dir)) {
                continue;
            }
            $themes[] = $theme_dir;
        }
        closedir($theme_list);
    }
}

// Get settings for name and email
if ($OVERRIDES['disable_change_name'] != true) {
    if (isset($user_config['display_name'])) {
        $display_name = $user_config['display_name'];
    } else {
        $display_name = $_POST['username'];
    }
    if (isset($user_config['display_email'])) {
        $display_email = $user_config['display_email'];
    } else {
        if (($display_email = get_user_config($_POST['username'], 'email')) == false) {
            $display_email = $_POST['username'] . '@' . $CONFIG['email_tail'];
        }
    }
}
sort($themes);
if (isset($_REQUEST['command']) && $_REQUEST['command'] == 'Configuration') {
    // Use modifications from retry configuration
    if ($_POST['retry'] == "retry") {
        $display_name = $_POST['display_name'];
        $display_email = $_POST['display_email'];
        $user_config['signature'] = $_POST['signature'];
        $user_config['xface'] = preg_replace("/[\n\r]/", "", urldecode($_POST['xface']));
        $user_config['hide_unsub'] = $_POST['hide_unsub'];
        $user_config['subscribed'] = $_POST['subscribed'];
        $user_config['theme'] = $_POST['theme'];
        $user_config['blocked_users_config'] = $_POST['blocked_users_config'];
        $user_config['send_mail_to_email'] = $_POST['send_mail_to_email'];
    }
    // Show Config
    echo '<hr><h1 class="np_thread_headline"></h1>';
    echo '<table cellspacing="0" width="100%" class="config_results_table">';
    echo '<tr class="config_thread_head"><td class="config_thread_head"><h2>Settings for ' . $_POST['username'] . ':</h2></td></tr>';
    echo '<form method="post" action="user.php">';
    echo '<tr class="config_table_row">';
    if ($OVERRIDES['disable_change_name'] != true) {
        // User Display Name
        echo '<td class="config_table_row" style="word-wrap:break-word";><h3>Display Name for posts: </h3>';
        echo '<input name="display_name" type="text" id="username"value="' . $display_name . '" maxlength="40"></td>';
        echo '</tr>';
        // User Display Email
        echo '<tr class="config_table_row">';
        echo '<td class="config_table_row" style="word-wrap:break-word";><h3>Display Email for posts: </h3>';
        echo '<input name="display_email" type="text" id="username"value="' . $display_email . '" maxlength="40"></td>';
        echo '</tr>';
        // Send Mail by Email
        if ($OVERRIDES['disable_mail_to_email'] !== true) {
            if (get_user_config($_POST['username'], 'email_verified') == 'true') {
                if ($email_address = get_user_config($_POST['username'], 'email')) {
                    echo '<tr class="config_table_row">';
                    echo '<td class="config_table_row" style="word-wrap:break-word";><h3>Send Mail to my Internet Email: </h3>';
                    if (! isset($user_config['send_mail_to_email'])) {
                        $user_config['send_mail_to_email'] = 'false';
                    }
                    if ($user_config['send_mail_to_email'] == 'true') {
                        echo '<input type="radio" name="send_mail_to_email" id="send_mail_to_email" value="true" checked="checked">';
                    } else {
                        echo '<input type="radio" name="send_mail_to_email" id="send_mail_to_email" value="true">';
                    }
                    echo '<label for="send_mail_to_email"> Yes, Forward Mail to my Email</label><br >';

                    if ($user_config['send_mail_to_email'] == 'false') {
                        echo '<input type="radio" name="send_mail_to_email" id="send_mail_to_email" value="false" checked="checked">';
                    } else {
                        echo '<input type="radio" name="send_mail_to_email" id="send_mail_to_email" value="false">';
                    }
                    echo '<label for="send_mail_to_email"> No, Do Not Forward Mail to my Email</label><br >';

                    echo '</tr>';
                }
            }
        }
        echo '</td></tr>';
    }
    // Signature
    echo '<tr class="config_table_row">';
    echo '<td class="config_table_row" style="word-wrap:break-word";><h3>Signature:</h3></td>';
    echo '</tr>';
    echo '<tr class="config_table_row">';
    echo '<td class="config_table_row" style="word-wrap:break-word";><textarea class="configuration" id="signature" name="signature" rows="6" cols="70">' . $user_config['signature'];
    echo '</textarea></td>';
    echo '</tr>';
    // X-Face
    if ($OVERRIDES['disable_xface'] != true) {
        echo '<tr class="config_table_row">';
        echo '<td class="config_table_row" style="word-wrap:break-word";><h3>X-Face:</h3></td>';
        $xflink = $config_dir . 'xface.txt';
        if (file_exists($xflink)) {
            echo '</tr><td class="config_table_row" style="word-wrap:break-word";>' . file_get_contents($xflink) . '</td><tr>';
        }
        echo '</tr>';
        echo '<tr class="config_table_row">';
        echo '<td class="config_table_row" style="word-wrap:break-word";><textarea class="configuration" id="xface" name="xface" rows="4" cols="80">' . $user_config['xface'];
        echo '</textarea></td>';
    }
    // Theme
    echo '<tr class="config_table_row">';
    if (isset($user_config['theme']) && trim($user_config['theme']) != '') {
        echo '<td class="config_table_row" style="word-wrap:break-word";><h3>Theme: (' . $user_config['theme'] . ')</h3></td>';
    } else {
        echo '<td class="config_table_row" style="word-wrap:break-word";><h3>Theme:</h3></td>';
    }
    echo '</tr>';
    echo '<tr class="config_table_row">';
    echo '<td class="config_table_row" style="word-wrap:break-word">';
    echo '<select name="theme" class="theme_listbox" size="10">';
    foreach ($themes as $theme) {
        if ($theme == $user_config['theme']) {
            echo '<option value="' . $theme . '" selected="selected">' . $theme . '</option>';
        } else {
            echo '<option value="' . $theme . '">' . $theme . '</option>';
        }
    }
    echo '</select>';
    echo '</td>';
    echo '</tr>';
    // Subscriptions
    if (! isset($user_config['hide_unsub'])) {
        $user_config['hide_unsub'] = 'show';
    }
    echo '<tr class="config_table_row">';
    echo '<td class="config_table_row" style="word-wrap:break-word";><h3>Subscriptions:</h3></td>';
    echo '</tr>';
    echo '<tr class="config_table_row">';
    echo '<td class="config_table_row" style="word-wrap:break-word";>';
    echo '&nbsp;While viewing section pages:<br >';

    if ($user_config['hide_unsub'] == 'hide') {
        echo '<input type="radio" name="hide_unsub" id="hide" value="hide" checked="checked">';
    } else {
        echo '<input type="radio" name="hide_unsub" id="hide" value="hide">';
    }
    echo '<label for="hide_unsub"> Hide Unsubscribed Groups</label><br >';

    if ($user_config['hide_unsub'] == 'show') {
        echo '<input type="radio" name="hide_unsub" id="show" value="show" checked="checked">';
    } else {
        echo '<input type="radio" name="hide_unsub" id="show" value="show">';
    }
    echo '<label for="hide_unsub"> Show All Groups</label>';
    echo '</td></tr>';

    echo '<tr class="config_table_row">';
    echo '<td class="config_table_row" style="word-wrap:break-word";><h3>Subscribed groups:</h3></td>';
    echo '</tr>';
    echo '<tr class="config_table_row">';
    echo '<td class="config_table_row" style="word-wrap:break-word";><textarea class="configuration" id="subscribed" name="subscribed" rows="10" cols="40">';

    if (isset($user_config['subscribed'])) {
        $userdata = $user_config['subscribed'];
        print_r($user_config['subscribed']);
    } else {
        foreach ($userdata as $key => $value) {
            if ($key == "DO.NOT.DELETE") {
                continue;
            }
            echo $key . "\n";
        }
    }
    echo '</textarea></td>';
    echo '</tr>';

    // Blocklist
    if ($userdata = get_user_mail_auth_data($_COOKIE['mail_name'])) {
        $blockfile = $spooldir . '/' . strtolower($_COOKIE['mail_name']) . '-blocked_posters.dat';
        if (file_exists($blockfile)) {
            $blocked_users_config = unserialize(file_get_contents($blockfile));
        } else {
            $blocked_users_config = null;
        }
    }
    echo '<tr class="config_table_row">';
    echo '<td class="config_table_row" style="word-wrap:break-word";><h3>Blocklist:</h3> (you may only remove from this list)</td>';
    echo '</tr>';
    echo '<tr class="config_table_row">';
    echo '<td class="config_table_row" style="word-wrap:break-word";><textarea class="configuration" id="blocked_users_config" name="blocked_users_config" rows="10" cols="40">';
    if (isset($blocked_users_config)) {
        $blockdata = $user_config['blocked_users_config'];
        foreach ($blocked_users_config as $key => $value) {
            echo $key . "\n";
            //            echo $value . "\n";
        }
    }
    echo '</textarea></td>';
    echo '</tr>';

    // User Display Name
    echo '<tr class="config_table_row">';
    echo '<td class="config_table_row" style="word-wrap:break-word";><h3>New password: </h3>';
    echo '<input name="password" type="password" id="password" maxlength="40"></td>';
    echo '</tr>';
    // User Display Email
    echo '<tr class="config_table_row">';
    echo '<td class="config_table_row" style="word-wrap:break-word";><h3>Re-enter new password: </h3>';
    echo '<input name="password2" type="password" id="password2" maxlength="40"></td>';
    echo '</tr>';

    /*
     * // Timezone
     * echo '<td class="config_table_row" style="word-wrap:break-word";>Timezone offset (+/- hours from UTC):</td>';
     * echo '</tr><tr><td class="config_table_row" style="word-wrap:break-word";><input type="text" name="timezone" value="'.$user_config[timezone].'"></td>';
     * echo '</tr>';
     */
    // Password confirmation
    echo '<tr class="config_table_row_submit">';
    echo '<td class="config_table_row_submit" style="word-wrap:break-word";><h3>Current password: </h3><h4>(required)</h4>';
    echo '<input name="confirm_password" type="password" id="confirm_password" maxlength="40">';
 //   echo '</tr>';
 //   echo '<tr class="config_table_row_alt"><td class="config_table_row_alt">';
 //   echo '</td></tr>';
 //   echo '<tr class="config_table_row_submit">';
 //   echo '<td class="config_table_row_submit" style="word-wrap:break-word";>';
    echo '&nbsp;<button class="np_button_link" type="submit">Save Configuration</button>';
    echo '<a href="' . $_SERVER['PHP_SELF'] . '">Cancel</a>';
    echo '</td></tr>';
    echo '<input name="command" type="hidden" id="command" value="SaveConfig" readonly="readonly">';
    echo '</form>';
    echo '</tbody></table><br >';
} else {
    echo '<br >';
}
include "tail.inc";

function retry_configuration($message)
{
    global $frame;
    echo '<center>';
    echo $message;
    echo '<form target="' . $frame['content'] . '" method="post" action="user.php">';
    echo '<input name="command" type="hidden" id="command" value="Configuration" readonly="readonly">';
    echo "<input type='hidden' name='retry' value='retry' >";
    echo "<input type='hidden' name='username' value='" . $_POST['username'] . "' >";
    echo "<input type='hidden' name='display_name' value='" . $_POST['display_name'] . "' >";
    echo "<input type='hidden' name='display_email' value='" . $_POST['display_email'] . "' >";
    echo "<input type='hidden' name='signature' value='" . $_POST['signature'] . "' >";
    echo "<input type='hidden' name='xface' value='" . urlencode($_POST['xface']) . "' >";
    echo "<input type='hidden' name='hide_unsub' value='" . $_POST['hide_unsub'] . "' >";
    echo "<input type='hidden' name='subscribed' value='" . $_POST['subscribed'] . "' >";
    echo "<input type='hidden' name='theme' value='" . $_POST['theme'] . "' >";
    echo "<input type='hidden' name='blocked_users_config' value'" . $_POST['blocked_users_config'] . "' >";
    echo "<input type='hidden' name='send_mail_to_email' value'" . $_POST['send_mail_to_email'] . "' >";
    echo '<button class="np_button_link" type="submit">Return to Configuration</button>';
    echo '</center>';
    exit();
}
