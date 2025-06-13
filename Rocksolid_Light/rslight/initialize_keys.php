<?php
/**
 * Initialize keys.dat file for RockSolid Light
 *
 * This script creates the initial keys.dat file that stores cryptographic keys
 * used for user authentication and session management. Run this after installation
 * and before accessing the web interface.
 *
 * Usage:
 *   - For production (installed in /etc/rslight/): php initialize_keys.php
 *   - For development: php initialize_keys.php dev
 */

// Determine if we're in production or development mode
$is_production = !isset($argv[1]) || $argv[1] !== 'dev';

if ($is_production) {
    // Production mode: assume we're in /etc/rslight/ directory
    echo "🚀 Running in PRODUCTION mode\n";

    // Configuration file should be in current directory
    $config_file = __DIR__ . '/rslight.inc.php';
    if (!file_exists($config_file)) {
        die("❌ Error: rslight.inc.php not found in " . __DIR__ . "\n" .
            "   Please run this script from the /etc/rslight/ directory.\n" .
            "   Or use 'php initialize_keys.php dev' for development mode.\n");
    }

    // Look for security.inc.php in web directory
    $security_paths = [
        '/var/www/html/rslight/common/security.inc.php',
        '/var/www/html/rslight/rocksolid/security.inc.php',
        '/var/www/html/rslight/spoolnews/security.inc.php'
    ];

    $security_file = null;
    foreach ($security_paths as $path) {
        if (file_exists($path)) {
            $security_file = $path;
            break;
        }
    }

    if (!$security_file) {
        die("❌ Error: security.inc.php not found in expected locations:\n" .
            "   " . implode("\n   ", $security_paths) . "\n");
    }

    echo "📁 Config file: $config_file\n";
    echo "🔒 Security file: $security_file\n";

} else {
    // Development mode: original behavior
    echo "🔧 Running in DEVELOPMENT mode\n";

    if (!file_exists('rslight/rslight.inc.php')) {
        die("❌ Error: rslight.inc.php not found. Please run this from the RockSolid Light root directory.\n");
    }

    $config_file = 'rslight/rslight.inc.php';
    $security_file = 'common/security.inc.php';
}

// Load configuration
$CONFIG = include $config_file;

// Load common config for spooldir in development mode
if (!$is_production) {
    if (file_exists('common/config.inc.php')) {
        include_once 'common/config.inc.php';
    }
}

if (file_exists($security_file)) {
    include_once $security_file;
} else {
    echo "⚠️  Warning: security.inc.php not found, using basic serialization\n";
}

// Determine spool directory
if (!isset($CONFIG)) {
    die("❌ Error: Configuration not loaded properly.\n");
}

// Get spooldir from configuration or global variable
if (isset($CONFIG['spooldir'])) {
    $spooldir = $CONFIG['spooldir'];
} elseif (isset($spooldir)) {
    // $spooldir is set by common/config.inc.php
    if ($spooldir === '<spooldir>') {
        // Development mode fallback
        $spooldir = __DIR__ . '/spool';
        echo "ℹ️  Using development spool directory: $spooldir\n";
    }
} else {
    // Production mode fallback - try common locations
    $spool_locations = [
        '/var/spool/rslight',
        '/var/spool/rocksolid',
        '/tmp/rslight_spool',
        __DIR__ . '/spool'
    ];

    $spooldir = null;
    foreach ($spool_locations as $location) {
        if (is_dir($location) || mkdir($location, 0755, true)) {
            $spooldir = $location;
            echo "ℹ️  Using spool directory: $spooldir\n";
            break;
        }
    }

    if (!$spooldir) {
        die("❌ Error: Cannot determine or create spool directory.\n" .
            "   Tried: " . implode(', ', $spool_locations) . "\n");
    }
}

if (!is_dir($spooldir)) {
    if (!mkdir($spooldir, 0755, true)) {
        die("❌ Error: Spool directory '$spooldir' does not exist and cannot be created.\n");
    }
    echo "📁 Created spool directory: $spooldir\n";
}

echo "📂 Spool directory: $spooldir\n";
$keyfile = $spooldir . '/keys.dat';

// Check if keys file already exists
if (file_exists($keyfile)) {
    echo "ℹ️  Keys file already exists at: $keyfile\n";

    // Try to load existing keys to verify they're valid
    try {
        $keys_content = file_get_contents($keyfile);
        if ($keys_content === false) {
            throw new Exception("Cannot read existing keys file");
        }

        // Try both secure_unserialize and regular unserialize
        $keys = null;
        if (function_exists('secure_unserialize')) {
            try {
                $keys = secure_unserialize($keys_content);
            } catch (Exception $e) {
                echo "ℹ️  secure_unserialize failed, trying standard unserialize...\n";
                $keys = unserialize($keys_content);
            }
        } else {
            $keys = unserialize($keys_content);
        }

        if (is_array($keys) && count($keys) >= 2) {
            echo "✅ Existing keys file is valid with " . count($keys) . " keys\n";
            echo "   Key 0: " . substr($keys[0], 0, 10) . "... (44 bytes)\n";
            echo "   Key 1: " . substr($keys[1], 0, 10) . "... (44 bytes)\n";
            echo "🎉 No action needed - keys file is already properly initialized!\n";
            exit(0);
        } else {
            echo "⚠️  Existing keys file is invalid, recreating...\n";
            echo "   Debug: keys type: " . gettype($keys) . "\n";
            if (is_array($keys)) {
                echo "   Debug: array count: " . count($keys) . "\n";
            }
        }
    } catch (Exception $e) {
        echo "⚠️  Existing keys file is corrupted, recreating...\n";
        echo "   Error: " . $e->getMessage() . "\n";
    }
}

// Create new keys
echo "🔑 Creating new cryptographic keys...\n";

$newkeys = array();
$newkeys[0] = base64_encode(openssl_random_pseudo_bytes(44));
$newkeys[1] = base64_encode(openssl_random_pseudo_bytes(44));

// Save keys to file
$serialized_keys = serialize($newkeys);
if (file_put_contents($keyfile, $serialized_keys) === false) {
    die("❌ Error: Failed to write keys file to $keyfile\n" .
        "   Check permissions on the spool directory.\n");
}

// Set secure permissions - readable only by owner (web server user)
if (!chmod($keyfile, 0600)) {
    echo "⚠️  Warning: Could not set secure permissions on $keyfile\n";
    echo "   Please manually set permissions: chmod 600 $keyfile\n";
}

echo "✅ Keys file created successfully at: $keyfile\n";
echo "   Key 0: " . substr($newkeys[0], 0, 10) . "... (44 bytes, base64)\n";
echo "   Key 1: " . substr($newkeys[1], 0, 10) . "... (44 bytes, base64)\n";
echo "   File size: " . filesize($keyfile) . " bytes\n";
echo "   Permissions: " . substr(sprintf('%o', fileperms($keyfile)), -4) . "\n";

// Verify the file can be read back
try {
    $verify_content = file_get_contents($keyfile);
    if ($verify_content === false) {
        throw new Exception("Cannot read back the keys file");
    }

    // Use the same method for verification as the application will use
    if (function_exists('secure_unserialize')) {
        // Try secure_unserialize first, but fall back to unserialize if it fails
        try {
            $verify_keys = secure_unserialize($verify_content);
        } catch (Exception $e) {
            echo "ℹ️  secure_unserialize failed, trying standard unserialize...\n";
            $verify_keys = unserialize($verify_content);
        }
    } else {
        $verify_keys = unserialize($verify_content);
    }

    if (is_array($verify_keys) && count($verify_keys) === 2) {
        echo "✅ Keys file verification successful\n";
        echo "🎉 Setup complete! You can now access the web interface.\n";
    } else {
        echo "❌ Keys file verification failed - invalid array structure\n";
        echo "   Debug: verify_keys type: " . gettype($verify_keys) . "\n";
        if (is_array($verify_keys)) {
            echo "   Debug: array count: " . count($verify_keys) . "\n";
        }

        // Try to show what we actually got
        echo "   Debug: file contents (first 100 chars): " . substr($verify_content, 0, 100) . "\n";

        // Even if verification fails, let's check if the basic unserialize works
        try {
            $basic_test = unserialize($verify_content);
            if (is_array($basic_test) && count($basic_test) === 2) {
                echo "✅ Basic unserialize works - the keys file is valid!\n";
                echo "   The application should be able to use this keys file.\n";
                echo "🎉 Setup complete! You can now access the web interface.\n";
            }
        } catch (Exception $e) {
            exit(1);
        }
    }
} catch (Exception $e) {
    echo "❌ Keys file verification failed: " . $e->getMessage() . "\n";

    // Try basic unserialize as fallback
    try {
        $verify_content = file_get_contents($keyfile);
        $basic_test = unserialize($verify_content);
        if (is_array($basic_test) && count($basic_test) === 2) {
            echo "✅ But basic unserialize works - the keys file is valid!\n";
            echo "🎉 Setup complete! You can now access the web interface.\n";
        } else {
            exit(1);
        }
    } catch (Exception $e2) {
        echo "❌ Even basic unserialize failed: " . $e2->getMessage() . "\n";
        exit(1);
    }
}

echo "\n📋 NEXT STEPS:\n";
echo "   1. The keys will be automatically rotated every 4 hours by the cron job\n";
echo "   2. Make sure to set up the cron job as documented in the installation guide\n";
echo "   3. Ensure the web server user can read the keys file\n";

if ($is_production) {
    echo "   4. For production, the web interface should now work at your domain\n";
} else {
    echo "   4. For development, you can now test the web interface\n";
}
?>
