<?php

  include "config.inc.php";
  include ("$file_newsportal");

  if(filemtime($spooldir.'/'.$config_name.'-expire-timer')+86400 > time()) {
    exit;
  }
  $lockfile = $lockdir . '/'.$config_name.'-spoolnews.lock';
  $pid = file_get_contents($lockfile);
  if (posix_getsid($pid) === false || !is_file($lockfile)) {
    print "Starting expire...\n";
    file_put_contents($lockfile, getmypid()); // create lockfile
  } else {
    print "expire currently running\n";
    exit;
  }

  $webserver_group=$CONFIG['webserver_user'];
  $logfile=$logdir.'/expire.log';

  $grouplist = file($config_dir.'/'.$config_name.'/groups.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
  foreach($grouplist as $groupline) {
      $groupname=explode(' ', $groupline);
      $group=$groupname[0];
      if($group[0] == ':') {
          continue;
      }
      $expire_conf = $CONFIG['expire_days'];
      $expire_user = get_config_value('expire.conf', $group);
      
      if($expire_user !== false) {
          $expire = $expire_user;
      } else {
          $expire = $expire_conf;
      }
      if($expire < 1) {
          continue;
      }
      $expireme = time() - ($expire * 86400);
      $showme = date('d M, Y', $expireme);
      
echo "Expire $group articles before $showme\n";
file_put_contents($logfile, "\n".format_log_date()." ".$config_name." ".$group." Expiring: articles before ".$showme, FILE_APPEND);

echo "Expiring overview database...\n";
file_put_contents($logfile, "\n".format_log_date()." ".$config_name." ".$group." Expiring overview database...", FILE_APPEND);
     $database = $spooldir.'/articles-overview.db3';
     $dbh = overview_db_open($database);
     $query = $dbh->prepare('DELETE FROM overview WHERE newsgroup=:newsgroup AND date<:expireme');
     $query->execute([':newsgroup' => $group, ':expireme' => $expireme]);
     $dbh = null;

    if($CONFIG['article_database'] == '1') {
echo "Expiring article database...\n";
file_put_contents($logfile, "\n".format_log_date()." ".$config_name." ".$group." Expiring article database...", FILE_APPEND);
      $database = $spooldir.'/'.$group.'-articles.db3';
      if(is_file($database)) {
         $articles_dbh = article_db_open($database);
         $articles_query = $articles_dbh->prepare('DELETE FROM articles WHERE newsgroup=:newsgroup AND date<:expireme');
         $articles_query->execute([':newsgroup' => $group, ':expireme' => $expireme]);
         $articles_dbh = null;
      }
    } else { // Expire tradspool
        $database = $spooldir.'/articles-overview.db3';
        $dbh = overview_db_open($database);
        $query = $dbh->prepare('SELECT FROM overview WHERE newsgroup=:newsgroup AND date<:expireme');
        $query->execute([':newsgroup' => $group, ':expireme' => $expireme]);
        $grouppath = preg_replace('/\./', '/', $group);
        while($row = $query->fetch()) {
            unlink($spooldir.'/articles/'.$grouppath.'/'.$row['number']);
        }
        $dbh = null;
    }
  }
  unlink($lockfile);
  touch($spooldir.'/'.$config_name.'-expire-timer');
?>
