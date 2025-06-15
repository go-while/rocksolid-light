#!/bin/bash

# Script to migrate configuration and core files to rocksolid/lib/
# This improves organization and reduces clutter in the rocksolid root

echo "🔄 Migrating Core Files to rocksolid/lib/"
echo "========================================="

# Files to migrate
declare -a FILES_TO_MOVE=(
    "auth.inc.php"
    "config.inc.php"
    "head.inc"
    "tail.inc"
    "overrides.inc.php"
    "security.inc.php"
)

# Backup first
echo "📦 Creating backup..."
mkdir -p backup/rocksolid
cp -r rocksolid/ backup/rocksolid/

echo "🚀 Moving files to rocksolid/lib/..."
for file in "${FILES_TO_MOVE[@]}"; do
    if [[ -f "rocksolid/$file" ]]; then
        echo "  Moving rocksolid/$file → rocksolid/lib/$file"
        mv "rocksolid/$file" "rocksolid/lib/$file"
    else
        echo "  ⚠️  File rocksolid/$file not found, skipping"
    fi
done

echo "🔍 Updating include references..."

# Update includes in rocksolid directory
echo "  Updating rocksolid/*.php files..."
find rocksolid/ -name "*.php" -type f -exec sed -i \
    -e 's|include.*"config\.inc\.php"|include "lib/config.inc.php"|g' \
    -e 's|require.*"config\.inc\.php"|require "lib/config.inc.php"|g' \
    -e 's|include.*"auth\.inc\.php"|include "lib/auth.inc.php"|g' \
    -e 's|require.*"auth\.inc\.php"|require "lib/auth.inc.php"|g' \
    -e 's|include.*"security\.inc\.php"|include "lib/security.inc.php"|g' \
    -e 's|require.*"security\.inc\.php"|require "lib/security.inc.php"|g' \
    -e 's|include.*"overrides\.inc\.php"|include "lib/overrides.inc.php"|g' \
    -e 's|require.*"overrides\.inc\.php"|require "lib/overrides.inc.php"|g' \
    {} \;

# Update head.inc and tail.inc references
find rocksolid/ -name "*.php" -type f -exec sed -i \
    -e 's|include.*"head\.inc"|include "lib/head.inc"|g' \
    -e 's|require.*"head\.inc"|require "lib/head.inc"|g' \
    -e 's|include.*"tail\.inc"|include "lib/tail.inc"|g' \
    -e 's|require.*"tail\.inc"|require "lib/tail.inc"|g' \
    {} \;

# Update spoolnews references
echo "  Updating spoolnews/*.php files..."
find spoolnews/ -name "*.php" -type f -exec sed -i \
    -e 's|include.*"../rocksolid/config\.inc\.php"|include "../rocksolid/lib/config.inc.php"|g' \
    -e 's|require.*"../rocksolid/config\.inc\.php"|require "../rocksolid/lib/config.inc.php"|g' \
    -e 's|include.*"../rocksolid/head\.inc"|include "../rocksolid/lib/head.inc"|g' \
    -e 's|require.*"../rocksolid/head\.inc"|require "../rocksolid/lib/head.inc"|g' \
    -e 's|include.*"../rocksolid/tail\.inc"|include "../rocksolid/lib/tail.inc"|g' \
    -e 's|require.*"../rocksolid/tail\.inc"|require "../rocksolid/lib/tail.inc"|g' \
    {} \;

# Update common references
echo "  Updating common/*.php files..."
find common/ -name "*.php" -type f -exec sed -i \
    -e 's|include.*"../rocksolid/config\.inc\.php"|include "../rocksolid/lib/config.inc.php"|g' \
    -e 's|require.*"../rocksolid/config\.inc\.php"|require "../rocksolid/lib/config.inc.php"|g' \
    {} \;

# Update __DIR__ relative references in the moved files themselves
echo "  Updating relative paths in moved files..."
sed -i 's|__DIR__ \. "/overrides\.inc\.php"|__DIR__ . "/overrides.inc.php"|g' rocksolid/lib/config.inc.php
sed -i 's|__DIR__ \. "/../overrides\.inc\.php"|__DIR__ . "/overrides.inc.php"|g' rocksolid/lib/config.inc.php

echo "🧪 Testing the migration..."

# Basic syntax check
echo "  Checking PHP syntax..."
for file in "${FILES_TO_MOVE[@]}"; do
    if [[ -f "rocksolid/lib/$file" && "$file" == *.php ]]; then
        if php -l "rocksolid/lib/$file" >/dev/null 2>&1; then
            echo "    ✅ rocksolid/lib/$file syntax OK"
        else
            echo "    ❌ rocksolid/lib/$file syntax ERROR"
        fi
    fi
done

echo ""
echo "✅ Migration complete!"
echo ""
echo "📁 New structure:"
echo "   rocksolid/lib/auth.inc.php     - Authentication functions"
echo "   rocksolid/lib/config.inc.php   - Main configuration loader"
echo "   rocksolid/lib/head.inc         - HTML header template"
echo "   rocksolid/lib/tail.inc         - HTML footer template"
echo "   rocksolid/lib/overrides.inc.php - Configuration overrides"
echo "   rocksolid/lib/security.inc.php - Security functions"
echo ""
echo "🔄 To test: Visit your site and verify everything works"
echo "📦 To rollback: rm -rf rocksolid/ && cp -r backup/rocksolid/ ./"
echo ""
echo "🚀 Ready for clean, organized codebase!"
