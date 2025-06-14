<?php
/*
 ********************************************************************
 * DELETE MESSAGES BY MESSAGE-ID ##
 *
 * You may use this file to delete messages listed in a text file
 * containing a list of message-ids, one per line
 *
 * php /path_to_config_dir/scripts/delete_msgid.php name_of_msgid_list_file
 ********************************************************************
*/
include("paths.inc.php");
chdir($spoolnews_path);
include "../lib/config.inc.php";
include("$file_newsportal");

$logfile = $logdir . '/debug.log';
// Change to webserver user if root
$uinfo = posix_getpwnam($CONFIG['webserver_user']);
/* Change to non root user */
change_identity($uinfo["uid"], $uinfo["gid"]);
$processUser = posix_getpwuid(posix_geteuid());
if ($processUser['name'] != $CONFIG['webserver_user']) {
  echo "You are running as: " . $processUser['name'] . "\n";
  echo 'Please run this scripts as: ' . $CONFIG['webserver_user'] . "\n";
  exit();
}
/* Everything below runs as $CONFIG['webserver_user'] */

$processUser = posix_getpwuid(posix_geteuid());
if ($processUser['name'] != $CONFIG['webserver_user']) {
  echo "You are running as: " . $processUser['name'] . "\n";
  echo 'Please run this scripts as: ' . $CONFIG['webserver_user'] . "\n";
  exit();
}

$msgid_list = file($argv[1]);

$database = $spooldir . '/articles-overview.db3';
$dbh = overview_db_open($database);
$query = $dbh->prepare('SELECT * FROM overview WHERE msgid=:messageid');
$articles = array();
$i = 0;
foreach ($msgid_list as $msgid) {
  $msgid = trim($msgid);
  $query->execute(['messageid' => $msgid]);
  echo "Searching: " . $msgid . "\n";
  while (($row = $query->fetch()) !== false) {
    echo "Found in: " . $row['newsgroup'] . "\n";
    $articles[$i]['msgid'] = $msgid;
    $articles[$i]['newsgroup'] = $row['newsgroup'];
    $i++;
  }
}
$dbh = null;
foreach ($articles as $article) {
  delete_message($article['msgid'], $article['newsgroup']);
}
