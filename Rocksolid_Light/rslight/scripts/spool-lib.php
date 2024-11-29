<?php
function get_articles($ns, $group, $refill_start = false)
{
    global $enable_rslight, $rslight_gpg, $config_name, $spooldir, $nocem_dir, $save_nocem_messages, $CONFIG;
    global $remote_groups_array_file, $OVERRIDES, $user_ban_file, $maxarticles_per_run, $maxfirstrequest, $workpath, $path;
    global $file_groups, $logdir, $config_name, $spamlog, $logfile, $debug_log;
    global $OVERRIDES;

    if ($ns == false) {
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Lost connection to " . $CONFIG['remote_server'] . ":" . $CONFIG['remote_port'], FILE_APPEND);
        exit();
    }

    if ($refill_start != false) {
        $maxfirstrequest = $refill_start;
        $maxarticles_per_run = $refill_start;
    }

    $grouppath = $path . preg_replace('/\./', '/', $group);
    // $banned_names = file($user_ban_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $msgid_filter = get_config_value('header_filters.conf', 'Message-ID');
    $subject_filter = get_config_value('header_filters.conf', 'Subject');
    $from_filter = get_config_value('header_filters.conf', 'From');
    $path_filter = get_config_value('header_filters.conf', 'Path');

    $nocem_check = "@@NCM";
    $bbsmail_check = "@@RSL";

    # Check if group exists. Open it if it does
    fputs($ns, "group " . $group . "\r\n");
    $response = line_read($ns);
    $remote_disp = $CONFIG['remote_server'] . ":" . $CONFIG['remote_port'];
    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " " . $remote_disp . " " . $group . ": " . $response, FILE_APPEND);

    if (strcmp(substr($response, 0, 3), "211") != 0) {
        echo "\n" . $response;
        return false;
    }
    # Get config
    if (file_exists($remote_groups_array_file)) {
        $remote_groups_array = unserialize(file_get_contents($remote_groups_array_file));
    } else {
        $remote_groups_array = array();
    }
    if (isset($remote_groups_array[$group])) {
        $article = $remote_groups_array[$group];
    } else {
        $article = false;
    }

    if (isset($CONFIG['enable_nntp']) && $CONFIG['enable_nntp'] == true) {
        // Get next available article number for group
        $local = get_next_article_number($group);
    }
    # Split group response line to get last article number
    # $article is the next number we want, not the last we retrieved
    $detail = explode(" ", $response);
    if ($detail[1] < 1) { // Remote server contains no articles for this group
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " " . $remote_disp . " contains no articles for " . $group . " Skipping", FILE_APPEND);
        return false;
    }

    if (! isset($article) || $article == false || $article < 2) {
        $article = $detail[3] - $maxfirstrequest;
        if ($article < $detail[2]) {
            $article = $detail[2];
        }
        $refill_start = true;
    }

    // Get only articles that exist on server
    if ($refill_start != false) {
        $article = get_first_article_number_from_remote($ns, $group, $maxfirstrequest);
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Starting " . $group . " at article number " . $article, FILE_APPEND);
    }

    if ($article > $detail[3]) {
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " " . $remote_disp . " for " . $group . " We are up to date", FILE_APPEND);
        // Just in case we have an error and $article is too large:
        $article = $detail[3] + 1;
    } else {
        // Get overview from server
        if (($detail[3] - $article) > $maxarticles_per_run) {
            $getlast = $article + $maxarticles_per_run;
        } else {
            $getlast = $detail[3];
        }
        if ($article > $getlast || $article == $getlast) {
            // This is probably not necessary
            fputs($ns, "xover " . $getlast . "\r\n");
        } else {
            fputs($ns, "xover " . $article . "-" . $getlast . "\r\n");
        }
        $response = line_read($ns); // and once more
        if ((substr($response, 0, 3) != "224")) {
            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Cannot get overview from " . $CONFIG['remote_server'] . " for " . $group . " (requested: xover " . $article . "-" . $getlast . " received " . $response . ")", FILE_APPEND);
            return false;
        } else {
            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " " . $CONFIG['remote_server'] . " " . $group . " (requested: overview " . $article . "-" . $getlast . " received " . $response . ")", FILE_APPEND);
        }
        while (rtrim($response = line_read($ns)) !== '.') {
            $ov = preg_split("/\t/", $response);
            $overview_msgid[$ov[0]] = $ov[4];
        }

        # Pull articles and save them in our spool
        if (! is_dir($grouppath)) {
            mkdir($grouppath, 0755, 'recursive');
        }
        $i = 0;
        $dates_used = array();
        // GET INDIVIDUAL ARTICLE
        while ($article <= $detail[3]) {
            if (! is_numeric($article)) {
                file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " DEBUG This should show server group:article number: " . $CONFIG['remote_server'] . " " . $group . ":" . $article, FILE_APPEND);
                break;
            }
            fputs($ns, "stat " . $article . "\r\n");
            $response = line_read($ns);
            if (strcmp(substr($response, 0, 3), "223") != 0) {
                file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " No such article: " . $group . ":" . $article . " " . $overview_msgid[$article], FILE_APPEND);
                $article++;
                continue;
            }
            // Create array for article, then send to insert_article_from_array()
            if (isset($current_article)) {
                unset($current_article);
                $current_article = array();
            }
            if ($CONFIG['enable_nntp'] != true) {
                $local = $article;
            }
            if (check_duplicate_msgid($overview_msgid[$article], $group)) {
                echo "\n(spoolnews)Duplicate Message-ID for: " . $group . ":" . $article . " " . $overview_msgid[$article];
                file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Duplicate Message-ID for: " . $group . ":" . $article . " " . $overview_msgid[$article], FILE_APPEND);
                $article++;
                continue;
            }
            fputs($ns, "article " . $article . "\r\n");
            $response = line_read($ns);
            if (strcmp(substr($response, 0, 3), "220") != 0) {
                echo "\n" . $response;
                file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " " . $group . " " . $response, FILE_APPEND);
                $article++;
                continue;
            }
            $articleHandle = $grouppath . "/" . $local;
            unlink($articleHandle);
            unset($references);
            $response = line_read($ns);
            $lines = 0;
            $bytes = 0;
            $ref = 0;
            $sub = 0;
            $ng = 0;
            $supersedes = false;
            $boundary = false;
            $banned = false;
            $integrity = false;
            $is_header = 1;
            $body = "";
            $content_transfer_encoding = null;
            $response = str_replace("\n", "", str_replace("\r", "", $response));
            while (strcmp($response, ".") != 0) {
                $is_xref = false;
                $bytes = $bytes + mb_strlen($response, '8bit');
                if (trim($response) == "" && $lines > 0) {
                    if ($is_header == 1) {
                        file_put_contents($articleHandle, $current_article['xref'] . "\n", FILE_APPEND);
                    }
                    $is_header = 0;
                }
                if ($is_header == 1) {
                    $response = str_replace("\t", " ", $response);
                    if (strpos($response, ': ') !== false) {
                        $ref = 0;
                        $sub = 0;
                        $ng = 0;
                    }
                    // Find article date
                    if (stripos($response, "Date: ") === 0) {
                        $finddate = explode(': ', $response, 2);
                        $artdate = strtotime($finddate[1]);
                    }
                    if (stripos($response, "Injection-Date: ") === 0) {
                        $finddate = explode(': ', $response, 2);
                        $injectiondate = strtotime($finddate[1]);
                    }
                    if (stripos($response, "Supersedes: ") === 0) {
                        $supersedes = explode(': ', $response, 2);
                        $supersedes = $supersedes[1];
                    }
                    // Get overview data
                    if (stripos($response, "Message-ID: ") === 0) {
                        $mid = explode(': ', $response, 2);
                        if (preg_match($msgid_filter, $mid[1])) {
                            $banned = "msgid_filter";
                        }
                    }
                    if (stripos($response, "From: ") === 0) {
                        $from = explode(': ', $response, 2);
                        if (preg_match($from_filter, $from[1])) {
                            $banned = "from_filter";
                        }
                    }
                    if (stripos($response, "Path: ") === 0) {
                        $msgpath = explode(': ', $response, 2);
                        if (preg_match($path_filter, $msgpath[1])) {
                            $banned = "path_filter";
                        }
                    }
                    if (stripos($response, "Subject: ") === 0) {
                        $this_subject = explode('Subject: ', $response, 2);
                        $subject = $this_subject[1];
                        $sub = 1;
                        if (preg_match($subject_filter, $subject)) {
                            $banned = "subject_filter";
                        }
                    }
                    // Transfer encoding
                    if (stripos($response, "Content-Transfer-Encoding: ") === 0) {
                        $enco = explode(': ', $response, 2);
                        $content_transfer_encoding = $enco[1];
                    }

                    if (stripos($response, "Newsgroups: ") === 0) {
                        $response = str_ireplace($group, $group, $response);
                        // Identify each group name for xref
                        $groupnames = explode("Newsgroups: ", $response);
                        $allgroups = preg_split("/\ |\,/", $groupnames[1]);
                        // Create Xref: header
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
                        $ng = 1;
                    }
                    if (stripos($response, "Xref: ") === 0) {
                        if (isset($CONFIG['enable_nntp']) && $CONFIG['enable_nntp'] == true) {
                            $is_xref = true;
                        }
                        $xref = $response;
                    }
                    if (stripos($response, "Content-Type: ") === 0) {
                        preg_match('/.*charset=.*/', $response, $te);
                        $content_type = explode("Content-Type: text/plain; charset=", $te[0]);
                        if (preg_match('/.*boundary=.*/', $response, $be)) {
                            $boundary = explode("boundary=", $response, 2);
                            $boundary = trim($boundary[1], '\";');
                        }
                    }
                    if (stripos($response, "References: ") === 0) {
                        $this_references = explode('References: ', $response);
                        $references = $this_references[1];
                        $ref = 1;
                    }
                    if (preg_match('/^\s/', $response) && $ng == 1) {
                        $addgroups = preg_split("/\ |\,/", trim($response));
                        $allgroups = array_merge($allgroups, $addgroups);
                    }

                    if (preg_match('/^\s/', $response) && $ref == 1) {
                        $references = $references . $response;
                    }
                    if (preg_match('/^\s/', $response) && $sub == 1) {
                        $subject = $subject . $response;
                    }
                } else {
                    $body .= $response . "\n";
                }
                if (! $is_xref) {
                    file_put_contents($articleHandle, $response . "\n", FILE_APPEND);
                }
                // Check here for broken $ns connection before continuing
                $response = fgets($ns, 1200);
                if ($response == false) {
                    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Lost connection to " . $CONFIG['remote_server'] . ":" . $CONFIG['remote_port'] . " retrieving article " . $article, FILE_APPEND);
                    unlink($articleHandle);
                    break;
                    // continue;
                }
                $response = str_replace("\n", "", str_replace("\r", "", $response));
                $lines++;
            }
            file_put_contents($articleHandle, $response . "\n", FILE_APPEND);
            $lines = $lines - 1;
            $bytes = $bytes + ($lines * 2);

            // Prefer Injection-Date to Date header
            // Some newsreaders (PiaoHong) produce a Date header that php does not like
            if (isset($injectiondate)) {
                $artdate = $injectiondate;
                file_put_contents($debug_log, "\n" . format_log_date() . " " . $config_name . " Used Injection-Date " . $artdate . " for: " . $mid[1], FILE_APPEND);
            } else {
                file_put_contents($debug_log, "\n" . format_log_date() . " " . $config_name . " Used Date " . $artdate . " for: " . $mid[1], FILE_APPEND);
            }

            // Check if date matches exactly another article and handle else sorting doesn't like it
            while (isset($dates_used[$artdate])) {
                $artdate = $artdate + 1;
                $finddate[1] = date("D, j M Y G:i:s (T)", $artdate);
                file_put_contents($debug_log, "\n" . format_log_date() . " " . $config_name . " Rewrote date to: " . $artdate . " " . $finddate[1] . " for " . $group . ":" . $local, FILE_APPEND);
            }
            $article_date = $artdate;
            $dates_used[$article_date] = true;

            // Don't spool article if $banned or fails integrity test
            $integrity = check_article_integrity(file($articleHandle), $artdate);
            if (($banned !== false) || ($integrity !== false)) {
                unlink($articleHandle);
                if ($integrity) {
                    file_put_contents($logfile, "\n" . format_log_date() . " " . $integrity, FILE_APPEND);
                } elseif ($banned) {
                    file_put_contents($spamlog, "\n" . format_log_date() . " " . $banned . " :\tSPAM\t" . $mid[1] . "\t" . $groupnames[1] . "\t" . $from[1], FILE_APPEND);
                }
                $article++;
            } else {
                if ((strpos($CONFIG['nocem_groups'], $group) !== false) && ($CONFIG['enable_nocem'] == true)) {
                    if (strpos($subject, $nocem_check) !== false) {
                        $is_from = address_decode($from[1], 'nowhere');
                        $nocem_file = tempnam($spooldir . "/nocem", $is_from[0]['mailbox'] . "@" . $is_from[0]['host'] . "[" . date("Y.m.d.H.i.s") . "]");
                        copy($articleHandle, $nocem_file);
                        chmod($nocem_file, 0644);
                        if ($save_nocem_messages == true) {
                            $saved_nocem_file = tempnam($nocem_dir, $is_from[0]['mailbox'] . "@" . $is_from[0]['host'] . "[" . date("Y.m.d.H.i.s") . "]-");
                            copy($articleHandle, $saved_nocem_file);
                        }
                    }
                }
                if ((strpos($rslight_gpg['nntp_group'], $group) !== false) && ($rslight_gpg['enable'] == '1')) {
                    if (strpos($subject, $bbsmail_check) !== false) {
                        $bbsmail_file = preg_replace('/@@RSL /', '', $subject);
                        $bbsmail_filename = $spooldir . "/bbsmail/in/bbsmail-" . $bbsmail_file;
                        copy($articleHandle, $bbsmail_filename);
                    }
                }
                $this_article = file_get_contents($articleHandle);
                if ($CONFIG['article_database'] == '1') {
                    unlink($articleHandle);
                    // CREATE SEARCH SNIPPET
                    if ($boundary !== false) {
                        $body_array = explode("\n", $body);
                        $found = false;
                        $start = false;
                        foreach ($body_array as $line) {
                            if ($found === false) {
                                if (strpos($line, $boundary) !== false) {
                                    $found = true;
                                    continue;
                                } else {
                                    continue;
                                }
                            }
                            if (trim($line != '') && $start === false) {
                                continue;
                            } else {
                                if ($start === false) {
                                    $start = true;
                                    continue;
                                }
                            }
                            $newbody .= $line . "\n";
                        }
                        file_put_contents($debug_log, "\n" . format_log_date() . " Created snippet from multipart article: " . $mid[1], FILE_APPEND);
                    } else {
                        $newbody = $body;
                    }

                    $this_snippet = get_search_snippet($newbody, $content_type[1], $content_transfer_encoding);
                    unset($newbody);
                } else {
                    touch($articleHandle, $article_date);
                }
                $current_article['mid'] = $mid[1];
                $current_article['epochdate'] = $article_date;
                $current_article['stringdate'] = $finddate[1];
                $current_article['from'] = $from[1];
                $current_article['subject'] = $subject;
                $current_article['references'] = $references;
                $current_article['bytes'] = $bytes;
                $current_article['lines'] = $lines;
                $current_article['article'] = $this_article;
                $current_article['snippet'] = $this_snippet;

                // Check Spam
                $res = 0;
                if (isset($CONFIG['spamassassin']) && ($CONFIG['spamassassin'] == true) && ($OVERRIDES['disable_spamassassin_spooling'] !== true)) {
                    $spam_result_array = check_spam($subject, $from[1], $groupnames[1], $references, $this_article, $mid[1]);
                    $res = $spam_result_array['res'];
                    $spamresult = $spam_result_array['spamresult'];
                    $spamcheckerversion = $spam_result_array['spamcheckerversion'];
                    $spamlevel = $spam_result_array['spamlevel'];
                }
                if ($res === 1) {
                    unlink($articleHandle);
                    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Skipping: " . $CONFIG['remote_server'] . " " . $group . ":" . $article . " Exceeds Spam Score", FILE_APPEND);
                    // $orig_newsgroups = $newsgroups;
                    // $newsgroups = $CONFIG['spamgroup'];
                    // $group = $newsgroups;
                    $i--;
                    $local--;
                } else {
                    $pass = false;
                    foreach ($allgroups as $agroup) {
                        $agroup = trim($agroup);
                        if ((! testGroup($agroup)) || $agroup == '') {
                            continue;
                        }
                        $current_article['group'] = $agroup;
                        if ($group == $agroup) {
                            $current_article['local'] = $local;
                        } else {
                            $current_article['local'] = get_next_article_number($agroup);
                        }
                        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Preparing to spool " . $group . ":" . $article, FILE_APPEND);
                        $tmp = insert_article_from_array($current_article, false);
                        if ($tmp[0] != "4") {
                            $pass = true;
                        } else {
                            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " " . $tmp, FILE_APPEND);
                        }
                    }
                    if (! $pass) {
                        $i--;
                    }
                }

                $i++;
                $article++;
                $local++;
                if ($i > $maxarticles_per_run) {
                    break;
                }
            }
            if ($supersedes !== false) {
                if (isset($OVERRIDES['enable_supersedes_support']) && $OVERRIDES['enable_supersedes_support'] == true) {
                    file_put_contents($debug_log, "\n" . format_log_date() . " Found Supersedes: " . $mid[1] . " for: " . $supersedes, FILE_APPEND);
                    if (!check_remote_for_msgid($supersedes)) {
                        file_put_contents($debug_log, "\n" . format_log_date() . " Will delete: " . $supersedes, FILE_APPEND);
                        delete_message($supersedes);
                    }
                }
            }
        }
    }
    // END GET INDIVIDUAL ARTICLE

    // Update group title
    if (! is_file($workpath . $group . "-title")) {
        fputs($ns, "XGTITLE " . $group . "\r\n");
        $response = line_read($ns);
        if (strcmp(substr($response, 0, 3), "282") == 0) {
            $titlefile = $workpath . $group . "-title";
            $response = line_read($ns);
            while (strcmp($response, ".") != 0) {
                file_put_contents($titlefile, $response);
                $response = line_read($ns);
            }
        }
    }
    # Save config
    if (file_exists($remote_groups_array_file)) {
        $remote_groups_array = unserialize(file_get_contents($remote_groups_array_file));
    } else {
        $remote_groups_array = array();
    }
    $remote_groups_array[$group] = $article;
    file_put_contents($remote_groups_array_file, serialize($remote_groups_array));
    return true;
}

function get_first_article_number_from_remote($ns, $group, $maxfirstrequest)
{
    global $logfile, $config_name;
    fputs($ns, "group " . $group . "\r\n");
    $response = line_read($ns);
    if (substr($response, 0, 3) != "211") {
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Cannot enter " . $group . " on " . $CONFIG['remote_server'] . ":" . $CONFIG['remote_port'], FILE_APPEND);
        return false;
    }
    fputs($ns, "listgroup\r\n");
    $response = line_read($ns);
    if (substr($response, 0, 3) != "211") {
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Cannot listgroup " . $group . " on " . $CONFIG['remote_server'] . ":" . $CONFIG['remote_port'], FILE_APPEND);
        return false;
    }
    $exists_array = array();
    while ($line = line_read($ns)) {
        if (trim($line) == '.') {
            break;
        }
        $exists_array[] = trim($line);
    }
    $exists_array = array_reverse($exists_array);
    if ($maxfirstrequest > count($exists_array)) {
        return $exists_array[1];
    } else {
        return $exists_array[$maxfirstrequest];
    }
}
