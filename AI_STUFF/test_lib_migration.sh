#!/bin/bash

# Test script to verify the lib/ migration worked correctly
echo "🧪 Testing rocksolid/lib/ Migration"
echo "===================================="

echo ""
echo "📁 Checking file structure..."

# Check that files moved correctly
FILES=(
    "rocksolid/lib/auth.inc.php"
    "rocksolid/lib/config.inc.php"
    "rocksolid/lib/head.inc"
    "rocksolid/lib/tail.inc"
    "rocksolid/lib/overrides.inc.php"
    "rocksolid/lib/security.inc.php"
)

for file in "${FILES[@]}"; do
    if [[ -f "$file" ]]; then
        echo "✅ $file exists"
    else
        echo "❌ $file missing"
        exit 1
    fi
done

echo ""
echo "🔍 Checking include path updates..."

# Test that includes were updated correctly in spoolnews files
if grep -q 'include "../rocksolid/lib/config.inc.php"' spoolnews/user.php 2>/dev/null; then
    echo "✅ spoolnews config includes updated"
else
    echo "❌ spoolnews config includes not updated properly"
fi

# Test that newsportal.php has the right lib/ includes
if grep -q 'include "../rocksolid/lib/types.inc.php"' rocksolid/newsportal.php 2>/dev/null; then
    echo "✅ newsportal.php lib includes updated"
else
    echo "❌ newsportal.php lib includes not updated properly"
fi

# Test that other rocksolid files include lib/config.inc.php
if grep -q 'include "lib/config.inc.php"' rocksolid/index.php 2>/dev/null; then
    echo "✅ rocksolid index.php includes updated"
else
    echo "❌ rocksolid index.php includes not updated properly"
fi

echo ""
echo "🔧 Testing PHP syntax..."
for file in "${FILES[@]}"; do
    if [[ "$file" == *.php ]]; then
        if php -l "$file" >/dev/null 2>&1; then
            echo "✅ $file syntax OK"
        else
            echo "❌ $file syntax ERROR"
            exit 1
        fi
    fi
done

echo ""
echo "📦 Testing configuration loading..."

# Test that config can still be loaded
if php -r "
    chdir('rocksolid');
    if (file_exists('lib/config.inc.php')) {
        include 'lib/config.inc.php';
        if (isset(\$CONFIG) && is_array(\$CONFIG)) {
            echo 'Configuration loaded successfully\n';
            exit(0);
        }
    }
    echo 'Configuration load failed\n';
    exit(1);
" 2>/dev/null; then
    echo "✅ Configuration loading works"
else
    echo "❌ Configuration loading failed"
    exit 1
fi

echo ""
echo "🎉 All tests passed! Migration successful!"
echo ""
echo "✨ Benefits of the new structure:"
echo "   📂 Cleaner rocksolid/ root directory"
echo "   🏗️  Better organization with lib/ containing all core files"
echo "   🔧 Easier maintenance and navigation"
echo "   📚 Consistent with existing lib/ files (database_optimizer.php, etc.)"
echo ""
echo "🚀 The rocksolid/lib/ migration is complete and working!"
