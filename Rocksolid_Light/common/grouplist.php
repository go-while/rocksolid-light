<?php
include "config.inc.php";
include "../spoolnews/config.inc.php";
include "../spoolnews/newsportal.php";
$title .= ' - Available Newsgroups';
include "head.inc";

if (disable_page_by_user_agent($client_device, "bot", "Grouplist")) {
    echo "<center>Page Disabled</center>";
    include "tail.inc";
    exit();
}

if (file_exists($config_dir . '/cache.inc.php')) {
    include $config_dir . '/cache.inc.php';
}

if (isset($_REQUEST['groupsearch'])) {
    $terms =  trim($_REQUEST['groupsearch']);
} else {
    $terms = '';
}

echo '<div class="grouplist_header_title">List of Available Newsgroups:</div>';

// Use cache if new enough
if (filemtime($grouplist_cache_filename) > (time() - 3600)) {
    // Allow refresh from cron.php
    if (isset($argv[1]) && $argv[1] == '.RELOAD') {
        // Do not use cache, instead rebuild grouplist
        $groups_array = build_group_list();
    } else {
        if ($enable_cache) {
            $cache_time = filemtime($grouplist_cache_filename);
            $memcache_key = $cache_key_prefix . '_grouplist-cache';
            $groups_array = unserialize(cache_get($memcache_key, $memcacheD));
            if ($enable_cache_logging) {
                if (is_array($groups_array)) {
                    file_put_contents($cache_log, "\n" . logging_prefix() . ' (cache hit) ' . $memcache_key, FILE_APPEND);
                } else {
                    file_put_contents($cache_log, "\n" . logging_prefix() . ' (cache miss) ' . $memcache_key, FILE_APPEND);
                }
            }
        } else {
            $groups_array = unserialize(file_get_contents($grouplist_cache_filename));
        }
        if (!is_array($groups_array)) {
            $groups_array = build_group_list();
        }
    }
} else {
    $groups_array = build_group_list();
}

// Search
display_search_tools();

echo '<table class="grouplist_table">';
echo '<tr>';
echo '<th class="grouplist_title_section_name">Section</th>';
echo '<th class="grouplist_title_newsgroup_name">Newsgroup</th>';
echo '<th class="grouplist_title_newsgroup_desc">Description</th>';
echo '<th class="grouplist_title_newsgroup_artnum">Messages</th>';
echo '</tr>';

foreach ($groups_array as $key) {
    
    $section = $key['section'];
    $group = $key['group'];
    $url = $key['url'];
    $title = $key['title'];
    $messages = $key['messages'];

    // Check if this is a search
    if ($terms != '') {
        if (!preg_match("/$terms/i", $group) && !preg_match("/$terms/i", $title))
            continue;
    }

    echo '<tr><td class="grouplist_row_section_name">';
    echo '&nbsp;' . $section;
    echo '</td><td class="grouplist_row_newsgroup_name">';
    echo '<a href="/' . $url . '">' . urldecode($group) . "</a><br>\r\n";
    echo '</td>';
    echo '<td class="grouplist_row_newsgroup_desc">' . $title . '</td>';
    echo '<td class="grouplist_row_newsgroup_artnum">';

    echo "\n" . $messages;

    echo '</td>';
    echo '</tr>';
    
}

echo '</table>';
echo '<br>';
include "../spoolnews/tail.inc";
echo '</body></html>';

function display_search_tools($home = true)
{
    global $CONFIG, $terms;
    echo '<form name="form1" method="get" action="grouplist.php">';
    echo '<table class="grouplist_header_search">';
    echo '<tr>';
    echo '<td class="grouplist_header_search_prompt">Search Group Names and Descriptions: ';
    echo '<input name="groupsearch" type="text" id="groupsearch" value="' . $terms . '" size="40" maxlength="120">&nbsp;';
    echo '<input type="submit" name="Submit" value="Search">';

    echo '&nbsp;<a href="grouplist.php">Show All Groups</a>';

    echo '</td></tr></table>';
    echo '</form>';
}

function build_group_list()
{
    global $config_dir, $spooldir, $cache_log, $grouplist_cache_filename;

    if (file_exists($config_dir . '/cache.inc.php')) {
        include $config_dir . '/cache.inc.php';
    }

    $ns = nntp_open();
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
                $groups_array[$ok_group[0]]['url'] = $menuitem[0] . '/thread.php?group=' . urlencode($ok_group[0]);

                // Get group title
                if (is_file($spooldir . '/' . urldecode($ok_group[0]) . '-title')) {
                    $title = file_get_contents($spooldir . '/' . urldecode($ok_group[0]) . '-title');
                    $title = strrchr($title, "\t");
                } else {
                    $title = '';
                }
                $groups_array[$ok_group[0]]['title'] = $title;
                $groups_array[$ok_group[0]]['section'] = $menuitem[0];
                $groups_array[$ok_group[0]]['group'] = $ok_group[0];

                // Get group message qty
                fputs($ns, "group " . urldecode($ok_group[0]) . "\r\n");
                $response = line_read($ns);
                $messages = explode(' ', $response);
                if (strcmp(substr($response, 0, 3), "211") == 0) {
                    $groups_array[$ok_group[0]]['messages'] = $messages[1];
                } else {
                    $groups_array[$ok_group[0]]['messages'] = 0;
                }

            }
        }
    }
    nntp_close($ns);

    if ($enable_cache) {
        $memcache_key = $cache_key_prefix . '_grouplist-cache';
        $nicole = cache_delete($memcache_key, $memcacheD);
        cache_add($memcache_key, serialize($groups_array), $cache_ttl, $memcacheD);
        if ($enable_cache_logging) {
            if ($nicole) {
                file_put_contents($cache_log, "\n" . logging_prefix() . " (cache update) $memcache_key", FILE_APPEND);
            } else {
                file_put_contents($cache_log, "\n" . logging_prefix() . " (cache write) $memcache_key", FILE_APPEND);
            }
        }
    }

    file_put_contents($grouplist_cache_filename, serialize($groups_array));
    return $groups_array;
}
