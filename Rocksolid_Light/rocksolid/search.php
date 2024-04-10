<?php
session_cache_limiter('public');
session_start();

header("Expires: " . gmdate("D, d M Y H:i:s", time() + (120)) . " GMT");
header("Cache-Control: max-age=120");
header("Pragma: cache");

include "config.inc.php";
include "newsportal.php";

$snippet_size = 100;

if (isset($_REQUEST['group'])) {
    $search_group = urldecode($_REQUEST['group']);
} else {
    $search_group = null;
}

if (isset($_REQUEST['data']) && $_REQUEST['data'] == '') {
    unset($_REQUEST['data']);
}

if ((! isset($_POST['key']) || ! password_verify($CONFIG['thissitekey'], $_POST['key'])) || ((strlen(trim($_REQUEST['terms'])) < 2) && ! $_REQUEST['data'])) {
    include "head.inc";

    if (disable_page_by_user_agent($client_device, "bot", "Search")) {
        echo "<center>Page Disabled</center>";
        include "tail.inc";
        exit();
    }

    echo '<h1 class="np_thread_headline">';
    echo '<a href="' . $file_index . '" target=' . $frame['menu'] . '>' . basename(getcwd()) . '</a> / ';
    if ($search_group) {
        echo '<a href="' . $file_thread . '?group=' . urlencode($search_group) . '" target=' . $frame['menu'] . '>' . $search_group . '</a> / ';
    }
    echo 'search</h1>';
    echo '<table cellpadding="0" cellspacing="0" class="np_buttonbar"><tr>';
    if (isset($search_group)) {
        $searching = $search_group;
    } else {
        $searching = $config_name;
    }
    echo '<body>';
    echo '<table width=100% border="0" align="center" cellpadding="0" cellspacing="1">';
    echo '<tr>';
    echo '<form name="form1" method="post" action="search.php">';
    echo '<td>';
    echo '<table width="100%" align="center" border="0" cellpadding="3" cellspacing="1">';
    echo '<tr>';
    echo '<td colspan="3">Searching <strong>' . $searching . '</strong></td>';
    echo '</tr>';
    echo '<tr>';
    if (! isset($_REQUEST['data'])) {
        echo '<td>Search Terms:&nbsp';
    } else {
        echo '<td>Search Poster:&nbsp';
    }
    if (isset($_GET['terms'])) {
        echo '<input name="terms" type="text" id="terms" value="' . $_GET['terms'] . '"></td>';
    } else {
        echo '<input name="terms" type="text" id="terms"></td>';
    }
    echo '</tr><tr><td>';
    if (isset($_GET['searchpoint']) && $_GET['searchpoint'] == 'Poster') {
        if ($CONFIG['article_database'] == '1') {
            echo '<input type="radio" name="searchpoint" value="body"/>Body&nbsp;';
        }
        echo '<input type="radio" name="searchpoint" value="subject"/>Subject&nbsp;';
        echo '<input type="radio" name="searchpoint" value="name" checked="checked"/>Poster&nbsp;';
        echo '<input type="radio" name="searchpoint" value="msgid"/>Message-ID';
    } else {
        if ($CONFIG['article_database'] == '1') {
            echo '&nbsp;<input type="radio" name="searchpoint" value="body" checked="checked"/>Body&nbsp;';
        }
        echo '<input type="radio" name="searchpoint" value="subject"/>Subject&nbsp;';
        echo '<input type="radio" name="searchpoint" value="name"/>Poster&nbsp;';
        echo '<input type="radio" name="searchpoint" value="msgid"/>Message-ID';
    }
    echo '</td></tr>';
    echo '<tr>';
    echo '<td><input name="command" type="hidden" id="command" value="Search" readonly="readonly"></td>';
    if (isset($search_group)) {
        echo '<input type="hidden" name="group" value="' . urlencode($search_group) . '">';
    }
    echo '<input type="hidden" name="key" value="' . password_hash($CONFIG['thissitekey'], PASSWORD_DEFAULT) . '">';
    if (isset($_GET['data'])) {
        echo '<input type="hidden" name="data" value="' . $_GET['data'] . '">';
    }
    echo '</tr><tr>';
    echo '<td><input type="submit" name="Submit" value="Search"></td>';
    echo '</tr></table></td></form></tr></table>';

    // Block poster
    if (isset($_COOKIE['mail_name'])) {
        if (isset($_REQUEST['data'])) {
            echo '<br><table width=100% border="0" align="center" cellpadding="0" cellspacing="1">';
            echo '<tr>';
            echo '<td colspan="3">Hide posts by <strong>' . $_GET['terms'] . '</strong></td>';
            echo '</tr>';
            echo '<tr>';
            echo '<form name="blockform" method="post" action="search.php">';
            echo '<td>';
            echo '<td><input name="command" type="hidden" id="command" value="Search" readonly="readonly"></td>';
            echo '<input type="hidden" name="key" value="' . password_hash($CONFIG['thissitekey'], PASSWORD_DEFAULT) . '">';
            if (isset($_GET['data'])) {
                echo '<input type="hidden" name="data" value="' . $_GET['data'] . '">';
            }
            echo '<input type="hidden" name="username" value="' . $_COOKIE['mail_name'] . '">';
            echo '</tr>';
            // Password confirmation
            echo '<tr>';
            echo '<td style="word-wrap:break-word";>Enter your password: ';
            echo '<input name="password" type="password" id="password" maxlength="40"></td>';
            echo '<input name="block_poster" type="hidden" id="block_poster" value="' . $_GET['terms'] . '"></td>';
            echo '</tr>';
            echo '<td><input type="submit" name="Submit" value="Add poster to my block list"></td>';
            echo '</tr></table></td></form>';
        }
    }
    // END Block poster

    exit(0);
}

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

# Maximum number of articles to show
$maxdisplay = 1000;

$thissite = '.';

$groupconfig = $config_path . "/groups.txt";

$title .= ' - search results for: ' . $_POST['terms'];
include "head.inc";

// Handle Block poster
if (isset($_POST['block_poster'])) {
    if ((password_verify($_POST['username'] . $keys[0] . get_user_config($_POST['username'], 'encryptionkey'), $_COOKIE['mail_auth'])) || (password_verify($_POST['username'] . $keys[1] . get_user_config($_POST['username'], 'encryptionkey'), $_COOKIE['mail_auth']))) {
        $logged_in = true;
    } else {
        if (check_bbs_auth($_POST['username'], $_POST['password'])) {
            if ($ip_pass) {
                $_SESSION['pass'] = true;
            }
            $authkey = password_hash($_POST['username'] . $keys[0] . get_user_config($_POST['username'], 'encryptionkey'), PASSWORD_DEFAULT);
            $pkey = hash('crc32', get_user_config($_POST['username'], 'encryptionkey'));
            set_user_config(strtolower($_POST['username']), "pkey", $pkey);
            $logged_in = true;
        }
    }
    if ($logged_in == true) {
        if ($userdata = get_user_mail_auth_data($_COOKIE['mail_name'])) {
            $blockfile = $spooldir . '/' . strtolower($_COOKIE['mail_name']) . '-blocked_posters.dat';
            if (file_exists($blockfile)) {
                $blocked_user_config = unserialize(file_get_contents($blockfile));
            } else {
                $blocked_user_config = array();
            }
            $blocked_user_config[base64_decode(urldecode($_REQUEST['data']))] = $_POST['block_poster'];
            file_put_contents($blockfile, serialize($blocked_user_config));
        }
        echo "<center><b>'".$_POST['block_poster']."'</b> successfully added to your blocklist";
        echo '<br>You may edit your blocklist on your <a href="/spoolnews/user.php?command=Configuration">Configuration Page</a></center>';
    } else {
        echo '<center>Password Incorrect.<br>Click Back to try again</center>';
    }
    exit(0);
}

ob_start();
if (isset($search_group)) {
    echo '<h1 class="np_thread_headline">';
    echo '<a href="' . $file_index . '" target=' . $frame['menu'] . '>' . basename(getcwd()) . '</a> / ';
    echo '<a href="' . $file_thread . '?group=' . urlencode($search_group) . '" target=' . $frame['menu'] . '>' . $search_group . '</a> / ';
    echo 'search results for: ' . $_POST['terms'] . '</h1>';
    // Newsgroups button (hidden)
    echo '<td>';
    echo '<form action="' . $file_index . '">';
    echo '<button class="np_button_hidden" type="submit">' . $text_thread["button_grouplist"] . '</button>';
    echo '</form>';
    echo '</td>';
    echo '</tr></table>';
} else {
    echo '<h1 class="np_thread_headline">';
    echo '<a href="' . $file_index . '" target=' . $frame['menu'] . '>' . basename(getcwd()) . '</a> / ';
    echo 'search results for: ' . $_POST['terms'] . '</h1>';
    echo '<table cellpadding="0" cellspacing="0" width="100%" class="np_buttonbar"><tr>';
    // Newsgroups button (hidden)
    echo '<td>';
    echo '<form action="' . $file_index . '">';
    echo '<button class="np_button_hidden" type="submit">' . $text_thread["button_grouplist"] . '</button>';
    echo '</form>';
    echo '</td>';
    echo '</tr></table>';
}
echo '<table cellspacing="0" width="100%" class="np_results_table">';

# Iterate through groups

$results = 0;
if (isset($_COOKIE['tzo'])) {
    $offset = $_COOKIE['tzo'];
} else {
    $offset = $CONFIG['timezone'];
}
$overview = array();
if ($_POST['searchpoint'] == 'body') {
    $overview = get_body_search($search_group, $_POST['terms']);
} else {
    if (isset($_REQUEST['data'])) {
        $overview = get_header_search($search_group, base64_decode(urldecode($_REQUEST['data'])));
    } else {
        $overview = get_header_search($search_group, $_POST['terms']);
    }
}
foreach ($overview as $overviewline) {
    /* Find section for links */
    $menulist = file($config_dir . "menu.conf", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($menulist as $menu) {
        if ($menu[0] == '#') {
            continue;
        }
        $menuitem = explode(':', $menu);
        $glfp = fopen($config_dir . $menuitem[0] . "/groups.txt", 'r');
        $section = "";
        while ($gl = fgets($glfp)) {
            $group_name = preg_split("/( |\t)/", $gl, 2);
            if (stripos(trim($overviewline['newsgroup']), trim($group_name[0])) !== false) {
                $section = $menuitem[0];
                break 2;
            }
        }
    }

    fclose($glfp);
    # Generate link
    $url = "../" . $section . "/article-flat.php?id=" . $overviewline['number'] . "&group=" . urlencode($overviewline['newsgroup']) . "#" . $overviewline['number'];
    $groupurl = "../" . $section . "/thread.php?group=" . urlencode($overviewline['newsgroup']);
    $fromoutput = explode("<", html_entity_decode($overviewline['name']));

    // Use local timezone if possible
    $ts = new DateTime(date($text_header["date_format"], $overviewline['date']), new DateTimeZone('UTC'));
    $ts->add(DateInterval::createFromDateString($offset . ' minutes'));

    if ($offset != 0) {
        $newdate = $ts->format('D, j M Y H:i');
    } else {
        $newdate = $ts->format($text_header["date_format"]);
    }

    unset($ts);

    $fromline = address_decode(headerDecode($overviewline['name']), "nowhere");

    if (! isset($fromline[0]["personal"])) {
        $lastname = $fromline[0]["mailbox"];
        ;
    } else {
        $lastname = $fromline[0]["personal"];
    }

    if (($results % 2) != 0) {
        echo '<tr class="np_result_line1"><td class="np_result_line1" style="word-wrap:break-word";>';
    } else {
        echo '<tr class="np_result_line2"><td class="np_result_line2" style="word-wrap:break-word";>';
    }

    echo '<p class=np_ob_subject>';
    echo '<b><a href="' . $url . '">' . htmlspecialchars(headerDecode($overviewline['subject'])) . "</a></b>\r\n";
    echo '</p><p class=np_ob_group>';
    echo '<a href="' . $groupurl . '">' . $overviewline['newsgroup'] . '</a>';
    echo '</p>';

    $fromline = address_decode($overviewline['name'], "nowhere");
    if (! isset($fromline[0]["host"]))
        $fromline[0]["host"] = "";
    $name_from = $fromline[0]["mailbox"] . "@" . $fromline[0]["host"];
    $name_username = $fromline[0]["mailbox"];
    if (! isset($fromline[0]["personal"])) {
        $poster_name = $fromline[0]["mailbox"];
    } else {
        $poster_name = $fromline[0]["personal"];
    }
    if (trim($poster_name) == '') {
        $fromoutput = explode("<", html_entity_decode($c->name));
        if (strlen($fromoutput[0]) < 1) {
            $poster_name = $fromoutput[1];
        } else {
            $poster_name = $fromoutput[0];
        }
    }
    $poster_name = trim(mb_decode_mimeheader($poster_name), " \n\r\t\v\0\"");
    echo '<p class=np_ob_posted_date>Posted: ' . $newdate . ' by: ' . create_name_link($poster_name, $name_from) . '</p>';
    if ($_POST['searchpoint'] == 'body') {
        $snip = strip_tags(quoted_printable_decode($overviewline['snippet']), '<strong><font><i>');
    } else {
        $snip = strip_tags(quoted_printable_decode($overviewline['search_snippet']), '<strong><font><i>');
        $snip = substr($snip, 0, $snippet_size);
    }
    echo $snip;
    echo '</td></tr>';
    if ($results ++ > ($maxdisplay - 2))
        break;
}

echo '</table>';
echo "<p class=np_ob_tail><b>" . $results . "</b> matching articles found.</p>\r\n";
# echo "<center><i>Rocksolid Overboard</i> version ".$version;
include "tail.inc";

$thispage = ob_get_contents();

ob_end_clean();

echo $thispage;

function get_body_search($group, $terms)
{
    GLOBAL $CONFIG, $config_name, $spooldir, $snippet_size;
    $terms = preg_replace("/'/", ' ', $terms);
    $terms = trim($terms);
    if ($terms[0] !== '"' || substr($terms, - 1) !== '"') {
        $terms = preg_replace('/"/', '', $terms);
        $terms = preg_replace("/\ /", '" "', $terms);
        $terms = preg_replace('/"NEAR"/', 'NEAR', $terms);
        $terms = preg_replace('/"AND"/', 'AND', $terms);
        $terms = preg_replace('/"OR"/', 'OR', $terms);
        $terms = preg_replace('/"NOT"/', 'NOT', $terms);
        $terms = '"' . $terms . '"';
    }
    if ($group != '') {
        $grouplist[0] = $group;
    } else {
        $local_groupfile = $spooldir . "/" . $config_name . "/local_groups.txt";
        $grouplist = file($local_groupfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }
    foreach ($grouplist as $thisgroup) {
        $name = explode(':', $thisgroup);
        $group = $name[0];
        $database = $spooldir . '/' . $group . '-articles.db3';
        if (! is_file($database)) {
            continue;
        }
        $dbh = article_db_open($database);
        $stmt = $dbh->prepare("SELECT snippet(search_fts, 6, '<strong><font class=search_result><i>', '</i></font></strong>', '...', $snippet_size) as snippet, newsgroup, number, name, date, subject, rank FROM search_fts WHERE search_fts MATCH 'search_snippet:$terms' ORDER BY rank");
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $overview[] = $row;
        }
        $dbh = null;
    }
    // do not perform a usort of an empty search result
    if ($overview != null) {
        usort($overview, function ($a, $b) {
            return $a['rank'] <=> $b['rank'];
        });
    }
    return $overview;
}

function get_header_search($group, $terms)
{
    GLOBAL $CONFIG, $config_name, $spooldir, $snippet_size;
    $terms = preg_replace('/\%/', '\%', $terms);
    $searchterms = "%" . $terms . "%";
    
    if (isset($group)) {
        $grouplist[0] = $group;
    } else {
        $local_groupfile = $spooldir . "/" . $config_name . "/local_groups.txt";
        $grouplist = file($local_groupfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    # Prepare search database
    $database = $spooldir . '/articles-overview.db3';
    $table = 'overview';
    $dbh = overview_db_open($database, $table);
    $overview = array();

    foreach ($grouplist as $thisgroup) {
        $name = explode(':', $thisgroup);
        $group = $name[0];
        $article_database = $spooldir . '/' . $group . '-articles.db3';
        if (! is_file($article_database)) {
            continue;
        }
        $article_dbh = article_db_open($article_database);
        $article_stmt = $article_dbh->prepare("SELECT * FROM articles WHERE number=:number");
        if (is_multibyte($_POST['terms'])) {
            $stmt = $dbh->prepare("SELECT * FROM $table WHERE newsgroup=:group");
            $stmt->bindParam(':group', $group);
            $stmt->execute();
            while ($found = $stmt->fetch()) {
                if (stripos(mb_decode_mimeheader($found[$_POST['searchpoint']]), $_POST['terms']) !== false) {
                    $article_stmt->bindParam(':number', $found['number']);
                    $article_stmt->execute();
                    $found_snip = $article_stmt->fetch();
                    $found['search_snippet'] = $found_snip['search_snippet'];
                    $found['sort_date'] = $found_snip['date'];
                    $overview[] = $found;
                }
            }
        } else {
            $stmt = $dbh->prepare("SELECT * FROM $table WHERE newsgroup=:group AND " . $_POST['searchpoint'] . " like :terms ESCAPE '\' ORDER BY date DESC");
            $stmt->bindParam(':group', $group);
            $stmt->bindParam(':terms', $searchterms);
            $check = "/([a-z]|[0-9]|\!|#|\$|\%|\&|\'|\*|\+|\-|\/|\=|\?|\^|\_|\"|\`|\{|\||\}|\~|\;)" . trim($searchterms, '\%') . "/i";
            $stmt->execute();
            while ($found = $stmt->fetch()) {
                if (isset($_REQUEST['data']) && ($_REQUEST['searchpoint'] == 'name')) {
                    if (preg_match($check, $found['name'])) {
                        continue;
                    }
                }
                $article_stmt->bindParam(':number', $found['number']);
                $article_stmt->execute();
                $found_snip = $article_stmt->fetch();
                $found['search_snippet'] = $found_snip['search_snippet'];
                $found['sort_date'] = $found_snip['date'];
                $overview[] = $found;
            }
        }
        $article_dbh = null;
    }
    $dbh = null;
    usort($overview, function ($b, $a) {
        return $a['sort_date'] <=> $b['sort_date'];
    });
    return $overview;
}

function highlightStr($haystack, $needle)
{
    preg_match_all("/$needle+/i", $haystack, $matches);
    if (is_array($matches[0]) && count($matches[0]) >= 1) {
        foreach ($matches[0] as $match) {
            $haystack = str_replace($match, '<b>' . $match . '</b>', $haystack);
        }
    }
    return $haystack;
}
?>
</body>
</html>
