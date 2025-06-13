<?php
// Common menu and section functions
// This file contains shared functions used across the application

// Read <config_dir>/menu.conf and return as array
function get_section_menu_array()
{
    global $config_dir;
    $menudata = file($config_dir . '/menu.conf');
    $newmenu = array();
    foreach ($menudata as $menuentry) {
        if (!preg_match("/^[a-zA-Z0-9]/", $menuentry)) { // Not an entry. Ignore
            continue;
        } else {
            $newmenu[] = $menuentry;
        }
    }
    return $newmenu;
}
?>
