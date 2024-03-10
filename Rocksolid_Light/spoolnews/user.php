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

include ("config.inc.php");
include ("newsportal.php");

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
    echo '<br />';
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

// How long should cookie allow user to stay logged in?
// 14400 = 4 hours
$auth_expire = 14400;
$logged_in = false;
if (! isset($_POST['username'])) {
    $_POST['username'] = $_COOKIE['mail_name'];
}
$name = $_POST['username'];
if (! isset($_POST['password'])) {
    $_POST['password'] = null;
}
if (! isset($_COOKIE['mail_auth'])) {
    $_COOKIE['mail_auth'] = null;
}
if ((password_verify($_POST['username'] . $keys[0] . get_user_config($_POST['username'], 'encryptionkey'), $_COOKIE['mail_auth'])) || (password_verify($_POST['username'] . $keys[1] . get_user_config($_POST['username'], 'encryptionkey'), $_COOKIE['mail_auth']))) {
    // if (((get_user_mail_auth_data($_COOKIE['mail_name'])) && password_verify($_POST['username'] . $keys[0] . get_user_config($_POST['username'], 'encryptionkey'), $_COOKIE['mail_auth'])) || (password_verify($_POST['username'] . $keys[1] . get_user_config($_POST['username'], 'encryptionkey'), $_COOKIE['mail_auth']))) {
    $logged_in = true;
} else {
    if (check_bbs_auth($_POST['username'], $_POST['password'])) {
        if ($ip_pass) {
            $_SESSION['pass'] = true;
        }
        $authkey = password_hash($_POST['username'] . $keys[0] . get_user_config($_POST['username'], 'encryptionkey'), PASSWORD_DEFAULT);
        $pkey = hash('crc32', get_user_config($_POST['username'], 'encryptionkey'));
        set_user_config(strtolower($_POST['username']), "pkey", $pkey);
        ?>
<script type="text/javascript">
       if (navigator.cookieEnabled)
         var authcookie = "<?php echo $authkey; ?>";
         var savename = "<?php echo stripslashes($name); ?>";
	 var auth_expire = "<?php echo $auth_expire; ?>";
	 var name_expire = "7776000";
	 var pkey = "<?php echo $pkey; ?>";
         document.cookie = "mail_auth="+authcookie+"; max-age="+auth_expire+"; path=/";
         document.cookie = "mail_name="+savename+"; max-age="+name_expire+"; path=/";
         document.cookie = "pkey="+pkey+"; max-age="+name_expire+"; path=/";
      </script>
<?php
        $logged_in = true;
    } else {
        echo 'Authentication Required';
    }
}

if (isset($_POST['command']) && $_POST['command'] == 'Configuration') {
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
    echo "<input type='hidden' name='username' value='" . $_POST['username'] . "' />";
    echo '<button class="np_button_link" type="submit">Mail</button>';
    echo '</form>';
    echo '</td>';
    // Files button
    echo '<td>';
    echo '<form target="' . $frame['content'] . '" method="post" action="files.php">';
    echo '<input name="command" type="hidden" id="command" value="Files" readonly="readonly">';
    echo "<input type='hidden' name='username' value='" . $_POST['username'] . "' />";
    echo '<button class="np_button_link" type="submit">Files</button>';
    echo '</form>';
    echo '</td>';
    // Configuration button
    echo '<td>';
    echo '<form target="' . $frame['content'] . '" method="post" action="user.php">';
    echo '<input name="command" type="hidden" id="command" value="Configuration" readonly="readonly">';
    echo "<input type='hidden' name='username' value='" . $_POST['username'] . "' />";
    echo '<button class="np_button_link" type="submit">Configuration</button>';
    echo '</form>';
    echo '</td>';
    // Logout button
    echo '<td>';
    echo '<form target="' . $frame['content'] . '" method="post" action="user.php">';
    echo '<input name="command" type="hidden" id="command" value="Logout" readonly="readonly">';
    echo "<input type='hidden' name='username' value='" . $_POST['username'] . "' />";
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
    echo '<table border="0" align="center" cellpadding="0" cellspacing="1">';
    echo '<form name="form1" method="post" action="user.php" enctype="multipart/form-data">';
    echo '<tr><td><strong>Please Login<br /></strong></td></tr>';
    echo '<tr><td>Username:</td><td><input name="username" type="text" id="username" value="' . $name . '"></td></tr>';
    echo '<tr><td>Password:</td><td><input name="password" type="password" id="password"></td></tr>';
    echo '<td><input name="command" type="hidden" id="command" value="Login" readonly="readonly"></td>';
    echo '<input type="hidden" name="key" value="' . password_hash($CONFIG['thissitekey'] . $name, PASSWORD_DEFAULT) . '">';
    echo '<td>&nbsp;</td>';
    echo '<td><input type="submit" name="Submit" value="Login"></td>';
    echo '</tr>';
    echo '</form>';
    echo '</table>';
    exit(0);
}

$user = strtolower($_POST['username']);
$_SESSION['username'] = $user;
unset($user_config);
$userfile = $spooldir . '/' . $user . '-articleviews.dat';
if (is_file($userfile)) {
    $userdata = unserialize(file_get_contents($userfile));
}
// Show Logged-In Message
if ($_POST['command'] != 'Configuration' && $_POST['command'] != 'SaveConfig') {
    if (isset($_POST['source'])) {
        $link = explode(':', $_POST['source']);
        $golink = '<a href="' . $link[1] . '">Continue to ' . $link[0] . '</a>';
    }
    echo "<center>";
    echo "<hr><p>You are logged in as " . $_POST['username'] . "</p>";
    echo "<p>" . $golink . "</p>";
    echo '</center>';
}
// Apply Config
if (isset($_POST['command']) && $_POST['command'] == 'SaveConfig') {
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
        if(isset($OVERRIDES['reserved_names'])) {
            $reserved_names = $OVERRIDES['reserved_names']; 
        } else {
            $reserved_names = array("admin", "sysop");
        }
        if(isset($OVERRIDES['duplicate_aliases'])) {
            $dupe_ok = $OVERRIDES['duplicate_aliases'];
        } else {
            $dupe_ok = false;
        }    
        foreach($reserved_names as $name) {
            if(strtolower($_POST['display_name']) == strtolower($name)) {
                // It's a reserved alias
                echo '<b>' . $_POST['display_name'] . "</b> is unavailable.<br />Please try again";
                echo '<form target="' . $frame['content'] . '" method="post" action="user.php">';
                echo '<input name="command" type="hidden" id="command" value="Configuration" readonly="readonly">';
                echo "<input type='hidden' name='username' value='" . $_POST['username'] . "' />";
                echo '<button class="np_button_link" type="submit">Return to Configuration</button>';
                exit();
            }
        }
        if ($value && (strtolower($_POST['display_name']) != $user)) {
            // It's someone else's username or alias
            echo '<b>' . $_POST['display_name'] . "</b> is unavailable.<br />Please try again";
            echo '<form target="' . $frame['content'] . '" method="post" action="user.php">';
            echo '<input name="command" type="hidden" id="command" value="Configuration" readonly="readonly">';
            echo "<input type='hidden' name='username' value='" . $_POST['username'] . "' />";
            echo '<button class="np_button_link" type="submit">Return to Configuration</button>';
            exit();
        }
        // Validate email format
        if (filter_var($_POST['display_email'], FILTER_VALIDATE_EMAIL) == false) {
            // Email address format invalid. Format is important but does not need to be a real address
            echo '</b> Display email format appears incorrect:<br><b>' . $_POST['display_email'] . '</b><br />Please try again';
            echo '<form target="' . $frame['content'] . '" method="post" action="user.php">';
            echo '<input name="command" type="hidden" id="command" value="Configuration" readonly="readonly">';
            echo "<input type='hidden' name='username' value='" . $_POST['username'] . "' />";
            echo '<button class="np_button_link" type="submit">Return to Configuration</button>';
            exit();
        }
        // Check if email already exists in user database
        if ($founduser = check_registered_email_addresses(trim($_POST['display_email']))) {
            // Email exists in database
            if (strtolower($user) != strtolower($founduser)) {
                // It's someone else's email
                echo '<b>' . $_POST['display_email'] . "</b> is unavailable.<br />Please try again";
                echo '<form target="' . $frame['content'] . '" method="post" action="user.php">';
                echo '<input name="command" type="hidden" id="command" value="Configuration" readonly="readonly">';
                echo "<input type='hidden' name='username' value='" . $_POST['username'] . "' />";
                echo '<button class="np_button_link" type="submit">Return to Configuration</button>';
                exit();
            }
        }
        $user_config['display_name'] = trim($_POST['display_name']);
        $user_config['display_email'] = trim($_POST['display_email']);
        // Apply alias into $config_dir/aliases_conf
        if(strtolower($user_config['display_name'] != strtolower($_POST['username']))) {
            $value_unique = true;
            if($dupe_ok) {
                foreach($dupe_ok as $dupe) {
                    if($dupe == strtolower($_POST['username'])) {
                        $value_unique = false;
                        break;
                    }
                }
            }
            save_config_value($config_dir . '/aliases.conf', strtolower($user_config['display_name']), strtolower($_POST['username']), $value_unique);
        }
    }
    $user_config['signature'] = $_POST['signature'];
    $user_config['xface'] = $_POST['xface'];
    $user_config['timezone'] = $_POST['timezone'];
    $user_config['theme'] = $_POST['listbox'];
    $user_config['hide_unsub'] = $_POST['hide_unsub'];
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
    $userdata = unserialize(file_get_contents($userfile));
    if ($userdata) {
        ksort($userdata);
    }
    echo 'Configuration Saved for ' . $_POST['username'];
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
if (isset($_POST['command']) && $_POST['command'] == 'Configuration') {
    // Show Config
    echo '<hr><h1 class="np_thread_headline"></h1>';
    echo '<table cellspacing="0" width="100%" class="np_results_table">';
    echo '<tr class="np_thread_head"><td class="np_thread_head"><h2>Settings for ' . $_POST['username'] . ':</h2></td></tr>';
    echo '<form method="post" action="user.php">';
    echo '<tr class="np_result_line1">';
    if ($OVERRIDES['disable_change_name'] != true) {
        // User Display Name
        echo '<td class="np_result_line1" style="word-wrap:break-word";><h3>Display Name for posts: </h3>';
        echo '<input name="display_name" type="text" id="username"value="' . $display_name . '" maxlength="40"></td>';
        echo '</tr>';
        // User Display Email
        echo '<td class="np_result_line1" style="word-wrap:break-word";><h3>Display Email for posts: </h3>';
        echo '<input name="display_email" type="text" id="username"value="' . $display_email . '" maxlength="40"></td>';
        echo '</tr>';
    }
    // Signature
    echo '<td class="np_result_line1" style="word-wrap:break-word";><h3>Signature:</h3></td>';
    echo '</tr><tr><td class="np_result_line1" style="word-wrap:break-word";><textarea class="configuration" id="signature" name="signature" rows="6" cols="70">' . $user_config['signature'];
    echo '</textarea></td>';
    echo '</tr>';
    // X-Face
    echo '<td class="np_result_line1" style="word-wrap:break-word";><h3>X-Face:</h3></td>';
    echo '</tr><tr><td class="np_result_line1" style="word-wrap:break-word";><textarea class="configuration" id="xface" name="xface" rows="4" cols="80">' . $user_config['xface'];
    echo '</textarea></td>';
    echo '</tr>';
    // Theme
    if (isset($user_config['theme'])) {
        echo '<td class="np_result_line1" style="word-wrap:break-word";><h3>Theme: (' . $user_config['theme'] . ')</h3></td>';
    } else {
        echo '<td class="np_result_line1" style="word-wrap:break-word";><h3>Theme:</h3></td>';
    }
    echo '</tr><tr><td class="np_result_line1" style="word-wrap:break-word">';
    echo '<select name="listbox" class="theme_listbox" size="10">';
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
    if(!isset($user_config['hide_unsub'])) {
        $user_config['hide_unsub'] = 'show';
    }
    echo '<td class="np_result_line1" style="word-wrap:break-word";><h3>Subscriptions:</h3></td>';
    echo '<tr><td class="np_result_line1" style="word-wrap:break-word";>';
    echo '&nbsp;While viewing section pages:<br />';
    
    if($user_config['hide_unsub'] == 'hide') {
        echo '<input type="radio" name="hide_unsub" id="hide" value="hide" checked="checked">';
    } else {
        echo '<input type="radio" name="hide_unsub" id="hide" value="hide">';
    }
    echo '<label for="hide_unsub"> Hide Unsubscribed Groups</label><br />';
    
    if($user_config['hide_unsub'] == 'show') {
        echo '<input type="radio" name="hide_unsub" id="show" value="show" checked="checked">';
    } else {
        echo '<input type="radio" name="hide_unsub" id="show" value="show">';
    }
    echo '<label for="hide_unsub"> Show All Groups</label>';
    echo '</td></tr>';

    echo '<td class="np_result_line1" style="word-wrap:break-word";><h3>Subscribed groups:</h3></td>';
    echo '</tr><tr><td class="np_result_line1" style="word-wrap:break-word";><textarea class="configuration" id="subscribed" name="subscribed" rows="10" cols="40">';
    foreach ($userdata as $key => $value) {
        if($key == "DO.NOT.DELETE") {
            continue;
        }
        echo $key . "\n";
    }
    echo '</textarea></td>';
    echo '</tr>';
    /*
     * // Timezone
     * echo '<td class="np_result_line1" style="word-wrap:break-word";>Timezone offset (+/- hours from UTC):</td>';
     * echo '</tr><tr><td class="np_result_line1" style="word-wrap:break-word";><input type="text" name="timezone" value="'.$user_config[timezone].'"></td>';
     * echo '</tr>';
     */
    echo '<td class="np_result_line2" style="word-wrap:break-word";>';
    echo '<button class="np_button_link" type="submit">Save Configuration</button>';
    echo '<a href="' . $_SERVER['PHP_SELF'] . '">Cancel</a>';
    echo '</td></tr>';
    echo '<input name="command" type="hidden" id="command" value="SaveConfig" readonly="readonly">';
    echo '</form>';
    echo '</tbody></table><br />';
} else {
    echo '<br />';
}
include "tail.inc";
?>
