<?php
/*
 * spoolnews NNTP news spool creator
 * Download: https://news.novabbs.com/getrslight
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
include ("$file_newsportal");
include $config_dir . '/gpg.conf';

if ($CONFIG['remote_server'] != '') {
    $remote_groupfile = $spooldir . "/" . $config_name . "/" . $CONFIG['remote_server'] . ":" . $CONFIG['remote_port'] . ".txt";
}
$file_groups = $config_path . "groups.txt";
$local_groupfile = $spooldir . "/" . $config_name . "/local_groups.txt";
$logfile = $logdir . '/spoolnews.log';

# END MAIN CONFIGURATION
@mkdir($spooldir . "/" . $config_name, 0755, 'recursive');

// Defaults
$maxarticles_per_run = 100;
$maxfirstrequest = 100;

// Overrides
if ($OVERRIDES['maxarticles_per_run'] > 0) {
    $maxarticles_per_run = $OVERRIDES['maxarticles_per_run'];
}
if ($OVERRIDES['maxfirstrequest'] > 0) {
    $maxfirstrequest = $OVERRIDES['maxfirstrequest'];
}

if (! isset($CONFIG['enable_nntp']) || $CONFIG['enable_nntp'] != true) {
    $maxfirstrequest = $maxarticles;
    $maxarticles_per_run = $maxfetch;
}

$workpath = $spooldir . "/";
$path = $workpath . "articles/";

$lockfile = $lockdir . '/' . $config_name . '-spoolnews.lock';
$pid = file_get_contents($lockfile);
if (posix_getsid($pid) === false || ! is_file($lockfile)) {
    print "Starting Spoolnews...\n";
    file_put_contents($lockfile, getmypid()); // create lockfile
} else {
    print "Spoolnews currently running\n";
    exit();
}

$sem = $spooldir . "/" . $config_name . ".reload";
if (is_file($sem)) {
    unlink($remote_groupfile);
    unlink($sem);
    $maxfirstrequest = 500;
}
if (filemtime($spooldir . '/' . $config_name . '-thread-timer') + 600 < time()) {
    $timer = true;
    touch($spooldir . '/' . $config_name . '-thread-timer');
} else {
    $timer = false;
}
# Check for groups file, create if necessary
// only do remote server groups if necessary
if ($CONFIG['remote_server'] != '') {
    create_spool_groups($file_groups, $remote_groupfile);
}
create_spool_groups($file_groups, $local_groupfile);

# Iterate through groups
$enable_rslight = 0;
# Refresh group list
$menulist = file($config_dir . "menu.conf", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
foreach ($menulist as $menu) {
    if (($menu[0] == '#') || (trim($menu) == "")) {
        continue;
    }
    $menuitem = explode(':', $menu);
    if (($menuitem[0] == $config_name) && ($menuitem[1] == '1')) {
        groups_read($server, $port, 1, true); // 'true' forces a refresh of the group list
        $enable_rslight = 1;
        echo "\nLoaded groups";
    }
}
# Clean outgoing directory for LOCAL sections
if ($CONFIG['remote_server'] == '') {
    $outgoing_dir = $spooldir . "/" . $config_name . "/outgoing/";
    $files = scandir($outgoing_dir);
    foreach ($files as $file) {
        $file_name = $outgoing_dir . $file;
        if (is_file($file_name) && (filemtime($file_name) < (time() - 3600))) {
            unlink($file_name);
        }
    }
}
if ($CONFIG['remote_server'] != '') {
    file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " remote_server: " . $CONFIG['remote_server'], FILE_APPEND);
    $ns = nntp2_open($CONFIG['remote_server'], $CONFIG['remote_port']);
    $ns2 = nntp_open();
    echo 'Open ns2: ' . $ns2 . "\n";
    if (! $ns) {
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Failed to connect to " . $CONFIG['remote_server'] . ":" . $CONFIG['remote_port'], FILE_APPEND);
        exit();
    }
    $grouplist = file($config_dir . '/' . $config_name . '/groups.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($grouplist as $findgroup) {
        if ($findgroup[0] == ":") {
            continue;
        }
        $name = preg_split("/( |\t)/", $findgroup, 2);
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Retrieving articles for: " . $name[0] . "...", FILE_APPEND);
        echo "\nRetrieving articles for: " . $name[0] . "...";
        get_articles($ns, $name[0]);

        if ($enable_rslight == 1) {
            if ($timer) {
                file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Updating threads for: " . $name[0] . "...", FILE_APPEND);
                echo 'Use ns2: ' . $ns2 . "\n";
                thread_load_newsserver($ns2, $name[0], 0);
            }
        }
    }
    nntp_close($ns2);
    nntp_close($ns);
}
# expire_overview();
unlink($lockfile);
echo "\nSpoolnews Done\n";

function get_articles($ns, $group)
{
    global $enable_rslight, $rslight_gpg, $spooldir, $CONFIG, $user_ban_file, $maxarticles_per_run, $maxfirstrequest, $workpath, $path, $remote_groupfile, $local_groupfile, $local, $logdir, $config_name, $logfile;

    if ($ns == false) {
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Lost connection to " . $CONFIG['remote_server'] . ":" . $CONFIG['remote_port'], FILE_APPEND);
        exit();
    }

    $grouppath = $path . preg_replace('/\./', '/', $group);
    $banned_names = file($user_ban_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $msgid_filter = get_config_value('header_filters.conf', 'Message-ID');
    $subject_filter = get_config_value('header_filters.conf', 'Subject');
    $from_filter = get_config_value('header_filters.conf', 'From');
    $path_filter = get_config_value('header_filters.conf', 'Path');

    $nocem_check = "@@NCM";
    $bbsmail_check = "@@RSL";

    # Check if group exists. Open it if it does
    fputs($ns, "group " . $group . "\r\n");
    $response = line_read($ns);
    if (strcmp(substr($response, 0, 3), "211") != 0) {
        echo "\n" . $response;
        return (1);
    }

    # Get config
    $grouplist = file($remote_groupfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($grouplist as $findgroup) {
        $name = explode(':', $findgroup);
        if (strcmp($name[0], $group) == 0) {
            if (is_numeric(trim($name[1]))) {
                $article = $name[1] + 1;
            } else {
                $article = 1;
            }
            break;
        }
    }
    if (isset($CONFIG['enable_nntp']) && $CONFIG['enable_nntp'] == true) {
        // Get next available article number for group
        $local = get_next_article_number($group);
    }
    # Split group response line to get last article number
    $detail = explode(" ", $response);
    if (! isset($article)) {
        $article = $detail[2];
    }
    if ($article < $detail[3] - $maxfirstrequest) {
        $article = $detail[3] - $maxfirstrequest;
    }
    if ($article < $detail[2]) {
        $article = $detail[2];
    }

    // Create list of message-ids
    $database = $spooldir . '/articles-overview.db3';
    $table = 'overview';
    $dbh = overview_db_open($database, $table);
    $stmt = $dbh->prepare("SELECT msgid FROM $table WHERE newsgroup=:newsgroup");
    $stmt->bindParam(':newsgroup', $group);
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        $msgids[$row['msgid']] = true;
    }
    $dbh = null;

    // Check history database for deleted message-ids
    $database = $spooldir . '/history.db3';
    $table = 'history';
    $dbh = history_db_open($database, $table);
    $stmt = $dbh->prepare("SELECT msgid FROM $table WHERE newsgroup=:newsgroup");
    $stmt->bindParam(':newsgroup', $group);
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        $msgids[$row['msgid']] = true;
    }
    $dbh = null;

    // Get overview from server
    $server_overview = array();
    $re = false;
    if (($detail[3] - $article) > $maxarticles_per_run) {
        $getlast = $article + $maxarticles_per_run;
    } else {
        $getlast = $detail[3];
    }
    fputs($ns, "xover " . $article . "-" . $getlast . "\r\n");
    $response = line_read($ns); // and once more
    if ((substr($response, 0, 3) != "224")) {
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Cannot get overview from " . $CONFIG['remote_server'] . " for " . $group, FILE_APPEND);
        return false;
    }
    while (trim($response = line_read($ns)) !== '.') {
        $ov = preg_split("/\t/", $response);
        $overview_msgid[$ov[0]] = $ov[4];
    }

    # Pull articles and save them in our spool
    @mkdir($grouppath, 0755, 'recursive');
    $i = 0;
    // GET INDIVIDUAL ARTICLE
    while ($article <= $detail[3]) {
        if (! is_numeric($article)) {
            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " DEBUG This should show server group:article number: " . $CONFIG['remote_server'] . " " . $group . ":" . $article, FILE_APPEND);
            break;
        }
        // Create array for article, then send to insert_article_from_array()
        if (isset($current_article)) {
            unset($current_article);
            $current_article = array();
        }
        if ($CONFIG['enable_nntp'] != true) {
            $local = $article;
        }
        if ($msgids[$overview_msgid[$article]] == true) {
            echo "\nDuplicate Message-ID for: " . $group . ":" . $local;
            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Duplicate Message-ID for: " . $group . ":" . $article, FILE_APPEND);
            $article ++;
            continue;
        }
        fputs($ns, "article " . $article . "\r\n");
        $response = line_read($ns);
        if (strcmp(substr($response, 0, 3), "220") != 0) {
            echo "\n" . $response;
            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " " . $group . " " . $response, FILE_APPEND);
            $article ++;
            continue;
        }
        $articleHandle = $grouppath . "/" . $local;
        $response = line_read($ns);
        $lines = 0;
        $bytes = 0;
        $ref = 0;
        $banned = false;
        $is_header = 1;
        $body = "";
        while (strcmp($response, ".") != 0) {
            $is_xref = false;
            $bytes = $bytes + mb_strlen($response, '8bit');
            if (trim($response) == "" || $lines > 0) {
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
                    if($artnum > 0) {
                        $current_article['xref'] .= ' ' . $agroup . ':' . $artnum;
                    }
                }
                if ($is_header != 0) {
                    file_put_contents($articleHandle, $current_article['xref'] . "\n", FILE_APPEND);
                }
                $is_header = 0;
                $lines ++;
            }
            if ($is_header == 1) {
                $response = str_replace("\t", " ", $response);
                // Find article date
                if (stripos($response, "Date: ") === 0) {
                    $finddate = explode(': ', $response, 2);
                    $article_date = strtotime($finddate[1]);
                }
                // Get overview data
                if (stripos($response, "Message-ID: ") === 0) {
                    $mid = explode(': ', $response, 2);
                    if (preg_match($msgid_filter, $mid[1])) {
                        $banned = "msgid_filter";
                    }
                    $ref = 0;
                }
                if (stripos($response, "From: ") === 0) {
                    $from = explode(': ', $response, 2);
                    if (preg_match($from_filter, $from[1])) {
                        $banned = "from_filter";
                    }
                    $ref = 0;
                }
                if (stripos($response, "Path: ") === 0) {
                    $msgpath = explode(': ', $response, 2);
                    if (preg_match($path_filter, $msgpath[1])) {
                        $banned = "path_filter";
                    }
                    $ref = 0;
                }
                if (stripos($response, "Subject: ") === 0) {
                    $subject = explode('Subject: ', $response, 2);
                    if (preg_match($subject_filter, $subject[1])) {
                        $banned = "subject_filter";
                    }
                    $ref = 0;
                }
                if (stripos($response, "Newsgroups: ") === 0) {
                    $response = str_ireplace($group, $group, $response);
                    // Identify each group name for xref
                    $groupnames = explode("Newsgroups: ", $response);
                    $allgroups = preg_split("/\ |\,/", $groupnames[1]);
                    $ref = 0;
                }
                if (stripos($response, "Xref: ") === 0) {
                    if (isset($CONFIG['enable_nntp']) && $CONFIG['enable_nntp'] == true) {
                        $is_xref = true;
                    }
                    $xref = $response;
                    $ref = 0;
                }
                if (stripos($response, "Content-Type: ") === 0) {
                    preg_match('/.*charset=.*/', $response, $te);
                    $content_type = explode("Content-Type: text/plain; charset=", $te[0]);
                }
                if (stripos($response, "References: ") === 0) {
                    $this_references = explode('References: ', $response);
                    $references = $this_references[1];
                    $ref = 1;
                }
                if ((stripos($response, ':') === false) && (strpos($response, '>'))) {
                    if ($ref == 1) {
                        $references = $references . $response;
                    }
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
                unlink($grouppath . "/" . $local);
                break;
                // continue;
            }
            $response = str_replace("\n", "", str_replace("\r", "", $response));
        }
        file_put_contents($articleHandle, $response . "\n", FILE_APPEND);
        $lines = $lines - 1;
        $bytes = $bytes + ($lines * 2);
        // Don't spool article if $banned != 0
        if ($banned != false) {
            unlink($grouppath . "/" . $local);
            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Skipping: " . $CONFIG['remote_server'] . " " . $group . ":" . $article . " banned in " . $banned, FILE_APPEND);
            $article ++;
        } else {
            if ((strpos($CONFIG['nocem_groups'], $group) !== false) && ($CONFIG['enable_nocem'] == true)) {
                if (strpos($subject[1], $nocem_check) !== false) {
                    $nocem_file = tempnam($spooldir . "/nocem", "nocem-" . $group . "-");
                    copy($grouppath . "/" . $local, $nocem_file);
                }
            }
            if ((strpos($rslight_gpg['nntp_group'], $group) !== false) && ($rslight_gpg['enable'] == '1')) {
                if (strpos($subject[1], $bbsmail_check) !== false) {
                    $bbsmail_file = preg_replace('/@@RSL /', '', $subject[1]);
                    $bbsmail_filename = $spooldir . "/bbsmail/in/bbsmail-" . $bbsmail_file;
                    copy($grouppath . "/" . $local, $bbsmail_filename);
                }
            }
            if ($CONFIG['article_database'] == '1') {
                $this_article = file_get_contents($grouppath . "/" . $local);
                // CREATE SEARCH SNIPPET
                $this_snippet = get_search_snippet($body, $content_type[1]);
            } else {
                if ($article_date > time()) {
                    $article_date = time();
                }
                touch($grouppath . "/" . $local, $article_date);
            }

            $current_article['mid'] = $mid[1];
            $current_article['epochdate'] = $article_date;
            $current_article['stringdate'] = $finddate[1];
            $current_article['from'] = $from[1];
            $current_article['subject'] = $subject[1];
            $current_article['references'] = $references;
            $current_article['bytes'] = $bytes;
            $current_article['lines'] = $lines;
            $current_article['article'] = $this_article;
            $current_article['snippet'] = $this_snippet;

            foreach ($allgroups as $agroup) {
                $agroup = trim($agroup);
                if ((! testGroup($agroup)) || $agroup == '') {
                    continue;
                }
                $current_article['group'] = $agroup;
                if ($group == $agroup) {
                    $current_article['local'] = $local;
                    insert_article_from_array($current_article);
                } else {
                    $current_article['local'] = get_next_article_number($agroup);
                    insert_article_from_array($current_article);
                }
            }

            $i ++;
            $article ++;
            $local ++;
            if ($i > $maxarticles_per_run) {
                break;
            }
        }
    }
    // END GET INDIVIDUAL ARTICLE
    $article --;
    // $local--;
    // Update title
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
    $grouplist = file($remote_groupfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $saveconfig = fopen($remote_groupfile, 'w+');
    foreach ($grouplist as $savegroup) {
        $name = explode(':', $savegroup);
        if (strcmp($name[0], $group) == 0) {
            fputs($saveconfig, $group . ":" . $article . "\n");
        } else {
            fputs($saveconfig, $savegroup . "\n");
        }
    }
    fclose($saveconfig);
    $grouplist = file($local_groupfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $saveconfig = fopen($local_groupfile, 'w+');
    foreach ($grouplist as $savegroup) {
        $name = explode(':', $savegroup);
        if (strcmp($name[0], $group) == 0) {
            fputs($saveconfig, $group . ":" . $local . "\n");
        } else {
            fputs($saveconfig, $savegroup . "\n");
        }
    }
    fclose($saveconfig);
    if ($CONFIG['article_database'] == '1') {
        $article_dbh = null;
    }
    $dbh = null;
}

function create_spool_groups($in_groups, $out_groups)
{
    global $spooldir;
    $grouplist = file($in_groups, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $temp_file = tempnam($spooldir . "/tmp/", 'groupfile-');
    $groupout = fopen($out_groups, "a+");
    foreach ($grouplist as $group) {
        if ($group[0] == ":") {
            continue;
        }
        $thisgroup = preg_split("/( |\t)/", $group, 2);
        $found = 0;
        while (($buffer = fgets($groupout)) !== false) {
            $mod_buffer = explode(':', $buffer);
            if (strcmp($thisgroup[0], $mod_buffer[0]) == 0) {
                file_put_contents($temp_file, "$buffer", FILE_APPEND);
                $found = 1;
                break;
            }
        }
        if ($found == 0) {
            file_put_contents($temp_file, "$thisgroup[0]\n", FILE_APPEND);
            continue;
        }
    }
    fclose($groupout);
    rename($temp_file, $out_groups);
    return;
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

?>
