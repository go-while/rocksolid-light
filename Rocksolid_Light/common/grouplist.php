<?php
include "config.inc.php";
include "../spoolnews/config.inc.php";
include "../spoolnews/newsportal.php";
$title .= ' - Available Newsgroups';
include "head.inc";

echo '<div class="grouplist_header_title">List of Available Newsgroups:</div>';

// Use cache if new enough
if (filemtime($grouplist_cache_filename) > (time() - $grouplist_cache_time)) {
    // Allow refresh from cron.php
    if (isset($argv[1]) && $argv[1] == '.RELOAD') {
        // Do not use cache, instead rebuild grouplist
    } else {
        echo file_get_contents($grouplist_cache_filename);
        exit();
    }
}

ob_start();
echo '<table class="grouplist_table">';
echo '<tr>';
echo '<th class="grouplist_title_section_name">Section</th>';
echo '<th class="grouplist_title_newsgroup_name">Newsgroup</th>';
echo '<th class="grouplist_title_newsgroup_desc">Description</th>';
echo '<th class="grouplist_title_newsgroup_artnum">Messages</th>';
echo '</tr>';

$menulist = get_section_menu_array();
$groups_array = array();
foreach ($menulist as $menu) {
    $menuitem = explode(':', $menu);
    if ($menuitem[0] == 'spoolnews') {
        continue;
    }
    if ($menuitem[2] == '1') {
        $in_gl = file($config_dir . $menuitem[0] . "/groups.txt");
        foreach ($in_gl as $ok_group) {
            if (($ok_group[0] == ':') || (trim($ok_group) == "")) {
                continue;
            }
            $ok_group = preg_split("/[ \t]/", trim($ok_group), 2);
            $groups_array[$ok_group[0]] = $menuitem[0] . '/thread.php?group=' . urlencode($ok_group[0]);
        }
    }
}

$ns = nntp_open();
foreach ($groups_array as $thisgroup) {
    $section = explode("/", $thisgroup);
    $group = explode("group=", $thisgroup);
    if (is_file($spooldir . '/' . urldecode($group[1]) . '-title')) {
        $title = file_get_contents($spooldir . '/' . urldecode($group[1]) . '-title');
        $title = strrchr($title, "\t");
    } else {
        $title = '';
    }
    echo '<tr><td class="grouplist_row_section_name">';
    echo '&nbsp;' . $section[0];
    echo '</td><td class="grouplist_row_newsgroup_name">';
    echo '<a href="/' . $thisgroup . '">' . urldecode($group[1]) . "</a><br>\r\n";
    echo '</td>';
    echo '<td class="grouplist_row_newsgroup_desc">' . $title . '</td>';
    echo '<td class="grouplist_row_newsgroup_artnum">';
    # Check if group exists. Open it if it does
    fputs($ns, "group " . urldecode($group[1]) . "\r\n");
    $response = line_read($ns);
    $messages = explode(' ', $response);
    if (strcmp(substr($response, 0, 3), "211") == 0) {
        echo "\n" . $messages[1];
    }
    echo '</td>';
    echo '</tr>';
}
nntp_close($ns);
echo '</table>';
echo '<br>';
include "../spoolnews/tail.inc";
echo '</body></html>';
file_put_contents($grouplist_cache_filename, ob_get_contents());
ob_end_flush();
