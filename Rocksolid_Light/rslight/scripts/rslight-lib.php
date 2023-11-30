<?php

function interact($msgsock, $use_crypto = false)
{
    global $CONFIG, $logdir, $lockdir, $logfile, $installed_path, $config_path, $config_dir, $groupconfig, $workpath, $path, $spooldir, $nntp_group, $nntp_article, $auth_ok, $user, $pass;
    $workpath = $spooldir . "/";
    $path = $workpath . "articles/";
    $groupconfig = $spooldir . "/spoolnews/groups.txt";

    $logfile = $logdir . '/nntp.log';
    $nntp_group = "";
    $nntp_article = "";
    /* CRYPTO */
    stream_set_blocking($msgsock, true);
    if ($use_crypto) {
        $cryptoSetup = stream_socket_enable_crypto($msgsock, TRUE, STREAM_CRYPTO_METHOD_TLSv1_0_SERVER | STREAM_CRYPTO_METHOD_TLSv1_1_SERVER | STREAM_CRYPTO_METHOD_TLSv1_2_SERVER);
    }
    stream_set_timeout($msgsock, 300);
    $client = stream_socket_get_name($msgsock, 1);
    $client_ip = explode(':', $client);
    if (strpos($CONFIG['open_clients'], $client_ip[0]) !== false) {
        $auth_ok = 1;
    }
    /* Send instructions. */
    $msg = "200 Rocksolid Light NNTP Server ready (no posting)\r\n";
    fwrite($msgsock, $msg, strlen($msg));
    do {
        $msg = "";
        set_time_limit(30);
        $buf = fgets($msgsock, 2048);
        if (file_exists($config_dir . "/nntp.disable")) {
            $parent_pid = file_get_contents($lockdir . '/rslight-nntp.lock', IGNORE_NEW_LINES);
            posix_kill($parent_pid, SIGTERM);
            exit();
        }
        if ($buf === false) {
            // file_put_contents($logfile, "\n".format_log_date()." socket read failed: reason: " . socket_strerror(socket_last_error($msgsock)), FILE_APPEND);
            break;
        }
        set_time_limit(0);
        $buf = trim($buf);
        if (strlen($buf) < 1) {
            continue;
        }
        if (stripos($buf, 'AUTHINFO PASS') !== false) {
            file_put_contents($logfile, "\n" . format_log_date() . " " . $client . " AUTHINFO PASS (hidden)", FILE_APPEND);
        } else {
            file_put_contents($logfile, "\n" . format_log_date() . " " . $client . " " . $buf, FILE_APPEND);
        }
        $command = explode(' ', $buf);
        $command[0] = strtolower($command[0]);
        if (isset($command[1])) {}
        if ($command[0] == 'date') {
            $msg = '111 ' . date('YmdHis') . "\r\n";
            fwrite($msgsock, $msg, strlen($msg));
            continue;
        }
        if ($command[0] == 'list') {
            if (isset($command[1])) {
                if (isset($command[2])) {
                    $msg = get_list(strtolower($command[1]), $command[2], $msgsock);
                } else {
                    $msg = get_list(strtolower($command[1]), false, $msgsock);
                }
            } else {
                $msg = get_list("active", false, $msgsock);
            }
            fwrite($msgsock, $msg, strlen($msg));
            continue;
        }
        if ($command[0] == 'post') {
            if ($auth_ok == 0) {
                $msg = "480 Posting not permitted (Auth required)\r\n";
                fwrite($msgsock, $msg, strlen($msg));
                continue;
            }
            $msg = "340 Send article to be posted\r\n";
            $tempfilename = tempnam(sys_get_temp_dir(), '');
            $tempfilehandle = fopen($tempfilename, 'wb');
            fwrite($msgsock, $msg, strlen($msg));
            $buf = fgets($msgsock, 2048);
            while (trim($buf) !== '.') {
                fwrite($tempfilehandle, $buf);
                $buf = fgets($msgsock, 2048);
            }
            fclose($tempfilehandle);
            $msg = prepare_post($tempfilename);
            fwrite($msgsock, $msg, strlen($msg));
            continue;
        }
        if ($command[0] == 'capabilities') {
            $msg = "101 Capability list:\r\n";
            $msg .= "VERSION 2\r\n";
            $msg .= "AUTHINFO USER\r\n";
            $msg .= "HDR\r\n";
            $msg .= "LIST ACTIVE HEADERS NEWSGROUPS OVERVIEW.FMT\r\n";
            if ($auth_ok == '1') {
                $msg .= "POST\r\n";
            }
            $msg .= "OVER\r\n";
            $msg .= "READER\r\n";
            $msg .= ".\r\n";
            fwrite($msgsock, $msg, strlen($msg));
            continue;
        }

        if ($command[0] == 'newgroups') {
            $msg = get_newgroups($command);
            fwrite($msgsock, $msg, strlen($msg));
            continue;
        }
        if ($command[0] == 'next') {
            $msg = get_next($nntp_group);
            fwrite($msgsock, $msg, strlen($msg));
            continue;
        }
        if ($command[0] == 'last') {
            $msg = get_last($nntp_group);
            fwrite($msgsock, $msg, strlen($msg));
            continue;
        }
        if ($command[0] == 'authinfo') {
            if (! isset($command[2])) {
                $command[2] = fgets($msgsock, 2048);
            }
            if (strtolower($command[1]) == 'user') {
                $user = $command[2];
                if (isset($command[3])) {
                    $user = $user . " " . $command[3];
                }
                $msg = "381 Enter password\r\n";
                fwrite($msgsock, $msg, strlen($msg));
                continue;
            }
            if (strtolower($command[1]) == 'pass') {
                if ($user == "") {
                    $msg = "482 Authentication commands issued out of sequence\r\n";
                } else {
                    $pass = $command[2];
                    if (check_bbs_auth($user, $pass)) {
                        $auth_ok = 1;
                        $msg = "281 Authentication succeeded\r\n";
                    } else {
                        $auth_ok = 0;
                        $msg = "481 Authentication failed\r\n";
                    }
                }
                fwrite($msgsock, $msg, strlen($msg));
                continue;
            }
            $msg = "501 Syntax error\r\n";
            fwrite($msgsock, $msg, strlen($msg));
            continue;
        }
        if ($command[0] == 'mode') { // Here to handle MODE READER. This is our only mode right now.
            $msg = "200 Rocksolid Light NNRP Server ready (no posting)\r\n";
            fwrite($msgsock, $msg, strlen($msg));
            continue;
        }
        if ($command[0] == 'stat') {
            $msg = get_stat($command[1]);
            fwrite($msgsock, $msg, strlen($msg));
            continue;
        }
        if ($command[0] == 'article') {
            $msg = get_article($command[1], $nntp_group);
            fwrite($msgsock, $msg, strlen($msg));
            continue;
        }
        if ($command[0] == 'head') {
            $msg = get_header($command[1], $nntp_group);
            fwrite($msgsock, $msg, strlen($msg));
            continue;
        }
        if ($command[0] == 'body') {
            $msg = get_body($command[1], $nntp_group);
            fwrite($msgsock, $msg, strlen($msg));
            continue;
        }
        if ($command[0] == 'listgroup') {
            if (isset($command[1])) {
                $nntp_group = $command[1];
            }
            $msg = get_listgroup($nntp_group, $msgsock);
            fwrite($msgsock, $msg, strlen($msg));
            continue;
        }
        if ($command[0] == 'group') {
            $change_group = $command[1];
            $msg = get_group($change_group);
            fwrite($msgsock, $msg, strlen($msg));
            continue;
        }
        if ($command[0] == 'xgtitle') {
            if (isset($command[1])) {
                $msg = get_title($command[1]);
            } else {
                $msg = get_title("active");
            }
            fwrite($msgsock, $msg, strlen($msg));
            continue;
        }
        if (($command[0] == 'xover') || ($command[0] == 'over')) {
            $msg = get_xover($command[1], $msgsock);
            fwrite($msgsock, $msg, strlen($msg));
            continue;
        }
        if (($command[0] == 'xhdr') || ($command[0] == 'hdr')) {
            $msg = get_xhdr($command[1], $command[2]);
            fwrite($msgsock, $msg, strlen($msg));
            continue;
        }
        if ($command[0] == 'help') {
            $msg = "100 Sorry, can't help\r\n";
            fwrite($msgsock, $msg, strlen($msg));
            continue;
        }
        if ($command[0] == 'quit') {
            $msg = "205 closing connection - goodbye!\r\n";
            fwrite($msgsock, $msg, strlen($msg));
            // socket_close($msgsock);
            exit(0);
        }
        file_put_contents($logfile, "\n" . format_log_date() . " Syntax error: " . $buf, FILE_APPEND);
        $talkback = "500 Syntax error or unknown command\r\n";
        fwrite($msgsock, $talkback, strlen($talkback));
    } while (true);
    exit(0);
}

/**
 * Become a daemon by forking and closing the parent
 */
function become_daemon()
{
    $pid = pcntl_fork();

    if ($pid == - 1) {
        /* fork failed */
        echo "fork failure!\n";
        exit();
    } elseif ($pid) {
        /* close the parent */
        exit();
    } else {
        /* child becomes our daemon */
        posix_setsid();
        chdir('/');
        umask(0);
        return posix_getpid();
    }
}

function prepare_post($filename)
{
    global $logdir, $spooldir, $config_dir, $rslight_gpg;
    $logfile = $logdir . '/nntp.log';
    $message = file($filename, FILE_IGNORE_NEW_LINES);
    $response = "441 Posting failed\r\n";
    $lines = 0;
    $is_header = 1;
    $head_from = false;
    $head_subject = false;
    $head_newsgroups = false;
    $nocem_check = "@@NCM";
    $bbsmail_check = "@@RSL";

    foreach ($message as $line) {
        if (trim($line) == "" || $lines > 0) {
            $is_header = 0;
            $lines ++;
        }
        if ($lines > 0 && $is_header = 0) {
            $break;
        }
        if (stripos($line, "From: ") === 0) {
            $lines ++;
            $head_from = true;
            continue;
        }
        if (stripos($line, "Newsgroups: ") === 0) {
            $ngroups = explode(': ', $line);
            $lines ++;
            $head_newsgroups = true;
            continue;
        }
        if (stripos($line, "Subject: ") === 0) {
            $sub = explode(': ', $line);
            $subject = $sub[1];
            $lines ++;
            $head_subject = true;
            continue;
        }
    }
    $ok = 0;
    if (! $head_from || ! $head_subject || ! $head_newsgroups) {
        $response = "441 Posting failed (Missing header line)\r\n";
        return $response;
    }
    $allgroups = preg_split("/\ |\,/", $ngroups[1]);
    foreach ($allgroups as $agroup) {
        $agroup = trim($agroup);
        if ((testGroup($agroup)) || $agroup == '') {
            $response = process_post($message, $agroup);
            if (substr($response, 0, 3) == "240") {
                $ok = 1;
            }
        }
    }

    if ($ok == 1) {
        if ((strpos($rslight_gpg['nntp_group'], $group) !== false) && ($rslight_gpg['enable'] == '1')) {
            if (strpos($subject, $bbsmail_check) !== false) {
                $bbsmail_file = preg_replace('/@@RSL /', '', $subject);
                $bbsmail_filename = $spooldir . "/bbsmail/in/bbsmail-" . $bbsmail_file;
                copy($filename, $bbsmail_filename);
            }
        }
        if (strpos($subject, $nocem_check) !== false) {
            $nocem_file = tempnam($spooldir . "/nocem", "nocem-" . $group . "-");
            copy($filename, $nocem_file);
        }
        $response = "240 Article received OK\r\n";
    } else {
        $response = "441 Posting failed (group not found)\r\n";
    }
    return $response;
}

function process_post($message, $group)
{
    global $logfile, $spooldir, $config_dir, $CONFIG, $nntp_group;
    $no_mid = 1;
    $no_date = 1;
    $no_org = 1;
    $is_header = 1;
    $body = "";
    $ref = 0;
    $response = "";
    $bytes = 0;
    $lines = 0;
    /* Process post */
    foreach ($message as $line) {
        $bytes = $bytes + mb_strlen($line, '8bit');
        if (trim($line) == "" || $lines > 0) {
            $is_header = 0;
            $lines ++;
        }
        if ($is_header == 0) {
            $body .= $line . "\n";
        } else {
            if (stripos($line, "Date: ") === 0) {
                $finddate = explode(': ', $line);
                $article_date = strtotime($finddate[1]);
                $no_date = 0;
            }
            if (stripos($line, "Organization: ") !== false) {
                $no_org = 0;
            }
            if (stripos($line, "Subject: ") !== false) {
                $subject = explode('Subject: ', $line, 2);
                $ref = 0;
            }
            if (stripos($line, "From: ") === 0) {
                $from = explode(': ', $line);
                $ref = 0;
            }
            if (stripos($line, "Xref: ") === 0) {
                $xref = $line;
                $ref = 0;
            }
            if (stripos($line, "Newsgroups: ") === 0) {
                $ngroups = explode(': ', $line);
                $newsgroups = $ngroups[1];
                $ref = 0;
            }
            if (stripos($line, "References: ") === 0) {
                $references_line = explode(': ', $line);
                $references = $references_line[1];
                $ref = 1;
            }
            if ((stripos($line, ':') === false) && (strpos($line, '>'))) {
                if ($ref == 1) {
                    $references = $references . " " . trim($line);
                }
            }
            if (stripos($line, "Message-ID: ") !== false) {
                $mid = explode(': ', $line);
                $no_mid = 0;
            }
        }
    }
    /*
     * SPAM CHECK
     */
    if (isset($CONFIG['spamassassin']) && ($CONFIG['spamassassin'] == true)) {
        $spam_result_array = check_spam($subject[1], $from[1], $newsgroups, $references, $body, $msgid);
        $res = $spam_result_array['res'];
        $spamresult = $spam_result_array['spamresult'];
        $spamcheckerversion = $spam_result_array['spamcheckerversion'];
        $spamlevel = $spam_result_array['spamlevel'];
    }
    if ($res === 1) {
        $orig_newsgroups = $newsgroups;
        $newsgroups = $CONFIG['spamgroup'];
        $group = $newsgroups;
    }
    /* Find section for posting */
    $section = get_section_by_group($group);

    @mkdir($spooldir . "/" . $section . "/outgoing", 0755, 'recursive');
    $postfilename = $spooldir . '/' . $section . '/outgoing/' . $mid[1] . '.msg';
    $postfilehandle = fopen($postfilename, 'w');
    if ($no_date == 1) {
        $article_date = time();
        $date_rep = date('D, j M Y H:i:s O', $article_date);
        fputs($postfilehandle, "Date: " . $date_rep . "\r\n");
    } else {
        $date_rep = $finddate[1];
    }
    if ($no_mid == 1) {
        $identity = $subject[1] . "," . $from[1] . "," . $ngroups[1] . "," . $references . "," . $body;
        $msgid = '<' . md5($identity) . '$1@' . trim($CONFIG['email_tail'], '@') . '>';
        fputs($postfilehandle, "Message-ID: " . $msgid . "\r\n");
    } else {
        $msgid = $mid[1];
    }
    if ($no_org == 1) {
        fputs($postfilehandle, "Organization: " . $CONFIG['organization'] . "\r\n");
    }
    if ($res === 1) {
        if ($orig_newsgroups !== $CONFIG['spamgroup']) {
            fputs($postfilehandle, "X-Rslight-Original-Group: " . $orig_newsgroups . "\r\n");
        }
    }
    $is_header = 1;
    $lines = 0;
    foreach ($message as $line) {
        if (trim($line) == "" || $lines > 0) {
            $is_header = 0;
            $lines ++;
        }
        if (stripos($line, "Newsgroups: ") === 0 && $is_header == 1) {
            fputs($postfilehandle, "Newsgroups: " . $newsgroups . "\r\n");
        } else {
            fputs($postfilehandle, $line . "\r\n");
        }
    }
    fclose($postfilehandle);
    chmod($postfilename, 0600);
    unlink($filename);
    if ($section == "") {
        $response = "441 Posting failed (group not found)\r\n";
    } else {
        $response = insert_article($section, $group, $postfilename, $subject[1], $from[1], $article_date, $date_rep, $msgid, $references, $bytes, $lines, $xref, $body);
    }
    return $response;
}

function get_next($nntp_group)
{
    global $spooldir, $nntp_article;
    if ($nntp_group == "") {
        $response = "412 Not in a newsgroup\r\n";
        return $response;
    }
    $ok_article = get_article_list($nntp_group);
    sort($ok_article);
    $last = $ok_article[key(array_slice($ok_article, - 1, 1, true))];
    if (($nntp_article + 1) > $last) {
        $response = "421 No next article to retrieve\r\n";
    } else {
        $nntp_article ++;
        $database = $spooldir . '/articles-overview.db3';
        $table = 'overview';
        $dbh = overview_db_open($database, $table);
        $stmt = $dbh->prepare("SELECT * FROM $table WHERE newsgroup=:newsgroup AND number=:number");
        $stmt->bindParam(':newsgroup', $nntp_group);
        $stmt->bindParam(':number', $nntp_article);
        $stmt->execute();
        while ($found = $stmt->fetch()) {
            $msgid = $found['msgid'];
            break;
        }
        $dbh = null;
        $response = "223 " . $nntp_article . " " . $msgid . " Article retrieved; request text separately\r\n";
    }
    return $response;
}

function get_last($nntp_group)
{
    global $spooldir, $nntp_article;
    if ($nntp_group == "") {
        $response = "412 Not in a newsgroup\r\n";
        return $response;
    }
    $ok_article = get_article_list($nntp_group);
    rsort($ok_article);
    $first = $ok_article[key(array_slice($ok_article, - 1, 1, true))];
    if (($nntp_article - 1) < $first || ! isset($nntp_article)) {
        $response = "422 No previous article to retrieve\r\n";
    } else {
        $nntp_article --;
        $database = $spooldir . '/articles-overview.db3';
        $table = 'overview';
        $dbh = overview_db_open($database, $table);
        $stmt = $dbh->prepare("SELECT * FROM $table WHERE newsgroup=:newsgroup AND number=:number");
        $stmt->bindParam(':newsgroup', $nntp_group);
        $stmt->bindParam(':number', $nntp_article);
        $stmt->execute();
        while ($found = $stmt->fetch()) {
            $msgid = $found['msgid'];
            break;
        }
        $dbh = null;
        $response = "223 " . $nntp_article . " " . $msgid . " Article retrieved; request text separately\r\n";
    }
    return $response;
}

function get_xhdr($header, $articles)
{
    global $config_dir, $spooldir, $nntp_group, $nntp_article, $workpath, $path;
    $tmpgroup = $nntp_group;
    $mid = false;

    // Use article pointer
    if (! isset($articles) && is_numeric($nntp_article)) {
        $articles = $nntp_article;
    }
    // By Message-ID
    if (strpos($articles, "@") !== false) {
        $found = find_article_by_msgid($articles);
        $nntp_group = $found['newsgroup'];
        $first = $found['number'];
        $last = $first;
        $this_id = $found['msgid'];
        $articles = $found['number'];
        if (! isset($articles)) {
            $output = "430 No article with that message-id\r\n";
            return $output;
        }
        $output = "221 Header information for " . $header . " follows (from articles)\r\n";
    }

    if (! isset($tmpgroup)) {
        $msg = "412 no newsgroup selected\r\n";
        return $msg;
    }
    $thisgroup = $path . "/" . preg_replace('/\./', '/', $tmpgroup);
    $article_num = explode('-', $articles);
    $first = $article_num[0];
    if (isset($article_num[1]) && is_numeric($article_num[1])) {
        $last = $article_num[1];
    } else {
        if (strpos($articles, "-")) {
            $ok_article = get_article_list($nntp_group);
            // fclose($group_overviewfp);
            sort($ok_article);
            $last = $ok_article[key(array_slice($ok_article, - 1, 1, true))];
            if (! is_numeric($last))
                $last = 0;
        } else {
            $last = $first;
        }
    }
    $msg = "221 Header information for " . $header . " follows (from articles)\r\n";
    for ($i = $first; $i <= $last; $i ++) {
        $article_full_path = $thisgroup . '/' . strval($i);
        $data = extract_header_line($article_full_path, $header, $tmpgroup, $i);
        if ($data !== false) {
            if ($mid !== false) {
                $msg .= $mid . " " . $data;
            } else {
                $msg .= strval($i) . " " . $data;
            }
        }
    }
    $msg .= ".\r\n";
    return $msg;
}

function extract_header_line($article_full_path, $header, $thisgroup, $article)
{
    global $CONFIG;
    if ($CONFIG['article_database'] == '1') {
        $thisarticle = np_get_db_article($article, $thisgroup);
    } else {
        $thisarticle = file($article_full_path, FILE_IGNORE_NEW_LINES);
    }
    foreach ($thisarticle as $thisline) {
        if ($thisline == "") {
            $msg2 .= ".\r\n";
            break;
        }
        if (stripos($thisline, $header) === 0) {
            $content = preg_split("/$header: /i", $thisline);
            return ($content[1] . "\r\n");
        }
    }
    return (false);
}

function get_title($mode)
{
    global $nntp_group, $workpath, $spooldir, $path;
    if ($mode == "active") {
        $msg = "481 descriptions unavailable\r\n";
        return $msg;
    }
    if (! file_exists($spooldir . "/" . $mode . "-title")) {
        $msg = "481 descriptions unavailable\r\n";
        return $msg;
    }
    $title = file_get_contents($spooldir . "/" . $mode . "-title");
    $msg = "282 list of group and description follows\r\n";
    $msg .= trim($title);

    $msg .= "\r\n.\r\n";
    return $msg;
}

function get_xover($articles, $msgsock)
{
    global $nntp_group, $nntp_article, $workpath, $path, $spooldir;
    // Use article pointer
    if (! isset($articles) && is_numeric($nntp_article)) {
        $articles = $nntp_article;
    }
    // By Message-ID
    if (strpos($articles, "@") !== false) {
        $found = find_article_by_msgid($articles);
        $nntp_group = $found['newsgroup'];
        $first = $found['number'];
        $last = $first;
        $this_id = $found['msgid'];
        $articles = $found['number'];
        if (! isset($articles)) {
            $output = "430 No article with that message-id\r\n";
            return $output;
        }
        $output = "224 Overview information follows for " . $this_id . "\r\n";
    }
    if ($nntp_group == '') {
        $msg = "412 no newsgroup selected\r\n";
        return $msg;
    }
    if (! isset($articles)) {
        $msg = "420 no article(s) selected\r\n";
        return $msg;
    }
    if (! isset($this_id)) {
        $article_num = explode('-', $articles);
        $first = $article_num[0];
        if (isset($article_num[1]) && is_numeric($article_num[1])) {
            $last = $article_num[1];
            $output = "224 Overview information follows for articles " . $first . " through " . $last . "\r\n";
        } else {
            if (strpos($articles, "-")) {
                $ok_article = get_article_list($nntp_group);
                sort($ok_article);
                $last = $ok_article[key(array_slice($ok_article, - 1, 1, true))];
                if (! is_numeric($last)) {
                    $last = 0;
                }
                $output = "224 Overview information follows for articles " . $first . " through " . $last . "\r\n";
            } else {
                $last = $first;
                $output = "224 Overview information follows for " . $first . "\r\n";
            }
        }
    }
    fwrite($msgsock, $output, strlen($output));

    $database = $spooldir . '/articles-overview.db3';
    $table = 'overview';
    $dbh = overview_db_open($database, $table);

    $stmt = $dbh->prepare("SELECT * FROM $table WHERE newsgroup=:thisgroup AND number=:number"); // Why doesn't BETWEEN work properly here?
    for ($i = $first; $i <= $last; $i ++) {
        $stmt->execute([
            'thisgroup' => $nntp_group,
            ':number' => $i
        ]);
        while ($row = $stmt->fetch()) {
            $msg .= $row['number'] . "\t" . $row['subject'] . "\t" . $row['name'] . "\t" . $row['datestring'] . "\t" . $row['msgid'] . "\t" . $row['refs'] . "\t" . $row['bytes'] . "\t" . $row['lines'] . "\t" . $row['xref'] . "\r\n";
        }
    }
    $dbh = null;
    $msg .= ".\r\n";
    return $msg;
}

function get_stat($article)
{
    global $nntp_group, $nntp_article, $workpath, $path;
    if ($nntp_group == '') {
        $msg = "412 Not in a newsgroup\r\n";
        return $msg;
    }
    // Use article pointer
    if (! isset($article) && is_numeric($nntp_article)) {
        $article = $nntp_article;
    }
    if (! is_numeric($article)) {
        $msg = "423 No article number selected\r\n";
        return $msg;
    }

    $database = $spooldir . '/articles-overview.db3';
    if (! is_file($database)) {
        return false;
    }
    $dbh = overview_db_open($database);
    $query = $articles_dbh->prepare('SELECT * FROM overview WHERE number=:number AND newsgroup=:newsgroup');
    $query->execute([
        'number' => $article,
        'newsgroup' => $nntp_group
    ]);
    $found = 0;
    while ($row = $query->fetch()) {
        $found = 1;
        break;
    }
    $dbh = null;
    if ($found == 1) {
        $msg = "223 " . $article . " " . $row['msgid'] . " status\r\n";
    } else {
        $msg = "423 No such article number " . $article . "\r\n";
    }
    return $msg;
}

function get_article($article, $nntp_group)
{
    global $CONFIG, $config_dir, $path, $groupconfig, $config_name, $spooldir, $nntp_article;
    $msg2 = "";
    // Use article pointer
    if (! isset($article) && is_numeric($nntp_article)) {
        $article = $nntp_article;
    }
    // By Message-ID
    if (! is_numeric($article)) {
        $found = find_article_by_msgid($article);
        $nntp_group = $found['newsgroup'];
        $article = $found['number'];
        $this_id = $found['msgid'];
    } else {
        // By article number
        if ($nntp_group === "") {
            $msg .= "412 no newsgroup has been selected\r\n";
            return $msg;
        }
        if (! is_numeric($article)) {
            $msg .= "420 no article has been selected\r\n";
            return $msg;
        }
    }
    if ($CONFIG['article_database'] == '1') {
        $thisarticle = np_get_db_article($article, $nntp_group);
        if ($thisarticle === FALSE) {
            $msg .= "430 no such article found\r\n";
            return $msg;
        }
        $thisarticle[] = ".";
    } else {
        $thisgroup = $path . "/" . preg_replace('/\./', '/', $nntp_group);
        if (! file_exists($thisgroup . "/" . $article)) {
            $msg .= "430 no such article found\r\n";
            return $msg;
        }
        $thisarticle = file($thisgroup . "/" . $article, FILE_IGNORE_NEW_LINES);
    }
    foreach ($thisarticle as $thisline) {
        if ((strpos($thisline, "Message-ID: ") === 0) && ! isset($mid[1])) {
            $mid = explode(': ', $thisline);
        }
        $msg2 .= $thisline . "\r\n";
    }
    $msg = "220 " . $article . " " . $mid[1] . " article retrieved - head and body follow\r\n";
    $nntp_article = $article;
    return $msg . $msg2;
}

function get_header($article, $nntp_group)
{
    global $CONFIG, $nntp_article, $config_dir, $path, $groupconfig, $config_name, $spooldir;
    $msg2 = "";
    // Use article pointer
    if (! isset($article) && is_numeric($nntp_article)) {
        $article = $nntp_article;
    }
    // By Message-ID
    if (! is_numeric($article)) {
        $found = find_article_by_msgid($article);
        $nntp_group = $found['newsgroup'];
        $article = $found['number'];
        $this_id = $found['msgid'];
    } else {
        // By article number
        if ($nntp_group === "") {
            $msg .= "412 no newsgroup has been selected\r\n";
            return $msg;
        }
        if (! is_numeric($article)) {
            $msg .= "420 no article has been selected\r\n";
            return $msg;
        }
    }
    if ($CONFIG['article_database'] == '1') {
        $thisarticle = np_get_db_article($article, $nntp_group);
        if ($thisarticle === FALSE) {
            $msg .= "430 no such article found\r\n";
            return $msg;
        }
    } else {
        $thisgroup = $path . "/" . preg_replace('/\./', '/', $nntp_group);
        if (! file_exists($thisgroup . "/" . $article)) {
            $msg .= "430 no such article found\r\n";
            return $msg;
        }
        $thisarticle = file($thisgroup . "/" . $article, FILE_IGNORE_NEW_LINES);
    }
    foreach ($thisarticle as $thisline) {
        if ($thisline == "") {
            $msg2 .= ".\r\n";
            break;
        }
        if ((strpos($thisline, "Message-ID: ") === 0) && ! isset($mid[1])) {
            $mid = explode(': ', $thisline);
        }
        $msg2 .= $thisline . "\r\n";
    }
    $msg = "221 " . $article . " " . $mid[1] . " article retrieved - header follows\r\n";
    return $msg . $msg2;
}

function get_body($article, $nntp_group)
{
    global $CONFIG, $nntp_article, $config_dir, $path, $groupconfig, $config_name, $spooldir;
    $msg2 = "";
    // Use article pointer
    if (! isset($article) && is_numeric($nntp_article)) {
        $article = $nntp_article;
    }
    // By Message-ID
    if (! is_numeric($article)) {
        $found = find_article_by_msgid($article);
        $nntp_group = $found['newsgroup'];
        $article = $found['number'];
        $this_id = $found['msgid'];
    } else {
        // By article number
        if ($nntp_group === "") {
            $msg .= "412 no newsgroup has been selected\r\n";
            return $msg;
        }
        if (! is_numeric($article)) {
            $msg .= "420 no article has been selected\r\n";
            return $msg;
        }
    }
    if ($CONFIG['article_database'] == '1') {
        $thisarticle = np_get_db_article($article, $nntp_group);
        if ($thisarticle === FALSE) {
            $msg .= "430 no such article found\r\n";
            return $msg;
        }
        $thisarticle[] = ".";
    } else {
        $thisgroup = $path . "/" . preg_replace('/\./', '/', $nntp_group);
        if (! file_exists($thisgroup . "/" . $article)) {
            $msg .= "430 no such article found\r\n";
            return $msg;
        }
        $thisarticle = file($thisgroup . "/" . $article, FILE_IGNORE_NEW_LINES);
    }
    foreach ($thisarticle as $thisline) {
        if (($thisline == "") && ($body == 0)) {
            $body = 1;
            continue;
        }
        if ((strpos($thisline, "Message-ID: ") === 0) && ! isset($mid[1])) {
            $mid = explode(': ', $thisline);
        }
        if ($body == 1) {
            $msg2 .= $thisline . "\r\n";
        }
    }
    $msg = "222 " . $article . " " . $mid[1] . " article retrieved - body follows\r\n";
    return $msg . $msg2;
}

function get_listgroup($nntp_group, $msgsock)
{
    global $spooldir, $path, $nntp_group, $groupconfig;
    $grouplist = file($groupconfig, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($nntp_group == '') {
        $msg = "412 no newsgroup selected\r\n";
        return $msg;
    }
    $ok_group = false;
    $count = 0;
    foreach ($grouplist as $findgroup) {
        $name = preg_split("/( |\t)/", $findgroup, 2);
        if (! strcmp($name[0], $nntp_group)) {
            $ok_group = true;
            break;
        }
    }
    $ok_article = get_article_list($nntp_group);
    // fclose($group_overviewfp);
    $count = count($ok_article);
    sort($ok_article);
    $last = $ok_article[key(array_slice($ok_article, - 1, 1, true))];
    $first = $ok_article[0];
    if (! is_numeric($last))
        $last = 0;
    if (! is_numeric($first))
        $first = 0;
    $output = "211 " . $count . " " . $first . " " . $last . " " . $nntp_group . "\r\n";
    fwrite($msgsock, $output, strlen($output));
    foreach ($ok_article as $art) {
        $output = $art . "\r\n";
        fwrite($msgsock, $output, strlen($output));
    }
    $msg = ".\r\n";
    return $msg;
}

function get_group($change_group)
{
    global $spooldir, $path, $nntp_group, $nntp_article, $groupconfig;
    $grouplist = file($groupconfig, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $ok_group = false;
    $count = 0;
    foreach ($grouplist as $findgroup) {
        $name = preg_split("/( |\t)/", $findgroup, 2);
        if (! strcmp($name[0], $change_group)) {
            $ok_group = true;
            break;
        }
    }
    if ($ok_group == false) {
        $response = "411 No such group " . $change_group . "\r\n";
        return $response;
    }
    $nntp_group = $change_group;
    $ok_article = get_article_list($nntp_group);
    $count = count($ok_article);
    sort($ok_article);
    $last = $ok_article[key(array_slice($ok_article, - 1, 1, true))];
    $first = $ok_article[0];
    if (! is_numeric($last))
        $last = 0;
    if (! is_numeric($first))
        $first = 0;
    $nntp_article = $first;
    $msg = "211 " . $count . " " . $first . " " . $last . " " . $nntp_group . "\r\n";
    return $msg;
}

function get_newgroups($mode)
{
    global $path, $groupconfig;
    $grouplist = file($groupconfig, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $mode = "active";
    if ($mode == "active") {
        $msg = '231 list of newsgroups follows' . "\r\n";
        foreach ($grouplist as $findgroup) {
            $name = preg_split("/( |\t)/", $findgroup, 2);
            if ($name[0][0] === ':')
                continue;
            $ok_article = get_article_list($nntp_group);
            sort($ok_article);
            $last = $ok_article[key(array_slice($ok_article, - 1, 1, true))];
            $first = $ok_article[0];
            if (! is_numeric($last))
                $last = 0;
            if (! is_numeric($first))
                $first = 0;
            $msg .= $name[0] . " " . $last . " " . $first . " n\r\n";
        }
    }
    if ($mode == "newsgroups") {
        $msg = '215 list of newsgroups and descriptions follows' . "\r\n";
        foreach ($grouplist as $findgroup) {
            if ($findgroup[0] === ':')
                continue;
            $msg .= $findgroup . "\r\n";
        }
    }
    if ($mode == "overview.fmt") {
        $msg = "215 Order of fields in overview database.\r\n";
        $msg .= "Subject:\r\n";
        $msg .= "From:\r\n";
        $msg .= "Date:\r\n";
        $msg .= "Message-ID:\r\n";
        $msg .= "References:\r\n";
        $msg .= "Bytes:\r\n";
        $msg .= "Lines:\r\n";
        $msg .= "Xref:full\r\n";
    }
    if (isset($msg)) {
        return $msg . ".\r\n";
    } else {
        $msg = "501 Syntax error or unknown command\r\n";
        return $msg . ".\r\n";
    }
}

function get_list($mode, $ngroup, $msgsock)
{
    global $path, $spooldir, $groupconfig;
    if ($ngroup) {
        $grouplist[0] = $ngroup;
    } else {
        $grouplist = file($groupconfig, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }
    if ($mode == "headers") {
        $msg = "215 metadata items supported:\r\n";
        $msg .= ":\r\n";
        $msg .= ":lines\r\n";
        $msg .= ":bytes\r\n";
    }
    if ($mode == "active") {
        $msg = '215 Newsgroups in form "group high low status"' . "\r\n";
        fwrite($msgsock, $msg, strlen($msg));
        foreach ($grouplist as $findgroup) {
            $name = preg_split("/( |\t)/", $findgroup, 2);
            if ($name[0][0] === ':')
                continue;
            $ok_article = get_article_list($findgroup);
            sort($ok_article);
            $last = $ok_article[key(array_slice($ok_article, - 1, 1, true))];
            $first = $ok_article[0];
            if (! is_numeric($last)) {
                $last = 0;
            }
            if (! is_numeric($first)) {
                $first = 0;
            }
            $output = $name[0] . " " . $last . " " . $first . " y\r\n";
            fwrite($msgsock, $output, strlen($output));
        }
        return ".\r\n";
    }
    if ($mode == "newsgroups") {
        $msg = '215 list of newsgroups and descriptions follows' . "\r\n";
        foreach ($grouplist as $findgroup) {
            if ($findgroup[0] === ':')
                continue;
            $name = preg_split("/( |\t)/", $findgroup, 2);
            if (trim($name[1]) !== "") {
                $msg .= $findgroup . "\r\n";
            } elseif (file_exists($spooldir . "/" . $name[0] . "-title")) {
                $msg .= file_get_contents($spooldir . "/" . $name[0] . "-title") . "\r\n";
            } else {
                $msg .= $findgroup . "\r\n";
            }
        }
    }
    if ($mode == "overview.fmt") {
        $msg = "215 Order of fields in overview database.\r\n";
        $msg .= "Subject:\r\n";
        $msg .= "From:\r\n";
        $msg .= "Date:\r\n";
        $msg .= "Message-ID:\r\n";
        $msg .= "References:\r\n";
        $msg .= "Bytes:\r\n";
        $msg .= "Lines:\r\n";
        $msg .= "Xref:full\r\n";
    }
    if (isset($msg)) {
        return $msg . ".\r\n";
    } else {
        $msg = "501 Syntax error or unknown command\r\n";
        return $msg . ".\r\n";
    }
}

/*
 * function encode_subject($line) {
 * $newstring=mb_encode_mimeheader(quoted_printable_decode($line));
 * return $newstring;
 * }
 */
function insert_article($section, $nntp_group, $filename, $subject_i, $from_i, $article_date, $date_i, $mid_i, $references_i, $bytes_i, $lines_i, $xref_i, $body)
{
    global $enable_rslight, $spooldir, $CONFIG, $logdir, $lockdir, $logfile;

    $return_val = "441 Posting failed\r\n";
    if ($CONFIG['remote_server'] !== '') {
        $sn_lockfile = $lockdir . '/' . $section . '-spoolnews.lock';
        $sn_pid = file_get_contents($sn_lockfile);
        if (posix_getsid($sn_pid) === false || ! is_file($sn_lockfile)) {
            file_put_contents($sn_lockfile, getmypid()); // create lockfile
        } else {
            file_put_contents($logfile, "\n" . format_log_date() . " " . $section . " Queuing local post: " . $nntp_group, FILE_APPEND);
            $return_val = "240 Article received OK (queued)\r\n";
            return ($return_val);
        }
    }
    $local_groupfile = $spooldir . "/" . $section . "/local_groups.txt";
    $article_date = strtotime($date_i);

    // Get list of article numbers to find what number is next
    $local = get_next_article_number($nntp_group);

    if ($article_date > time())
        $article_date = time();
    $in_file = fopen($filename, 'r');
    $tmp_file = tempnam(sys_get_temp_dir(), 'rslmsg-');

    // Prepare some tradspool paths
    if ($CONFIG['article_database'] !== '1') {
        $path = $spooldir . "/articles/";
        $grouppath = $path . preg_replace('/\./', '/', $nntp_group);
        $tradspool_out_file = fopen($grouppath . "/" . $local, 'w+');
        if (! is_dir($grouppath)) {
            mkdir($grouppath, 0755, true);
        }
    }

    $header = 1;
    $tmp_file_handle = fopen($tmp_file, 'w');
    while ($buf = fgets($in_file)) {
        if ($header == 1) {
            if (stripos($buf, "Content-Type: ") === 0) {
                preg_match('/.*charset=.*/', $buf, $te);
                $content_type = explode("Content-Type: text/plain; charset=", $te[0]);
            }
            if (stripos($buf, "Newsgroups: ") === 0) {
                $response = str_ireplace($group, $group, $buf);
                // Identify each group name for xref
                $groupnames = explode("Newsgroups: ", $buf);
                $allgroups = preg_split("/\ |\,/", $groupnames[1]);
                $ref = 0;
            }
        } else {}
        if ((trim($buf) == "") && ($header == 1)) {
            $current_article['xref'] = "Xref: " . $CONFIG['pathhost'];
            foreach ($allgroups as $agroup) {
                $agroup = trim($agroup);
                if ((! testGroup($agroup)) || $agroup == '') {
                    continue;
                }
                if ($group == $agroup) {
                    $artnum = $local;
                } else {
                    $artnum = get_next_article_number($agroup);
                }
                if ($artnum > 0) {
                    $current_article['xref'] .= ' ' . $agroup . ':' . $artnum;
                }
            }
            $header = 0;
            fputs($tmp_file_handle, $current_article['xref'] . PHP_EOL);
            $buf .= '';
        }
        fputs($tmp_file_handle, rtrim($buf, "\n\r") . PHP_EOL);
    }
    fputs($tmp_file_handle, "\n.\n");
    fclose($tmp_file_handle);
    fclose($in_file);
    touch($tmp_file, $article_date);
    file_put_contents($logfile, "\n" . format_log_date() . " " . $section . " Inserting local post: " . $nntp_group . ":" . $local, FILE_APPEND);

    $current_article = array();
    $current_article['article'] = file_get_contents($tmp_file);

    if ($CONFIG['article_database'] == '1') {
        if (isset($content_type[1])) {
            $this_snippet = get_search_snippet($body, $content_type[1]);
        } else {
            $this_snippet = get_search_snippet($body);
        }
    } else {
        rename($tmp_file, $tradspool_out_file);
    }

    $current_article['mid'] = $mid_i;
    $current_article['epochdate'] = $article_date;
    $current_article['stringdate'] = $date_i;
    $current_article['from'] = $from_i;
    $current_article['subject'] = $subject_i;
    $current_article['references'] = $references_i;
    $current_article['bytes'] = $bytes_i;
    $current_article['lines'] = $lines_i;
    $current_article['snippet'] = $this_snippet;

    foreach ($allgroups as $agroup) {
        $agroup = trim($agroup);
        if ((! testGroup($agroup)) || $agroup == '') {
            continue;
        }
        $current_article['group'] = $agroup;
        if ($nntp_group == $agroup) {
            $current_article['local'] = $local;
            insert_article_from_array($current_article);
        } else {
            $current_article['local'] = get_next_article_number($agroup);
            insert_article_from_array($current_article);
        }
    }

    $references = "";
    // End Overview
    $grouplist = file($local_groupfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $saveconfig = fopen($local_groupfile, 'w+');
    $local ++;
    foreach ($grouplist as $savegroup) {
        $name = explode(':', $savegroup);
        if (strcmp($name[0], $nntp_group) == 0) {
            fwrite($saveconfig, $nntp_group . ":" . $local . "\n");
        } else {
            fwrite($saveconfig, $savegroup . "\n");
        }
    }
    fclose($saveconfig);
    unlink($sn_lockfile);
    $return_val = "240 Article received OK (posted)\r\n";
    file_put_contents($logfile, "\n" . format_log_date() . " " . $nntp_group . ":" . -- $local . " " . $return_val, FILE_APPEND);
    return ($return_val);
}

function find_article_by_msgid($msgid)
{
    global $spooldir;
    $database = $spooldir . '/articles-overview.db3';
    $table = 'overview';
    $dbh = overview_db_open($database, $table);
    $stmt = $dbh->prepare("SELECT * FROM $table WHERE msgid like :terms");
    $stmt->bindParam(':terms', $msgid);
    $stmt->execute();
    while ($found = $stmt->fetch()) {
        $return['newsgroup'] = $found['newsgroup'];
        $return['number'] = $found['number'];
        $return['msgid'] = $found['msgid'];
        break;
    }
    $dbh = null;
    return $return;
}

function get_article_list($thisgroup)
{
    global $spooldir;
    $database = $spooldir . "/articles-overview.db3";
    $table = 'overview';
    $dbh = overview_db_open($database, $table);
    $stmt = $dbh->prepare("SELECT * FROM $table WHERE newsgroup=:thisgroup ORDER BY number");
    $stmt->execute([
        'thisgroup' => $thisgroup
    ]);
    $ok_article = array();
    while ($found = $stmt->fetch()) {
        $ok_article[] = $found['number'];
    }
    $dbh = null;
    return (array_unique($ok_article));
}

function create_node_ssl_cert($pemfile)
{
    global $CONFIG, $ssldir, $webtmp, $logdir, $config_dir;
    include $config_dir . '/letsencrypt.inc.php';
    $logfile = $logdir . '/nntp.log';
    $uinfo = posix_getpwnam($CONFIG['webserver_user']);
    $pubkeyfile = $ssldir . '/pubkey.pem';
    $pubkeytxtfile = $webtmp . '/pubkey.txt';
    $ssltime = filectime($letsencrypt['path'] . 'fullchain.pem');
    if (isset($letsencrypt['path'])) {
        file_put_contents($logfile, "\n" . format_log_date() . " Checking " . $letsencrypt['path'] . "fullchain.pem time", FILE_APPEND);
        if ($ssltime > filectime($pemfile)) {
            file_put_contents($logfile, "\n" . format_log_date() . " " . $letsencrypt['path'] . "fullchain.pem newer. Reloading cert.", FILE_APPEND);
            touch($config_dir . '/ssl.reload');
        }
    }
    if (! file_exists($config_dir . '/ssl.reload')) {
        if ((is_file($pemfile)) && (is_file($pubkeyfile)) && (is_file($pubkeytxtfile))) {
            if (md5_file($pubkeyfile) == md5_file($pubkeytxtfile)) {
                return;
            }
        }
    }
    @unlink($config_dir . '/ssl.reload');
    unlink($pemfile);
    unlink($pubkeyfile);
    unlink($pubkeytxtfile);
    /* Use letsencrypt */
    if ((isset($letsencrypt['server.pem'])) && (isset($letsencrypt['pubkey.pem']))) {
        echo "Using existing LetsEncrypt certificate.\n";
        file_put_contents($logfile, "\n" . format_log_date() . " Using existing LetsEncrypt certificate.", FILE_APPEND);
        file_put_contents($pemfile, $letsencrypt['server.pem'] . $letsencrypt['privkey']);
        file_put_contents($pubkeyfile, $letsencrypt['pubkey.pem']);
        file_put_contents($pubkeytxtfile, $letsencrypt['pubkey.pem']);
        touch($pemfile, $ssltime);
        touch($pubkeyfile, $ssltime);
        touch($pubkeytxtfile, $ssltime);
    } else {
        /* Create self signed cert */
        file_put_contents($logfile, "\n" . format_log_date() . " Creating self-signed certificate.", FILE_APPEND);
        $certificateData = array(
            "countryName" => "US",
            "stateOrProvinceName" => "New York",
            "localityName" => "New York City",
            "organizationName" => "Rocksolid",
            "organizationalUnitName" => "Rocksolid Light",
            "commonName" => $CONFIG['organization'],
            "emailAddress" => "rocksolid@example.com"
        );

        // Generate certificate
        $privateKey = openssl_pkey_new();
        $certificate = openssl_csr_new($certificateData, $privateKey);
        $certificate = openssl_csr_sign($certificate, null, $privateKey, 365);

        // Generate PEM file
        $pem_passphrase = null; // empty for no passphrase
        $pem = array();
        openssl_x509_export($certificate, $pem[0]);
        openssl_pkey_export($privateKey, $pem[1], $pem_passphrase);
        $pem = implode($pem);
        $pubkey = openssl_pkey_get_details($privateKey);

        // Save PEM file
        file_put_contents($pemfile, $pem);
        file_put_contents($pubkeyfile, $pubkey['key']);
        file_put_contents($pubkeytxtfile, $pubkey['key']);
    }
    chown($pemfile, $uinfo["uid"]);
    chown($pubkeyfile, $uinfo["uid"]);
    chown($pubkeytxtfile, $uinfo["uid"]);
    chmod($pemfile, 0660);
    chmod($pubkeyfile, 0660);
    chmod($pubkeytxtfile, 0660);
}
?>
