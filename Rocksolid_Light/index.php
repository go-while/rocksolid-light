<?php
session_start();
$_SESSION['isframed'] = 1;

include "common/config.inc.php";
$CONFIG = include($config_dir.'/rslight.inc.php');

if (isset($_REQUEST['content'])) {
    $CONFIG['default_content']=$_REQUEST['content'];
}

if (isset($_REQUEST['menu'])) {
    $default_menu=$_REQUEST['menu'];
}

die("header redirect");
header('Location: '.$CONFIG['default_content']);

?>
</html>
