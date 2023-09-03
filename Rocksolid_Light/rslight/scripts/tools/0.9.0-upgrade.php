<?php 

include "config.inc.php";
include ("$file_newsportal");

echo "TESTING: ".$argv[1];

if(isset($argv[1])) {
    import($argv[1]);
} else {
    import();
}

function import($group = '') {
    global $logfile, $workpath, $spooldir;
    $workpath=$spooldir."/";
    $path=$workpath."articles/";
    $group_list = get_group_list();
    $group = trim($group);
    if($group == '') {
        $group_files = scandir($workpath);
        foreach($group_files as $this_file) {
            if(strpos($this_file, '-overview') === false) {
                continue;
            }
            $group = preg_replace('/-overview/', '', $this_file);
            if (in_array($group, $group_list)) {
                echo "Importing: ".$group."\n";
                import_articles($group);
            } else {
                echo "Removing: ".$group."\n";
                rename($spooldir.'/'.$group.'-articles.db3',$spooldir.'/'.$group.'-articles.db3-removed');
                unlink($spooldir.'/'.$group.'-data.dat');
                unlink($spooldir.'/'.$group.'-info.txt');
                unlink($spooldir.'/'.$group.'-cache.txt');
                unlink($spooldir.'/'.$group.'-lastarticleinfo.dat');
                unlink($spooldir.'/'.$group.'-overboard.dat');
            }
        }
    } else {
        echo "Importing: ".$group."\n";
        import_articles($group);
    }
    echo "\nImport Done\r\n";
}

function get_group_list() {
    global $config_dir;
    $grouplist = array();
    $menulist = file($config_dir."menu.conf", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach($menulist as $menu) {
        if($menu[0] == '#') {
            continue;
        }
        $menuitem=explode(':', $menu);
        if($menuitem[2] == '0') {
            continue;
        }
        $glist = file($config_dir.$menuitem[0]."/groups.txt");
        foreach($glist as $gl) {
            if($gl[0] == ':') {
                continue;
            }
            $group_name = preg_split("/( |\t)/", $gl, 2);
            $grouplist[] = trim($group_name[0]);
        }
    }
    return $grouplist;
}

function import_articles($group) {
    global $spooldir, $CONFIG, $workpath, $path, $config_name, $logfile;
    # Prepare databases
    // Overview db
    $overview_dbh = UPGRADE_overview_db_open($spooldir.'/0.9.0-articles-overview.db3');
    $overview_sql = 'INSERT OR IGNORE INTO overview(newsgroup, number, msgid, date, datestring, name, subject, refs, bytes, lines, xref) VALUES(?,?,?,?,?,?,?,?,?,?,?)';
    $overview_stmt = $overview_dbh->prepare($overview_sql);
    
    $incoming_overview = file($spooldir.'/'.$group.'-overview');
    
    foreach($incoming_overview as $overviewline) {
        $import = explode("\t", $overviewline);
        $overview_stmt->execute([$group, $import[0], $import[4], strtotime($import[3]), $import[3], $import[2], $import[1], $import[5], $import[6], $import[7], $import[8]]);
        
        echo "\nImported: ".$group.":".$import[0];
        
    }
    $overview_dbh = null;
}

function UPGRADE_overview_db_open($database, $table='overview') {
    try {
        $dbh = new PDO('sqlite:'.$database);
    } catch (PDOException $e) {
        echo 'Connection failed: '.$e->getMessage();
        exit;
    }
    $dbh->exec("CREATE TABLE IF NOT EXISTS $table(
     id INTEGER PRIMARY KEY,
     newsgroup TEXT,
     number TEXT,
     msgid TEXT,
     date TEXT,
     datestring TEXT,
     name TEXT,
     subject TEXT,
     refs TEXT,
     bytes TEXT,
     lines TEXT,
     xref TEXT,
     unique (newsgroup, msgid))");
    $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS id_date on '.$table.'(date)');
    $stmt->execute();
    $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS id_newsgroup on '.$table.'(newsgroup)');
    $stmt->execute();
    $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS id_msgid on '.$table.'(msgid)');
    $stmt->execute();
    $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS id_newsgroup_number on '.$table.'(newsgroup,number)');
    $stmt->execute();
    $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS id_name on '.$table.'(name)');
    $stmt->execute();
    return($dbh);
}
?>