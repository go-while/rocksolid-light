<?php
session_start();

include "common/config.inc.php";
//$CONFIG = include($config_dir.'/rslight.inc.php');

if (isset($_REQUEST['content'])) {
    $CONFIG['default_content']=$_REQUEST['content'];
}

if (isset($_REQUEST['menu'])) {
    $default_menu=$_REQUEST['menu'];
}

if (!isset($CONFIG['default_content'])) {
	die("no default content set in config file: $config_file");
}

 // magically redirects to default content: /rocksolid/index.php or /common/grouplist.php
header('Location: '.$CONFIG['default_content']);

?>
</html>
