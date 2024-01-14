<?php
include "config.inc.php";
$title .= ' - Available Newsgroups';
include "head.inc";

$cache_filename = $spooldir . '/grouplist-cache.txt';

echo '<h3>List of Available Newsgroups:</h3>';
// Use cache if new enough
if (filemtime($cache_filename) > (time() - 3600)) {
    echo file_get_contents($cache_filename);
    exit();
}

ob_start();
echo '<table border="1">';

$menulist = file($config_dir . "menu.conf", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($menulist as $menu) {
    $groups_array = array();
    if (($menu[0] == '#') || trim($menu) == "") {
        continue;
    }
    $menuitem = explode(':', $menu);
    if($menuitem[0] == 'spoolnews') {
        continue;
    }
    if ($menuitem[2] == '1') {
        $in_gl = file($config_dir . $menuitem[0] . "/groups.txt");
        foreach ($in_gl as $ok_group) {
            if (($ok_group[0] == ':') || (trim($ok_group) == "")) {
                continue;
            }
            $ok_group = preg_split("/( |\t)/", trim($ok_group), 2);
            $groups_array[] = $menuitem[0].'/thread.php?group='.urlencode($ok_group[0]);
        }
    }
    echo '<br /><font size=6>&nbsp;&nbsp;Section: <a href="/'.$menuitem[0].'">'.$menuitem[0].'</font><br />';
    foreach($groups_array as $thisgroup) {
        $group = explode("group=", $thisgroup);
        echo '<font size=5><a href="/'.$thisgroup. '">'.urldecode($group[1])."</a></font><br />\r\n";
    }
}

echo '</h1></table>';
//echo ob_get_contents();
file_put_contents($cache_filename, ob_get_contents());
ob_end_flush();
echo '</body></html>';
?>