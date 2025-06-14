<?php
/**
 * Logging Control Script for RockSolid Light
 *
 * This script provides functions to control logging verbosity in production.
 * It allows administrators to disable DEBUG messages while maintaining
 * error and warning logs.
 */

/**
 * Check if verbose debug logging is enabled
 * Returns true if debug logging should be done, false otherwise
 */
if (!function_exists('is_debug_logging_enabled')) {
    function is_debug_logging_enabled() {
        global $OVERRIDES;

        // Check if debug logging is explicitly disabled in overrides
        if (isset($OVERRIDES['disable_debug_logging']) && $OVERRIDES['disable_debug_logging'] === true) {
            return false;
        }

        // Check if running in production mode (disable debug by default)
        if (isset($OVERRIDES['production_mode']) && $OVERRIDES['production_mode'] === true) {
            return false;
        }

        // Default: enable debug logging (backward compatibility)
        return true;
    }
}

/**
 * Wrapper function for debug logging
 * Only logs if debug logging is enabled
 */
if (!function_exists('debug_log')) {
    function debug_log($message, $logfile) {
        if (is_debug_logging_enabled()) {
            file_put_contents($logfile, $message, FILE_APPEND);
        }
    }
}

/**
 * Always log errors regardless of debug setting
 */
if (!function_exists('error_log_always')) {
    function error_log_always($message, $logfile) {
        file_put_contents($logfile, $message, FILE_APPEND);
    }
}

/**
 * Log only important messages (errors, warnings, connection issues)
 */
if (!function_exists('important_log')) {
    function important_log($message, $logfile) {
        // These patterns indicate important messages that should always be logged
        $important_patterns = [
            'ERROR',
            'FAILED',
            'TIMEOUT',
            'Failed to connect',
            'Lost connection',
            'Cannot connect',
            'Cannot get overview',
            'Cannot enter',
            'Cannot listgroup',
            'Fatal',
            'Exception',
            'SPAM',
            'banned',
            'integrity check',
            'Low Disk Space',
            'Authentication failed'
        ];

        foreach ($important_patterns as $pattern) {
            if (stripos($message, $pattern) !== false) {
                file_put_contents($logfile, $message, FILE_APPEND);
                return;
            }
        }

        // If not important and debug is disabled, don't log
        if (!is_debug_logging_enabled()) {
            return;
        }

        file_put_contents($logfile, $message, FILE_APPEND);
    }
}

?>
