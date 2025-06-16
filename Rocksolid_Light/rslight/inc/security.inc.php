<?php
/**
 * RockSolid Light Security Functions
 * Security Hardening - 2025-June-Patch-1
 * In Memory of Retro Guy
 */

$backtrace = debug_backtrace();
$parent = isset($backtrace[0]['file']) ? $backtrace[0]['file'] : 'Direct execution';
echo "<!-- [rslight/security.inc.php included by: " . basename($parent) . "]<br> -->\n";

/**
 * Secure session configuration
 */
function secure_session_start() {
    // Configure secure session settings
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']) ? 1 : 0);
    ini_set('session.cookie_samesite', 'Strict');

    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Regenerate session ID periodically
    if (!isset($_SESSION['last_regeneration'])) {
        $_SESSION['last_regeneration'] = time();
    } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
        session_regenerate_id(true);
        $_SESSION['last_regeneration'] = time();
    }

    // Update session access time
    if (!isset($_SESSION['last_access']) || (time() - $_SESSION['last_access']) > 60) {
        $_SESSION['last_access'] = time();
    }
    $_SESSION['rsactive'] = true; // somehow counts users by counting session files....
}

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
 * Securely validate and sanitize newsgroup names
 * Prevents path traversal and ensures valid characters
 *
 * @param string $group Newsgroup name
 * @return string|false Sanitized group name or false on failure
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
    if (session_status() === PHP_SESSION_NONE) {
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
    if (session_status() === PHP_SESSION_NONE) {
        die("verify_csrf_token session error: session not started");
    }

    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

function get_client_user_agent_info()
{
    global $config_dir, $logdir;

    // Try to get browser info to use for extra formatting of page
    $ua = strtolower($_SERVER["HTTP_USER_AGENT"]);
    $devices = array(
        "bot",
        "spider",
        "mobile",
        "lynx",
        "w3m",
        "links",
        "ipad",
        "tablet"
    );
    $client_device = "desktop";
    foreach ($devices as $device) {
        if (strpos($ua, $device) !== false) {
            $client_device = $device;
            break;
        }
    }
    if ($client_device == "spider" || $client_device == "crawler") {
        $client_device = "bot";
    }
    // Log client device if enabled by semaphore
    if (file_exists($config_dir . '/devicelog.enable')) {
        $client_ip = getenv("REMOTE_ADDR");
        $logfile = $logdir . '/device.log';
        file_put_contents($logfile, "\n" . format_log_date() . " " . $client_ip . " " . $client_device, FILE_APPEND);
    }
    return $client_device;
}

function throttle_hits($client_device = null)
{
    global $CONFIG, $OVERRIDES, $logdir, $abuse_log, $config_name, $spooldir;

    $rdns_file = $spooldir . '/rdns.dat';
    $rdns = array();
    if (file_exists($rdns_file)) {
        try {
            $rdns = secure_unserialize(file_get_contents($rdns_file));
            if (!is_array($rdns)) {
                $rdns = array();
            }
        } catch (Exception $e) {
            $rdns = array();
        }
    }

    if (! $client_device) {
        $client_device = get_client_user_agent_info();
    }
    $client_device = strtolower($client_device);

    // Block by user-agent
    if (isset($OVERRIDES['block_by_user_agent'])) {
        $this_ua = strtolower($_SERVER["HTTP_USER_AGENT"]);
        foreach ($OVERRIDES['block_by_user_agent'] as $block_user_agent) {
            if (stripos($this_ua, $block_user_agent) !== false) {
                file_put_contents($abuse_log, "\n" . logging_prefix() . " (blocking) '" . $block_user_agent . "' found in User-Agent block list", FILE_APPEND);
                $_SESSION['throttled'] = true;
                header("HTTP/1.0 403 Forbidden");
                exit();
            }
        }
    }

    // Block by rdns
    if (isset($OVERRIDES['block_by_rdns'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
        if (isset($rdns[$ip])) {
            $this_rdns = $rdns[$ip];
        } else {
            $this_rdns = gethostbyaddr($ip);
            $rdns[$ip] = $this_rdns;
            file_put_contents($rdns_file, serialize($rdns));
        }
        foreach ($OVERRIDES['block_by_rdns'] as $block_rdns) {
            if (stripos($this_rdns, $block_rdns) !== false) {
                file_put_contents($abuse_log, "\n" . logging_prefix() . " (blocking) '" . $block_rdns . "' found in RDNS block list", FILE_APPEND);
                $_SESSION['throttled'] = true;
                header("HTTP/1.0 403 Forbidden");
                exit();
            }
        }
    }

    // $loadrate = allowed article request per second
    $loadrate = .15;
    if ($client_device == "bot") {
        $_SESSION['bot'] = 'true';
        if (isset($OVERRIDES['throttle_hits_bot_loadrate']) && trim($OVERRIDES['throttle_hits_bot_loadrate']) != '') {
            $loadrate = $OVERRIDES['throttle_hits_bot_loadrate'];
        }
    }

    if (! isset($_SESSION['starttime'])) {
        $_SESSION['starttime'] = time();
        $_SESSION['views'] = 0;
    }
    $_SESSION['views']++;
    // $rate = current hits / seconds since start of session
    $rate = fdiv($_SESSION['views'], (time() - $_SESSION['starttime']));
    // if $rate > greater than $loadrate, throttle hits
    // but allow 50 hits at start of session to allow loading everything
    if (($rate > $loadrate) && ($_SESSION['views'] > 50)) {
        header("HTTP/1.0 429 Too Many Requests");
        if (! isset($_SESSION['throttled'])) {
            file_put_contents($abuse_log, "\n" . logging_prefix() . " (throttling) too many requests" . " (" . $rate . " > " . $loadrate . ")", FILE_APPEND);
            $_SESSION['throttled'] = true;
        }
        exit(0);
    }
    if (isset($_SESSION['throttled'])) {
        unset($_SESSION['throttled']);
    }
}

function write_access_log()
{
    global $logdir;
    $accessfile = $logdir . '/access.log';
    $currentPageUrl = $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];
    file_put_contents($accessfile, "\n" . logging_prefix() . " " . $currentPageUrl, FILE_APPEND);
}

function logging_prefix($sockip = null)
{
    global $client_ip_address;
    if ($sockip) {
        if (preg_match("/\./", $sockip)) {
            $ipv4_addr = preg_split("/\:/", $sockip);
            $client_ip = $ipv4_addr[0];
        } else {
            $ipv6_addr = explode("]", $sockip);
            $client_ip = substr($ipv6_addr[0], 1);
        }
    } else {
        $client_ip = $client_ip_address;
    }
    if (trim($client_ip == '')) {
        return format_log_date();
    } else {
        return format_log_date() . " [" . $client_ip . "]";
    }
}

function format_log_date()
{
    return date('M d H:i:s');
}
