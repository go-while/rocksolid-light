<?php

define('PRE_LOAD_CONF', true); // Define a constant to indicate pre-load context
include "common/config.inc.php";

/*
TODO REVIEW, maybe legacy code for handling content and menu parameters
// Handle content and menu parameters
if (isset($_REQUEST['content'])) {
    $CONFIG['default_content'] = $_REQUEST['content'];
}

if (isset($_REQUEST['menu'])) {
    $default_menu = $_REQUEST['menu'];
}
*/

if (!isset($CONFIG['default_content'])) {
    die("no default content set in config file: $config_file");
}

// Fallback: magically redirects to default content: /rocksolid/index.php or /common/grouplist.php
header('Location: ' . $CONFIG['default_content']);
?>
