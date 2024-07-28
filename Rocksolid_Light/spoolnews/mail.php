<?php
session_start();

include "config.inc.php";
include "newsportal.php";
include $config_dir . "/gpg.conf";

if (isset($_COOKIE['tzo'])) {
    $offset = $_COOKIE['tzo'];
} else {
    $offset = $CONFIG['timezone'];
}

if (! isset($_POST['command'])) {
    $_POST['command'] = null;
}

$logfile = $logdir . '/mail.log';
$keyfile = $spooldir . '/keys.dat';
$keys = unserialize(file_get_contents($keyfile));

$title .= ' - Mail';
include "head.inc";

if (disable_page_by_user_agent($client_device, "bot", "Mail")) {
    echo "<center>Page Disabled</center>";
    include "tail.inc";
    exit();
}

echo '<h1 class="np_thread_headline">';

echo '<a href="mail.php" target=' . $frame['menu'] . '>mail</a> / ';
echo htmlspecialchars($_POST['username']) . '</h1>';

echo '<table cellpadding="0" cellspacing="0" class="np_buttonbar"><tr>';
// New Message button
if ($_POST['command'] !== 'Send') {
    echo '<td>';
    echo '<form target="' . $frame['content'] . '" method="post" action="mail.php">';
    echo '<input name="command" type="hidden" id="command" value="Send" readonly="readonly">';
    echo "<input type='hidden' name='username' value='" . $_POST['username'] . "' />";
    echo '<button class="np_button_link" type="submit">New Message</button>';
    echo '</form>';
    echo '</td>';
}
// Delete Message button
if (isset($_POST['command']) && $_POST['command'] == 'Message') {
    echo '<td>';
    echo '<form target="' . $frame['content'] . '" method="post" action="mail.php">';
    echo '<input name="command" type="hidden" id="command" value="Delete" readonly="readonly">';
    echo "<input type='hidden' name='username' value='" . $_POST['username'] . "' />";
    echo "<input type='hidden' name='id' value='" . $_POST['id'] . "' />";
    echo '<button class="np_button_link" type="submit">Delete This Message</button>';
    echo '</form>';
    echo '</td>';
}
echo '<td width=100%></td></tr></table>';

if (isset($_POST['username'])) {
    $name = $_POST['username'];
    // Save name in cookie
    if ($setcookies == true) {
        setcookie("mail_name", stripslashes($name), time() + (3600 * 24 * 90), "/");
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
$logged_in = false;
if(trim($name) != '') {
    $logged_in = verify_logged_in(trim(strtolower($name)));
}

if ($logged_in !== true) {
    echo '<table border="0" align="center" cellpadding="0" cellspacing="1">';
    echo '<form name="form1" method="post" action="user.php" enctype="multipart/form-data">';
    // echo '<form name="form1" method="post" action="mail.php" enctype="multipart/form-data">';
    echo '<tr><td><strong>Please Login<br /></strong></td></tr>';
    echo '<tr><td>Username:</td><td><input name="username" type="text" id="username" value="' . $name . '"></td></tr>';
    echo '<tr><td>Password:</td><td><input name="password" type="password" id="password"></td></tr>';
    echo '<td><input name="command" type="hidden" id="command" value="Login" readonly="readonly"></td>';
    echo '<td><input name="source" type="hidden" id="source" value="Mail:mail.php" readonly="readonly"></td>';
    echo '<input type="hidden" name="key" value="' . password_hash($CONFIG['thissitekey'] . $name, PASSWORD_DEFAULT) . '">';
    echo '<td>&nbsp;</td>';
    echo '<td><input type="submit" name="Submit" value="Login"></td>';
    echo '</tr>';
    echo '</form>';
    echo '</table>';
    exit(0);
}

$user = strtolower($_POST['username']);

if (isset($_POST['command']) && $_POST['command'] == 'Delete') {
    $database = $spooldir . '/mail.db3';
    $dbh = mail_db_open($database);
    $query = $dbh->prepare('SELECT * FROM messages where id=:id');
    $query->execute([
        'id' => $_POST['id']
    ]);
    while (($row = $query->fetch()) !== false) {
        if (($row['mail_from'] != $user) && ($row['rcpt_to'] != $user)) {
            continue;
        }
        $istrue = 'true';
        if ($row['mail_from'] == $user) {
            $sql_update = $dbh->prepare('UPDATE messages SET from_hide=:from_hide WHERE id=:row_id');
            $sql_update->execute(array(
                ':from_hide' => $istrue,
                ':row_id' => $row['id']
            ));
        }
        if ($row['rcpt_to'] == $user) {
            $sql_update = $dbh->prepare('UPDATE messages SET to_hide=:to_hide WHERE id=:row_id');
            $sql_update->execute(array(
                ':to_hide' => $istrue,
                ':row_id' => $row['id']
            ));
        }
    }
    $dbh = null;
}

if (isset($_POST['command']) && $_POST['command'] == 'Message') {
    $database = $spooldir . '/mail.db3';
    $dbh = mail_db_open($database);
    $query = $dbh->prepare('SELECT * FROM messages where id=:id');
    $query->execute([
        'id' => $_POST['id']
    ]);
    while (($row = $query->fetch()) !== false) {
        $ts = new DateTime(date("D, j M Y H:i T", $row["date"]), new DateTimeZone('UTC'));
        $ts->add(DateInterval::createFromDateString($offset . ' minutes'));

        if ($offset != 0) {
            $newdate = $ts->format('D, j M Y H:i');
        } else {
            $newdate = $ts->format('D, j M Y H:i T');
        }
        unset($ts);
        if (($row['mail_from'] != $user) && ($row['rcpt_to'] != $user)) {
            continue;
        }
        $body = rtrim($row['message']) . '<br /><br />';
        echo '<div class="np_article_header">';
        echo '<b>Subject:</b> ' . $row['subject'] . '<br />';
        echo '<b>From:</b> ' . $row['mail_from'] . '<br />';
        echo '<b>To:</b> ' . $row['rcpt_to'] . '<br />';
        echo '<b>Date:</b> ' . $newdate . '<br />';
        echo '</div>';

        echo '<div class="np_article_body">';
        echo $body;
        echo '<form action="mail.php" method="post">';
        echo '<button class="np_button_link" type="submit">Reply</button>';
        echo "<input type='hidden' name='id' value='" . $row['id'] . "' />";
        echo "<input type='hidden' name='username' value='" . $_POST['username'] . "' />";
        echo '<input name="command" type="hidden" id="command" value="Send" readonly="readonly">';
        echo '</form>';
        echo '</div>';
        if ($row['mail_from'] == $user) {
            $sql_update = $dbh->prepare('UPDATE messages SET mail_viewed=? WHERE msgid=?');
            $sql_update->execute(array(
                'true',
                $row['msgid']
            ));
        }
        if ($row['rcpt_to'] == $user) {
            $sql_update = $dbh->prepare('UPDATE messages SET rcpt_viewed=? WHERE msgid=?');
            $sql_update->execute(array(
                'true',
                $row['msgid']
            ));
        }
    }
    $dbh = null;
}
if (isset($_POST['sendMessage'])) {
    if (isset($_POST['to']) && $_POST['to'] != '' && isset($_POST['from']) && $_POST['from'] != '' && isset($_POST['message']) && $_POST['message'] != '') {
        if (($to = get_config_value('aliases.conf', strtolower($_POST['to']))) == false) {
            $to = strtolower($_POST['to']);
        }
        $userlist = scandir($config_dir . '/users/');
        $found = 0;
        foreach ($userlist as $user) {
            if (trim($to) == trim($user)) {
                $found = 1;
            }
        }
        // Check if target is remote. If user enters @ our own domain, strip it (it's local)
        $remote_target = 0;
        if (strpos($to, '@') !== false) {
            $info = preg_split('/@/', $to, 2);
            if ($info[1] == $rslight_gpg['domain_name']) { // domain is our domain
                $to = $info[0];
                foreach ($userlist as $user) {
                    if (($to = get_config_value('aliases.conf', strtolower($info[0]))) == false) {
                        $to = strtolower($info[0]);
                    }
                    if (trim($to) == trim($user)) {
                        $found = 1;
                    }
                }
            } else { // domain is remote
                $found = 1;
                $remote_target = 1;
            }
        }
        if ($found == 0) {
            echo 'User not found: ' . $to;
        } else {
            $database = $spooldir . '/mail.db3';
            $dbh = mail_db_open($database);
            $from = $_POST['from'];
            $subject = $_POST['subject'];
            $message = $_POST['message'];
            $date = time();
            $message = $_POST['message'];
            $msgid = '<' . md5(strtolower($to) . strtolower($from) . strtolower($subject) . strtolower($message)) . '>';
            $sql = 'INSERT OR IGNORE INTO messages(msgid, mail_from, rcpt_to, rcpt_target, date, subject, message, from_hide, to_hide, mail_viewed, rcpt_viewed) VALUES(?,?,?,?,?,?,?,?,?,?,?)';
            $stmt = $dbh->prepare($sql);
            // For possible future use ($target is currently unused)
            $target = "local";
            $mail_viewed = "true";
            $rcpt_viewed = null;
            // $remote_target is handled here
            if ($q = $stmt->execute([
                $msgid,
                $from,
                $to,
                $target,
                $date,
                $subject,
                $message,
                null,
                null,
                $mail_viewed,
                $rcpt_viewed
            ])) {
                if ($remote_target == 1) {
                    $remote_result = send_external_mail($from, $to, $date, $subject, $message);
                    if ($remote_result == true) {
                        $return_val = "Message sent.";
                    } else {
                        $return_val = "Failed to Send. No Key for Destination";
                    }
                }
                $return_val = "Message sent.";
            } else {
                $return_val = "Failed to Send. Database Error";
            }
            // Act on return values for response to user
            echo $return_val;
            $dbh = null;
            $user = $from;
        }
    }
}
if (isset($_POST['command']) && $_POST['command'] == 'Send') {
    $mail_to = '';
    $subject = '';
    $message = '';
    if (isset($_POST['id'])) {
        $database = $spooldir . '/mail.db3';
        $dbh = mail_db_open($database);
        $query = $dbh->prepare('SELECT * FROM messages where id=:id');
        $query->execute([
            'id' => $_POST['id']
        ]);
        while (($row = $query->fetch()) !== false) {
            $mail_to = $row['mail_from'];
            if (strpos($row['subject'], 'Re: ') !== 0) {
                $subject = 'Re: ' . $row['subject'];
            } else {
                $subject = $row['subject'];
            }
            $body = explode("\n", $row['message']);
            $message = $row['mail_from'] . " wrote:\n\n";
            foreach ($body as $line) {
                if (trim($line) !== '') {
                    $line = '>' . $line;
                }
                $message .= $line;
            }
        }
        $dbh = null;
    }
    echo '<h3>Send Message:</h3>';
    echo "<form action='mail.php' method='POST'>";
    echo '<table><tbody><tr>';
    echo "<td>To: </td><td><input type='text' name='to' value='" . $mail_to . "'/></td>";
    echo '</tr><tr>';
    echo "<td>Subject: </td><td><input type='text' name='subject' value='" . htmlentities($subject) . "'/></td>";
    echo '</tr><tr>';
    echo "<td></td><td><textarea class='postbody' id='message' name='message'>$message</textarea></td>";
    echo '</tr><tr>';
    echo "<input type='hidden' name='from' value='" . $user . "' />";
    echo "<input type='hidden' name='username' value='" . $_POST['username'] . "' />";
    echo "<td></td><td><input type='submit' value='Send Mail' name='sendMessage' /></td>";
    echo '</tr></tbody></table></form>';
}

view_mailbox($user);

// Show My Messages
function view_mailbox($user)
{
    global $spooldir, $offset, $rslight_version;
    $database = $spooldir . '/mail.db3';
    $dbh = mail_db_open($database);
    echo '<hr><h1 class="np_thread_headline">My Messages:</h1>';
    echo '<table cellspacing="0" width="100%" class="np_results_table">';
    $query = $dbh->prepare('SELECT * FROM messages WHERE mail_from=:mail_from OR rcpt_to=:mail_from ORDER BY date DESC');
    $query->execute([
        'mail_from' => $user
    ]);
    echo '<tr class="np_thread_head"><td class="np_thread_head">Subject</td><td class="np_thread_head">From</td><td class="np_thread_head">To</td><td class="np_thread_head">Date</td></tr>';
    $i = 1;
    while (($row = $query->fetch()) !== false) {
        if (($row['mail_from'] == $user) && ($row['from_hide'] == 'true')) {
            continue;
        }
        if (($row['rcpt_to'] == $user) && ($row['to_hide'] == 'true')) {
            continue;
        }
        if (($i % 2) != 0) {
            echo '<tr class="np_result_line1"><td class="np_result_line1" style="word-wrap:break-word";>';
        } else {
            echo '<tr class="np_result_line2"><td class="np_result_line2" style="word-wrap:break-word";>';
        }
        $button_link = 'np_mail_button_link';
        ;
        if (($row['mail_from'] == $user) && ($row['mail_viewed'] == 'true')) {
            $button_link = 'np_mail_button_read';
        } elseif (($row['rcpt_to'] == $user) && ($row['rcpt_viewed'] == 'true')) {
            $button_link = 'np_mail_button_read';
        }
        // Use local timezone if possible
        $ts = new DateTime(date("D, j M Y H:i T", $row["date"]), new DateTimeZone('UTC'));
        $ts->add(DateInterval::createFromDateString($offset . ' minutes'));

        if ($offset != 0) {
            $newdate = $ts->format('D, j M Y H:i');
        } else {
            $newdate = $ts->format('D, j M Y H:i T');
        }
        unset($ts);
        echo '<form action="mail.php" method="post">';
        echo '<button class="' . $button_link . '" type="submit">' . $row["subject"] . '</button>';
        echo "<input type='hidden' name='id' value='" . $row['id'] . "' />";
        echo "<input type='hidden' name='username' value='" . $_POST['username'] . "' />";
        echo '<input name="command" type="hidden" id="command" value="Message" readonly="readonly">';
        echo '</form>';
        echo '</td><td>' . $row["mail_from"] . '</td><td>' . $row["rcpt_to"] . '</td><td>' . $newdate . '</td></tr>';
        $i ++;
    }
    echo '</tbody></table><br />';
    include "tail.inc";
}

function send_external_mail($sender, $recipient, $date, $subject, $message)
{
    global $rslight_gpg, $config_name, $spooldir, $rslight_version;
    putenv("GNUPGHOME=" . $rslight_gpg['gnupghome']);
    $res = gnupg_init();

    // Get target domain (then get key if necessary)
    $info = preg_split('/@/', $recipient, 2);
    $target['domain'] = $info[1];
    if (gnupg_keyinfo($res, "rslight@" . $target['domain']) == false) { // We don't have the key
        $retrieve = retrieve_key($res, $target['domain']);
        if ($retrieve == false) { // We can't get the key
            return false;
        }
    }
    $cwd = getcwd();
    $keydir = preg_replace('/spoolnews/', 'pubkey/', $cwd);
    $key_location = "/pubkey/server_pubkey.txt";
    $signing_key = trim(file_get_contents($keydir . '/server_fingerprint.txt'));
    $fingerprint_clean = preg_replace('/\ /', '', $signing_key);
    gnupg_addsignkey($res, $fingerprint_clean);
    gnupg_adddecryptkey($res, $fingerprint_clean, '');

    $keyinfo = gnupg_keyinfo($res, "rslight@" . $target['domain']);
    $target['fingerprint'] = $keyinfo[0]['subkeys'][0]['fingerprint'];
    $encrypt_to_key = $target['fingerprint'];
    gnupg_addencryptkey($res, $encrypt_to_key);

    $mydate = gmdate("D, d M Y H:i:s \U\T\C", $date);

    $outgoing_dir = $spooldir . '/' . $config_name . '/outgoing';
    if (! is_dir($outgoing_dir)) {
        mkdir($outgoing_dir, 0700, true);
    }
    $domain = $rslight_gpg['domain_name'];
    $organization = $CONFIG['organization'];
    $from = $rslight_gpg['from_email'];
    $contact = $rslight_gpg['contact'];

    $outgoing_file = tempnam($outgoing_dir, 'bbsmail-');

    $start = "@@BEGIN BBSMAIL HEADERS";
    $begin = "@@BEGIN BBSMAIL BODY";
    $end = "@@END BBSMAIL BODY";

    $body = '';
    $body .= "You may use this to import MAIL for $domain.\n\n";

    $body .= "This message was signed using the following key:\n";
    $body .= "$signing_key\n\n";

    $body .= "The GPG key needed to verify the signature of messages\n";
    $body .= "issued by $from is available at:\n";
    $body .= "$domain$key_location\n\n";

    $body .= "For information contact $contact.\n\n";

    $body .= $start . "\n";
    $body .= '    Version: ' . $rslight_version . "\n";
    $body .= '    From: ' . $from . "\n";
    $hashtail = hash('crc32', $domain . $organization . $sender . $rslight_gpg['nntp_group']);
    $thishash = hash('crc32', $message . $hashtail) . hash('crc32', $signing_key);
    $body .= "    Notice-ID: " . $thishash . "\n";
    $body .= "    Key: " . $signing_key . "\n";
    $body .= "    Location: " . $domain . $key_location . "\n";
    $body .= "    Domain: " . $domain . "\n";

    $body .= $begin . "\n";
    $body .= "    Sender: " . $sender . "\n";
    $body .= "    Recipient: " . $recipient . "\n";
    $body .= "    Date: " . $mydate . "\n";
    $body .= "    Subject: " . $subject . "\n";
    $body .= "    Body: " . $message . "\n";
    $body .= $end . "\n";

    $header = '';
    $header .= "From: $from\n";
    $header .= "Newsgroups: " . $rslight_gpg['nntp_group'] . "\n";
    $header .= "Subject: @@RSL BBSMAIL notice " . $thishash . "\n";
    $header .= "Date: " . $mydate . "\n";
    $header .= "Message-ID: <$thishash@$domain>\n";
    $header .= "Content-Type: text/plain; charset=utf-8; format=flowed\n";
    $header .= "Content-Transfer-Encoding: 8bit\n";
    $header .= "Organization: $organization\n\n";

    $encrypted_text = gnupg_encryptsign($res, $body);

    file_put_contents($outgoing_file, $header . $encrypted_text);
    return true;
}

function retrieve_key($res, $domain)
{
    global $config_name, $logfile;
    // Let's try to get the key
    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " No KEY for posting. Trying to retrieve for " . $domain, FILE_APPEND);

    $location = "http://" . $domain . '/pubkey/server_pubkey.txt';
    $import = gnupg_import($res, file_get_contents($location));
    if (isset($import['fingerprint'])) {
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " IMPORTED: " . $import['fingerprint'], FILE_APPEND);

        // Verify that domain in IMPORTED KEY matches exactly: "Location" and "Domain" in MAILKEY message
        // If it DOES NOT, then DELETE the new key immediately
        $keyinfo = gnupg_keyinfo($res, $import['fingerprint']);
        $imported_domain = preg_replace('/rslight@/', '', $keyinfo[0]['uids'][0]['uid']);
        if (($imported_domain == $domain)) {
            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Domain Match: " . $imported_domain, FILE_APPEND);
            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " New PGP Key added for: " . $imported_domain . " Domain: " . $imported_domain . " Fingerprint: " . $import['fingerprint'], FILE_APPEND);
            send_admin_message('admin', 'admin', 'New PGP Key added for: ' . $imported_domain, 'Domain: ' . $imported_domain . "\nFingerprint: " . $import['fingerprint'] . "\n");
            return true;
        } else {
            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Domain MIS-MATCH: " . $imported_domain . " DELETING...", FILE_APPEND);
            if (gnupg_deletekey($res, $import['fingerprint'])) {
                file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " SUCCESS Deleting " . $import['fingerprint'], FILE_APPEND);
            } else {
                file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " WARNING!: FAILED to Delete " . $import['fingerprint'], FILE_APPEND);
            }
            return false;
        }
    } else {
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Failed to import key from " . $location, FILE_APPEND);
        return false;
    }
    return false;
}
