<?php
session_start();

include "common/config.inc.php";

// Handle router-based requests first
if (isset($_GET['page'])) {
    // We have a page parameter, let the router handle it
    if (function_exists('rslight_route_page')) {
        if (rslight_route_page()) {
            exit(); // Router handled the request
        }
    }
    // If router fails, fall through to default handling
}

// Handle content and menu parameters
if (isset($_REQUEST['content'])) {
    $CONFIG['default_content'] = $_REQUEST['content'];
}

if (isset($_REQUEST['menu'])) {
    $default_menu = $_REQUEST['menu'];
}

if (!isset($CONFIG['default_content'])) {
    die("no default content set in config file: $config_file");
}

// If no page parameter and no specific content requested, serve default index
if (!isset($_GET['page']) && !isset($_REQUEST['content'])) {
    // Try to serve the new consolidated index page
    if (function_exists('rslight_serve_default_page')) {
        if (rslight_serve_default_page()) {
            exit(); // Default page served successfully
        }
    }
}

// Fallback: magically redirects to default content: /rocksolid/index.php or /common/grouplist.php
header('Location: ' . $CONFIG['default_content']);
?>
