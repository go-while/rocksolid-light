#!/bin/bash
# Sync script to update production server with latest changes

SERVER="root@dns2.usenet-server.com"
LOCAL_BASE="/home/fed/GO/src/github.com/go-while/rocksolid-light/Rocksolid_Light"
REMOTE_BASE="/var/www/html"

echo "=== Syncing Rocksolid Light changes to production ==="

echo "1. Backing up current production functions.inc.php..."
ssh $SERVER "cp /etc/rslight/inc/functions.inc.php /etc/rslight/inc/functions.inc.php.backup.$(date +%Y%m%d_%H%M%S)"

echo "2. Copying updated functions.inc.php..."
scp "$LOCAL_BASE/rslight/inc/functions.inc.php" $SERVER:/etc/rslight/inc/

echo "3. Copying updated logging_control.php..."
scp "$LOCAL_BASE/rslight/inc/logging_control.php" $SERVER:/etc/rslight/inc/

echo "4. Copying updated overrides.inc.php..."
scp "$LOCAL_BASE/rslight/inc/overrides.inc.php" $SERVER:/etc/rslight/inc/

echo "5. Copying updated common/config.inc.php..."
scp "$LOCAL_BASE/common/config.inc.php" $SERVER:$REMOTE_BASE/common/

echo "6. Copying updated pages/pages.php (router)..."
scp "$LOCAL_BASE/pages/pages.php" $SERVER:$REMOTE_BASE/pages/

echo "7. Copying updated rocksolid/index.php..."
scp "$LOCAL_BASE/rocksolid/index.php" $SERVER:$REMOTE_BASE/rocksolid/

echo "8. Copying updated logging control script..."
scp "$LOCAL_BASE/rslight/scripts/logging_control.sh" $SERVER:/etc/rslight/scripts/

echo "9. Setting proper permissions..."
ssh $SERVER "
    chown -R www-data:www-data $REMOTE_BASE/
    chmod 644 /etc/rslight/inc/*.php
    chmod 755 /etc/rslight/scripts/*.sh
"

echo "10. Testing the fix..."
ssh $SERVER "cd $REMOTE_BASE && php -l /etc/rslight/inc/functions.inc.php"

if [ $? -eq 0 ]; then
    echo "✅ Syntax check passed"

    echo "11. Quick test of group section lookup..."
    ssh $SERVER "cd $REMOTE_BASE && php /tmp/debug_groups.php | grep 'Found in section' | head -3"

    echo ""
    echo "🎉 Sync complete! The production server should now have:"
    echo "   - Fixed SQLite error handling"
    echo "   - Working fallback mechanisms for last post info"
    echo "   - Reduced log spam"
    echo "   - Groups should now display with last message info"
    echo ""
    echo "You can test by visiting: http://dns2.usenet-server.com/rocksolid/index.php"
else
    echo "❌ Syntax error detected - please check the files"
    exit 1
fi

echo "=== Sync completed ==="
