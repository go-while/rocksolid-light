<?php

// Include security functions with production-ready path resolution

/*
 * This script allows importing a group .db3 file from a backup
 * or another rslight site, and other features.
 *
 * Use -help to see other features.
 *
 * To import a group db3 file:
 * Place the article database file group.name-articles.db3 in
 * your spool directory, and change user/group to your web user.
 * Run this script as your web user:
 * php $config_dir/scripts/maintenance -import group.name
 *
 * This will create the overview files necessary to import the group
 * into your site.
 * Next: Add the group to the groups.txt file of the section you wish
 * it to appear:
 * $config_dir/<section>/groups.txt
 */
include("paths.inc.php");
chdir($spoolnews_path);
include "../lib/config.inc.php";
include("$file_newsportal");
include "spool-lib.php";

if (! isset($argv[1])) {
    $argv[1] = "-help";
}

if ($argv[1] != '-newsection') {
    $processUser = posix_getpwuid(posix_geteuid());
    echo "You are running as user: " . $processUser['name'] . "\n";

    // Change to webserver user if root
    $uinfo = posix_getpwnam($CONFIG['webserver_user']);
    /* Change to non root user */
    change_identity($uinfo["uid"], $uinfo["gid"]);
    $processUser = posix_getpwuid(posix_geteuid());

    if ($processUser['name'] != $CONFIG['webserver_user']) {
        echo "You are running as user: " . $processUser['name'] . "\n";
        echo 'Please run this script as: ' . $CONFIG['webserver_user'] . "\n";
        exit();
    }
    /* Everything below runs as $CONFIG['webserver_user'] */
    echo "You are running as user: " . $processUser['name'] . "\n";

    $processUser = posix_getpwuid(posix_geteuid());
    if ($processUser['name'] != $CONFIG['webserver_user']) {
        echo "You are running as user: " . $processUser['name'] . "\n";
        echo 'Please run this script as: ' . $CONFIG['webserver_user'] . "\n";
        exit();
    }

    if (isset($argv[2])) {
        $config_name = get_section_by_group($argv[2]);
    } else {
        $config_name = null;
    }

    $logfile = $logdir . '/spoolnews.log';
    $lockfile = $lockdir . '/' . $config_name . '-spoolnews.lock';

    $pid = file_get_contents($lockfile);
    if (posix_getsid($pid) === false || ! is_file($lockfile)) {
        print "Starting...\n";
        file_put_contents($lockfile, getmypid()); // create lockfile
    } else {
        print "Spoolnews currently running\n";
        exit();
    }
} else {
    $processUser = posix_getpwuid(posix_geteuid());
    echo "You are running as user: " . $processUser['name'] . "\n";
}

if ($argv[1][0] == '-') {
    switch ($argv[1]) {
        case "-version":
            echo 'Version ' . $rslight_version . "\n";
            break;
        case "-clear-diskcache":
            clear_disk_cache();
            break;
        case "-refill":
            if (!isset($argv[2]) || !isset($argv[3])) {
                echo "Please provide a group name followed by number of articles to poll\n";
                exit;
            }
            echo "Refilling: " . $argv[2] . " going back " . $argv[3] . " articles\n";
            refill_group($argv[2], $argv[3]);
            break;
        case "-remove":
            echo "Removing: " . $argv[2] . "\n";
            remove_articles($argv[2]);
            reset_group($argv[2], 1);
            break;
        case "-reset":
            echo "Reset: " . $argv[2] . "\n";
            remove_articles($argv[2]);
            reset_group($argv[2], 0);
            break;
        case "-reset-section":
            if (!isset($argv[2])) {
                echo "Please provide a section name\n";
                exit;
            }
            echo "Reset Section: " . $argv[2] . "\n";
            reset_section($argv[2]);
            break;
        case "-import":
            if (isset($argv[2])) {
                import($argv[2]);
            } else {
                import();
            }
            break;
        case "-newsection":
            if (!isset($argv[2])) {
                echo "Please provide a section name\n";
                exit;
            }
            echo "Creating section: " . $argv[2] . "\n";
            echo create_section($argv[2]);
            break;
        case "-import-mbox-to-articles":
            if (!isset($argv[2]) || !isset($argv[3])) {
                echo "Please provide a group name followed by full path to mbox file\n";
                exit;
            }
            if (!isset($config_name)) {
                echo "Please add " . $argv[2] . " to groups.txt for a section\n";
                exit;
            }
            mbox_import_articles($argv[2], $argv[3]);
            break;
        case "-clean":
            clean_spool();
            break;
        default:
            echo "-help: This help page\n";
            echo "-version: Display version\n";
            echo "******************* IMPORTANT **************************\n";
            echo "*** PLEASE DISABLE cron.php WHEN RUNNING THIS SCRIPT ***\n";
            echo "********************************************************\n";
            echo "-clean: Remove extraneous group db3 files\n";
            echo "-clear-diskcache: Remove all cache files if using Disk Caching\n";
            echo "-import: Import articles from a .db3 file (-import alt.test-articles)\n";
            echo "         You must first add group name to <config_dir>/<section>/groups.txt manually\n";
            echo "-import-mbox-to-articles: Import articles from a mbox file\n";
            echo "         -import-mbox-to-articles alt.test /path/to/file/alt.test.mbox\n";
            echo "         You must first add group name to <config_dir>/<section>/groups.txt manually\n";
            echo "-newsection: Create a new section for groups\n";
            echo "-refill: Go back x articles and retrieve missing from remote server\n";
            echo "         -refill alt.test 3000 will retrive missing articles for alt.test\n";
            echo "         starting 3000 articles earlier than latest remote article number\n";
            echo "-remove: Remove all data for a group (-remove alt.test)\n";
            echo "         You must also remove group name from <config_dir>/<section>/groups.txt manually\n";
            echo "-reset: Reset a group to restart from zero messages (-reset alt.test)\n";
            echo "-reset-section: Reset ALL GROUPS in a Section to restart from zero messages\n";
            echo "         (-reset-section rocksolid) THIS CAN TAKE A LOT OF TIME TO RUN\n";
            break;
    }
    exit();
} else {
    exit();
}

function clear_disk_cache()
{
    global $config_dir;
    if (file_exists($config_dir . '/cache.inc.php')) {
        include $config_dir . '/cache.inc.php';
    } else {
        echo "Disk Cache not configured in " . $config_dir . '/cache.inc.php' . "\n";
        exit;
    }
    if ($enable_cache != 'diskcache' || !isset($cache_dir)) {
        echo "Disk Cache not configured in " . $config_dir . '/cache.inc.php' . "\n";
        exit;
    }
    echo "Clearing Disk Cache in " . $cache_dir . "\n";
    foreach (glob($cache_dir . "/*") as $filename) {
        if (is_file($filename)) {
            echo "Deleting " . $filename . "\n";
            unlink($filename);
        } else {
            echo "NOT Deleting: " . $filename . "\n";
        }
    }
}

function create_section($section = false)
{
    global $spooldir, $config_dir, $spoolnews_path, $CONFIG;
    $menufile = $config_dir . '/menu.conf';

    if (!isset($section)) {
        return "Please include a section name\n";
    }
    $uinfo = posix_getpwnam($CONFIG['webserver_user']);
    $spoolsection = $spooldir . '/' . $section;
    $configsection = $config_dir . '/' . $section;

    $websectionarray = explode('/', $spoolnews_path);
    $websectionpath = substr($spoolnews_path, 0, strlen($spoolnews_path) - 9);
    $websection = $websectionpath . $section;
    $websection = $websectionpath . '/' . $section;

    if (!file_exists($websection . '/newsportal.php')) {
        echo "Creating symlinks " . $websection . "\n";
        mkdir($websection);
        exec("ln -s " . $websectionpath . '/rocksolid/*' . ' ' . $websection);
    }

    if (!file_exists($configsection . '/groups.txt')) {
        mkdir($configsection);
        echo 'Creating ' . $configsection . '/groups.txt' . "\n";
        touch($configsection . '/groups.txt');
    }

    $menuexists = false;
    $menudata = file($config_dir . '/menu.conf');
    $newmenu = array();
    foreach ($menudata as $menuentry) {
        if (trim($menuentry) == '') {
            continue;
        }
        if (strpos($menuentry, $section) !== false) {
            echo "Menu entry already exists for: " . $section . "\n";
            $menuexists = true;
            break;
        }
        $newmenu[] = $menuentry;
    }
    if (!$menuexists) {
        echo "Adding menu entry to " . $config_dir . "/menu.conf\n";
        $newmenu[] = $section . ":1:1\n";
        $newmenu = implode($newmenu);
        file_put_contents($config_dir . '/menu.conf', $newmenu);
    }
    echo 'Please now edit ' . $configsection . "/groups.txt to add groups to this section\n";
}

function clean_spool()
{
    global $logfile, $workpath, $spooldir;
    $workpath = $spooldir . "/";
    $path = $workpath . "articles/";
    $group_list = get_group_list();
    $group = trim($group);
    $group_files = scandir($workpath);
    foreach ($group_files as $this_file) {
        if (strpos($this_file, '-articles.db3') === false) {
            continue;
        }
        $group = preg_replace('/-articles.db3/', '', $this_file);
        if (in_array($group, $group_list)) {
            continue;
        } else {
            echo "Removing: " . $this_file . "\n";
            remove_articles($group);
            reset_group($group, 1);
        }
    }
    echo "\nImport Done\r\n";
}

function import($group = '')
{
    global $logfile, $workpath, $spooldir;
    $workpath = $spooldir . "/";
    $path = $workpath . "articles/";
    $group_list = get_group_list();
    $group = trim($group);
    if ($group == '') {
        $group_files = scandir($workpath);
        foreach ($group_files as $this_file) {
            if (strpos($this_file, '-articles.db3') === false) {
                continue;
            }
            $group = preg_replace('/-articles.db3/', '', $this_file);
            if (in_array($group, $group_list)) {
                echo "Importing: " . $group . "\n";
                import_articles($group);
            } else {
                echo "Removing: " . $group . "\n";
                remove_articles($group);
                reset_group($group, 1);
            }
        }
    } else {
        echo "Importing: " . $group . "\n";
        import_articles($group);
    }
    echo "\nImport Done\r\n";
}

function get_group_list()
{
    global $config_dir;
    $grouplist = array();
    $menulist = file($config_dir . "/menu.conf", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($menulist as $menu) {
        if ($menu[0] == '#') {
            continue;
        }
        $menuitem = explode(':', $menu);
        if ($menuitem[2] == '0') {
            continue;
        }
        $glist = file($config_dir . $menuitem[0] . "/groups.txt");
        foreach ($glist as $gl) {
            if ($gl[0] == ':') {
                continue;
            }
            $group_name = preg_split("/( |\t)/", $gl, 2);
            $grouplist[] = trim($group_name[0]);
        }
    }
    return $grouplist;
}

function refill_group($group, $start)
{
    global $spooldir, $config_dir, $logfile, $remote_groups_array_file, $workpath, $CONFIG, $config_name, $path;

    $workpath = $spooldir . "/";
    $path = $workpath . "articles/";

    $config_name = get_section_by_group($group);
    if (file_exists($config_dir . $config_name . '.inc.php')) {
        $config_file = $config_dir . $config_name . '.inc.php';
    } else {
        $config_file = $config_dir . 'rslight.inc.php';
    }
    $CONFIG = include($config_file);

    $remote_groups_array_file = $spooldir . "/" . $config_name . "/" . $CONFIG['remote_server'] . ":" . $CONFIG['remote_port'] . "-remote_groups.dat";
    if (file_exists($remote_groups_array_file)) {
        try {
            $remote_groups_array = secure_unserialize($remote_groups_array_file);
            if (!is_array($remote_groups_array)) {
                $remote_groups_array = array();
            }
        } catch (Exception $e) {
            $remote_groups_array = array();
        }
    } else {
        $remote_groups_array = array();
    }

    foreach ($remote_groups_array as $key => $value) {
        if ($key == $group) {
            $newarray[$key] = $remote_groups_array[$key] - $start;
        } else {
            $newarray[$key] = $remote_groups_array[$key];
        }
    }
    file_put_contents($remote_groups_array_file, serialize($newarray));

    $ns = nntp2_open($CONFIG['remote_server'], $CONFIG['remote_port']);
    if ($ns == false) {
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Failed to connect to " . $CONFIG['remote_server'] . ":" . $CONFIG['remote_port'], FILE_APPEND);
        exit();
    }

    echo "Finding missing articles from Remote Server for: " . $group . " starting -" . $start . " articles\n";
    get_articles($ns, $group, $start);
}

function reset_section($section = "")
{
    global $config_dir;
    $section = trim($section);

    $gldata = file($config_dir . $section . "/groups.txt");
    foreach ($gldata as $gl) {
        if (($gl[0] == ':') || (trim($gl) == "")) {
            continue;
        }
        $group_name = preg_split("/( |\t)/", $gl, 2);
        $group = trim($group_name[0]);
        echo "START Reset " . $group . "\n";
        remove_articles($group);
        reset_group($group, 0);
    }
}

function reset_group($group, $remove = 0)
{
    global $config_dir, $spooldir;
    $group = trim($group);

    if (! $section = get_section_by_group($group)) {
        return false;
    }
    $config_location = $spooldir . '/' . $section;
    $config_files = array_diff(scandir($config_location), array(
        '..',
        '.',
        'outgoing'
    ));
    foreach ($config_files as $config_file) {
        if (!str_ends_with($config_file, '_groups.dat')) {
            continue;
        }
        try {
            $groups_array = secure_unserialize($config_location . '/' . $config_file);
            if (!is_array($groups_array)) {
                echo "Invalid groups file format: $config_file\n";
                continue;
            }
        } catch (Exception $e) {
            echo "Error reading groups file: $config_file\n";
            continue;
        }
        if (isset($groups_array[$group])) {
            echo "Current group pointer for " . $group . ": " . $groups_array[$group] . "\n";
            $groups_array[$group] = '1';
            echo "New group pointer for " . $group . ": " . $groups_array[$group] . "\n";
        }
        file_put_contents($config_location . '/' . $config_file, serialize($groups_array));
    }
}

function remove_articles($group)
{
    global $spooldir, $CONFIG, $workpath, $path, $config_name, $logfile;
    $group = trim($group);

    # Overview
    $overview_dbh = overview_db_open($spooldir . '/articles-overview.db3');

    $fetch_stmt = $overview_dbh->prepare("SELECT msgid FROM overview WHERE newsgroup=:group");
    $fetch_stmt->bindParam(':group', $group);
    $fetch_stmt->execute();
    $del_array = array();
    while ($row = $fetch_stmt->fetch()) {
        if (isset($row['msgid'])) {
            $del_array[] = $row['msgid'];
        }
    }
    $overview_dbh = null;
    foreach ($del_array as $delme) {
        delete_message($delme, $group);
        echo "Deleting " . $delme . " from " . $group . "\n";
    }

    # History
    $history_dbh = history_db_open($spooldir . '/history.db3');
    $clear_stmt = $history_dbh->prepare("DELETE FROM history WHERE newsgroup=:group");
    $clear_stmt->bindParam(':group', $group);
    $clear_stmt->execute();
    $history_dbh = null;

    @rename($spooldir . '/' . $group . '-articles.db3', $spooldir . '/' . $group . '-articles.db3-removed');
    @unlink($spooldir . '/' . $group . '-data.db3');
    @unlink($spooldir . '/' . $group . '-info.txt');
    @unlink($spooldir . '/' . $group . '-cache.txt');
    @unlink($spooldir . '/' . $group . '-lastarticleinfo.dat');
    @unlink($spooldir . '/' . $group . '-overboard.dat');
}

function mbox_import_articles($group, $mbox)
{
    global $spooldir, $CONFIG, $workpath, $path, $config_name, $logfile;
    # Prepare databases

    $database = $spooldir . '/' . $group . '-articles.db3';
    if (file_exists($database)) {
        echo $database . ' already exists. Please "-remove ' . $group . '" to import this mbox data' . "\n";
        echo "Exiting...\n";
        exit;
    }
    $new_article_dbh = article_db_open($database);

    if (!file_exists($mbox)) {
        echo 'Cannot open ' . $mbox . "\n";
        echo "Exiting...\n";
        exit;
    }

    $article = 0;
    $mbox_article = array();

    $file = fopen($mbox, 'r');
    while (($mbox_line = fgets($file)) !== false) {
        if ($article == 0 && trim($mbox_line == '')) {
            continue;
        }
        if (preg_match("/^From /", $mbox_line)) {
            if ($article > 0) {
                mbox_get_one_article($group, $mbox_article);
                echo "\nRetrieving " . $article;
                $mbox_article = array();
            }
            $article++;
            continue;
        }
        $mbox_article[] = rtrim($mbox_line);
    }
    fclose($file);
    mbox_get_one_article($group, $mbox_article);
    echo "Retrieving " . $article . "\n";
    exit;
}

// GET INDIVIDUAL ARTICLE
function mbox_get_one_article($group, $article_array)
{
    global $CONFIG, $config_name, $rslight_gpg, $spooldir, $logfile, $debug_log;
    $grouppath = $spooldir . '/articles/' . preg_replace('/\./', '/', $group);
    if (!is_dir($grouppath)) {
        mkdir($grouppath, 0777, 'recursive');
    }
    // $banned_names = file($user_ban_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $msgid_filter = get_config_value('header_filters.conf', 'Message-ID');
    $subject_filter = get_config_value('header_filters.conf', 'Subject');
    $from_filter = get_config_value('header_filters.conf', 'From');
    $path_filter = get_config_value('header_filters.conf', 'Path');

    // Create array for article, then send to insert_article_from_array()
    if (isset($current_article)) {
        unset($current_article);
        $current_article = array();
    }

    $local = get_next_article_number($group);
    $articleHandle = $grouppath . "/" . $local;
    if (file_exists($articleHandle)) {
        unlink($articleHandle);
    }
    unset($references);
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
    foreach ($article_array as $response) {
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
                if ($msgid_filter != false) {
                    if (preg_match($msgid_filter, $mid[1])) {
                        $banned = "msgid_filter";
                    }
                }
            }
            if (stripos($response, "From: ") === 0) {
                $from = explode(': ', $response, 2);
                if ($from_filter != false) {
                    if (preg_match($from_filter, $from[1])) {
                        $banned = "from_filter";
                    }
                }
            }
            if (stripos($response, "Path: ") === 0) {
                if ($path_filter != false) {
                    $msgpath = explode(': ', $response, 2);
                    if (preg_match($path_filter, $msgpath[1])) {
                        $banned = "path_filter";
                    }
                }
            }
            if (stripos($response, "Subject: ") === 0) {
                $this_subject = explode('Subject: ', $response, 2);
                $subject = $this_subject[1];
                $sub = 1;
                if ($subject_filter != false) {
                    if (preg_match($subject_filter, $subject)) {
                        $banned = "subject_filter";
                    }
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
        if (isset($rslight_gpg['nntp_group']) && ((strpos($rslight_gpg['nntp_group'], $group) !== false) && ($rslight_gpg['enable'] == '1'))) {
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
            if (isset($content_type[1])) {
                $this_snippet = get_search_snippet($newbody, $content_type[1], $content_transfer_encoding);
            } else {
                $this_snippet = get_search_snippet($newbody, '', $content_transfer_encoding);
            }
            unset($newbody);
        } else {
            touch($articleHandle, $article_date);
        }
        $current_article['mid'] = $mid[1];
        $current_article['epochdate'] = $article_date;
        $current_article['stringdate'] = $finddate[1];
        $current_article['from'] = $from[1];
        $current_article['subject'] = $subject;
        if (isset($references)) {
            $current_article['references'] = $references;
        }
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
            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Skipping: " . $CONFIG['remote_server'] . " " . $group . ":" . $mid[1] . " Exceeds Spam Score", FILE_APPEND);
            // $orig_newsgroups = $newsgroups;
            // $newsgroups = $CONFIG['spamgroup'];
            // $group = $newsgroups;
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
                file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Preparing to spool " . $group . ":" . $mid[1], FILE_APPEND);
                $tmp = insert_article_from_array($current_article, false);
                if ($tmp && $tmp[0] != "4") {
                    $pass = true;
                }
                file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " " . rtrim($tmp), FILE_APPEND);

            }
        }
        $local++;
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

function import_articles($group)
{
    global $spooldir, $CONFIG, $workpath, $path, $config_name, $logfile;
    # Prepare databases
    // Overview db
    $new_article_dbh = article_db_open($spooldir . '/' . $group . '-articles.db3-new');
    $new_article_sql = 'INSERT OR IGNORE INTO articles(newsgroup, number, msgid, date, name, subject, article, search_snippet) VALUES(?,?,?,?,?,?,?,?)';
    $new_article_stmt = $new_article_dbh->prepare($new_article_sql);
    $database = $spooldir . '/articles-overview.db3';
    $table = 'overview';
    $overview_dbh = overview_db_open($database, $table);
    $clear_stmt = $overview_dbh->prepare("DELETE FROM overview WHERE newsgroup=:group");
    $clear_stmt->bindParam(':group', $group);
    $clear_stmt->execute();
    clear_history_by_group($group);
    $overview_sql = 'INSERT OR IGNORE INTO overview(newsgroup, number, msgid, date, datestring, name, subject, refs, bytes, lines, xref) VALUES(?,?,?,?,?,?,?,?,?,?,?)';
    $overview_stmt = $overview_dbh->prepare($overview_sql);

    // Incoming db
    $article_dbh = article_db_open($spooldir . '/' . $group . '-articles.db3');
    $article_stmt = $article_dbh->query('SELECT DISTINCT * FROM articles');
    while ($row = $article_stmt->fetch()) {
        $local = $row['number'];
        $this_article = preg_split("/\r\n|\n|\r/", $row['article']);
        $lines = 0;
        $bytes = 0;
        $ref = 0;
        $banned = 0;
        $is_header = 1;
        $body = "";
        foreach ($this_article as $response) {
            $bytes = $bytes + mb_strlen($response, '8bit');
            if (trim($response) == "" || $lines > 0) {
                $is_header = 0;
                $lines++;
            }
            if ($is_header == 1) {
                $response = str_replace("\t", " ", $response);
                if (strpos($response, ': ') !== false) {
                    $ref = 0;
                }
                // Find article date
                if (stripos($response, "Date: ") === 0) {
                    $finddate = explode(': ', $response, 2);
                }
                // Get overview data
                $mid[1] = $row['msgid'];
                $from[1] = $row['name'];
                $subject[1] = $row['subject'];
                $article_date = $row['date'];
                if (stripos($response, "Content-Type: ") === 0) {
                    preg_match('/.*charset=.*/', $response, $te);
                    if (isset($te[0])) {
                        $content_type = explode("Content-Type: text/plain; charset=", $te[0]);
                    }
                }
                if (stripos($response, "References: ") === 0) {
                    $this_references = explode('References: ', $response);
                    $references = $this_references[1];
                    $ref = 1;
                }
                if (preg_match('/^\s/', $response) && $ref == 1) {
                    $references = $references . $response;
                }
                $response = str_replace("\n", "", str_replace("\r", "", $response));
            } else {
                $body .= $response . "\n";
            }
        }
        $lines = $lines - 1;
        $bytes = $bytes + ($lines * 2);
        // add to database
        // CREATE SEARCH SNIPPET
        $this_snippet = get_search_snippet($body, $content_type[1]);
        $xref = create_xref_from_msgid($mid[1], $group, $local);
        $new_article_stmt->execute([
            $group,
            $local,
            $mid[1],
            $article_date,
            $from[1],
            $subject[1],
            $row['article'],
            $this_snippet
        ]);
        $overview_stmt->execute([
            $group,
            $local,
            $mid[1],
            $article_date,
            $finddate[1],
            $from[1],
            $subject[1],
            $references,
            $bytes,
            $lines,
            $xref
        ]);
        $status = "respooled";
        $statusdate = time();
        $statusreason = "repair";
        $statusnotes = '';
        add_to_history($group, $local, $mid[1], $status, $statusdate, $statusreason, $statusnotes);
        echo "\nImported: " . $group . " " . $local;
        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Imported: " . $group . ":" . $local, FILE_APPEND);
        $i++;
        $references = "";
    }
    $new_article_dbh = null;
    $article_dbh = null;
    $overview_dbh = null;
    unlink($spooldir . '/' . $group . '-articles.db3');
    rename($spooldir . '/' . $group . '-articles.db3-new', $spooldir . '/' . $group . '-articles.db3');
    unlink($spooldir . '/' . $group . '-info.txt');
    unlink($spooldir . '/' . $group . '-cache.txt');
    unlink($spooldir . '/' . $group . '-lastarticleinfo.dat');
    unlink($spooldir . '/' . $group . '-overboard.dat');
    reset_group($group);
}
