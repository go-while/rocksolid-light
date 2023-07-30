<?php

include "config.inc.php";
include ("$file_newsportal");
include $config_dir."/gpg.conf";

$logfile = $logdir.'/mail.log';

$lockfile = $lockdir . '/rslight-bbsmail.lock';
$pid = file_get_contents($lockfile);
if (posix_getsid($pid) === false || !is_file($lockfile)) {
    print "Starting BBSmail...\n";
    file_put_contents($lockfile, getmypid()); // create lockfile
} else {
    print "BBSmail currently running\n";
    exit;
}

$bbsmail_path=$spooldir."/bbsmail/";
if(!is_dir($bbsmail_path.'in')) {
    mkdir($bbsmail_path.'in', 0700, true);
}
if(!is_dir($bbsmail_path.'out')) {
    mkdir($bbsmail_path.'out', 0700, true);
}

// Set up gnupg
putenv("GNUPGHOME=".$rslight_gpg['gnupghome']);
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

/***** Send mail *****/
// $messages=scandir($bbsmail_path.'/out/');


/***** Receive mail *****/
 unset($messages);
 $messages = array_diff(scandir($bbsmail_path.'/in/'), array('..', '.'));
 foreach($messages as $message) {
     $filename = explode($bbsmail_path.'/in/', $message);
     $filename = $filename[0];
     if(($inspect = inspect_message($bbsmail_path.'/in/'.$message, $filename)) == false) {
         continue;
     }
     echo $message."\n";
     if($inspect['type'] == 'mailkey') {
         if(($info = verify_gpg_signature($res, $inspect['body'])) == true) {
             echo 'GOOD signature in: "'.$filename.'"'."\n";
             file_put_contents($logfile, "\n".format_log_date()." ".$config_name.' GOOD signature in: "'.$filename.'"', FILE_APPEND);
         // Do we already have this key?
             if(gnupg_keyinfo($res, $inspect['mailkey_domain']) !== false) { // Yes, we do
                 file_put_contents($logfile, "\n".format_log_date()." ".$config_name.' Key already in keyring for: '.$inspect['mailkey_domain'], FILE_APPEND);
             } else { // No, we don't
                 file_put_contents($logfile, "\n".format_log_date()." ".$config_name.' Key not found in keyring for: '.$inspect['mailkey_domain'], FILE_APPEND);
             }
         } else {
             echo 'BAD or UNKNOWN signature in: "'.$filename.'"'."\n";
             file_put_contents($logfile, "\n".format_log_date()." ".$config_name.' BAD or UNKNOWN signature in: "'.$filename.'"', FILE_APPEND);
         }
     }
     if($inspect['type'] == 'bbsmail') {
         $info = gnupg_decryptverify($res,$inspect['body'],$plaintext);
         echo "\n".$plaintext."\n";
         if($info !== false) {
             if($info[0]['summary'] > 3) {
                 echo $gnupg_summary[$info[0]['summary']]." in: ".$filename."\n";
                 file_put_contents($logfile, "\n".format_log_date()." ".$config_name." ".$gnupg_summary[$info[0]['summary']]." in: ".$filename, FILE_APPEND);
             } else {
                 echo 'GOOD signature in: "'.$filename.'"'."\n";
                 file_put_contents($logfile, "\n".format_log_date()." ".$config_name.' GOOD signature in: "'.$filename.'"', FILE_APPEND);
             }
         } else {
             $error = gnupg_geterror($res);
             echo 'BAD signature in: "'.$filename.'"'."\n";
             echo $error."\n";
             file_put_contents($logfile, "\n".format_log_date()." ".$config_name.' BAD signature in: "'.$filename.'" '.$error, FILE_APPEND);
         }

           //  echo "SUMMARY: ".$gnupg_summary[$info[0]['summary']]."\n";

     }
 }
 

/***** Send key to group *****/
// How often to send key to group
// in seconds (default 1 month)
$mail_update_time = 2592000;
$do_mail_update = false;
if(filemtime($spooldir.'/bbs-mail-update-timer') + $mail_update_time > time()) { //false
    if(is_file($config_dir.'/bbs-mail-debug')) { //true
        $do_mail_update = true;
    }
} else { //true
    $do_mail_update = true;
}

if($do_mail_update == true) {
    echo "Sending keys to ".$rslight_gpg['nntp_group']."\n";
    send_keys_to_group($res, $rslight_gpg);
    touch($spooldir.'/bbs-mail-update-timer');
}

function inspect_message($message, $filename) {
    global $logfile, $config_name;
    
    $header = array();
    $body = array();
    $return_data = array();
    
    if(strpos($message, 'bbsmail-MAILKEY notice')) {
        file_put_contents($logfile, "\n".format_log_date()." ".$config_name." Found MAILKEY message ".$filename, FILE_APPEND);
    } else {
        if(strpos($message, 'bbsmail-BBSMAIL notice')) {
            $return_data['type'] = 'bbsmail';
            file_put_contents($logfile, "\n".format_log_date()." ".$config_name." Found BBSMAIL message ".$filename, FILE_APPEND);
        } else {
            file_put_contents($logfile, "\n".format_log_date()." ".$config_name." Found UNKNOWN message ".$filename, FILE_APPEND);
            return false;
        }
    }
    
    $raw_message = file($message);
    $is_header = 1;
    $mailkey_header = 0;
    $mailkey_body = 0;
    
    foreach($raw_message as $line) {
        if(trim($line) == '') {
            $is_header = 0;
            continue;
        }
        if($is_header == 1) {
            if(strpos($line, 'From: ') !== false) {
                $from_line = explode("From: ", $line);
                $from = trim($from_line[1]);
                $return_data['from'] = $from;
            }
            if(strpos($line, 'Subject: ') !== false) {
                $subject_line = explode("Subject: ", $line);
                $subject = trim($subject_line[1]);
                if(strpos($subject, '@@RSL MAILKEY notice') !== false) {
                    $return_data['type'] = 'mailkey';
                } else {
                    if(strpos($subject, '@@RSL BBSMAIL notice') !== false) {
                        $return_data['type'] = 'bbsmail';
                    } else {
                        return false;
                    }
                }
            }
            $header[] = $line;
        } else {
          if($return_data['type'] == 'mailkey') {
            if(strpos($line, '@@BEGIN MAILKEY HEADERS') !== false) {
                $mailkey_header = 1;
            }
            if($mailkey_header == 1) {
                if(strpos($line, 'From: ') !== false) {
                    $mailkey = explode("From: ", $line);
                    $return_data['mailkey_from'] = trim($mailkey[1]);
                } else {
                    if(strpos($line, 'Version: ') !== false) {
                        $mailkey = explode("Version: ", $line);
                        $return_data['mailkey_version'] = trim($mailkey[1]);
                    } else {
                        if(strpos($line, 'Notice-ID: ') !== false) {
                            $mailkey = explode("Notice-ID: ", $line);
                            $return_data['mailkey_notice-id'] = trim($mailkey[1]);
                        }
                    }
                }
            }
            if(strpos($line, '@@BEGIN MAILKEY BODY') !== false) {
                $mailkey_body = 1;
                $mailkey_header = 0;
            }
            if($mailkey_body == 1) {
                if(strpos($line, 'Key: ') !== false) {
                    $mailkey = explode("Key: ", $line);
                    $return_data['mailkey_key'] = trim($mailkey[1]);
                } else {
                    if(strpos($line, 'Location: ') !== false) {
                        $mailkey = explode("Location: ", $line);
                        $return_data['mailkey_location'] = trim($mailkey[1]);
                    } else {
                        if(strpos($line, 'Domain: ') !== false) {
                            $mailkey = explode("Domain: ", $line);
                            $return_data['mailkey_domain'] = trim($mailkey[1]);
                        }
                    }
                }
            }
            if(trim($line) == '.') {
                $line = ' ';
            }
            $body[] = rtrim($line);
          } else {
              $body[] = rtrim($line);
          }
        }
    }
    $return_data['body'] = implode("\n", $body);
    return($return_data);
}

function send_keys_to_group($res, $rslight_gpg) {
    global $spooldir, $config_name, $mail_update_time, $CONFIG, $rslight_version;
    
    $cwd = getcwd();
    $keydir = preg_replace('/spoolnews/','pubkey/',$cwd);
    $key_location = "/pubkey/server_pubkey.txt";
    $signing_key = trim(file_get_contents($keydir.'/server_fingerprint.txt'));
    $fingerprint_clean = preg_replace('/\ /', '', $signing_key);
    gnupg_addsignkey($res,$fingerprint_clean)."\n";
    
    $start="@@BEGIN MAILKEY HEADERS";
    $begin="@@BEGIN MAILKEY BODY";
    $end="@@END MAILKEY BODY";
    
/* Get days since last sent for creating message-id    
 * (Don't allow posting more than once per day)
*/
    $date1 = date_create(date("Y-m-d", time() - $mail_update_time));
    $date2 = date_create(date("Y-m-d", time()));
    $diff_days = date_diff($date1,$date2);
    
    $outgoing_dir = $spooldir.'/'.$config_name.'/outgoing';
    if(!is_dir($outgoing_dir)) {
        mkdir($outgoing_dir, 0700, true);
    }
    $domain = $rslight_gpg['domain_name'];
    $organization = $CONFIG['organization'];
    $from = $rslight_gpg['from_email'];
    $contact = $rslight_gpg['contact'];
    
    $outgoing_file = tempnam($outgoing_dir, 'bbsmail-');
        
    $body='';
    $body.="******************************************************\n";
    $body.="THIS IS A TEST POST! DO NOT USE THIS FOR A REAL SITE!\n";
    $body.="******************************************************\n\n";
    $body.="You may use this to import the public key for $domain.\n";
    $body.="This message is automatically generated by $from.\n\n";
    
    $body.="This message was signed using the following key:\n";
    $body.="$signing_key\n\n";
    
    $body.="The GPG key needed to verify the signature of messages\n";
    $body.="issued by $from is available at:\n";
    $body.="$domain$key_location\n\n";
    
    $body.="For information contact $contact.\n\n";
    
    $body.=$start."\n";
    $body.='    Version: '.$rslight_version."\n";
    $body.='    From: '.$from."\n";
    $hashtail = hash('crc32', $domain.$organization.$from.$rslight_gpg['nntp_group']);
    $thishash = hash('crc32', $body.$diff_days->format("%a").$hashtail).hash('crc32', $signing_key);
    $body.="    Notice-ID: ".$thishash."\n";
    
    $body.=$begin."\n";
    $body.="    Key: ".$signing_key."\n";
    $body.="    Location: ".$domain.$key_location."\n";
    $body.="    Domain: ".$domain."\n";
    $body.=$end."\n";   
    
    $header='';
    $header.="From: $from\n";
    $header.="Newsgroups: ".$rslight_gpg['nntp_group']."\n";
    $header.="Subject: @@RSL MAILKEY notice ".$thishash."\n";
    $header.="Message-ID: <$thishash@$domain>\n";
    $header.="Content-Type: text/plain; charset=utf-8; format=flowed\n";
    $header.="Content-Transfer-Encoding: 8bit\n";
    $header.="Organization: $organization\n\n";
    
    $signed_body = gnupg_sign($res, $body);
    file_put_contents($outgoing_file, $header.$signed_body);
    echo "Posted <".$thishash."@".$domain.">\n\n";   
}

