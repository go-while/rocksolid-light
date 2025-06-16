<?php
session_cache_limiter('public');

header("Expires: " . gmdate("D, d M Y H:i:s", time() + (120)) . " GMT");
header("Cache-Control: max-age=120");
header("Pragma: cache");

include "lib/config.inc.php";
include "$file_newsportal";
require_once(__DIR__ . '/lib/security.inc.php');

// Add security headers
add_security_headers();

$logfile = $logdir . '/search.log';

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
    include "lib/head.inc";
    if (disable_page_by_user_agent($client_device, "bot", "Search")) {
        echo "<center>Page Disabled</center>";
        include "lib/tail.inc";
        exit();
    }

    // Display search tools
    display_search_tools();

    // Block poster
    if (isset($_COOKIE['mail_name'])) {
        if (isset($_REQUEST['data'])) {
            echo '<br>';
            echo '<form name="blockform" method="post" action="search.php">';
            echo '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">';
            echo '<table width=100% border="0" class="search_hide_posts">';
            echo '<tr>';
            echo '<td>Hide posts by <strong>' . secure_input($_GET['terms'], 'html') . '</strong></td>';
            echo '</tr>';
            echo '<input name="command" type="hidden" id="command" value="Search">';
            echo '<input type="hidden" name="key" value="' . password_hash($CONFIG['thissitekey'], PASSWORD_DEFAULT) . '">';
            if (isset($_GET['data'])) {
                echo '<input type="hidden" name="data" value="' . secure_input($_GET['data'], 'html') . '">';
            }
            echo '<input type="hidden" name="username" value="' . secure_input($_COOKIE['mail_name'], 'html') . '">';
            // Password confirmation
            echo '<tr>';
            echo '<td style="word-wrap:break-word";>Enter your password: ';
            echo '<input name="password" type="password" id="password" maxlength="40"></td>';
            echo '<input name="block_poster" type="hidden" id="block_poster" value="' . secure_input($_GET['terms'], 'html') . '"></td>';
            echo '</tr>';
            echo '<td><input type="submit" name="Submit" value="Add poster to my block list"></td>';
            echo '</tr></table></td></form>';
        }
    }
    // END Block poster
    exit(0);
} else {
    // Determine default view style
    if (isset($_COOKIE['mail_name'])) {
        if ($user_searchsort = get_config_file_value($config_dir . '/userconfig/' . strtolower($_COOKIE['mail_name']), 'searchsort')) {
            $_SESSION['searchsort'] = $user_searchsort;
        }
    }
    if (isset($_POST['searchsort'])) {
        $_SESSION['searchsort'] = $_POST['searchsort'];
    }
    if (! isset($_SESSION['searchsort'])) {
        if (isset($OVERRIDES['search_default_sort'])) {
            $_SESSION['searchsort'] = $OVERRIDES['search_default_sort'];
        } else {
            $_SESSION['searchsort'] = 'relevance';
        }
    }
    if (isset($_COOKIE['mail_name'])) {
        save_config_value($config_dir . '/userconfig/' . strtolower($_COOKIE['mail_name']), 'searchsort', $_SESSION['searchsort'], true);
    }
}

if (isset($frames_on) && $frames_on === true) {
?>
    <script>
        var contentURL = window.location.pathname + window.location.search + window.location.hash;
        if (window.self !== window.top) {
            /* Great! now we move along */
        } else {
            window.location.href = '../index.php?content=' + encodeURIComponent(contentURL);
        }
        top.history.replaceState({}, 'Title', 'index.php?content=' + encodeURIComponent(contentURL));
    </script>

<?php
}

# Maximum number of articles to show
$maxdisplay = 1000;

$thissite = '.';

$groupconfig = $config_path . "/groups.txt";

$title .= ' - search results for: ' . $_POST['terms'];
include "lib/head.inc";

// Handle Block poster
$post_username = trim(strtolower($_POST['username']));
if (isset($_POST['block_poster'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        echo '<div class="error">Security Error: Invalid form submission. Please try again.</div>';
        exit();
    }

    if ((password_verify($post_username . $keys[0] . get_user_config($post_username, 'encryptionkey'), $_COOKIE['mail_auth'])) || (password_verify($post_username . $keys[1] . get_user_config($post_username, 'encryptionkey'), $_COOKIE['mail_auth']))) {
        $logged_in = true;
    } else {
        if (check_bbs_auth($post_username, $_POST['password'])) {
            if ($ip_pass) {
                $_SESSION['pass'] = true;
            }
            set_user_logged_in_cookies($post_username, $keys);
            $logged_in = true;
        }
    }
    if ($logged_in == true) {
        if ($userdata = get_user_mail_auth_data($_COOKIE['mail_name'])) {
            $blockfile = $spooldir . '/' . strtolower($_COOKIE['mail_name']) . '-blocked_posters.dat';
            if (file_exists($blockfile)) {
                try {
                    $blocked_user_config = secure_unserialize(file_get_contents($blockfile));
                    if (!is_array($blocked_user_config)) {
                        $blocked_user_config = array();
                    }
                } catch (Exception $e) {
                    $blocked_user_config = array();
                }
            } else {
                $blocked_user_config = array();
            }
            $blocked_user_config[base64_decode(urldecode($_REQUEST['data']))] = $_POST['block_poster'];
            file_put_contents($blockfile, serialize($blocked_user_config));
        }
        echo "<center><b>'" . secure_input($_POST['block_poster'], 'html') . "'</b> successfully added to your blocklist";
        echo '<br>You may edit your blocklist on your <a href="/spoolnews/user.php?command=Configuration">Configuration Page</a></center>';
        echo '<center><br><i>(Articles may still appear on Cached Pages)</i></center>';
    } else {
        echo '<center>Password Incorrect.<br>Click Back to try again</center>';
    }
    exit(0);
}

display_search_tools();

// Display search suggestions
if (isset($_REQUEST['searchpoint']) && $_REQUEST['searchpoint'] == 'body' && isset($_REQUEST['terms']) && trim($_REQUEST['terms']) != '') {
    $suggestion = get_suggestion($_REQUEST['terms']);
    if ($suggestion != false) {
        echo '<form method="post" action="search.php" class="search_suggestion_inline">';

        echo '<input type="hidden" name="group" value="' . secure_input($_REQUEST['group'], 'html') . '">';
        echo '<input type="hidden" name="terms" value="' . $suggestion . '">';
        echo '<input type="hidden" name="key" value="' . secure_input($_REQUEST['key'], 'html') . '">';
        echo '<input type="hidden" name="command" value="' . secure_input($_REQUEST['command'], 'html') . '">';
        echo '<input type="hidden" name="searchpoint" value="' . secure_input($_REQUEST['searchpoint'], 'html') . '">';

        echo '<button type="submit" name="submit_param" value="submit_value" class="search_suggestion_link-button">';
        echo 'Did you mean: <b><i>' . htmlentities($suggestion) . '</i></b>';
        echo '</button>';
        echo '</form>';
        file_put_contents($logfile, "\n" . logging_prefix() . " SEARCH: " . $_REQUEST['terms'] . " SUGGESTING: " . $suggestion, FILE_APPEND);
    } else {
        file_put_contents($logfile, "\n" . logging_prefix() . " SEARCH: " . $_REQUEST['terms'], FILE_APPEND);
    }
}

echo "<hr>";

ob_start();
if (isset($search_group)) {
    echo '<h1 class="np_thread_headline">';
    echo '<a href="' . $file_index . '">' . basename(getcwd()) . '</a> / ';
    echo '<a href="' . $file_thread . '&group=' . urlencode($search_group) . '">' . $search_group . '</a> / ';
    echo 'search results for: ' . secure_input($_POST['terms'], 'html') . '</h1>';
} else {
    echo '<h1 class="np_thread_headline">';
    echo '<a href="' . $file_index . '">' . basename(getcwd()) . '</a> / ';
    echo 'search results for: ' . secure_input($_POST['terms'], 'html') . '</h1>';
}
echo '<table class="np_buttonbar"><tr>';
echo '<td class="search_sort_toggle">';

echo '<div style="float:right;">';
if ($_REQUEST['searchpoint'] == 'body') {
    show_search_sort_toggle();
}
echo '</div>';
echo '</td>';
echo '</tr></table>';
echo '<table class="search_results_table">';

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
    $menulist = get_section_menu_array();
    foreach ($menulist as $menu) {
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
    $url = "../" . $section . "/?page=article-flat&id=" . $overviewline['number'] . "&group=" . urlencode($overviewline['newsgroup']) . "#" . $overviewline['number'];
    $groupurl = "../" . $section . "/thread.php&group=" . urlencode($overviewline['newsgroup']);
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
        $lastname = $fromline[0]["mailbox"];;
    } else {
        $lastname = $fromline[0]["personal"];
    }

    if (($results % 2) != 0) {
        echo '<tr class="search_result_line1"><td class="search_result_line1">';
    } else {
        echo '<tr class="search_result_line2"><td class="search_result_line2">';
    }

    echo '<p class="search_subject">';
    echo '<b><a href="' . $url . '">' . htmlspecialchars(headerDecode($overviewline['subject'])) . "</a></b>\r\n";
    echo '</p><p class="search_group">';
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
    echo '<p class="search_posted_date">Posted: ' . $newdate . ' by: ' . create_name_link($poster_name, $name_from) . '</p>';
    if ($_POST['searchpoint'] == 'body') {
        $snip = strip_tags(quoted_printable_decode($overviewline['snippet']), '<strong><font><i>');
    } else {
        $snip = strip_tags(quoted_printable_decode($overviewline['search_snippet']), '<strong><font><i>');
        $snip = substr($snip, 0, $snippet_size);
    }
    echo '<div class="search_snippet">';
    echo $snip;
    echo '</div>';
    echo '</td></tr>';
    if ($results++ > ($maxdisplay - 2))
        break;
}

echo '</table>';
echo "<p class=np_ob_tail><b>" . $results . "</b> matching articles found.</p>\r\n";
# echo "<center><i>Rocksolid Overboard</i> version ".$version;
include "lib/tail.inc";

$thispage = ob_get_contents();

ob_end_clean();

echo $thispage;

function get_body_search($group, $terms)
{
    global $CONFIG, $config_name, $config_dir, $debug_log, $spooldir, $snippet_size;
    $terms = preg_replace("/'/", ' ', urldecode($terms));
    $terms = trim($terms);
    if ($terms[0] !== '"' || substr($terms, -1) !== '"') {
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
        $local_groupfile = $config_dir . "/" . $config_name . "/groups.txt";
        $grouplist = file($local_groupfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }
    foreach ($grouplist as $thisgroup) {
        $name = preg_split("/( |\t)/", $thisgroup, 2);
        $group = $name[0];
        $database = $spooldir . '/' . $group . '-articles.db3';
        if (! is_file($database)) {
            continue;
        }
        $dbh = article_db_open($database);
        if (!$dbh) {
            file_put_contents($debug_log, "\n" . format_log_date() . " " . $config_name . " Failed to open database: " . $database . " in search.php", FILE_APPEND);
            continue;
        }
        $stmt = $dbh->prepare("SELECT snippet(search_fts, 6, '<strong><font class=search_result><i>', '</i></font></strong>', '...', $snippet_size) as snippet, newsgroup, number, name, date, subject, rank FROM search_fts WHERE search_fts MATCH 'search_snippet:$terms' ORDER BY rank");
        $stmt->execute();

        while ($row = $stmt->fetch()) {
            $overview[] = $row;
        }
        $dbh = null;
    }
    // do not perform a usort of an empty search result
    if ($overview != null) {
        if ($_SESSION['searchsort'] != 'date') {
            usort($overview, function ($a, $b) {
                return $a['rank'] <=> $b['rank'];
            });
        } else {
            usort($overview, function ($a, $b) {
                return $b['date'] <=> $a['date'];
            });
        }
    }
    return $overview;
}

function show_search_sort_toggle()
{
    echo '<form method="post" action="' . $_SERVER['REQUEST_URI'] . '">';
    echo 'Sort by: ';
    if ($_SESSION['searchsort'] == 'date') {
        echo '<input type="radio" name="searchsort" value="date" checked>Date';
        echo '&nbsp;';
        echo '<input type="radio" name="searchsort" value="relevance">Relevance';
        echo '&nbsp;';
    } else {
        echo '<input type="radio" name="searchsort" value="date">Date';
        echo '&nbsp;';
        echo '<input type="radio" name="searchsort" value="relevance" checked>Relevance';
        echo '&nbsp;';
    }
    echo '<input type="hidden" name="group" value="' . secure_input($_REQUEST['group'], 'html') . '">';
    echo '<input type="hidden" name="data" value="' . secure_input($_REQUEST['data'], 'html') . '">';
    echo '<input type="hidden" name="terms" value="' . secure_input($_REQUEST['terms'], 'html') . '">';
    echo '<input type="hidden" name="key" value="' . secure_input($_REQUEST['key'], 'html') . '">';
    echo '<input type="hidden" name="command" value="' . secure_input($_REQUEST['command'], 'html') . '">';
    echo '<input type="hidden" name="searchpoint" value="' . secure_input($_REQUEST['searchpoint'], 'html') . '">';
    echo '<input class="np_button_link" type="submit" value="Reload" name="reload">';
    echo '</form >';
}

function get_header_search($group, $terms)
{
    global $CONFIG, $config_name, $config_dir, $spooldir, $debug_log, $snippet_size;
    $terms = preg_replace('/\%/', '\%', urldecode($terms));
    $searchterms = "%" . $terms . "%";

    if (isset($group)) {
        $grouplist[0] = $group;
    } else {
        $local_groupfile = $config_dir . "/" . $config_name . "/groups.txt";
        $grouplist = file($local_groupfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    # Prepare search database
    $database = $spooldir . '/articles-overview.db3';
    $table = 'overview';
    $dbh = overview_db_open($database, $table);
    $overview = array();

    foreach ($grouplist as $thisgroup) {
        $name = preg_split("/( |\t)/", $thisgroup, 2);
        $group = $name[0];
        $article_database = $spooldir . '/' . $group . '-articles.db3';
        if (! is_file($article_database)) {
            continue;
        }
        $article_dbh = article_db_open($article_database);
        if (!$article_dbh) {
            file_put_contents($debug_log, "\n" . format_log_date() . " " . $config_name . " Failed to open database: " . $article_database . " in search.php", FILE_APPEND);
            continue;
        }
        $article_stmt = $article_dbh->prepare("SELECT * FROM articles WHERE number=:number");
        if (!isset($_POST['data']) && is_multibyte($_POST['terms'])) {
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

function display_search_tools($home = true)
{
    global $CONFIG, $config_name, $search_group, $file_index, $frame, $file_thread, $suggestion;
    echo '<h1 class="np_thread_headline">';
    echo '<a href="' . $file_index . '">' . basename(getcwd()) . '</a> / ';
    if ($search_group) {
        echo '<a href="' . $file_thread . '&group=' . urlencode($search_group) . '">' . $search_group . '</a> / ';
    }
    echo 'search</h1>';
    echo '<form name="form1" method="post" action="search.php">';
    echo '<table class="search_form_table"><tr>';
    if (isset($search_group)) {
        $searching = $search_group;
    } else {
        $searching = $config_name;
    }
    echo '<td>Searching <strong>' . $searching . '</strong></td>';
    echo '</tr>';
    echo '<tr>';
    if (! isset($_REQUEST['data'])) {
        echo '<td>Search Terms:&nbsp;';
    } else {
        echo '<td>Search Poster:&nbsp;';
    }
    if (isset($_REQUEST['terms'])) {
        echo '<input name="terms" type="text" id="terms" value="' . htmlentities($_REQUEST['terms']) . '"></td>';
    } else {
        echo '<input name="terms" type="text" id="terms"></td>';
    }
    echo '</tr><tr><td>';

    // Create radio buttons (prefilled if available)
    if (isset($_REQUEST['searchpoint'])) {
        if ($_REQUEST['searchpoint'] == 'Poster' || $_REQUEST['searchpoint'] == 'name') {
            if ($CONFIG['article_database'] == '1') {
                echo '<input type="radio" name="searchpoint" value="body">Body&nbsp;';
            }
            echo '<input type="radio" name="searchpoint" value="subject">Subject&nbsp;';
            echo '<input type="radio" name="searchpoint" value="name" checked="checked">Poster&nbsp;';
            echo '<input type="radio" name="searchpoint" value="msgid">Message-ID';
        } elseif ($_REQUEST['searchpoint'] == 'subject') {
            if ($CONFIG['article_database'] == '1') {
                echo '&nbsp;<input type="radio" name="searchpoint" value="body">Body&nbsp;';
            }
            echo '<input type="radio" name="searchpoint" value="subject" checked="checked">Subject&nbsp;';
            echo '<input type="radio" name="searchpoint" value="name">Poster&nbsp;';
            echo '<input type="radio" name="searchpoint" value="msgid">Message-ID';
        } elseif ($_REQUEST['searchpoint'] == 'msgid') {
            if ($CONFIG['article_database'] == '1') {
                echo '&nbsp;<input type="radio" name="searchpoint" value="body">Body&nbsp;';
            }
            echo '<input type="radio" name="searchpoint" value="subject">Subject&nbsp;';
            echo '<input type="radio" name="searchpoint" value="name">Poster&nbsp;';
            echo '<input type="radio" name="searchpoint" value="msgid" checked="checked">Message-ID';
        } else {
            if ($CONFIG['article_database'] == '1') {
                echo '&nbsp;<input type="radio" name="searchpoint" value="body" checked="checked">Body&nbsp;';
            }
            echo '<input type="radio" name="searchpoint" value="subject">Subject&nbsp;';
            echo '<input type="radio" name="searchpoint" value="name">Poster&nbsp;';
            echo '<input type="radio" name="searchpoint" value="msgid">Message-ID';
        }
    } else {
        if ($CONFIG['article_database'] == '1') {
            echo '&nbsp;<input type="radio" name="searchpoint" value="body" checked="checked">Body&nbsp;';
        }
        echo '<input type="radio" name="searchpoint" value="subject">Subject&nbsp;';
        echo '<input type="radio" name="searchpoint" value="name">Poster&nbsp;';
        echo '<input type="radio" name="searchpoint" value="msgid">Message-ID';
    }

    echo '<input name="command" type="hidden" id="command" value="Search" >';
    if (isset($search_group)) {
        echo '<input type="hidden" name="group" value="' . urlencode($search_group) . '">';
    }
    echo '<input type="hidden" name="key" value="' . password_hash($CONFIG['thissitekey'], PASSWORD_DEFAULT) . '">';
    if (isset($_REQUEST['data'])) {
        echo '<input type="hidden" name="data" value="' . secure_input($_REQUEST['data'], 'html') . '">';
    }
    echo '</td></tr>';
    echo '<tr>';
    echo '<td><input type="submit" name="Submit" value="Search"></td>';
    echo '<form method="post" action="some_page" class="inline">';

    echo '</tr></table></form>';
}

function get_suggestion($word)
{
    global $OVERRIDES, $logfile, $debug_log;

    if (!function_exists('pspell_new')) {
        file_put_contents($debug_log, "\n" . logging_prefix() . " PSPELL Missing: php-pspell support unavailable", FILE_APPEND);
        return false;
    }

    if (isset($OVERRIDES['lang_default'])) {
        $lang = $OVERRIDES['lang_default'];
    } else {
        $lang = 'en';
    }

    if (($pspell = pspell_new($lang)) == false) {
        file_put_contents($logfile, "\n" . logging_prefix() . " SEARCH: " . $lang . " dictionary not found", FILE_APPEND);
        return false;
    }

    // Remove specific characters here
    $word = preg_replace("/(\"|\'|\(|\)|\-|\+|\_)/", '', $word);

    if (!preg_match("/ /", trim($word))) { // Just one word in search
        if (!pspell_check($pspell, $word)) {
            $suggestions = pspell_suggest($pspell, $word);
            if (isset($suggestions[0])) {
                if (strtolower($word) == strtolower($suggestions[0])) {
                    return false;
                } else {
                    return $suggestions[0];
                }
            } else {
                return false;
            }
        }
    } else { // Multiple words in search
        $return_string = '';
        $words = explode(" ", $word);
        foreach ($words as $one_word) {
            if (preg_match("/^(AND|OR|NOT|\+)$/i", $one_word)) {
                $return_string .= $one_word . " ";
                continue;
            }
            if (!pspell_check($pspell, $one_word)) {
                $suggestions = pspell_suggest($pspell, $one_word);
                if (isset($suggestions[0])) {
                    $return_string .= $suggestions[0] . " ";
                } else {
                    $return_string .= $one_word . " ";
                }
            } else {
                $return_string .= $one_word . " ";
            }
        }
        if (trim(strtolower($return_string)) != trim(strtolower($word))) {
            return trim($return_string);
        } else {
            return false;
        }
    }
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