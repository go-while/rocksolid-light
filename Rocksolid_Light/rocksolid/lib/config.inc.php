<?php
/* * RockSolid BBS - Configuration file
 * This file is included by other scripts to set up the environment
 * and load necessary configurations.
 */

define("PRE_LOAD_CONF", true); // Define a constant to indicate pre-load context
require("../common/config.inc.php");
//echo "<!--[ rocksolid/lib/config.inc.php: include ../common/config.inc.php loaded]<br> -->\n";

if(empty($config_path)) {
    die("[ERROR rocksolid/lib/config.inc.php config_path is not set :L=5!]<br>\n");
}
//echo "[<!-- rocksolid/lib/config.inc.php: config_path: $config_path]<br> -->\n";
// Ensure the config_path is set correctly

//echo "<!-- Debug: config_path set to '$config_path' -->\n";
//echo "<!-- Debug: script_path set to '$script_path' -->\n";

$backtrace = debug_backtrace();
$parent = isset($backtrace[0]['file']) ? $backtrace[0]['file'] : 'Direct execution';
//echo "<!-- [rocksolid/lib/config.inc.php included by: " . basename($parent) . "]<br> -->\n";

$installed_path = getcwd();

// For router system, determine the correct section
// Since files were originally in web/spoolnews/, the config is in /etc/rslight/spoolnews/
//echo "<!-- rocksolid/lib/config.inc.php: Debug: config_path is '$config_path' -->\n";
// $CONFIG = include($config_file); // Already loaded by common/config.inc.php]['file']) ? $backtrace[0]['file'] : 'Direct execution';ace = debug_backtrace();
$parent = isset($backtrace[0]['file']) ? $backtrace[0]['file'] : 'Direct execution';
//echo "<!-- [rocksolid/lib/config.inc.php included by: " . basename($parent) . "]<br> -->\n";
//echo "<!-- Debug: rocksolid/lib/config.inc.php loading -->\n";
//echo "<!-- Debug: config_path set to '$config_path' -->\n";

$installed_path = getcwd();

/* Version */
$version_file = '../common/version.txt';
if(!file_exists($version_file)) {
    die("Critical Error: Version file '$version_file' not found");
}
$rslight_version = file_get_contents($version_file);
//echo "<!-- [rocksolid/lib/config.inc.php rslight_version=$rslight_version]<br> -->\n";

// CONFIG VALUE CHECKS : @USER :: DO NOT CHANGE ANYTHING BELOW THIS LINE UNLESS YOU KNOW WHAT YOU ARE DOING

/*
 * settings for the article flat view, if used.
 * had always been hardcoded, now configurable
 */
if(!isset($CONFIG["articleflat_articles_per_page"])){
    $CONFIG["articleflat_articles_per_page"] = 25;
}
$articleflat_articles_per_page = $CONFIG["articleflat_articles_per_page"];

if(!isset($CONFIG["articleflat_chars_per_articles"])){
    $CONFIG["articleflat_chars_per_articles"] = 10000;
}
$articleflat_chars_per_articles = $CONFIG["articleflat_chars_per_articles"];

// check PHP executable
if(!isset($CONFIG['php_exec']) || empty($CONFIG['php_exec'])) {
    $CONFIG['php_exec'] = "/usr/bin/php"; // Default PHP executable path
}
if(!file_exists($CONFIG['php_exec'])) {
    die("ERROR [rocksolid/lib/config.inc.php: CONFIG php_exec file does not exist!]");
}
if(!is_executable($CONFIG['php_exec'])) {
    die("ERROR [rocksolid/lib/config.inc.php: CONFIG php_exec is not executable!]");
}

// check webserver user
if(!isset($CONFIG['webserver_user']) || empty($CONFIG['webserver_user']) || $CONFIG['webserver_user'] == "root") {
    die("ERROR [rocksolid/lib/config.inc.php: CONFIG webserver_user is not set or is set to root but should not be root!]");
}

// check server path
if(!isset($CONFIG['server_path'])||empty($CONFIG['server_path'])) {
    if(!isset($_SERVER['HTTP_HOST']) || empty($_SERVER['HTTP_HOST'])) {
        die("ERROR [rocksolid/lib/config.inc.php: CONFIG server_path is not set and HTTP_HOST is not available!]");
    }
    $CONFIG['server_path'] = "@" . $_SERVER['HTTP_HOST'];
}

// default address for anonymous user
if(!isset($CONFIG['anonym_address'])) {
    $CONFIG['anonym_address'] = "anonuser@anon.rocksolidbbs.usenet-server.com";
}

// check pathhost
if(!isset($CONFIG['pathhost'])||empty($CONFIG['pathhost'])) {
    $CONFIG['pathhost'] = $CONFIG['server_path'];
}

if(array_key_exists('min_spool_disk_space', $OVERRIDES)) {
    if ($OVERRIDES['min_spool_disk_space'] > 0) {
        $min_spool_disk_space = $OVERRIDES['min_spool_disk_space'];
    }
}

$free_spool_disk_space = disk_free_space($spooldir) /1024/1024/1024; // Convert to GB
if ($free_spool_disk_space < $min_spool_disk_space) {
    $low_spool_disk_space = true;
} else {
    $low_spool_disk_space = false;
}

// Logging
if(isset($_SERVER['REMOTE_ADDR'])) {
    $client_ip_address = $_SERVER['REMOTE_ADDR'];
}

$logdir = $spooldir . '/log';
$debug_log = $logdir . '/debug.log';
$abuse_log = $logdir . '/abuse.log';
$auth_log = $logdir . '/auth.log';
$mail_log = $logdir . '/mail.log';
$lockdir = $spooldir . '/lock';
$ssldir = $spooldir . '/ssl/';
$user_ban_file = $config_dir . '/banned_names.conf';

$grouplist_cache_filename = $spooldir . '/grouplist-cache.txt';
$grouplist_cache_time = 14400;

/* Permanent configuration changes */
@mkdir($logdir, 0755, 'recursive');
@mkdir($spooldir . '/upload', 0755, 'recursive');
chown($logdir, $CONFIG['webserver_user']);
chown($spooldir . '/upload', $CONFIG['webserver_user']);

date_default_timezone_set('UTC');
$overboard = true;
$spoolnews = true;

/*
if (isset($CONFIG['enable_nntp']) && ($CONFIG['enable_nntp'] == true || $CONFIG['enable_nntp'] == '1')) {
    $server = $CONFIG['local_server'];
    $port = $CONFIG['local_port'];
} else {
    $server = $CONFIG['remote_server'];
    $port = $CONFIG['remote_port'];
    $CONFIG['server_auth_user'] = $CONFIG['remote_auth_user'];
    $CONFIG['server_auth_pass'] = $CONFIG['remote_auth_pass'];
}
*/

/*
 * Frames (frames is not up to date and probably not so great)
 */



// The default content for the left side 'menu' frame
$default_menu = "/rocksolid/index.php";

//

$style_css = "style.css";
$frame['content'] = "_self";
$frame['menu'] = "_self";
$frame['header'] = "_self";

$frame_externallink = "_blank";

/*
 * directories and files
 */
$imgdir = "img";

// why we call it file_?
// because sometimes it's easier to keep a
// legacy pattern than reinventing the wheel!
$file_newsportal = "newsportal.php";
$file_index = "index.php";
$file_thread = "index.php?page=thread";
$file_article = "index.php?page=article-flat";
$file_article_full = "index.php?page=article";
$file_attachment = "index.php?page=attachment";
$file_post = "index.php?page=post";
$file_cancel = "index.php?page=cancel";
$file_search = "index.php?page=search";
$file_groups = "index.php?page=grouplist";
$file_overboard = "index.php?page=overboard";
$file_user = "index.php?page=user";
$file_register = "index.php?page=register";
$file_mail = "index.php?page=mail";
$file_decrypt = "index.php?page=decrypt";
$file_files = "index.php?page=files";
$file_upload = "index.php?page=upload";

if(!isset($config_dir)) die("config_dir is not set in rocksolid/lib/config.inc.php:L=109!");

// Language selection: Check for user preference in cookie, fallback to default
//include $config_dir."inc/allowed_languages.inc.php";
//$default_language = $config_dir."inc/lang/english.lang";

$file_language = $config_dir . "/inc/lang/english.lang";

if (isset($_COOKIE['user_language']) && !empty($_COOKIE['user_language'])) {
    $requested_lang = $_COOKIE['user_language'];
    // Security: Only allow languages from hardcoded approved list
    if (is_language_allowed($requested_lang)) {
        $requested_lang_path = $config_dir . "/inc/lang/" . $requested_lang . ".lang";
        if (file_exists($requested_lang_path)) {
            $file_language = $requested_lang_path;
        }
    }
}

$file_footer = "footer.inc";
$file_groups = $config_path . "groups.txt";

$title = $CONFIG['title_full'];

/*
 * Grouplist Layout
 */
$gl_age = true;

/*
 * Thread layout
 */
# When viewing a thread should the articles be sorted by subthreads, or
# simply by date, oldest to newest?
# Set to false to sort by date, true to sort into subthreads.
# Generally, false makes it easier to find the latest posts at the bottom.
$thread_articles = false;

$thread_treestyle = 7;
$thread_show["date"] = false;
$thread_show["subject"] = true;
$thread_show["author"] = true;
$thread_show["authorlink"] = false;
$thread_show["replies"] = false;
$thread_show["lastdate"] = true; // makes only sense with $thread_show["replies"]=false
$thread_show["threadsize"] = true;
$thread_show["latest"] = true;
$thread_maxSubject = 120; // will become deprecated

$maxfetch = 1000;
$maxarticles = 0;
$maxarticles_extra = 0;
$age_count = 3;

// $age_color[x] is class name in style.css
$age_time[1] = 86400; // 24 hours
$age_color[1] = "group_display_message_count_1";
$age_time[2] = 259200; // 3 days
$age_color[2] = "group_display_message_count_2";
$age_time[3] = 604800; // 7 days
$age_color[3] = "group_display_message_count_3";
$thread_sort_order = - 1;
$thread_sort_type = "thread";
$articles_per_page = 200;
$startpage = "first";

/*
 * article layout
 */
$article_show["Subject"] = true;
$article_show["From"] = true;
$article_show["Newsgroups"] = true;
$article_show["Followup"] = true;
$article_show["Organization"] = true;
$article_show["Date"] = true;
$article_show["Message-ID"] = false;
$article_show["User-Agent"] = false;
$article_show["References"] = true;
$article_show["From_link"] = false;
$article_show["trigger_headers"] = true;
// $article_show["From_rewrite"]=array('@',' (at) ');
$article_showthread = true;
$article_graphicquotes = true;


/*
 * Message posting
 */
$send_poster_host = false;
$testgroup = true; // don't disable unless you really know what you are doing!
$validate_email = 1;
$setcookies = true;
$anonym_address = $CONFIG['anonym_address'];
$msgid_generate = "md5"; // no other option available yet TODO generate_msgid()
$post_autoquote = false;
$post_captcha = false;
$wrap_width = 72;

/*
 * Attachments
 */
$attachment_show = true;
$attachment_delete_alternative = true; // delete non-text mutipart/alternative
$attachment_uudecode = true; // experimental!

/*
 * Security settings
 */
$block_xnoarchive = false;

/*
 * User registration and database
 */
// $npreg_lib="lib/npreg.inc.php";

/*
 * Cache
 */
$cache_articles = false; // article cache, experimental!
$cache_index = 600; // cache the group index for ten minutes before reloading
$cache_thread = 60; // cache the thread for one minute reloading

/*
 * Misc
 */
$cutsignature = true;
$compress_spoolfiles = false;

if (isset($spoolnews) && ($spoolnews === true)) {
    $spoolpath = $spooldir . "/articles/";
    $localeol = PHP_EOL . PHP_EOL;
} else {
    $spoolpath = "/var/spool/news/articles/";
    $localeol = "\r\n\r\n";
}

// website charset, "koi8-r" for example
// $www_charset = "iso-8859-15";
$www_charset = "utf-8";
// Use the iconv extension for improved charset conversions
$iconv_enable = true;






// Get server protocol etc. into string
if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
    $sitelink = "https";
} else {
    $sitelink = "http";
}
$sitelink .= "://";
if (isset($_SERVER["HTTP_HOST"])) {
    $sitelink .= $_SERVER["HTTP_HOST"];
}


// ============================================================================
// OPTIONAL SECURITY SETTINGS (disabled by default for compatibility)
// ============================================================================
// Uncomment and configure these to enhance upload security:

// File upload validation (disabled by default)
// $CONFIG['validate_file_uploads'] = true;

// Maximum upload file size in bytes (5MB default when enabled)
// $CONFIG['max_upload_size'] = 5 * 1024 * 1024;

// Allowed MIME types for file uploads (when validation is enabled)
// $CONFIG['allowed_file_types'] = [
//     'image/jpeg', 'image/png', 'image/gif', 'image/webp',
//     'text/plain', 'application/pdf'
// ];

// Log all file upload attempts to error log
// $CONFIG['log_file_uploads'] = true;

// Track upload statistics to logs/uploads.log
// $CONFIG['track_uploads'] = true;

define("PRE_LOAD_DONE", true); // Define a constant to indicate pre-load context
require("../common/config.inc.php");

// we should not reach below here. if we do, something is wrong with the page switch
die("ERROR [rocksolid/lib/config.inc.php: include ../common/config.inc.php loaded]<br>\n");

?>