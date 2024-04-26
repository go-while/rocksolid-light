<?php
session_start();
if (! isset($_SESSION['last_access']) || (time() - $_SESSION['last_access']) > 60) {
    $_SESSION['last_access'] = time();
}

header("Expires: " . gmdate("D, d M Y H:i:s", time() + (120)) . " GMT");
header("Cache-Control: max-age=120");
header("Pragma: cache");

/*
 * rocksolid overboard - overboard for rslight
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
include "$file_newsportal";

if (isset($frames_on) && $frames_on === true) {
    ?>
<script>
    var contentURL=window.location.pathname+window.location.search+window.location.hash;
    if ( window.self !== window.top ) {
        /* Great! now we move along */
    } else {
        window.location.href = '../index.php?content='+encodeURIComponent(contentURL);
    }
    top.history.replaceState({}, 'Title', 'index.php?content='+encodeURIComponent(contentURL));
</script>

<?php
}
if (isset($_GET['thisgroup'])) {
    $title .= " - " . _rawurldecode(_rawurldecode($_GET['thisgroup'])) . " - latest messages";
    $activegroup = urldecode($_GET['thisgroup']);
} else {
    $title .= " - " . $config_name . " - overboard";
}
include "head.inc";
if (disable_page_by_user_agent($client_device, "bot", "Overboard")) {
    echo "<center>Page Disabled</center>";
    include "tail.inc";
    exit();
}

$CONFIG = include ($config_file);
$logfile = $logdir . '/overboard.log';

# How many days old should articles be displayed?
if (isset($_GET['thisgroup'])) {
    $article_age = 30;
} else {
    $article_age = 30;
}

$version = 1.2;

# How long in seconds to cache results
$cachetime = 60;

# Maximum number of articles to show
$maxdisplay = 1000; // default 1000

# How many characters of the body to display per article
$snippetlength = 240;

$spoolpath_regexp = '/' . preg_replace('/\//', '\\/', $spoolpath) . '/';
$thissite = '.';

$groupconfig = $file_groups;
$cachefile = $spooldir . "/" . $config_name . "-overboard.dat";
$oldest = (time() - (86400 * $article_age));

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
    $_GET['thisgroup'] = _rawurldecode($_GET['thisgroup']);
    if (get_section_by_group($_GET['thisgroup']) == false) {
        echo "Group not found";
        exit(1);
    }
    $grouplist = array();
    $grouplist[0] = _rawurldecode(_rawurldecode($_GET['thisgroup']));
    $cachefile = $spooldir . "/" . $grouplist[0] . "-overboard.dat";
    if (isset($_COOKIE['mail_name'])) {
        if ($userdata = get_user_mail_auth_data($_COOKIE['mail_name'])) {
            $userfile = $spooldir . '/' . strtolower($_COOKIE['mail_name']) . '-articleviews.dat';
            $user_config = unserialize(file_get_contents($config_dir . '/userconfig/' . strtolower($_COOKIE['mail_name']) . '.config'));
            $userdata[$grouplist[0]] = time();
            file_put_contents($userfile, serialize($userdata));
        }
    }
} else {
    $grouplist = file($groupconfig, FILE_IGNORE_NEW_LINES);
}

show_overboard_header($grouplist);

// Determine default view style
if (isset($_COOKIE['mail_name'])) {
    if ($user_obstyle = get_config_value($config_dir . '/userconfig/' . strtolower($_COOKIE['mail_name']), 'obstyle')) {
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
if (isset($_COOKIE['mail_name'])) {
    save_config_value($config_dir . '/userconfig/' . strtolower($_COOKIE['mail_name']), 'obstyle', $_SESSION['obstyle'], true);
}
show_overboard_style_toggle();

$results = 0;

if (! isset($this_overboard['version'])) {
    $this_overboard['version'] = '0';
}
if (is_file($cachefile)) {
    $stats = stat($cachefile);
    $this_overboard = unserialize(file_get_contents($cachefile));
    $cachedate = ($this_overboard['lastmessage'] - 86400);
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
$dbh = overview_db_open($database, $table);
$query = $dbh->prepare('SELECT * FROM ' . $table . ' WHERE newsgroup=:findgroup AND date >= ' . $cachedate . ' ORDER BY date DESC LIMIT ' . $maxdisplay);
foreach ($grouplist as $findgroup) {
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

            if (! isset($this_overboard['threads'][$target['date']])) {
                $this_overboard['threads'][$target['date']] = $thismsgid;
                $this_overboard['msgids'][$thismsgid] = $target;
                if (trim($overviewline['refs']) != '') {
                    $ref = preg_split("/[\s]+/", $overviewline['refs']);
                    $this_overboard['threadlink'][$thismsgid] = $ref[0];
                }
                if ($results ++ > ($maxdisplay - 2)) {
                    break;
                }
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
    global $article_age, $logfile, $config_name, $this_overboard;
    if (! isset($this_overboard['expire'])) {
        $this_overboard['expire'] = time();
    }
    $prune = false;
    if ($this_overboard['expire'] < (time() - 86400)) {
        $prune = true;
        foreach ($this_overboard['msgids'] as $key => $value) {
            $target = $this_overboard['msgids'][$key];
            if ($target['date'] < (time() - (86400 * $article_age))) {
                file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Expiring: " . $target['newsgroup'] . ":" . $target['number'], FILE_APPEND);
                unset($this_overboard['threads'][$target['date']]);
                unset($this_overboard['msgids'][$key]);
                unset($this_overboard['threadlink'][$key]);
            }
        }
        $this_overboard['expire'] = time();
    }
    if ($prune) {
        file_put_contents($cachefile, serialize($this_overboard));
    }
}

function display_threads($threads, $oldest)
{
    global $CONFIG, $OVERRIDES, $thissite, $logfile, $config_name, $spooldir, $config_dir, $snippetlength, $maxdisplay, $this_overboard, $article_age;
    $expireme = time() - ($article_age * 86400);
    $display = '<table cellspacing="0" width="100%" class="np_results_table">';
    if (! isset($threads)) {
        $threads = (object) [];
    } else {
        krsort($threads);
    }
    // Get registered user settings
    if (isset($_COOKIE['mail_name'])) {
        if ($userdata = get_user_mail_auth_data($_COOKIE['mail_name'])) {
            $userfile = $spooldir . '/' . strtolower($_COOKIE['mail_name']) . '-articleviews.dat';
            $user_config = unserialize(file_get_contents($config_dir . '/userconfig/' . strtolower($_COOKIE['mail_name']) . '.config'));
            $userfile = $spooldir . '/' . strtolower($_COOKIE['mail_name']) . '-blocked_posters.dat';
            if (file_exists($userfile)) {
                $blocked_user_config = unserialize(file_get_contents($userfile));
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
        $target_head = $this_overboard['msgids'][$key];
        if (! isset($target_head['msgid'])) {
            $target_head = get_data_from_msgid($key);
        }
        // Skip if not in registered users sub list
        $checkgroup = $target_head['newsgroup'];
        if (! isset($userdata[$checkgroup])) {
            if (isset($user_config['hide_unsub']) && $user_config['hide_unsub'] == 'hide') {
                continue;
            }
        }
        $nohead = true;
        $result_count = count($value);
        foreach ($value as $new) {
            $target = $this_overboard['msgids'][$new];
            if (! isset($target['msgid'])) {
                $target = get_data_from_msgid($new);
            }
            if ($target['date'] < $oldest) {
                continue;
            }
            $results ++;
            $lone == '';
            $skip = '';
            if ($nohead) {
                if (($style % 2) == 0) {
                    $display .= '<tr class="np_result_line2"><td class="np_result_line2" style="word-wrap:break-word";>';
                } else {
                    $display .= '<tr class="np_result_line1"><td class="np_result_line1" style="word-wrap:break-word";>';
                }
                $display .= '<center>';
                // Check user blocklist
                $block = false;
                foreach ($blocked_user_config as $key => $value) {
                    $blockme = '/' . addslashes($key) . '/';
                    if (preg_match($blockme, $target_head['name'])) {
                        $block = true;
                        break;
                    }
                }
                if ($block) {}
                $url = $thissite . "/article-flat.php?id=" . $target_head['number'] . "&group=" . _rawurlencode($target_head['newsgroup']) . "#" . $target_head['number'];
                $display .= '<p class=np_ob_subject>';
                $display .= '<b><a href="' . $url . '"><span>' . headerDecode($target_head['subject']) . '</span></a></b></p>';
                $display .= '<a href="thread.php?group=' . _rawurlencode($target_head['newsgroup']) . '">' . $target_head['newsgroup'] . '</a>';
                if ($result_count > 1 && isset($target_head['date'])) {
                    $poster = get_poster_name(mb_decode_mimeheader($target_head['name']));
                    $block = false;
                    foreach ($blocked_user_config as $key => $value) {
                        $blockme = '/' . addslashes($key) . '/';
                        if (preg_match($blockme, $target_head['name'])) {
                            $block = true;
                            break;
                        }
                    }
                    if ($block) {
                        $display .= '<br /><br />';
                        $display .= '<p class=np_ob_subject>';
                        $display .= '<b><span>(message #' . $target_head['number'] . ' hidden by your blocklist)</span></a></b>';
                    } else {
                        $display .= '<p class=np_ob_posted_date>Posted: ' . get_date_interval(date("D, j M Y H:i T", $target_head['date'])) . ' by: ' . create_name_link($poster['name'], $poster['from']) . '</p>';
                        if ($CONFIG['article_database'] == '1') {
                            $article = get_db_data_from_msgid($target_head['msgid'], $target_head['newsgroup'], 1);
                            $display .= wordwrap(substr($article['search_snippet'], 0, $snippetlength), ($snippetlength / 2), "<br />\n", true);
                        }
                    }
                    $skip = $target_head['number'];
                }
                $display .= '</center>';
                $style ++;
                $nohead = false;
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
                    $display .= '<br /><br />';
                    $display .= '<p class=np_ob_subject>';
                    $display .= '<b><span>(message #' . $target['number'] . ' hidden by your blocklist)</span></a></b>';
                } else {
                    $groupurl = $thissite . "/thread.php?group=" . _rawurlencode($target['newsgroup']);
                    $url = $thissite . "/article-flat.php?id=" . $target['number'] . "&group=" . _rawurlencode($target['newsgroup']) . "#" . $target['number'];
                    $display .= '<br /><br />';
                    $display .= '<p class=np_ob_subject>';
                    $display .= '<b><a href="' . $url . '"><span>' . headerDecode($target['subject']) . '</span></a></b>';

                    $display .= '</p>';
                    $display .= '<p class=np_ob_body>';
                    $display .= 'by: <b><i><span class="visited">' . create_name_link($poster['name'], $poster['from']) . '</span></i></b>';

                    $display .= '</p>';
                    $display .= '<p class=np_ob_posted_date>Posted: ' . get_date_interval(date("D, j M Y H:i T", $target['date'])) . ' in: <a href="' . $groupurl . '"><span class="visited">' . $target['newsgroup'] . '</span></a></p>';
                    if ($CONFIG['article_database'] == '1') {
                        $article = get_db_data_from_msgid($target['msgid'], $target['newsgroup'], 1);
                        $display .= htmlentities(substr($article['search_snippet'], 0, $snippetlength));
                    }
                    if ($target['date'] < $expireme) {
                        unset($this_overboard['threads'][$target['date']]);
                        unset($this_overboard['threadlink'][$new]);
                        file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Pruning: " . $target['newsgroup'] . ":" . $target['number'], FILE_APPEND);
                    }
                }
            }
        }
        $display .= '</td></tr>';
    }

    $display .= "</table>";
    echo $display;
    return ($results);
}

function display_flat($threads, $oldest)
{
    global $CONFIG, $OVERRIDES, $thissite, $logfile, $spooldir, $config_name, $config_dir, $snippetlength, $maxdisplay, $this_overboard, $article_age;
    $expireme = time() - ($article_age * 86400);
    $display = '<table cellspacing="0" width="100%" class="np_results_table">';
    if (! isset($threads)) {
        $threads = (object) [];
    } else {
        krsort($threads);
    }
    // Get registered user settings
    if (isset($_COOKIE['mail_name'])) {
        if ($userdata = get_user_mail_auth_data($_COOKIE['mail_name'])) {
            $userfile = $spooldir . '/' . strtolower($_COOKIE['mail_name']) . '-articleviews.dat';
            $user_config = unserialize(file_get_contents($config_dir . '/userconfig/' . strtolower($_COOKIE['mail_name']) . '.config'));
        }
        $userfile = $spooldir . '/' . strtolower($_COOKIE['mail_name']) . '-blocked_posters.dat';
        if (file_exists($userfile)) {
            $blocked_user_config = unserialize(file_get_contents($userfile));
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
    }
    $results = 0;
    foreach ($threads as $key => $value) {
        $target = $this_overboard['msgids'][$value];
        $checkgroup = $target['newsgroup'];
        if (! isset($target['msgid'])) {
            $target = get_data_from_msgid($value);
        }
        if (! isset($userdata[$checkgroup])) {
            if (isset($user_config['hide_unsub']) && $user_config['hide_unsub'] == 'hide') {
                continue;
            }
        }
        if ($target['date'] < $oldest) {
            continue;
        }
        if ($target['date'] < $expireme) {
            unset($this_overboard['threads'][$target['date']]);
            unset($this_overboard['threadlink'][$new]);
            file_put_contents($logfile, "\n" . format_log_date() . " " . $config_name . " Pruning: " . $target['newsgroup'] . ":" . $target['number'], FILE_APPEND);
        }
        $poster = get_poster_name(mb_decode_mimeheader($target['name']));
        $groupurl = $thissite . "/thread.php?group=" . _rawurlencode($target['newsgroup']);
        if (($results % 2) == 0) {
            $display .= '<tr class="np_result_line2"><td class="np_result_line2" style="word-wrap:break-word";>';
        } else {
            $display .= '<tr class="np_result_line1"><td class="np_result_line1" style="word-wrap:break-word";>';
        }
        $block = false;
        foreach ($blocked_user_config as $key => $value) {
            $blockme = '/' . addslashes($key) . '/';
            if (preg_match($blockme, $target['name'])) {
                $block = true;
                break;
            }
        }
        if ($block) {
            // $display .= '<br /><br />';
            $display .= '<p class=np_ob_subject>';
            $display .= '<b><span>(message #' . $target['number'] . ' hidden by your blocklist)</span></a></b>';
        } else {
            $url = $thissite . "/article-flat.php?id=" . $target['number'] . "&group=" . _rawurlencode($target['newsgroup']) . "#" . $target['number'];
            $display .= '<p class=np_ob_subject>';
            $display .= '<b><a href="' . $url . '"><span>' . headerDecode($target['subject']) . '</span></a></b>';

            // link for (thread), if possible
            if (isset($this_overboard['threadlink'][$value])) {
                $thread = get_data_from_msgid($this_overboard['threadlink'][$value], $target['newsgroup']);
                if ($thread !== false) {
                    $display .= '<font class="np_ob_group"><a href="article-flat.php?id=' . $thread['number'] . '&group=' . rawurlencode($thread['newsgroup']) . '#' . $thread['number'] . '"> (thread)</a></font>';
                }
            }
            $display .= '</p>';
            $display .= '</p><p class=np_ob_group>';
            $display .= '<a href="' . $groupurl . '"><span class="visited">' . $target['newsgroup'] . '</span></a>';
            $display .= '</p>';
            $display .= '<p class=np_ob_posted_date>Posted: ' . get_date_interval(date("D, j M Y H:i T", $target['date'])) . ' by: ' . create_name_link($poster['name'], $poster['from']) . '</p>';
            if ($CONFIG['article_database'] == '1') {
                $article = get_db_data_from_msgid($target['msgid'], $target['newsgroup'], 1);
                $display .= htmlentities(substr($article['search_snippet'], 0, $snippetlength));
            }
        }
        $results ++;
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
        echo '<table cellpadding="0" cellspacing="0" class="np_buttonbar"><tr>';
        // Refresh button
        echo '<td>';
        echo '<form action="overboard.php">';
        echo '<input type="hidden" name="thisgroup" value="' . $_GET['thisgroup'] . '"/>';
        if (isset($user_time)) {
            echo '<button class="np_button_link" type="submit">overboard</button>';
        } else {
            echo '<button class="np_button_link" type="submit">' . $text_article["refresh"] . '</button>';
        }

        echo '</form>';
        echo '</td>';
        // Article List button
        echo '<td>';
        echo '<form action="' . $file_thread . '">';
        echo '<input type="hidden" name="group" value="' . $grouplist[0] . '"/>';
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
        echo '<td width=100%></td></tr></table>';
    } else {
        echo '<h1 class="np_thread_headline">';
        echo '<a href="' . $file_index . '" target=' . $frame['menu'] . '>' . basename(getcwd()) . '</a> / ';
        echo 'latest messages</h1>';
        echo '<table cellpadding="0" cellspacing="0" class="np_buttonbar"><tr>';
        // Refresh button
        echo '<td>';
        echo '<form action="overboard.php">';
        echo '<button class="np_button_link" type="submit">' . $text_article["refresh"] . '</button>';
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
        echo '<td width=100%></td></tr></table>';
    }
}

function show_overboard_style_toggle()
{
    echo '<form method="post" action="' . $_SERVER['REQUEST_URI'] . '">';
    echo '<p class="np_ob_posted_date" style="text-align: right;">';
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
    echo '</p>';
}

function show_overboard_footer($stats, $results, $iscached)
{
    global $user_time, $rslight_version;
    if (isset($user_time)) {
        $recent = 'new';
    } else {
        $recent = 'recent';
    }
    if ($results == '1') {
        $arts = 'article';
    } else {
        $arts = 'articles';
    }
    echo '</table>';
    echo "<p class=np_ob_tail><b>" . $results . "</b> " . $recent . " " . $arts . " found.</p>\r\n";
    # echo "<center><i>Rocksolid Overboard</i> version ".$version;
    include "tail.inc";
    if ($iscached) {
        echo "<p class=np_ob_tail><font size='1em'>cached copy: " . date("D M j G:i:s T Y", $stats[9]) . "</font></p>\r\n";
    }
}
