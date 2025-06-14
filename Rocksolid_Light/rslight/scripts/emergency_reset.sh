#!/bin/bash

#############################################################################
# Simple Spool Reset - One-liner for emergency situations
#############################################################################

SPOOL_DIR="/var/spool/rslight"

# Check if custom spool directory provided
if [[ -n "$1" ]]; then
    SPOOL_DIR="$1"
fi

echo "🔄 Emergency Spool Reset for: $SPOOL_DIR"
echo ""

if [[ ! -d "$SPOOL_DIR" ]]; then
    echo "❌ Spool directory does not exist: $SPOOL_DIR"
    exit 1
fi

# Stop services
echo "⏸️  Stopping services..."
systemctl stop apache2 2>/dev/null || true
systemctl stop httpd 2>/dev/null || true
systemctl stop nginx 2>/dev/null || true
pkill -f spoolnews.php 2>/dev/null || true

echo "🗑️  Removing article data..."

# Quick cleanup - remove the most common problematic files
rm -rf "$SPOOL_DIR"/articles/ 2>/dev/null || true
rm -f "$SPOOL_DIR"/*-articles.db3* 2>/dev/null || true
rm -f "$SPOOL_DIR"/articles-overview.db3 2>/dev/null || true
rm -f "$SPOOL_DIR"/history.db3 2>/dev/null || true
rm -f "$SPOOL_DIR"/*-cache.txt 2>/dev/null || true
rm -f "$SPOOL_DIR"/*-cache.dat 2>/dev/null || true
rm -f "$SPOOL_DIR"/*-groups.dat 2>/dev/null || true
rm -f "$SPOOL_DIR"/*-info.txt 2>/dev/null || true
rm -f "$SPOOL_DIR"/*-data.dat 2>/dev/null || true
rm -f "$SPOOL_DIR"/*-lastarticleinfo.dat 2>/dev/null || true
rm -f "$SPOOL_DIR"/*-overboard.dat 2>/dev/null || true
rm -rf "$SPOOL_DIR"/tmp/* 2>/dev/null || true
rm -rf "$SPOOL_DIR"/lock/* 2>/dev/null || true

echo "📁 Recreating directory structure..."

# Recreate essential directories
mkdir -p "$SPOOL_DIR"/{articles,log,lock,upload,tmp}

# Set permissions
chown -R www-data:www-data "$SPOOL_DIR" 2>/dev/null || \
chown -R apache:apache "$SPOOL_DIR" 2>/dev/null || \
echo "⚠️  Could not set ownership - you may need to fix permissions manually"

chmod -R 755 "$SPOOL_DIR"
chmod -R 777 "$SPOOL_DIR"/{log,lock,upload,tmp} 2>/dev/null || true

echo "🔄 Starting services..."
systemctl start apache2 2>/dev/null || true
systemctl start httpd 2>/dev/null || true
systemctl start nginx 2>/dev/null || true

echo ""
echo "✅ Emergency reset completed!"
echo ""
echo "Next steps:"
echo "1. Check that keys.dat still exists in $SPOOL_DIR"
echo "2. Run spoolnews manually to rebuild article databases"
echo "3. Check web interface functionality"
echo ""
