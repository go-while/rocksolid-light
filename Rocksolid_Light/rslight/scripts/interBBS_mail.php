<?php
include "config.inc.php";
include ("$file_newsportal");
include $config_dir . "/gpg.conf";

$logfile = $logdir . '/mail.log';

$lockfile = $lockdir . '/rslight-bbsmail.lock';
$pid = file_get_contents($lockfile);
if (posix_getsid($pid) === false || ! is_file($lockfile)) {
    print "Starting BBSmail...\n";
    file_put_contents($lockfile, getmypid()); // create lockfile
} else {
    print "BBSmail currently running\n";
    exit();
}

$bbsmail_path = $spooldir . "/bbsmail/";
if (! is_dir($bbsmail_path . 'in')) {
    mkdir($bbsmail_path . 'in', 0700, true);
}
if (! is_dir($bbsmail_path . 'failed')) {
    mkdir($bbsmail_path . 'failed', 0700, true);
}
if (! is_dir($bbsmail_path . 'processed')) {
    mkdir($bbsmail_path . 'processed', 0700, true);
}
prune_dir_by_days($bbsmail_path . 'failed', 30);
prune_dir_by_days($bbsmail_path . 'processed', 30);

// Set up gnupg
putenv("GNUPGHOME=" . $rslight_gpg['gnupghome']);
$res = gnupg_init();

$gnupg_summary = array(
    "1" => "The signature is fully valid",
    "2" => "The signature is good",
    "4" => "The signature is bad",
    "16" => "One key has been revoked",
    "32" => "One key has expired",
    "64" => "The signature has expired",
    "128" => "Can't verify: key missing",
    "256" => "CRL not available",
    "512" => "Available CRL is too old",
    "1024" => "A policy was not met",
    "2048" => "A system error occured"
);

$gnupg_validity = array(
    "0" => "Validity: UNKNOWN",
    "1" => "Validity: UNDEFINED",
    "2" => "Validity: NEVER",
    "3" => "Validity: MARGINAL",
    "4" => "Validity: FULL",
    "5" => "Validity: ULTIMATE"
);

/**
 * *** Receive mail ****
 */
unset($messages);
$messages = array_diff(scandir($bbsmail_path . '/in/'), array(
    '..',
    '.'
));
foreach ($messages as $message) {
    $filename = explode($bbsmail_path . '/in/', $message);
    $filename = $filename[0];
    // Put message data into array $inspect[]
    if (($inspect = inspect_message($bbsmail_path . '/in/' . $message, $filename)) == false) {
        continue;
    }
    if ($inspect['type'] == 'mailkey') {
        if (($info = verify_gpg_signature($res, $inspect['body'])) == true) {
            echo 'GOOD signature in: "' . $filename . '"' . "\n";
            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . ' GOOD signature in: "' . $filename . '"', FILE_APPEND);
            // Do we already have this key?
            if (gnupg_keyinfo($res, $inspect['mailkey_domain']) !== false) { // Yes, we do
                file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . ' Key already in keyring for: ' . $inspect['mailkey_domain'], FILE_APPEND);
                rename($bbsmail_path . '/in/' . $message, $bbsmail_path . 'processed/' . $message);
            } else { // No, we don't
                file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . ' Key not found in keyring for: ' . $inspect['mailkey_domain'], FILE_APPEND);
            }
        } else {
            echo 'BAD or UNKNOWN signature in: "' . $filename . '"' . "\n";
            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . ' BAD or UNKNOWN signature in: "' . $filename . '"', FILE_APPEND);
            get_key_from_message($res, $inspect, $message);
        }
    }
    if ($inspect['type'] == 'bbsmail') {
        $info = gnupg_decryptverify($res, $inspect['body'], $plaintext);
        if ($info !== false) {
            if ($info[0]['summary'] > 3) {
                echo $gnupg_summary[$info[0]['summary']] . " in: " . $filename . "\n";
                file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " " . $gnupg_summary[$info[0]['summary']] . " in: " . $filename, FILE_APPEND);

                $inspect['mailkey_domain'] = preg_split('/@/', $inspect['from'], 2);
                $inspect['mailkey_domain'] = $inspect['mailkey_domain'][1];

                $inspect['mailkey_location'] = $inspect['mailkey_domain'] . '/pubkey/server_pubkey.txt';
                get_key_from_message($res, $inspect, $message);
                if (strpos($filename, '-retry') !== false) {
                    rename($bbsmail_path . '/in/' . $message, $bbsmail_path . 'failed/' . $message);
                } else {
                    rename($bbsmail_path . '/in/' . $message, $bbsmail_path . '/in/' . $message . '-retry');
                }
            } else {
                echo 'GOOD signature in: "' . $filename . '"' . "\n";
                file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . ' GOOD signature in: "' . $filename . '"', FILE_APPEND);
                // Now let's get and import the mail message
                // Does the @from match the signature domain?
                $inspect = inspect_bbsmail($res, $plaintext);
                $keyinfo = gnupg_keyinfo($res, $info[0]['fingerprint']);
                $signature_domain = preg_replace('/rslight@/', '', $keyinfo[0]['uids'][0]['uid']);
                $info = preg_split('/\@/', $inspect['bbsmail_from'], 2);
                $bbsmail_domain = $info[1];

                if (($signature_domain == $bbsmail_domain) && ($signature_domain == $inspect['bbsmail_domain'])) { // Yes, the domains match
                    echo "THE DOMAINS MATCH. OK TO IMPORT MESSAGE\n";
                    echo $plaintext;
                    print_r($inspect);

                    $mail_from = $inspect['bbsmail_sender'] . '@' . $inspect['bbsmail_domain'];
                    $info = preg_split('/@/', $inspect['bbsmail_recipient'], 2);
                    $rcpt_to = $info[0];

                    $date = strtotime($inspect['bbsmail_date']);

                    if (! isset($inspect['bbsmail_sender']) || ! isset($inspect['bbsmail_recipient']) || ! isset($inspect['bbsmail_sender']) || ! isset($inspect['bbsmail_body'])) {
                        echo "Incomplete Headers... Aborting Message Import\n";
                    } else {
                        if (import_user_message($mail_from, $rcpt_to, $date, $inspect['bbsmail_subject'], $inspect['bbsmail_body'])) {
                            rename($bbsmail_path . '/in/' . $message, $bbsmail_path . 'processed/' . $message);
                        }
                    }
                } else { // No, the domains DO NOT MATCH
                    echo "DOMAIN MISMATCH\n";
                    file_put_contents($logfile, "\nComparing sig_dom: " . $signature_domain . " bbsmail_domain: " . $bbsmail_domain . " ins[bbs_dom]: " . $inspect['bbsmail_domain'], FILE_APPEND);
                    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . ' DOMAIN MISMATCH in: "' . $filename . '" ' . $error, FILE_APPEND);
                    rename($bbsmail_path . '/in/' . $message, $bbsmail_path . 'failed/' . $message);
                }
            }
        } else {
            $error = gnupg_geterrorinfo($res);
            print_r($error);
            echo 'BAD signature in: "' . $filename . '"' . "\n";
            echo $error['generic_message'] . ': ' . $error['gpgme_message'] . "\n";
            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . ' BAD signature in: "' . $filename . '" ' . $error['generic_message'] . ': ' . $error['gpgme_message'], FILE_APPEND);
            $inspect['mailkey_domain'] = preg_replace('/rslight@/', '', $inspect['from']);
            $inspect['mailkey_location'] = $inspect['mailkey_domain'] . '/pubkey/server_pubkey.txt';
            get_key_from_message($res, $inspect, $message);
            if (strpos($filename, '-retry') !== false) {
                rename($bbsmail_path . '/in/' . $message, $bbsmail_path . 'failed/' . $message);
            } else {
                rename($bbsmail_path . '/in/' . $message, $bbsmail_path . '/in/' . $message . '-retry');
            }
        }
    }
}

/**
 * *** Send key to group ****
 */
// How often to send key to group
// in seconds (default 1 month)
$mail_update_time = 2592000;
$do_mail_update = false;
if (filemtime($spooldir . '/bbs-mail-update-timer') + $mail_update_time > time()) { // false
    if (is_file($config_dir . '/bbs-mail-debug')) { // true
        $do_mail_update = true;
    }
} else { // true
    $do_mail_update = true;
}

if ($do_mail_update == true) {
    echo "Sending keys to " . $rslight_gpg['nntp_group'] . "\n";
    send_keys_to_group($res, $rslight_gpg);
    touch($spooldir . '/bbs-mail-update-timer');
}

function import_user_message($from, $rcpt, $date, $subject, $message)
{
    global $config_dir, $spooldir;

    if (($to = get_config_value('aliases.conf', strtolower($rcpt))) == false) {
        $to = strtolower($rcpt);
    }
    $to = trim($to);
    if (strlen($subject) < 1) {
        $subject = "(no subject)";
    }
    $database = $spooldir . '/mail.db3';
    $dbh = mail_db_open($database);
    if (! $dbh) {
        echo "Database error\n";
        return false;
    }
    $msgid = '<' . md5(strtolower($to) . strtolower($from) . strtolower($subject) . strtolower($message)) . '>';
    $sql = 'INSERT OR IGNORE INTO messages(msgid, mail_from, rcpt_to, rcpt_target, date, subject, message, from_hide, to_hide, mail_viewed, rcpt_viewed) VALUES(?,?,?,?,?,?,?,?,?,?,?)';
    $stmt = $dbh->prepare($sql);
    $target = "local";
    $mail_viewed = null;
    $rcpt_viewed = null;
    $q = $stmt->execute([
        $msgid,
        $from,
        $to,
        $target,
        intval($date),
        $subject,
        $message,
        null,
        null,
        $mail_viewed,
        $rcpt_viewed
    ]);

    $dbh = null;
    return true;
}

function get_key_from_message($res, $inspect, $message)
{
    global $logfile, $config_name, $bbsmail_path;
    $filename = explode($bbsmail_path . '/in/', $message);
    $filename = $filename[0];
    // Let's try to get the key
    echo "Let's try to get the key\n";
    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Let's try to get the key", FILE_APPEND);
    // Display stuff for testing
    echo "Domain: " . $inspect['mailkey_domain'] . "\n";
    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Domain: " . $inspect['mailkey_domain'], FILE_APPEND);
    echo "Location: " . $inspect['mailkey_location'] . "\n";
    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Location: " . $inspect['mailkey_location'], FILE_APPEND);
    $location = "http://" . $inspect['mailkey_location'];
    $import = gnupg_import($res, file_get_contents($location));
    if ($import) {
        echo "IMPORTED: " . $import['fingerprint'] . "\n";
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " IMPORTED: " . $import['fingerprint'], FILE_APPEND);

        // Verify that domain in IMPORTED KEY matches exactly: "Location" and "Domain" in MAILKEY message
        // If it DOES NOT, then DELETE the new key immediately
        $keyinfo = gnupg_keyinfo($res, $import['fingerprint']);
        $imported_domain = preg_replace('/rslight@/', '', $keyinfo[0]['uids'][0]['uid']);
        $mailkey_location = explode('/', $inspect['mailkey_location']);
        if (($imported_domain == $inspect['mailkey_domain']) && ($imported_domain == $mailkey_location[0])) {
            echo "Domain Match: " . $imported_domain . "\n";
            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Domain Match: " . $imported_domain, FILE_APPEND);
            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " New PGP Key added for: " . $imported_domain . " Domain: " . $imported_domain . "\nFingerprint: " . $import['fingerprint'], FILE_APPEND);
            send_admin_message('admin', 'admin', 'New PGP Key added for: ' . $imported_domain, 'Domain: ' . $imported_domain . "\nFingerprint: " . $import['fingerprint'] . "\n");
            return true;
        } else {
            echo "Domain MIS-MATCH: " . $imported_domain . " DELETING...\n";
            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Domain MIS-MATCH: " . $imported_domain . " DELETING...", FILE_APPEND);
            if (gnupg_deletekey($res, $import['fingerprint'])) {
                echo "SUCCESS Deleting " . $import['fingerprint'] . "\n";
                file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " SUCCESS Deleting " . $import['fingerprint'], FILE_APPEND);
            } else {
                echo "WARNING!: FAILED to Delete " . $import['fingerprint'] . "\n";
                file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " WARNING!: FAILED to Delete " . $import['fingerprint'], FILE_APPEND);
            }
            return false;
        }
    } else {
        echo "Failed to import key from " . $location . "\n";
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Failed to import key from " . $location, FILE_APPEND);
        if (strpos($filename, '-retry') !== false) {
            rename($bbsmail_path . '/in/' . $filename, $bbsmail_path . 'failed/' . $filename);
        } else {
            rename($bbsmail_path . '/in/' . $filename, $bbsmail_path . '/in/' . $filename . '-retry');
        }
        return false;
    }
}

function inspect_bbsmail($res, $plaintext)
{
    $bbsmail_header = 0;
    $bbsmail_body = 0;
    $message_body = 0;
    $plaintext = explode("\n", $plaintext);
    foreach ($plaintext as $line) {
        if (strpos($line, '@@BEGIN BBSMAIL HEADERS') !== false) {
            $bbsmail_header = 1;
        }
        if ($bbsmail_header == 1) {
            if (strpos($line, 'From: ') !== false) {
                $bbsmail = explode("From: ", $line);
                $return_data['bbsmail_from'] = trim($bbsmail[1]);
            } else {
                if (strpos($line, 'Version: ') !== false) {
                    $bbsmail = explode("Version: ", $line);
                    $return_data['bbsmail_version'] = trim($bbsmail[1]);
                } else {
                    if (strpos($line, 'Notice-ID: ') !== false) {
                        $bbsmail = explode("Notice-ID: ", $line);
                        $return_data['bbsmail_notice-id'] = trim($bbsmail[1]);
                    }
                }
            }
            if (strpos($line, 'Key: ') !== false) {
                $bbsmail = explode("Key: ", $line);
                $return_data['bbsmail_key'] = trim($bbsmail[1]);
            } else {
                if (strpos($line, 'Location: ') !== false) {
                    $bbsmail = explode("Location: ", $line);
                    $return_data['bbsmail_location'] = trim($bbsmail[1]);
                } else {
                    if (strpos($line, 'Domain: ') !== false) {
                        $bbsmail = explode("Domain: ", $line);
                        $return_data['bbsmail_domain'] = trim($bbsmail[1]);
                    }
                }
            }
        }
        if (strpos($line, '@@BEGIN BBSMAIL BODY') !== false) {
            $bbsmail_header = 0;
            $bbsmail_body = 1;
            continue;
        }
        if ($bbsmail_body == 1) {
            if (strpos($line, '@@END BBSMAIL BODY') !== false) {
                break;
            }

            if ($message_body == 1) {
                $return_data['bbsmail_body'] .= $line . "\n";
                continue;
            }

            if (strpos($line, 'Sender: ') !== false) {
                $bbsmail = explode("Sender: ", $line);
                $return_data['bbsmail_sender'] = trim($bbsmail[1]);
            } else {
                if (strpos($line, 'Recipient: ') !== false) {
                    $bbsmail = explode("Recipient: ", $line);
                    $return_data['bbsmail_recipient'] = trim($bbsmail[1]);
                } else {
                    if (strpos($line, 'Date: ') !== false) {
                        $bbsmail = explode("Date: ", $line);
                        $return_data['bbsmail_date'] = trim($bbsmail[1]);
                    } else {
                        if (strpos($line, 'Subject: ') !== false) {
                            $bbsmail = explode("Subject: ", $line);
                            $return_data['bbsmail_subject'] = trim($bbsmail[1]);
                        } else {
                            if (strpos($line, 'Body: ') !== false) {
                                $bbsmail = explode("Body: ", $line);
                                $return_data['bbsmail_body'] = $bbsmail[1] . "\n";
                                $message_body = 1;
                            }
                        }
                    }
                }
            }
        }
        if (trim($line) == '.') {
            $line = ' ';
        }
        if ($bbsmail_body == 1) {
            if (! isset($return_data['body'])) {
                $line = ltrim($line);
            }
        }
    }
    return ($return_data);
}

function inspect_message($message, $filename)
{
    global $logfile, $config_name, $bbsmail_path;

    $header = array();
    $body = array();
    $return_data = array();

    if (strpos($message, 'bbsmail-MAILKEY notice')) {
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Found MAILKEY message " . $filename, FILE_APPEND);
    } else {
        if (strpos($message, 'bbsmail-BBSMAIL notice')) {
            $return_data['type'] = 'bbsmail';
            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Found BBSMAIL message " . $filename, FILE_APPEND);
        } else {
            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Found UNKNOWN message " . $filename, FILE_APPEND);
            rename($bbsmail_path . '/in/' . $filename, $bbsmail_path . 'failed/' . $filename);
            return false;
        }
    }

    $raw_message = file($message);
    $is_header = 1;
    $mailkey_header = 0;
    $mailkey_body = 0;

    foreach ($raw_message as $line) {
        if (trim($line) == '' && $is_header == 1) {
            $is_header = 0;
            continue;
        }
        if ($is_header == 1) {
            $return_data['header'] .= $line;
            if (strpos($line, 'From: ') !== false) {
                $from_line = explode("From: ", $line);
                $from = trim($from_line[1]);
                $return_data['from'] = $from;
            }
            if (strpos($line, 'Subject: ') !== false) {
                $subject_line = explode("Subject: ", $line);
                $subject = trim($subject_line[1]);
                if (strpos($subject, '@@RSL MAILKEY notice') !== false) {
                    $return_data['type'] = 'mailkey';
                } else {
                    if (strpos($subject, '@@RSL BBSMAIL notice') !== false) {
                        $return_data['type'] = 'bbsmail';
                    } else {
                        return false;
                    }
                }
            }
            $header[] = $line;
        } else {
            $return_data['body'] .= $line;
            if ($return_data['type'] == 'mailkey') {
                if (strpos($line, '@@BEGIN MAILKEY HEADERS') !== false) {
                    $mailkey_header = 1;
                }
                if ($mailkey_header == 1) {
                    if (strpos($line, 'From: ') !== false) {
                        $mailkey = explode("From: ", $line);
                        $return_data['mailkey_from'] = trim($mailkey[1]);
                    } else {
                        if (strpos($line, 'Version: ') !== false) {
                            $mailkey = explode("Version: ", $line);
                            $return_data['mailkey_version'] = trim($mailkey[1]);
                        } else {
                            if (strpos($line, 'Notice-ID: ') !== false) {
                                $mailkey = explode("Notice-ID: ", $line);
                                $return_data['mailkey_notice-id'] = trim($mailkey[1]);
                            }
                        }
                    }
                }
                if (strpos($line, '@@BEGIN MAILKEY BODY') !== false) {
                    $mailkey_body = 1;
                    $mailkey_header = 0;
                }
                if ($mailkey_body == 1) {
                    if (strpos($line, 'Key: ') !== false) {
                        $mailkey = explode("Key: ", $line);
                        $return_data['mailkey_key'] = trim($mailkey[1]);
                    } else {
                        if (strpos($line, 'Location: ') !== false) {
                            $mailkey = explode("Location: ", $line);
                            $return_data['mailkey_location'] = trim($mailkey[1]);
                        } else {
                            if (strpos($line, 'Domain: ') !== false) {
                                $mailkey = explode("Domain: ", $line);
                                $return_data['mailkey_domain'] = trim($mailkey[1]);
                            }
                        }
                    }
                }
                if (trim($line) == '.') {
                    $line = ' ';
                }
            }
        }
    }
    return ($return_data);
}

function send_keys_to_group($res, $rslight_gpg)
{
    global $spooldir, $config_name, $logfile, $mail_update_time, $CONFIG, $rslight_version;

    $cwd = getcwd();
    $keydir = preg_replace('/spoolnews/', 'pubkey/', $cwd);
    $key_location = "/pubkey/server_pubkey.txt";
    $signing_key = trim(file_get_contents($keydir . '/server_fingerprint.txt'));
    $fingerprint_clean = preg_replace('/\ /', '', $signing_key);
    if (gnupg_keyinfo($res, $fingerprint_clean) == false) { // We have no private key, abort.
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Private Key not Found", FILE_APPEND);
        return false;
    }

    gnupg_addsignkey($res, $fingerprint_clean) . "\n";

    $start = "@@BEGIN MAILKEY HEADERS";
    $begin = "@@BEGIN MAILKEY BODY";
    $end = "@@END MAILKEY BODY";

    /*
     * Get days since last sent for creating message-id
     * (Don't allow posting more than once per day)
     */
    $date1 = date_create(date("Y-m-d", time() - $mail_update_time));
    $date2 = date_create(date("Y-m-d", time()));
    $diff_days = date_diff($date1, $date2);

    $outgoing_dir = $spooldir . '/' . $config_name . '/outgoing';
    if (! is_dir($outgoing_dir)) {
        mkdir($outgoing_dir, 0700, true);
    }
    $domain = $rslight_gpg['domain_name'];
    $organization = $CONFIG['organization'];
    $from = $rslight_gpg['from_email'];
    $contact = $rslight_gpg['contact'];

    $outgoing_file = tempnam($outgoing_dir, 'bbsmail-');

    $body = '';
    $body .= "You may use this to import the public key for $domain.\n";
    $body .= "This message is automatically generated by $from.\n";
    $body .= "for inter-bbs mail exchange for Rocksolid Light.\n\n";

    $body .= "This message was signed using the following key:\n";
    $body .= "$signing_key\n\n";

    $body .= "The GPG key needed to verify the signature of messages\n";
    $body .= "issued by $from is available at:\n";
    $body .= "$domain$key_location\n\n";

    $body .= "For information contact $contact.\n\n";

    $body .= $start . "\n";
    $body .= '    Version: ' . $rslight_version . "\n";
    $body .= '    From: ' . $from . "\n";
    $hashtail = hash('crc32', $domain . $organization . $from . $rslight_gpg['nntp_group']);
    $thishash = hash('crc32', $body . $diff_days->format("%a") . $hashtail) . hash('crc32', $signing_key);
    $body .= "    Notice-ID: " . $thishash . "\n";

    $body .= $begin . "\n";
    $body .= "    Key: " . $signing_key . "\n";
    $body .= "    Location: " . $domain . $key_location . "\n";
    $body .= "    Domain: " . $domain . "\n";
    $body .= $end . "\n";

    $header = '';
    $header .= "From: $from\n";
    $header .= "Newsgroups: " . $rslight_gpg['nntp_group'] . "\n";
    $header .= "Subject: @@RSL MAILKEY notice " . $thishash . "\n";
    $header .= "Message-ID: <$thishash@$domain>\n";
    $header .= "Content-Type: text/plain; charset=utf-8; format=flowed\n";
    $header .= "Content-Transfer-Encoding: 8bit\n";
    $header .= "Organization: $organization\n\n";

    $signed_body = gnupg_sign($res, $body);
    file_put_contents($outgoing_file, $header . $signed_body);
    echo "Posted <" . $thishash . "@" . $domain . ">\n\n";
    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Mail Sent: <" . $thishash . "@" . $domain . ">", FILE_APPEND);
    return true;
}

