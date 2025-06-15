<?php
/*
 * rocksolid overboard - overboard for rslight
 * Download: https://github.com/go-while/rocksolid-light
 * Original: https://news.novabbs.com/getrslight (Retro Guy's legacy)
 *
 * Original Author: Retro Guy (retroguy@novabbs.com)
 * Web: https://github.com/go-while/rocksolid-light
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

// Load rocksolid config for additional variables
//require_once(__DIR__ . '/../rocksolid/lib/config.inc.php');

// Fix config_path since $config_name is deprecated
$file_groups = $config_path . "groups.txt";

// Add security headers
add_security_headers();

if (! isset($_SESSION['last_access']) || (time() - $_SESSION['last_access']) > 60) {
    $_SESSION['last_access'] = time();
}

// Initialize title (should already be set by rocksolid/lib/config.inc.php but ensure it exists)
if (!isset($title)) {
    $title = isset($CONFIG['title_full']) ? $CONFIG['title_full'] : 'Rocksolid Light';
}

// Debug title components
echo "<!-- Debug: title before = '$title' -->\n";
echo "<!-- Debug: CONFIG[title_full] = '" . (isset($CONFIG['title_full']) ? $CONFIG['title_full'] : 'NOT_SET') . "' -->\n";

if (isset($_GET['thisgroup'])) {
    $title .= " - " . rawurldecode(rawurldecode($_GET['thisgroup'])) . " - latest messages";
    $activegroup = urldecode($_GET['thisgroup']);
} else {
    $title .= " - overboard";
}

rslight_render_complete_header($title);

if (disable_page_by_user_agent(null, "bot", "Overboard")) {
    echo "<center>Page Disabled</center>";
    rslight_render_complete_footer();
    exit();
}

$logfile = $logdir . '/overboard.log';

$cookie_mail_name = $_COOKIE['mail_name'];
if (isset($_COOKIE['mail_name']) && $_COOKIE['mail_name'] == $CONFIG['anonusername']) {
    unset($cookie_mail_name);
}

# How many days old should articles be displayed?
if (isset($_GET['thisgroup'])) {
    $article_age = 30;
} else {
    $article_age = 30;
}

$version = 1.25;

# How long in seconds to cache results
$cachetime = 60;

# Maximum number of articles to show
$maxdisplay = 300; // default 1000

# How many characters of the body to display per article
$snippetlength = 240;

$spoolpath_regexp = '/' . preg_replace('/\//', '\\/', $spoolpath) . '/';
$thissite = '.';

$groupconfig = $file_groups;
echo "<!-- Debug: file_groups = '$file_groups' -->\n";
echo "<!-- Debug: config_path = '" . (isset($config_path) ? $config_path : 'NOT_SET') . "' -->\n";
echo "<!-- Debug: config_dir = '" . (isset($config_dir) ? $config_dir : 'NOT_SET') . "' -->\n";
$cachefile = $spooldir . "/overboard.dat";
$oldest = (time() - (86400 * $article_age));
echo "<!-- Debug: article_age=$article_age, oldest=$oldest (" . date('Y-m-d H:i:s', $oldest) . ") -->\n";

if (isset($_GET['time'])) {
    $user_time = $_GET['time'];
    if (is_numeric($user_time)) {
        if (($user_time > time()) || ($user_time < $oldest)) {
            unset($user_time);
        }
    } else {
        unset($user_time);
    }
}

if (isset($_GET['thisgroup'])) {
    $_GET['thisgroup'] = rawurldecode($_GET['thisgroup']);
    if (get_section_by_group($_GET['thisgroup']) == false) {
        echo "Group not found";
        exit(1);
    }
    $grouplist = array();
    $grouplist[0] = rawurldecode(rawurldecode($_GET['thisgroup']));
    $cachefile = $spooldir . "/" . $grouplist[0] . "-overboard.dat";
    if (isset($cookie_mail_name)) {
        if ($userdata = get_user_mail_auth_data($cookie_mail_name)) {
            $userfile = $spooldir . '/' . strtolower($cookie_mail_name) . '-articleviews.dat';
            try {
                $user_config = secure_unserialize(file_get_contents($config_dir . '/userconfig/' . strtolower($cookie_mail_name) . '.config'));
                if (!is_array($user_config)) {
                    $user_config = array();
                }
            } catch (Exception $e) {
                $user_config = array();
            }
            $userdata[$grouplist[0]] = time();
            file_put_contents($userfile, serialize($userdata));
        }
    }
} else {
    // Debug: Check if groups file exists
    echo "<!-- Debug: Loading groups from $groupconfig -->\n";
    $grouplist = file($groupconfig, FILE_IGNORE_NEW_LINES);
    echo "<!-- Debug: Loaded " . count($grouplist) . " groups: " . implode(', ', $grouplist) . " -->\n";
}

// Determine default view style
if (isset($cookie_mail_name)) {
    if ($user_obstyle = get_config_file_value($config_dir . '/userconfig/' . strtolower($cookie_mail_name), 'obstyle')) {
        $_SESSION['obstyle'] = $user_obstyle;
    }
}
if (isset($_POST['obstyle'])) {
    $_SESSION['obstyle'] = $_POST['obstyle'];
}
if (! isset($_SESSION['obstyle'])) {
    if (isset($OVERRIDES['overboard_default_view'])) {
        $_SESSION['obstyle'] = $OVERRIDES['overboard_default_view'];
    } else {
        $_SESSION['obstyle'] = 'articles';
    }
}
if (isset($cookie_mail_name)) {
    save_config_value($config_dir . '/userconfig/' . strtolower($cookie_mail_name), 'obstyle', $_SESSION['obstyle'], true);
}
show_overboard_header($grouplist);

$results = 0;

if (! isset($this_overboard['version'])) {
    $this_overboard['version'] = '0';
}
if (is_file($cachefile)) {
    $stats = stat($cachefile);
    try {
        $this_overboard = secure_unserialize(file_get_contents($cachefile));
        if (!is_array($this_overboard)) {
            $this_overboard = array('version' => '0');
        }
    } catch (Exception $e) {
        $this_overboard = array('version' => '0');
    }
    if (isset($this_overboard['lastmessage']) && $this_overboard['lastmessage'] > 0) {
        $cachedate = ($this_overboard['lastmessage'] - 86400);
    } else {
        $cachedate = ($oldest - 86400);
    }
    $oldest = $cachedate;
} else {
    $cachedate = ($oldest - 86400);
}
if ($this_overboard['version'] !== $version) {
    unset($this_overboard);
    if (is_file($cachefile)) {
        unlink($cachefile);
    }
    $this_overboard['version'] = $version;
    $cachedate = ($oldest - 86400);
}

# Iterate through groups

$database = $spooldir . '/articles-overview.db3';
$table = 'overview';
echo "<!-- Debug: Database=$database, searching for articles newer than " . date('Y-m-d H:i:s', $cachedate) . " -->\n";
$dbh = overview_db_open($database, $table);
$query = $dbh->prepare('SELECT * FROM ' . $table . ' WHERE newsgroup=:findgroup AND date >= ' . $cachedate . ' ORDER BY date DESC LIMIT ' . $maxdisplay);
foreach ($grouplist as $findgroup) {
    // Remove any group description info from group name
    $groups = preg_split("/(\ |\t)/", $findgroup, 2);
    $findgroup = $groups[0];

    $overboard_noshow = explode(' ', $CONFIG['overboard_noshow']);
    foreach ($overboard_noshow as $noshow) {
        if ((strpos($findgroup, $noshow) !== false) && ! isset($_GET['thisgroup'])) {
            continue 2;
        }
    }
    $thisgroup = preg_replace('/\./', '/', $findgroup);
    if ($dbh) {
        echo "<!-- Debug: Searching group '$findgroup' -->\n";
        $query->execute([
            'findgroup' => $findgroup
        ]);
        $results = 0;
        while (($overviewline = $query->fetch()) !== false) {
            $thismsgid = $overviewline['msgid'];
            $target = get_data_from_msgid($thismsgid, $findgroup);
            if ($target['date'] > time()) {
                continue;
            }
            if (! isset($this_overboard['lastmessage'])) {
                $this_overboard['lastmessage'] = 0;
            }
            if ($target['date'] > $this_overboard['lastmessage']) {
                $this_overboard['lastmessage'] = $target['date'];
            }

            // Must handle crossposted articles (time is equal)
            $unique_date = $target['date'] . "." . rand(0, 4) . rand(0, 40);
            // if (! isset($this_overboard['threads'][$unique_date])) {
            $this_overboard['threads'][$unique_date] = $thismsgid;
            $this_overboard['msgids'][$thismsgid] = $target;
            if (trim($overviewline['refs']) != '') {
                $ref = preg_split("/[\s]+/", $overviewline['refs']);
                $this_overboard['threadlink'][$thismsgid] = $ref[0];
            }
            file_put_contents($logfile, "\n" . format_log_date() . " overboard Adding: " . $thismsgid, FILE_APPEND);
            if ($results++ > ($maxdisplay - 2)) {
                break;
            }
        }
    }
}

$this_overboard['version'] = $version;
file_put_contents($cachefile, serialize($this_overboard));
if (isset($user_time)) {
    $oldest = ($user_time - 900);
} else {
    $oldest = (time() - (86400 * $article_age));
}

if ($_SESSION['obstyle'] == 'threads') {
    $results = display_threads($this_overboard['threads'], $oldest);
} else {
    $results = display_flat($this_overboard['threads'], $oldest);
}

show_overboard_footer(null, $results, null);
echo '</body></html>';
expire_overboard($cachefile);

function expire_overboard($cachefile)
{
    global $article_age, $logfile, $this_overboard;
    if ((! isset($this_overboard['expire'])) || ($this_overboard['expire'] < (time() - 1800))) {
        foreach ($this_overboard['threads'] as $key => $value) {
            if ($key < (time() - (86400 * $article_age))) {
                file_put_contents($logfile, "\n" . format_log_date() . " overboard Expiring: " . $value, FILE_APPEND);
                unset($this_overboard['threads'][$key]);
                unset($this_overboard['msgids'][$value]);
                unset($this_overboard['threadlink'][$value]);
            }
        }
        $this_overboard['expire'] = time();
        file_put_contents($logfile, "\n" . format_log_date() . " overboard Setting expire time to now", FILE_APPEND);
        file_put_contents($cachefile, serialize($this_overboard));
    }
}

function display_threads($threads, $oldest)
{
    global $CONFIG, $OVERRIDES, $thissite, $logfile, $config_dir, $spooldir, $logdir;
    global $cookie_mail_name, $snippetlength, $maxdisplay, $this_overboard, $article_age, $newonly;
    $logfile = $logdir . '/overboard.log';  // Ensure logfile is set correctly
    $expireme = time() - ($article_age * 86400);
    $display = '<table class="overboard_results_table">';
    if (! isset($threads)) {
        $threads = (object) [];
    } else {
        krsort($threads);
    }
    // Get registered user settings
    $newonly = false;
    if (isset($cookie_mail_name)) {
        if ($userdata = get_user_mail_auth_data($cookie_mail_name)) {
            try {
                $user_config = secure_unserialize(file_get_contents($config_dir . '/userconfig/' . strtolower($cookie_mail_name) . '.config'));
                if (!is_array($user_config)) {
                    $user_config = array();
                }
            } catch (Exception $e) {
                $user_config = array();
            }
            $userfile = $spooldir . '/' . strtolower($cookie_mail_name) . '-blocked_posters.dat';
            if (file_exists($userfile)) {
                try {
                    $blocked_user_config = secure_unserialize(file_get_contents($userfile));
                    if (!is_array($blocked_user_config)) {
                        $blocked_user_config = array();
                    }
                } catch (Exception $e) {
                    $blocked_user_config = array();
                }
            } else {
                $blocked_user_config = null;
            }
        }

        if (! isset($user_config['hide_unsub'])) {
            if (isset($OVERRIDES['hide_unsub'])) {
                $user_config['hide_unsub'] = $OVERRIDES['hide_unsub'];
            } else {
                $user_config['hide_unsub'] = 'hide';
            }
        }
        // Show NEW in Section only
        if (isset($_REQUEST['new']) && $_REQUEST['new'] == true) {
            $newonly = true;
        }
    }
    // Build display array
    $nicole = array();
    foreach ($threads as $key => $value) {
        if ($key < $oldest) {
            continue;
        }
        if (! isset($this_overboard['threadlink'][$value])) {
            // Add article with no available top reference to array
            $nicole[$value][$value] = $value;
        } else {
            $nicole[$this_overboard['threadlink'][$value]][$value] = $value;
        }
    }
    $style = 0;
    $results = 0;
    foreach ($nicole as $key => $value) {
        if (isset($target_head)) {
            unset($target_head);
        }
        if (isset($this_overboard['msgids'][$key])) {
            $target_head = $this_overboard['msgids'][$key];
        }
        if (! isset($target_head['msgid'])) {
            $target_head = get_data_from_msgid($key);
        }
        $nohead = true;
        $result_count = count($value);
        foreach ($value as $new) {
            if (! $foundgroup = check_group_for_user($new, $userdata, $user_config, true)) {
                continue;
            }
            $target = $this_overboard['msgids'][$new];
            if (! isset($target['msgid'])) {
                $target = get_data_from_msgid($new);
            }
            if ($target['date'] < $oldest) {
                continue;
            }
            if ($newonly) {
                if ($foundgroup && $foundgroup != '') {
                    if ($target['date'] < $userdata[$foundgroup]) {
                        continue;
                    }
                }
            }
            $results++;
            $skip = '';
            if ($nohead) {
                if(!isset($target_head['number'])) {
                    $target_head = $target;
                }
                if (($style % 2) == 0) {
                    $display .= '<tr class="overboard_result_line2"><td class="overboard_result_line2" style="word-wrap:break-word">';
                } else {
                    $display .= '<tr class="overboard_result_line1"><td class="overboard_result_line1" style="word-wrap:break-word">';
                }
                if ($target_head) {
                    $display .= '<div class="overboard_threadhead_result">';
                    $url = $thissite . "/?page=article-flat&id=" . $target_head['number'] . "&group=" . _rawurlencode($target_head['newsgroup']) . "#" . $target_head['number'];
                    $display .= '<p class=np_ob_subject>';
                    $display .= '<b><a href="' . $url . '"><span>' . htmlentities(headerDecode($target_head['subject'])) . '</span></a></b></p>';
                    $display .= '<a href="thread.php?group=' . _rawurlencode($target_head['newsgroup']) . '">' . $target_head['newsgroup'] . '</a>';
                    $timetest = $oldest;
                    if ($newonly) {
                        $timetest = $userdata[$target_head['newsgroup']];
                    }
                    if ((($target_head['date'] < $timetest) || $result_count > 10) && isset($target_head['date'])) {
                        $poster = get_poster_name(mb_decode_mimeheader($target_head['name']));
                        $block = false;
                        foreach ($blocked_user_config as $bkey => $bvalue) {
                            $blockme = '/' . addslashes($bkey) . '/';
                            if (preg_match($blockme, $target_head['name'])) {
                                $block = true;
                                break;
                            }
                        }
                        if ($block) {
                            $display .= '<br><br>';
                            $display .= '<p class="overboard_blocked_user_notice">';
                            $display .= '<b><span>(message #' . $target_head['number'] . ' hidden by your blocklist)</span></a></b></p>';
                        } else {
                            $display .= '<p class=np_ob_posted_date>Posted: ' . get_date_interval(date("D, j M Y H:i T", $target_head['date'])) . ' by: ' . create_name_link($poster['name'], $poster['from']) . '</p>';
                            if ($CONFIG['article_database'] == '1') {
                                $article = get_db_data_from_msgid($target_head['msgid'], $target_head['newsgroup'], 1);

                                $text = $article['search_snippet'];
                                $text = rewrite_body($text);
                                $display .= strip_tags(wordwrap(substr($text, 0, $snippetlength), ($snippetlength / 2), "<br>\n", true));
                            }
                        }
                        $skip = $target_head['number'];
                    }
                    $display .= '</div>';
                    $style++;
                    $nohead = false;
                }
            }
            if ($skip != $target['number']) {
                $poster = get_poster_name(mb_decode_mimeheader($target['name']));
                $block = false;
                foreach ($blocked_user_config as $key => $value) {
                    $blockme = '/' . addslashes($key) . '/';
                    if (preg_match($blockme, $target['name'])) {
                        $block = true;
                        break;
                    }
                }
                if ($block) {
                    $display .= '<br><br>';
                    $display .= '<p class="overboard_blocked_user_notice">';
                    $display .= '<b><span>(message #' . $target['number'] . ' hidden by your blocklist)</span></a></b></p>';
                } else {
                    $groupurl = $thissite . "/thread.php?group=" . _rawurlencode($target['newsgroup']);
                    $url = $thissite . "/?page=article-flat&id=" . $target['number'] . "&group=" . _rawurlencode($target['newsgroup']) . "#" . $target['number'];
                    $display .= '<br><br>';
                    $display .= '<p class=np_ob_subject>';
                    $display .= '<b><a href="' . $url . '"><span>' . htmlentities(headerDecode($target['subject'])) . '</span></a></b>';
                    $display .= '</p>';
                    $display .= '<p class=np_ob_body>';
                    $display .= 'by: <b><i><span class="visited">' . create_name_link($poster['name'], $poster['from']) . '</span></i></b>';

                    $display .= '</p>';
                    $display .= '<p class=np_ob_posted_date>Posted: ' . get_date_interval(date("D, j M Y H:i T", $target['date'])) . ' in: <a href="' . $groupurl . '"><span class="visited">' . $target['newsgroup'] . '</span></a></p>';
                    if ($CONFIG['article_database'] == '1') {
                        $article = get_db_data_from_msgid($target['msgid'], $target['newsgroup'], 1);
                        $text = $article['search_snippet'];
                        $text = rewrite_body($text);
                        $display .= strip_tags(html_parse(text2html(substr($text, 0, $snippetlength))));
                        //     $display .= strip_tags(htmlentities(substr($text, 0, $snippetlength)));
                    }
                    if ($target['date'] < $expireme) {
                        unset($this_overboard['threads'][$target['date']]);
                        unset($this_overboard['threadlink'][$new]);
                        file_put_contents($logfile, "\n" . format_log_date() . " overboard Pruning: " . $target['newsgroup'] . ":" . $target['number'], FILE_APPEND);
                    }
                }
            }
        }
        $display .= '</td></tr>';
        if ($results > ($maxdisplay - 1)) {
            break;
        }
    }

    $display .= "</table>";
    echo $display;
    return ($results);
}

function display_flat($threads, $oldest)
{
    global $CONFIG, $OVERRIDES, $thissite, $logfile, $spooldir, $config_dir, $logdir;
    global $cookie_mail_name, $snippetlength, $maxdisplay, $this_overboard, $article_age, $newonly;
    $logfile = $logdir . '/overboard.log';  // Ensure logfile is set correctly
    $expireme = time() - ($article_age * 86400);
    $display = '<table class="overboard_results_table">';
    if (! isset($threads)) {
        $threads = (object) [];
    } else {
        krsort($threads);
    }
    // Get registered user settings
    $newonly = false;
    if (isset($cookie_mail_name)) {
        if ($userdata = get_user_mail_auth_data($cookie_mail_name)) {
            $userfile = $spooldir . '/' . strtolower($cookie_mail_name) . '-articleviews.dat';
            try {
                $user_config = secure_unserialize(file_get_contents($config_dir . '/userconfig/' . strtolower($cookie_mail_name) . '.config'));
                if (!is_array($user_config)) {
                    $user_config = array();
                }
            } catch (Exception $e) {
                $user_config = array();
            }
        }
        $userfile = $spooldir . '/' . strtolower($cookie_mail_name) . '-blocked_posters.dat';
        if (file_exists($userfile)) {
            try {
                $blocked_user_config = secure_unserialize(file_get_contents($userfile));
                if (!is_array($blocked_user_config)) {
                    $blocked_user_config = array();
                }
            } catch (Exception $e) {
                $blocked_user_config = array();
            }
        } else {
            $blocked_user_config = null;
        }

        if (! isset($user_config['hide_unsub'])) {
            if (isset($OVERRIDES['hide_unsub'])) {
                $user_config['hide_unsub'] = $OVERRIDES['hide_unsub'];
            } else {
                $user_config['hide_unsub'] = 'hide';
            }
        }
        // Show NEW in Section only
        if (isset($_REQUEST['new']) && $_REQUEST['new'] == true) {
            $newonly = true;
        }
    }
    $results = 0;
    $shown = array();
    foreach ($threads as $key => $value) {
        // Skip if not in registered users sub list
        if (! $foundgroup = check_group_for_user($value, $userdata, $user_config, true)) {
            continue;
        }
        $target = $this_overboard['msgids'][$value];
        if (! isset($target['msgid'])) {
            $target = get_data_from_msgid($value);
        }
        if (isset($shown[$value . $target['newsgroup']])) {
            continue;
        } else {
            $shown[$value . $target['newsgroup']] = $value;
        }
        if ($target['date'] < $oldest) {
            if ($oldest - $target['date'] > 300) {
                break;
            } else {
                continue;
            }
        }
        if ($newonly) {
            if ($foundgroup && $foundgroup != '') {
                if ($target['date'] < $userdata[$foundgroup]) {
                    continue;
                }
            }
        }
        if ($target['date'] < $expireme) {
            unset($this_overboard['threads'][$target['date']]);
            unset($this_overboard['threadlink'][$value]);
            file_put_contents($logfile, "\n" . format_log_date() . " overboard Pruning: " . $target['newsgroup'] . ":" . $target['number'], FILE_APPEND);
        }
        $poster = get_poster_name(mb_decode_mimeheader($target['name']));
        $groupurl = $thissite . "/thread.php?group=" . _rawurlencode($target['newsgroup']);
        if (($results % 2) == 0) {
            $display .= '<tr class="overboard_result_line2"><td class="overboard_result_line2" style="word-wrap:break-word">';
        } else {
            $display .= '<tr class="overboard_result_line1"><td class="overboard_result_line1" style="word-wrap:break-word">';
        }
        $block = false;
        foreach ($blocked_user_config as $bkey => $bvalue) {
            $blockme = '/' . addslashes($bkey) . '/';
            if (preg_match($blockme, $target['name'])) {
                $block = true;
                break;
            }
        }
        if ($block) {
            $display .= '<p class="overboard_blocked_user_notice">';
            $display .= '<b><span>(message #' . $target['number'] . ' hidden by your blocklist)</span></a></b></p>';
        } else {
            $url = $thissite . "/?page=article-flat&id=" . $target['number'] . "&group=" . _rawurlencode($target['newsgroup']) . "#" . $target['number'];
            $display .= '<p class=np_ob_subject>';
            $display .= '<b><a href="' . $url . '"><span>' . htmlentities(headerDecode($target['subject'])) . '</span></a></b>';
            $display .= '<p class=np_ob_body>';
            $display .= 'by: <b><i><span class="visited">' . create_name_link($poster['name'], $poster['from']) . '</span></i></b>';

            $display .= '</p>';
            // link for (thread), if possible
            if (isset($target_head)) {
                unset($target_head);
            }
            // Display 'full thread' link if available
            if (isset($this_overboard['threadlink'][$value])) {
                $target_head = get_data_from_msgid($this_overboard['threadlink'][$value]);
                if ($target_head !== false) {
                    $display .= '<font class="np_ob_group"><a href="?page=article-flat&id=' . $target_head['number'] . '&group=' . rawurlencode($target_head['newsgroup']) . '#' . $target_head['number'] . '"> (full thread)</a></font>';
                }
            }
            $display .= '<p class=np_ob_posted_date>Posted: ' . get_date_interval(date("D, j M Y H:i T", $target['date'])) . ' in: <a href="' . $groupurl . '"><span class="visited">' . $target['newsgroup'] . '</span></a></p>';
            if ($CONFIG['article_database'] == '1') {
                $article = get_db_data_from_msgid($target['msgid'], $target['newsgroup'], 1);
                $text = $article['search_snippet'];
                $text = rewrite_body($text);
                $display .= strip_tags(html_parse(text2html(substr($text, 0, $snippetlength))));
                //$display .= htmlentities(substr($text, 0, $snippetlength));
            }
        }
        $results++;
        if ($results > ($maxdisplay - 1)) {
            break;
        }
    }
    $display .= "</table>";
    echo $display;
    return ($results);
}

function show_overboard_header($grouplist)
{
    global $text_thread, $frame, $text_article, $file_index, $file_thread, $user_time;

    if (isset($_GET['thisgroup'])) {
        echo '<h1 class="np_thread_headline">';
        echo '<a href="' . $file_index . '" target=' . $frame['menu'] . '>' . basename(getcwd()) . '</a> / ';
        echo '<a href="' . $file_thread . '?group=' . rawurlencode($grouplist[0]) . '" target=' . $frame["content"] . '>' . htmlspecialchars(group_displaY_name($grouplist[0])) . '</a> / ';
        if (isset($user_time)) {
            echo ' new messages</h1>';
        } else {
            echo ' latest</h1>';
        }
        echo '<table class="np_buttonbar"><tr>';
        // Refresh button
        echo '<td>';
        echo '<div style="float:left;">';
        echo '<form action="overboard.php">';
        echo '<input type="hidden" name="thisgroup" value="' . htmlspecialchars($_GET['thisgroup'], ENT_QUOTES, 'UTF-8') . '">';
        if (isset($user_time)) {
            echo '<button class="np_button_link" type="submit">overboard</button>';
        } else {
            echo '<button class="np_button_link" type="submit">' . $text_article["refresh"] . '</button>';
        }

        echo '</form>';
        echo '</div>';
        // Article List button
        echo '<form action="' . $file_thread . '">';
        echo '<input type="hidden" name="group" value="' . $grouplist[0] . '">';
        echo '<button class="np_button_link" type="submit">' . htmlspecialchars(group_display_name($grouplist[0])) . '</button>';
        echo '</form>';
        echo '</td>';
        // Newsgroups button (hidden)
        if (isset($frames_on) && $frames_on === true) {
            echo '<td>';
            echo '<form action="' . $file_index . '">';
            echo '<button class="np_button_hidden" type="submit">' . $text_thread["button_grouplist"] . '</button>';
            echo '</form>';
            echo '</td>';
        }
    } else {
        echo '<h1 class="np_thread_headline">';
        echo '<a href="' . $file_index . '" target=' . $frame['menu'] . '>' . basename(getcwd()) . '</a> / ';
        echo 'latest messages</h1>';
        echo '<table class="np_buttonbar"><tr>';
        // Refresh button
        echo '<td>';
        echo '<form action="overboard.php">';
        echo '<button class="np_button_link" type="submit">overboard</button>';
        echo '</form>';
        echo '</td>';
        // Newsgroups button (hidden)
        if (isset($frames_on) && $frames_on === true) {
            echo '<td>';
            echo '<form action="' . $file_index . '">';
            echo '<button class="np_button_hidden" type="submit">' . $text_thread["button_grouplist"] . '</button>';
            echo '</form>';
            echo '</td>';
        }
    }
    echo '<td></td>';
    echo '<td class="np_ob_style_toggle">';

    echo '<div style="float:right;">';

    show_overboard_style_toggle();
    echo '</div>';
    echo '</td>';
    echo '</tr></table>';
}

// Return TRUE unless group is not subscribed by user
// It is assumed $newsgroups to check are verified to be in SECTION
function check_group_for_user($msgid, $userdata, $user_config, $check_section = false)
{
    global $logdir;
    $logfile = $logdir . '/overboard.log';
    if (! is_array($userdata)) {
        // No logged in user
        return true;
    }
    if (! isset($user_config['hide_unsub']) || $user_config['hide_unsub'] != 'hide') {
        return true;
    }
    $newsgroups = get_newsgroups_by_msgid($msgid);
    if ($newsgroups == false) {
        return false;
    }
    foreach ($newsgroups as $newsgroup) {
        if ($check_section) {
            // For overboard, we show all sections, so skip section filtering
            // Just check if user is subscribed to this group
            if (isset($userdata[$newsgroup])) {
                return $newsgroup;
            }
        } else {
            if (isset($userdata[$newsgroup])) {
                return $newsgroup;
            }
        }
    }
    return false;
}

function show_overboard_style_toggle()
{
    echo '<form method="post" action="' . $_SERVER['REQUEST_URI'] . '">';
    echo 'Display as: ';
    if ($_SESSION['obstyle'] == 'threads') {
        echo '<input type="radio" name="obstyle" value="threads" checked>Threads';
        echo '&nbsp;';
        echo '<input type="radio" name="obstyle" value="articles">Articles';
        echo '&nbsp;';
    } else {
        echo '<input type="radio" name="obstyle" value="threads">Threads';
        echo '&nbsp;';
        echo '<input type="radio" name="obstyle" value="articles" checked>Articles';
        echo '&nbsp;';
    }
    echo '<input class="np_button_link" type="submit" value="Reload" name="reload">';
    echo '</form >';
}

function show_overboard_footer($stats, $results, $iscached)
{
    global $user_time, $rslight_version, $newonly;
    if (isset($user_time) || $newonly) {
        $recent = 'new';
    } else {
        $recent = 'recent';
    }
    if ($results == '1') {
        $arts = 'article';
    } else {
        $arts = 'articles';
    }
    echo "<p class=np_ob_tail><b>" . $results . "</b> " . $recent . " " . $arts . " found.</p>\r\n";

    rslight_render_complete_footer();

    if ($iscached) {
        echo "<p class=np_ob_tail><font size='1em'>cached copy: " . date("D M j G:i:s T Y", $stats[9]) . "</font></p>\r\n";
    }
}
