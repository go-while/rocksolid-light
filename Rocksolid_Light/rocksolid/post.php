<?php
/*
 * rslight NNTP<->HTTP Gateway
 * Download: https://news.novabbs.com/getrslight
 *
 * Based on Newsportal by Florian Amrhein
 *
 * E-Mail: retroguy@novabbs.com
 * Web: https://news.novabbs.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */
include "config.inc.php";
$CONFIG = include($config_file);
include $file_newsportal;

include "head.inc";

if (disable_page_by_user_agent($client_device, "bot", "Post")) {
    echo "<center>Page Disabled</center>";
    include "tail.inc";
    exit();
}

if (! isset($_SESSION['last_access']) || (time() - $_SESSION['last_access']) > 60) {
    $_SESSION['last_access'] = time();
}

$logfile = $logdir . '/post.log';

@$fieldnamedecrypt = $_REQUEST['fielddecrypt'];
@$newsgroups = $_REQUEST["newsgroups"];
@$group = $_REQUEST["group"];
@$type = $_REQUEST["type"];
@$subject = stripslashes($_POST[md5($fieldnamedecrypt . "subject")]);
@$name = $_POST[md5($fieldnamedecrypt . "name")];
@$email = $_POST[md5($fieldnamedecrypt . "email")];
@$body = $_POST[md5($fieldnamedecrypt . "body")];
@$abspeichern = $_REQUEST["abspeichern"];
@$references = $_REQUEST["references"];
@$id = $_REQUEST["id"];

if (isset($_REQUEST['followupto']) && trim($_REQUEST['followupto']) != '') {
    $followupto = trim($_REQUEST['followupto']);
    $followupto = sanitize_header($followupto);
} else {
    $followupto = null;
}

// Check some header strings for bad characters
$newsgroups = sanitize_header($newsgroups);
$subject = sanitize_header($subject);
$email = sanitize_header($email);

// Load name from cookies
if ($setcookies) {
    if ((isset($_COOKIE["mail_name"])) && (! isset($name)))
        $name = $_COOKIE["mail_name"];
}

// Truncate username at 30 characters to avoid abuse
$name = substr($name, 0, 30);
$name = sanitize_header($name);

$logged_in = false;
if (trim($name) != '') {
    $logged_in = verify_logged_in(trim(strtolower($name)));
}

// This will log user post info (group and username)
$enable_post_log = false;
if ($OVERRIDES['enable_post_log'] > 0) {
    $enable_post_log = $OVERRIDES['enable_post_log'];
}

$allow_ng_header_edit_post = true;
$allow_ng_header_edit_reply = false;

if (isset($OVERRIDES['allow_ng_header_edit'])) {
    if ($OVERRIDES['allow_ng_header_edit'] == 'post') {
        $allow_ng_header_edit_post = true;
    } else {
        $allow_ng_header_edit_post = false;
    }
    if ($OVERRIDES['allow_ng_header_edit'] == 'reply') {
        $allow_ng_header_edit_reply = true;
    } else {
        $allow_ng_header_edit_reply = false;
    }
    if ($OVERRIDES['allow_ng_header_edit'] == 'both') {
        $allow_ng_header_edit_post = true;
        $allow_ng_header_edit_reply = true;
    }
    if ($OVERRIDES['allow_ng_header_edit'] == 'none') {
        $allow_ng_header_edit_post = false;
        $allow_ng_header_edit_reply = false;
    }
}

$allow_ngs_edit = false;
if ($type == 'reply') {
    if ($allow_ng_header_edit_reply) {
        $allow_ngs_edit = true;
    }
    if (isset($OVERRIDES['max_crosspost_reply']) && $OVERRIDES['max_crosspost_reply'] > 0) {
        $max_crosspost = $OVERRIDES['max_crosspost_reply'];
    } else {
        $max_crosspost = 12;
    }
} else {
    if ($allow_ng_header_edit_post) {
        $allow_ngs_edit = true;
    }
    if (isset($OVERRIDES['max_crosspost_post']) && $OVERRIDES['max_crosspost_post'] > 0) {
        $max_crosspost = $OVERRIDES['max_crosspost_post'];
    } else {
        $max_crosspost = 3;
    }
}

if (! isset($group) && isset($newsgroups)) {
    $group = $newsgroups;
}
// Save name in cookies
if (strcmp(stripslashes($name), $CONFIG['anonusername']) !== 0) {
    if (($setcookies == true) && (isset($abspeichern)) && ($abspeichern == "ja")) {
        setcookie("mail_name", stripslashes($name), time() + (3600 * 24 * 90), "/");
    }
}
if ((isset($post_server)) && ($post_server != ""))
    $server = $post_server;
if ((isset($post_port)) && ($post_port != ""))
    $port = $post_port;

global $synchro_user, $synchro_pass;
// check to which groups the user is allowed to post to
$thisgroup = _rawurldecode($_REQUEST['group']);

// Is this a reply to an article containing Followup-To?
if (isset($_REQUEST['fgroups'])) {
    $thisgroup = preg_replace('!\s+!', ',', $_REQUEST['fgroups']);
    $thisgroup = preg_replace('/\,+/', ',', $thisgroup);
}

$newsgroups = $thisgroup;
if ($_REQUEST['returngroup']) {
    $returngroup = $_REQUEST['returngroup'];
} else {
    $returngroup = $thisgroup;
}

$linkgroups = preg_split("/[\s,]+/", $returngroup);
foreach ($linkgroups as $linkgroup) {
    $linkgroup = trim($linkgroup);
    if (get_section_by_group($linkgroup)) {
        $returngroup = $linkgroup;
        break;
    }
}

echo '<h1 class="np_thread_headline">';
echo '<a href="' . $file_index . '" target=' . $frame['menu'] . '>' . basename(getcwd()) . '</a> / ';
echo '<a href="' . $file_thread . '?group=' . rawurlencode($returngroup) . '" target=' . $frame["content"] . '>' . htmlspecialchars(group_display_name($returngroup)) . '</a>';
if (isset($type) && $type == 'post') {
    echo ' / ' . $subject . '</h1>';
} else {
    echo '</h1>';
}

// has the user write-rights on the newsgroups?
if ((function_exists("npreg_group_has_read_access") && ! npreg_group_has_read_access($newsgroups)) || (function_exists("npreg_group_has_write_access") && ! npreg_group_has_write_access($newsgroups))) {
    die("access denied");
}

// Load name and email from the registration system, if available
if (function_exists("npreg_get_name")) {
    $name = npreg_get_name();
}

if (function_exists("npreg_get_email")) {
    $email = npreg_get_email();
    $form_noemail = true;
}

if (! strcmp($name, $CONFIG['anonusername']) && (isset($CONFIG['anonuser']))) {
    $userpass = $CONFIG['anonuserpass'];
    $email = $name . $CONFIG['email_tail'];
    $_SESSION['pass'] = false;
} else {
    $userpass = $email;
    $request = "email";
    $get_email = get_user_config($name, $request);
    if ($get_email === FALSE) {
        $email = $name . $CONFIG['email_tail'];
    } else {
        $email = trim($get_email);
    }
}

if (isset($CONFIG['synchronet']) && ($CONFIG['synchronet'] == true)) {
    $synchro_user = $name;
    $synchro_pass = $userpass;
}

if ($name == "")
    $name = $_SERVER['REMOTE_USER'];

if ((! isset($references)) || ($references == "")) {
    $references = false;
}

if (! isset($type)) {
    $type = "new";
}

if ($type == "new") {
    $subject = "";
    $bodyzeile = "";
    $show = 1;
}

// Is there a new article to post to the newsserver?
if ($type == "post") {
    $show = 0;
    if (! $CONFIG['synchronet']) {
        if (! $logged_in) {
            if (check_bbs_auth(trim($name), $userpass) == FALSE) {
                $type = "retry";
                $error = $text_error["auth_error"];
                $_SESSION['pass'] = false;
                $logged_in = false;
            } else {
                $_SESSION['pass'] = true;
                $logged_in = true;
                if (set_user_logged_in_cookies($name, $keys)) {
                    file_put_contents($auth_log, "\n" . logging_prefix() . " SET AUTH COOKIES for: " . $name, FILE_APPEND);
                }
            }
        } else {
            // Update cookie times to stay logged in
            if (set_user_logged_in_cookies($name, $keys)) {
                file_put_contents($auth_log, "\n" . logging_prefix() . " UPDATED AUTH COOKIES for: " . $name, FILE_APPEND);
            }
        }
    }
    // Check that user has not been recently banned
    if (! is_file($config_dir . '/users/' . strtolower(trim($name)))) {
        $type = "retry";
        $error = $text_error["auth_error"];
        $_SESSION['pass'] = false;
        $logged_in = false;
    }
    // error handling
    if (trim($body) == "") {
        $type = "retry";
        $error = $text_post["missing_message"];
    }
    if ((trim($email) == "") && (! isset($anonym_address))) {
        $type = "retry";
        $error = $text_post["missing_email"];
    }
    if (($email) && (! validate_email(trim($email)))) {
        $type = "retry";
        $error = $text_post["error_wrong_email"];
    }
    if (trim($name) == "") {
        $type = "retry";
        $error = $text_post["missing_name"];
    }
    if (trim($subject) == "") {
        $type = "retry";
        $error = $text_post["missing_subject"];
    }
    if ($allow_ngs_edit) {
        $grouptotal = preg_split("/( |\,)/", $newsgroups);
        if (count($grouptotal) > $max_crosspost) {
            $type = "retry";
            $error = "Too many newsgroups";
        }
    }
    // captcha-check
    if (($post_captcha) && (captcha::check() == false)) {
        $type = "retry";
        $error = $text_post["captchafail"];
    }

    if ($type == "post") {
        $name = trim($name);
        if (! $CONFIG['readonly']) {
            // post article to the newsserver
            if ($references)
                $references_array = explode(" ", $references);
            else
                $references_array = false;
            if (($email == "") && (isset($anonym_address)))
                $nemail = $anonym_address;
            else
                $nemail = $email;
            $rate_limit = get_user_config($name, 'rate_limit');
            if (($rate_limit !== FALSE) && ($rate_limit > 0)) {
                $CONFIG['rate_limit'] = $rate_limit;
            }
            if ($CONFIG['rate_limit'] == true) {
                $postsremaining = check_rate_limit($name);
                if ($postsremaining < 1) {
                    $wait = check_rate_limit($name, 0, 1);
                    echo 'You have reached the limit of ' . $CONFIG['rate_limit'] . ' posts per hour.<br />Please wait ' . round($wait) . ' minutes before posting again.';
                    echo '<p><a href="' . $file_thread . '?group=' . urlencode($returngroup) . '">' . $text_post["button_back"] . '</a> ' . $text_post["button_back2"] . ' ' . group_display_name($returngroup) . '</p>';
                    return;
                }
            }

            // Wrap long lines in message body
            $body = wrap_post($body);

            if (isset($_FILES["photo"]) && $_FILES["photo"]["error"] == 0) {
                $_FILES['photo']['name'] = preg_replace('/[^a-zA-Z0-9\.]/', '_', $_FILES['photo']['name']);
                // There is an attachment to handle
                $message = message_post(quoted_printable_encode($subject), $nemail . " (" . quoted_printable_encode($name) . ")", $newsgroups, $references_array, addslashes($body), $_POST['encryptthis'], $_POST['encryptto'], strtolower($name), $_POST['fromname'], $followupto, true);
            } else {
                $message = message_post(quoted_printable_encode($subject), $nemail . " (" . quoted_printable_encode($name) . ")", $newsgroups, $references_array, addslashes($body), $_POST['encryptthis'], $_POST['encryptto'], strtolower($name), $_POST['fromname'], $followupto);
            }
            // Article sent without errors, or duplicate?
            if ((substr($message, 0, 3) == "240") || (substr($message, 0, 7) == "441 435")) {
                // Is there a moderated group in Newsgroups: ?
                if (is_moderated($newsgroups)) {
                    echo '<p>** <i>Moderated Newsgroup **</p>';
                    echo '<p>** <i>Message Queued for Moderation **</p>';
                } else {
                    echo '<p>' . $text_post["message_posted2"] . '</p>';
                }
                if (isset($CONFIG['auto_return']) && ($CONFIG['auto_return'] == true)) {
                    echo '<meta http-equiv="refresh" content="0;url=' . $file_thread . '?group=' . urlencode($returngroup) . '"';
                }
                if ($CONFIG['rate_limit'] == true) {
                    $postsremaining = check_rate_limit($name, 1);
                    echo 'You have ' . $postsremaining . ' posts remaining of ' . $CONFIG['rate_limit'] . ' posts per hour.<br />';
                    if ($postsremaining < 1) {
                        $wait = check_rate_limit($name, 0, 1);
                        echo 'Please wait ' . round($wait) . ' minutes before posting again.<br />';
                    }
                }
                echo '<p><a href="' . $file_thread . '?group=' . urlencode($returngroup) . '">Back</a></p>';
            } else {
                // article not accepted by the newsserver
                $type = "retry";
                $error = $text_post["error_newsserver"] . "<br><pre>$message</pre>";
            }
        } else {
            echo $text_post["error_readonly"];
        }
    }
}

// A reply of an other article.
if ($type == "reply") {
    $message = message_read($id, 0, $newsgroups);
    $head = $message->header;

    $body = explode("\n", rtrim($message->body[0]));
    nntp_close($ns);
    if ($head->name != "") {
        $bodyzeile = $head->name;
    } else {
        $bodyzeile = $head->from;
    }
    // For Synchronet use (deprecated)
    $fromname = $bodyzeile;

    // Set quote reply format (On date somebody wrote:)
    if (!isset($OVERRIDES['quote_head'])) {
        $OVERRIDES['quote_head'] = 'date_name';
    }
    switch ($OVERRIDES['quote_head']) {
        case 'date_name':
            $bodyzeile = "On " . date("D, j M Y G:i:s O,", $head->date) . " " . $bodyzeile . $text_post["wrote_suffix"] . "\n\n";
            break;
        case 'msgid_name':
            $bodyzeile = "In " . $head->id . ", " . $bodyzeile . $text_post["wrote_suffix"] . "\n\n";
            break;
        case 'date_msgid_name':
            $bodyzeile = "On " . date("D, j M Y G:i:s O,", $head->date) . " in " . $head->id . ", " . $bodyzeile . $text_post["wrote_suffix"] . "\n\n";
            break;
        case 'name':
            $bodyzeile = $text_post["wrote_prefix"] . $bodyzeile . $text_post["wrote_suffix"] . "\n\n";
            break;
        default:
            $bodyzeile = "On " . date("D, j M Y G:i:s O,", $head->date) . " " . $bodyzeile . $text_post["wrote_suffix"] . "\n\n";
            break;
    }

    for ($i = 0; $i <= count($body) - 1; $i++) {
        if ((isset($cutsignature)) && ($cutsignature == true) && ($body[$i] == '-- ')) {
            break;
        }
        // Try not to quote blank lines at the end of all quotes
        if ((trim($body[$i]) == "") && ($body[$i + 1] == '-- ' || $i >= count($body) - 1)) {
        } else {
            // Remove spaces from starting quote '>' characters
            $body = preg_replace("/^> >/", ">>", $body);

            // Quote blank lines? YES by default
            if (! isset($OVERRIDES['quote_blank_lines']) || $OVERRIDES['quote_blank_lines'] == true) {
                if ($body[$i][0] == '>')
                    $bodyzeile .= ">" . $body[$i] . "\n";
                else
                    $bodyzeile .= "> " . $body[$i] . "\n";
            } else {
                if (trim($body[$i]) != "") {
                    if ($body[$i][0] == '>')
                        $bodyzeile .= ">" . $body[$i] . "\n";
                    else
                        $bodyzeile .= "> " . $body[$i] . "\n";
                } else {
                    $bodyzeile .= "\n";
                }
            }
        }
    }
    $subject = $head->subject;
    // Offer choice of whether to use Followup-To
    $has_followup = false;
    if (isset($head->followup) && ($head->followup != "")) {
        $newsgroups = $head->followup;
        $has_followup = $head->newsgroups;
    } else {
        $newsgroups = $head->newsgroups;
    }
    splitSubject($subject);
    $subject = "Re: " . $subject;
    // Cut off old parts of a subject
    // for example: 'foo (was: bar)' becomes 'foo'.
    $subject = preg_replace('/(\(wa[sr]: .*\))$/i', '', $subject);
    $show = 1;
    $references = false;
    if (isset($head->references[0])) {
        for ($i = 0; $i <= count($head->references) - 1; $i++) {
            $references .= $head->references[$i] . " ";
        }
    }
    $references .= $head->id;
}

if ($type == "retry") {
    $show = 1;
    $bodyzeile = $body;
}

if ($show == 1) {

    if ($newsgroups == "") {
        echo $text_post["followup_not_allowed"];
        echo " " . $newsgroups;
    } else {
        // check that we can post to the newsgroup
        $ngroups = preg_split("/[\s,]+/", $newsgroups);
        $found = false;
        foreach ($ngroups as $group) {
            $group = trim($group);
            if (get_section_by_group($group)) {
                $found = true;
                break;
            }
        }
        // show post form
        $fieldencrypt = md5(rand(1, 10000000));
        if ($type == 'reply') {
            echo '<h1 class="np_post_headline">' . $text_post["group_head_reply"] . group_display_name($newsgroups) . $text_post["group_tail"];
        } else {
            echo '<h1 class="np_post_headline">' . $text_post["group_head"] . group_display_name($newsgroups) . $text_post["group_tail"];
        }
        if (! $found) {
            echo ' (posting will fail - no such group)';
        }
        echo '</h1>';

        if (isset($error))
            echo "<p>$error</p>";

        echo '<form action="' . $file_post . '" method="post" name="postform"';
        echo 'enctype="multipart/form-data">';

        echo '<div class="np_post_header">';
        echo '<table><tr>';
        echo '<td align="right"><b>' . $text_header["subject"] . '</b></td>';
        echo '<td><input class="post" type="text" ';
        echo 'name="' . md5($fieldencrypt . "subject") . '" ';
        echo 'value="' . htmlspecialchars($subject) . '" ';
        echo 'size="40" maxlength="' . $thread_maxSubject . '"></td>';
        echo '</tr><tr>';

        if ($has_followup) {
            echo '<td align="right"><b>Newsgroups:&nbsp;</b>';
            echo '</td><td>';

            echo '<input type="radio" id="hasfollowup" name="fgroups" value="' . $head->followup . '" checked>';
            echo '&nbsp;';
            echo '<label for="followup">' . $head->followup . ' (Followup-To is set';
            if (! get_section_by_group($head->followup)) {
                echo ' but <b><i>posting will fail - no such group </i></b>';
            }
            echo ')</label></td>';
            echo '</tr><tr>';
            echo '<td align="right"><b>or:&nbsp;</b>';
            echo '</td><td>';
            echo '<input type="radio" id="nofollowup" name="fgroups" value="' . $head->newsgroups . '">';
            echo '&nbsp;';
            echo '<label for="newsgroups">' . $head->newsgroups . '</label>';
            echo '</tr><tr>';
        } else {
            if (!isset($OVERRIDES['disable_ngs_edit']) || $OVERRIDES['disable_ngs_edit'] == false) {
                echo '<td align="right"><b>Newsgroups:</b></td>';
                echo '<td>';
                if ($allow_ngs_edit) {
                    echo '<input tclass="post" type="text" name="fgroups" size="40" maxlength="240" value="' . $newsgroups . '">';
                    echo "&nbsp;(max $max_crosspost groups)";
                    echo '</td><td>';
                    echo '</tr><tr>';
                    echo '<td align="right"><b>Followup-To:</b></td>';
                    echo '<td>';
                    echo '<input tclass="post" type="text" name="followupto" size="40" value="' . $followupto . '" maxlength="80" placeholder="name of group to redirect replies">';
                    echo "&nbsp;(optional)";
                } else {
                    echo '<input tclass="post" type="text" name="fgroups" size="40" value="' . $newsgroups . '" readonly>';
                }
            } else {
                echo '<input tclass="post" type="hidden" name="fgroups" value="' . $newsgroups . '">';
            }
            echo '</td><td>';
            echo '</tr><tr>';
        }

        echo '<td align="right"><b>' . $text_post["name"] . '</b></td>';
        echo '<td align="left">';
        if (! isset($name) && $CONFIG['anonuser'])
            $name = $CONFIG['anonusername'];
        echo '<input class="post" type="text" name="' . md5($fieldencrypt . "name") . '"';
        if (isset($name))
            echo 'value="' . htmlspecialchars($name) . '"';
        if ($logged_in && isset($name)) {
            echo 'size="40" maxlength="40" readonly>';
            file_put_contents($auth_log, "\n" . logging_prefix() . " AUTH SET for: " . $name, FILE_APPEND);
        } else {
            echo 'size="40" maxlength="40">';
            file_put_contents($auth_log, "\n" . logging_prefix() . " AUTH NOT SET for: " . $name, FILE_APPEND);
        }
        if ($CONFIG['anonuser'])
            echo '&nbsp;or "' . $CONFIG['anonusername'] . '" with no password';
        echo '</td></tr><tr>';
        echo '<td align="right"><b>' . $text_post["password"] . '</b></td>';
        echo '<td align="left">';

        if ($logged_in && isset($name)) {
            echo '<input class="post" type="password" name="' . md5($fieldencrypt . "email") . '"value="**********"';
            echo 'size="40" maxlength="40" readonly>';
        } else {
            echo '<input class="post" type="password" name="' . md5($fieldencrypt . "email") . '"';
            echo 'size="40" maxlength="40">';
        }
        // Check for custom name/email from user configuration
        if ($OVERRIDES['disable_change_name'] != true) {
            $user_config = unserialize(file_get_contents($config_dir . '/userconfig/' . strtolower($name) . '.config'));
            if (isset($user_config['display_name']) && trim($user_config['display_name']) != '') {
                if (isset($user_config['display_email']) && trim($user_config['display_email']) != '') {
                    echo '<tr><td align="right">';
                    echo '<b>From: </b></td>';
                    $showemail = '<' . $user_config['display_email'] . '>';
                    echo '<td align="left">';
                    echo '<input class="post" type="text" value="' . $user_config['display_name'] . ' ' . htmlspecialchars($showemail) . '" size="40" maxlength="40" readonly>';
                    // echo $user_config['display_name'] . ' ' . htmlspecialchars($showemail);
                    echo '</td></tr>';
                }
            }
        }
        echo '<input class="post" type="hidden" name="fromname" value="' . $fromname . '">';
        echo '</td></tr>';
        // May we post encrypted messages to this group?
        if (check_encryption_groups($newsgroups)) {
            echo '<tr>';
            echo '<td align="left"><input type="checkbox" name="encryptthis"';
            echo 'value="encrypt"> <b>Encrypt to:</b></td>';
            echo '<td><input type="text" name="encryptto" value="' . $fromname . '"></td>';
            echo '</tr>';
        }
        echo '</table></div>';

        echo '<div class="np_post_body">';
        echo '<table><tr>';
        echo '<td><b>' . $text_post["message"] . '</b>';
        echo '&nbsp;&nbsp;<font size="2em">(Lines will wrap at ' . $wrap_width . ' characters after posting)</font>';
        echo '<br> <textarea cols="' . $wrap_width . '"';
        echo 'class="postbody" id="postbody" cols="72"';
        echo 'name="' . md5($fieldencrypt . "body") . '" wrap="soft">';

        $bodyzeile = wrap_post($bodyzeile);
        if ((isset($bodyzeile)) && ($post_autoquote))
            echo htmlspecialchars(rtrim($bodyzeile) . "\n");
        if (is_string($body))
            echo htmlspecialchars(rtrim($body) . "\n");
        echo '</textarea></td></tr><tr><td>';
        if (! $post_autoquote) {
            echo '<input type="hidden" id="hidebody"';
            echo 'value="';
            if (isset($bodyzeile)) {
                echo htmlspecialchars(rtrim($bodyzeile) . "\n");
            }
            echo '">';

?>
            <script language="JavaScript">
                <!--
                function quoten() {
                    document.getElementById("postbody").value = document.getElementById("hidebody").value;
                    document.getElementById("hidebody").value = "";
                }
                //
                -->
            </script>

        <?php } ?>

        <input type="submit" value="<?php echo $text_post["button_post"]; ?>">
        <?php if ($setcookies == true) { ?>
            &nbsp;
            <input tabindex="100" type="Button" name="quote"
                value="<?php echo $text_post["quote"] ?>"
                onclick="quoten(); this.style.visibility= 'hidden';">
            &nbsp;

        <?php
        }
        if (! isset($OVERRIDES['disable_attach'])) {
            $OVERRIDES['disable_attach'] = array();
        }
        if (! in_array($config_name, $OVERRIDES['disable_attach'])) {
            echo '&nbsp;';
            echo '<input type="file" name="photo" id="fileSelect" value="fileSelect" accept="image/*,audio/*,text/*,application/pdf">';
            echo '</td></tr>';
        }
        if ($post_captcha) {
            echo '<tr><td>';
            echo captcha::form($text_post["captchainfo1"], $text_post["captchainfo2"]);
            echo '</td></tr>';
        }
        ?>

        </table>
        </div>
        <input type="hidden" name="type" value="post">
        <input type="hidden" name="newsgroups"
            value="<?php echo htmlspecialchars($newsgroups); ?>">
        <input type="hidden" name="references"
            value="<?php echo htmlentities($references); ?>">
        <input type="hidden" name="group"
            value="<?php echo htmlspecialchars($newsgroups); ?>">
        <input type="hidden" name="returngroup"
            value="<?php echo htmlspecialchars($thisgroup); ?>">
        <input type="hidden" name="fielddecrypt"
            value="<?php echo htmlspecialchars($fieldencrypt); ?>">
        </form>

<?php }
} ?>