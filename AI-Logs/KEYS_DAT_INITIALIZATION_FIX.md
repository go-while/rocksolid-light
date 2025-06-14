# Keys.dat Initialization Fix

## Problem Summary

The RockSolid Light web interface fails with "Critical Error: Cannot load keys file securely" because the `keys.dat` file doesn't exist in fresh installations. This file stores cryptographic keys used for user authentication and session management.

## Root Cause

The `keys.dat` file is created by the cron job (`rotate_keys()` function), but the web interface tries to load it before the cron job has run. This creates a chicken-and-egg problem in fresh installations.

## Solution

The `initialize_keys.php` script creates the initial `keys.dat` file with the proper format and secure permissions.

## Installation & Usage

### Production Installation (Debian/Ubuntu)

1. **Copy the script to the config directory during installation:**
   ```bash
   # In debian-install.sh, add this line:
   cp initialize_keys.php /etc/rslight/
   ```

2. **Run the script after installation:**
   ```bash
   cd /etc/rslight
   php initialize_keys.php
   ```

### Development Usage

```bash
# From the RockSolid Light root directory:
php initialize_keys.php dev
```

## Script Features

- **Auto-detects environment**: Production vs development mode
- **Path resolution**: Works with both `/etc/rslight/` and development structures
- **Security**: Sets proper file permissions (600)
- **Verification**: Tests that keys can be read back properly
- **Idempotent**: Safe to run multiple times
- **Fallback**: Works even without `secure_unserialize()` function

## Output Example

```
🚀 Running in PRODUCTION mode
📁 Config file: /etc/rslight/rslight.inc.php
🔒 Security file: /var/www/html/rslight/rocksolid/security.inc.php
📂 Spool directory: /var/spool/rslight
🔑 Creating new cryptographic keys...
✅ Keys file created successfully at: /var/spool/rslight/keys.dat
   Key 0: VmF3ZTNPck... (44 bytes, base64)
   Key 1: R2VuZXJhdGV... (44 bytes, base64)
   File size: 156 bytes
   Permissions: 0600
✅ Keys file verification successful
🎉 Setup complete! You can now access the web interface.
```

## Integration with debian-install.sh

Add these lines to the installation script:

```bash
# Copy keys initialization script
cp initialize_keys.php /etc/rslight/

# Initialize keys after spool directory is created
echo "Initializing cryptographic keys..."
cd /etc/rslight
php initialize_keys.php

if [ $? -eq 0 ]; then
    echo "✅ Keys initialized successfully"
else
    echo "❌ Keys initialization failed"
    exit 1
fi
```

## File Structure

The keys.dat file contains a serialized PHP array with 2 base64-encoded random keys:
- Key 0: Current authentication key (44 bytes)
- Key 1: Previous authentication key (44 bytes)

This allows seamless key rotation without breaking existing user sessions.

## Security Notes

- File permissions set to 0600 (readable only by owner)
- Keys are cryptographically secure random data
- Automatic rotation every 4 hours via cron job
- No sensitive data logged or displayed

## Troubleshooting

### "Configuration not loaded properly"
- Ensure `rslight.inc.php` exists in `/etc/rslight/`
- Check file permissions on config files

### "Spool directory does not exist"
- Create the spool directory: `mkdir -p /var/spool/rslight`
- Set proper ownership: `chown www-data:www-data /var/spool/rslight`

### "Failed to write keys file"
- Check spool directory permissions
- Ensure web server user can write to spool directory

### "security.inc.php not found"
- Script will work with basic serialization
- Warning displayed but not fatal
