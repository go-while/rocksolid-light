#!/bin/bash

# Generate a secure random site key for Rocksolid Light
# This script creates a 16-character random key using a mix of letters, numbers, and safe symbols

echo "🔐 Rocksolid Light - Secure Site Key Generator"
echo "==============================================="
echo ""

# Generate a 16-character random key
SITE_KEY=$(openssl rand -base64 12 | tr -d "=+/" | cut -c1-16)

echo "Generated secure site key: $SITE_KEY"
echo ""
echo "To update your configuration:"
echo "1. Access the setup page at: http://your-domain/common/setup.php"
echo "2. Find the 'Random security key' field"
echo "3. Replace the current value with: $SITE_KEY"
echo "4. Save the configuration"
echo ""
echo "⚠️  Important: Keep this key secure and don't share it publicly!"
echo "   This key is used for authentication and security purposes."
echo ""

# Also generate some other useful random values
ANON_PASS=$(openssl rand -base64 9 | tr -d "=+/" | cut -c1-12)
LOCAL_PASS=$(openssl rand -base64 12 | tr -d "=+/" | cut -c1-16)

echo "💡 Additional suggested secure passwords:"
echo "   Anonymous user password: $ANON_PASS"
echo "   Local server password:   $LOCAL_PASS"
echo ""
echo "Remember to update these in your configuration as well!"
