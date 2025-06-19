<?php
/**
 * Production-ready security include helper for scripts
 * Handles path resolution between development and production deployments
 */

function load_security_functions() {
    $security_paths = [
        // Try relative path first (works in development)
        __DIR__ . '/../../rocksolid/security.inc.php',
        // Try production web directory paths
        '/var/www/html/rocksolid/security.inc.php',
        '/var/www/html/rslight/rocksolid/security.inc.php',
        '/var/www/html/spoolnews/security.inc.php',
        '/var/www/html/rslight/spoolnews/security.inc.php'
    ];

    foreach ($security_paths as $path) {
        if (file_exists($path)) {
            require_once($path);
            return true;
        }
    }

    // Log the issue but continue without security functions
    error_log("Warning: security.inc.php not found in any expected location");
    return false;
}

// Auto-load security functions when this file is included
load_security_functions();
?>
