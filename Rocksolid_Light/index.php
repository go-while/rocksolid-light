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

//die("die index.php header redirect");
header('Location: '.$CONFIG['default_content']);

?>
</html>
