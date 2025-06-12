<?php
/**
 * RockSolid Light Security Functions
 * Security Hardening - 2025-June-Patch-1
 * In Memory of Retro Guy
 */

/**
 * Secure replacement for unserialize() operations
 * Prevents PHP object injection attacks
 *
 * @param string $file File path to unserialize
 * @param array $allowed_classes Array of allowed class names for unserialize
 * @param bool $use_json_fallback Try JSON decode first
 * @return mixed|false Unserialized data or false on failure
 */
function secure_unserialize($file, $allowed_classes = [], $use_json_fallback = true) {
    if (!file_exists($file) || !is_readable($file)) {
        return false;
    }

    $content = file_get_contents($file);
    if ($content === false || empty($content)) {
        return false;
    }

    // Try JSON first (safer alternative)
    if ($use_json_fallback) {
        $json_data = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json_data;
        }
    }

    // If we must use unserialize, do it safely
    $options = ['allowed_classes' => $allowed_classes];

    // Additional validation for serialized data format
    if (!preg_match('/^[adbois]:[0-9]+/', $content)) {
        return false;
    }

    try {
        return unserialize($content, $options);
    } catch (Exception $e) {
        error_log("Secure unserialize failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Secure file write for serialized data
 * Uses JSON by default, falls back to serialize if needed
 *
 * @param string $file File path to write
 * @param mixed $data Data to serialize
 * @param bool $use_json Use JSON format instead of PHP serialize
 * @return bool Success status
 */
function secure_serialize_file($file, $data, $use_json = true) {
    if ($use_json) {
        $content = json_encode($data, JSON_PRETTY_PRINT);
        if ($content === false) {
            return false;
        }
    } else {
        $content = serialize($data);
    }

    // Atomic write using temporary file
    $temp_file = $file . '.tmp.' . uniqid();
    if (file_put_contents($temp_file, $content, LOCK_EX) === false) {
        return false;
    }

    if (!rename($temp_file, $file)) {
        unlink($temp_file);
        return false;
    }

    return true;
}

/**
 * Secure input validation
 *
 * @param mixed $input Input to validate
 * @param string $type Validation type
 * @param int $max_length Maximum length (optional)
 * @return bool|mixed Validation result or sanitized input
 */
function secure_input($input, $type, $max_length = null) {
    if ($max_length && strlen($input) > $max_length) {
        return false;
    }

    switch ($type) {
        case 'alphanum':
            return preg_match('/^[a-zA-Z0-9]+$/', $input) ? $input : false;

        case 'alphanumext':
            return preg_match('/^[a-zA-Z0-9\._-]+$/', $input) ? $input : false;

        case 'email':
            return filter_var($input, FILTER_VALIDATE_EMAIL);

        case 'int':
            return filter_var($input, FILTER_VALIDATE_INT);

        case 'float':
            return filter_var($input, FILTER_VALIDATE_FLOAT);

        case 'url':
            return filter_var($input, FILTER_VALIDATE_URL);

        case 'groupname':
            // Newsgroup name validation
            return preg_match('/^[a-zA-Z0-9\.\-_]+$/', $input) ? $input : false;

        case 'filename':
            // Safe filename validation
            $input = basename($input);
            return preg_match('/^[a-zA-Z0-9\._-]+$/', $input) ? $input : false;

        case 'html':
            return htmlspecialchars($input, ENT_QUOTES, 'UTF-8');

        case 'sql':
            // For database input - still use prepared statements!
            return addslashes($input);

        default:
            return false;
    }
}

/**
 * Secure file upload handler
 *
 * @param array $file $_FILES array element
 * @param array $allowed_types Allowed MIME types
 * @param int $max_size Maximum file size in bytes
 * @return array|false Upload result or false on failure
 */
function secure_file_upload($file, $allowed_types = ['image/jpeg', 'image/png', 'image/gif'], $max_size = 5242880) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return false;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    if ($file['size'] > $max_size) {
        return false;
    }

    // Check MIME type using fileinfo
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mime, $allowed_types)) {
        return false;
    }

    // Sanitize filename
    $filename = basename($file['name']);
    $filename = preg_replace('/[^a-zA-Z0-9\._-]/', '_', $filename);

    // Generate unique filename to prevent conflicts
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    $basename = pathinfo($filename, PATHINFO_FILENAME);
    $unique_filename = $basename . '_' . uniqid() . '.' . $extension;

    return [
        'original_name' => $file['name'],
        'secure_filename' => $unique_filename,
        'mime_type' => $mime,
        'size' => $file['size'],
        'tmp_name' => $file['tmp_name']
    ];
}

/**
 * Safe command execution replacement
 * Avoids shell injection by using escapeshellarg
 *
 * @param string $command Base command
 * @param array $args Arguments to escape
 * @return string|false Command output or false on failure
 */
function secure_exec($command, $args = []) {
    // Whitelist of allowed commands
    $allowed_commands = [
        'file',
        'uuencode',
        'convert',
        'identify'
    ];

    if (!in_array($command, $allowed_commands)) {
        return false;
    }

    $escaped_args = array_map('escapeshellarg', $args);
    $full_command = $command . ' ' . implode(' ', $escaped_args);

    return shell_exec($full_command);
}

/**
 * Get MIME type safely without shell execution
 *
 * @param string $filepath File path
 * @return string|false MIME type or false on failure
 */
function get_secure_mime_type($filepath) {
    if (!file_exists($filepath) || !is_readable($filepath)) {
        return false;
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        return false;
    }

    $mime = finfo_file($finfo, $filepath);
    finfo_close($finfo);

    return $mime;
}

/**
 * Secure path validation to prevent directory traversal
 *
 * @param string $path Path to validate
 * @param string $base_dir Base directory to restrict to
 * @return string|false Normalized safe path or false
 */
function secure_path($path, $base_dir = '.') {
    // Remove any null bytes
    $path = str_replace("\0", '', $path);

    // Check for obvious path traversal attempts
    if (strpos($path, '../') !== false || strpos($path, './') !== false) {
        return false;
    }

    // Check for absolute paths (potential traversal)
    if (strpos($path, '/') === 0) {
        return false;
    }

    // If base_dir is provided and valid, do full path resolution
    if ($base_dir !== '.' && is_dir($base_dir)) {
        $real_base = realpath($base_dir);
        $real_path = realpath($base_dir . '/' . $path);

        if ($real_path === false || $real_base === false) {
            return false;
        }

        // Check if path is within base directory
        if (strpos($real_path, $real_base) !== 0) {
            return false;
        }

        return $real_path;
    }

    // For simple validation without filesystem resolution
    return $path;
}

/**
 * Generate secure random tokens
 *
 * @param int $length Token length
 * @return string Random token
 */
function generate_secure_token($length = 32) {
    if (function_exists('random_bytes')) {
        return bin2hex(random_bytes($length / 2));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        return bin2hex(openssl_random_pseudo_bytes($length / 2));
    } else {
        // Fallback for older PHP versions
        return hash('sha256', uniqid(rand(), true));
    }
}

/**
 * Validate and sanitize newsgroup names
 *
 * @param string $group Newsgroup name
 * @return string|false Sanitized group name or false
 */
function secure_newsgroup_name($group) {
    // Basic validation
    if (empty($group) || strlen($group) > 255) {
        return false;
    }

    // Allow standard newsgroup characters
    if (!preg_match('/^[a-zA-Z0-9\.\-_]+$/', $group)) {
        return false;
    }

    // Prevent path traversal in group names
    if (strpos($group, '..') !== false) {
        return false;
    }

    return $group;
}

/**
 * Rate limiting helper
 *
 * @param string $identifier Unique identifier (IP, user, etc.)
 * @param int $max_requests Maximum requests
 * @param int $time_window Time window in seconds
 * @return bool True if request is allowed
 */
function rate_limit_check($identifier, $max_requests = 60, $time_window = 3600) {
    global $spooldir;

    $rate_file = $spooldir . '/rate_' . md5($identifier) . '.dat';
    $current_time = time();

    if (file_exists($rate_file)) {
        $data = json_decode(file_get_contents($rate_file), true);
        if ($data && isset($data['requests']) && isset($data['window_start'])) {
            if ($current_time - $data['window_start'] < $time_window) {
                if ($data['requests'] >= $max_requests) {
                    return false;
                }
                $data['requests']++;
            } else {
                // Reset window
                $data = ['requests' => 1, 'window_start' => $current_time];
            }
        } else {
            $data = ['requests' => 1, 'window_start' => $current_time];
        }
    } else {
        $data = ['requests' => 1, 'window_start' => $current_time];
    }

    file_put_contents($rate_file, json_encode($data));
    return true;
}

/**
 * Add security headers to HTTP response
 */
function add_security_headers() {
    // Prevent XSS attacks
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');

    // HTTPS enforcement (only if already using HTTPS)
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }

    // Content Security Policy - basic protection
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'; img-src 'self' data:;");
}

/**
 * Generate CSRF token
 *
 * @return string CSRF token
 */
function generate_csrf_token() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 *
 * @param string $token Token to verify
 * @return bool True if valid
 */
function verify_csrf_token($token) {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Secure session configuration
 */
function secure_session_start() {
    // Configure secure session settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');

    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    // Regenerate session ID periodically
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }
}
