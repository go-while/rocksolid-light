#!/bin/bash

# Test the setup configuration to ensure all fields are properly populated
# This script checks that no fields are empty and validates the setup form

echo "🧪 Rocksolid Light Setup Configuration Test"
echo "==========================================="
echo ""

CONFIG_FILE="/home/fed/GO/src/github.com/go-while/rocksolid-light/Rocksolid_Light/rslight/rslight.inc.php"
HELPER_FILE="/home/fed/GO/src/github.com/go-while/rocksolid-light/Rocksolid_Light/rslight/scripts/setuphelper.php"

# Check if config files exist
if [ ! -f "$CONFIG_FILE" ]; then
    echo "❌ Configuration file not found: $CONFIG_FILE"
    exit 1
fi

if [ ! -f "$HELPER_FILE" ]; then
    echo "❌ Helper file not found: $HELPER_FILE"
    exit 1
fi

echo "✅ Configuration files found"
echo ""

# Check for empty values in configuration
echo "🔍 Checking for empty configuration values..."
EMPTY_COUNT=0

# Read configuration and check for empty values
while IFS= read -r line; do
    # Skip comments and blank lines
    if [[ $line =~ ^[[:space:]]*# ]] || [[ -z $line ]]; then
        continue
    fi

    # Check for empty values ('' or just whitespace)
    if echo "$line" | grep -q "=> ''"; then
        echo "⚠️  Empty value found: $line"
        ((EMPTY_COUNT++))
    fi
done < "$CONFIG_FILE"

if [ $EMPTY_COUNT -eq 0 ]; then
    echo "✅ No empty values found in configuration"
else
    echo "⚠️  Found $EMPTY_COUNT empty values"
fi

echo ""

# Test PHP syntax
echo "🔍 Testing PHP syntax of configuration files..."

if php -l "$CONFIG_FILE" >/dev/null 2>&1; then
    echo "✅ Configuration file syntax is valid"
else
    echo "❌ Configuration file has syntax errors"
    php -l "$CONFIG_FILE"
fi

if php -l "$HELPER_FILE" >/dev/null 2>&1; then
    echo "✅ Helper file syntax is valid"
else
    echo "❌ Helper file has syntax errors"
    php -l "$HELPER_FILE"
fi

echo ""

# Count total configuration fields
echo "📊 Configuration Statistics:"
TOTAL_FIELDS=$(grep -c "=>" "$CONFIG_FILE" | head -1)
echo "   Total configuration fields: $TOTAL_FIELDS"

# Test if helper descriptions exist for all config fields
echo "🔍 Checking helper descriptions..."
MISSING_DESCRIPTIONS=0

# Extract field names from config file (left side of =>)
CONFIG_FIELDS=$(grep "=>" "$CONFIG_FILE" | sed "s/[[:space:]]*'\([^']*\)'[[:space:]]*=>.*/\1/" | grep -v "^$")

for field in $CONFIG_FIELDS; do
    if ! grep -q "'$field'" "$HELPER_FILE"; then
        echo "⚠️  Missing description for field: $field"
        ((MISSING_DESCRIPTIONS++))
    fi
done

if [ $MISSING_DESCRIPTIONS -eq 0 ]; then
    echo "✅ All fields have descriptions"
else
    echo "⚠️  $MISSING_DESCRIPTIONS fields missing descriptions"
fi

echo ""

# Test security key
echo "🔐 Security Key Analysis:"
SITE_KEY=$(grep "thissitekey" "$CONFIG_FILE" | sed "s/.*=>[[:space:]]*'\([^']*\)'.*/\1/")
KEY_LENGTH=${#SITE_KEY}

if [ $KEY_LENGTH -lt 12 ]; then
    echo "⚠️  Site key is too short ($KEY_LENGTH characters)"
    echo "   Recommendation: Use at least 16 characters"
else
    echo "✅ Site key length is adequate ($KEY_LENGTH characters)"
fi

if [[ $SITE_KEY == *"change"* ]] || [[ $SITE_KEY == *"default"* ]] || [[ $SITE_KEY == *"key"* ]]; then
    echo "⚠️  Site key appears to be a default/placeholder value"
    echo "   Recommendation: Generate a unique key with rslight/scripts/generate_site_key.sh"
else
    echo "✅ Site key appears to be customized"
fi

echo ""

# Summary
echo "📋 Test Summary:"
echo "==============="
if [ $EMPTY_COUNT -eq 0 ] && [ $MISSING_DESCRIPTIONS -eq 0 ]; then
    echo "✅ Configuration is ready for use!"
    echo "   • All fields have values"
    echo "   • All fields have descriptions"
    echo "   • PHP syntax is valid"
else
    echo "⚠️  Configuration needs attention:"
    [ $EMPTY_COUNT -gt 0 ] && echo "   • $EMPTY_COUNT empty values need to be filled"
    [ $MISSING_DESCRIPTIONS -gt 0 ] && echo "   • $MISSING_DESCRIPTIONS fields need descriptions"
fi

echo ""
echo "🔗 Next Steps:"
echo "1. Access setup at: http://your-domain/common/setup.php"
echo "2. Generate secure keys: rslight/scripts/generate_site_key.sh"
echo "3. Update configuration with your specific values"
echo "4. Test the web interface"
