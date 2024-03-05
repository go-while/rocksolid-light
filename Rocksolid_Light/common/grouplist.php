<?php
include "config.inc.php";
include "../spoolnews/config.inc.php";
include "../spoolnews/newsportal.php";
$title .= ' - Available Newsgroups';
include "head.inc";

$cache_filename = $spooldir . '/grouplist-cache.txt';
echo '<center>';
echo '<h3>List of Available Newsgroups:</h3>';
// Use cache if new enough
// 14400 = 4 hours
if (filemtime($cache_filename) > (time() - 14400)) {
    echo file_get_contents($cache_filename);
    exit();
}

ob_start();
echo '<table border="1">';
echo '<tr>';
echo '<th>Group</th>';
echo '<th>Description</th>';
echo '<th>Messages</th>';
echo '</tr>';

$menulist = file($config_dir . "menu.conf", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$groups_array = array();
foreach ($menulist as $menu) {
    if (($menu[0] == '#') || trim($menu) == "") {
        continue;
    }
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
ksort($groups_array);

$ns = nntp_open();
foreach ($groups_array as $thisgroup) {
    echo '<tr>';
    echo '<td>';
    $group = explode("group=", $thisgroup);
    if (is_file($spooldir . '/' . urldecode($group[1]) . '-title')) {
        $title = file_get_contents($spooldir . '/' . urldecode($group[1]) . '-title');
        $title = strrchr($title, "\t");
    } else {
        $title = '';
    }
    echo '<font size=5><a href="/' . $thisgroup . '">' . urldecode($group[1]) . "</a></font><br />\r\n";
    echo '</td>';
    echo '<td>' . $title . '</td>';
    echo '<td>';
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
echo '<br />';
include "../spoolnews/tail.inc";
echo '</center>';
echo '</body></html>';
file_put_contents($cache_filename, ob_get_contents());
ob_end_flush();