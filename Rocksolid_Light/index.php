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
    ?>
    <h1>Error: 'default_content' not set in configuration</h1>
    <p>Please check your configuration file to ensure that the <code>default_content</code> parameter is set.</p>
    <p>Example: <code>$CONFIG['default_content'] = '/rocksolid/index.php';</code></p>
    <p>If you are seeing this message, it means the configuration is incomplete or the file is not properly included.</p>
    <p>For more information, please refer to the documentation or contact your system administrator.</p>
    <?php
    die();
}

// Fallback: magically redirects to default content: /rocksolid/index.php or /common/grouplist.php
header('Location: ' . $CONFIG['default_content']);
?>
