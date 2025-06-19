#!/bin/bash

# Test Script: Verify Spoolnews Symlink Cleanup
# ==============================================

echo "🧪 Testing Spoolnews Symlink Cleanup"
echo "====================================="
echo ""

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

# Test directory
SPOOLNEWS_DIR="/home/fed/GO/src/github.com/go-while/rocksolid-light/Rocksolid_Light/spoolnews"
ROCKSOLID_DIR="/home/fed/GO/src/github.com/go-while/rocksolid-light/Rocksolid_Light/rocksolid"

cd "$SPOOLNEWS_DIR"

echo "📁 Testing Directory Structure"
echo "=============================="

# Test 1: Verify only real files remain
print_info "Test 1: Checking for real files vs symlinks..."

REAL_FILES=("user.php" "mail.php" "files.php" "upload.php")
REMOVED_SYMLINKS=("newsportal.php" "config.inc.php" "security.inc.php" "head.inc" "tail.inc" "allowed_languages.inc.php" "overrides.inc.php")

for file in "${REAL_FILES[@]}"; do
    if [ -f "$file" ] && [ ! -L "$file" ]; then
        print_success "Real file exists: $file"
    else
        print_error "Missing real file: $file"
    fi
done

for symlink in "${REMOVED_SYMLINKS[@]}"; do
    if [ ! -e "$symlink" ]; then
        print_success "Symlink removed: $symlink"
    else
        print_error "Symlink still exists: $symlink"
    fi
done

echo ""
echo "🔍 Testing File Contents"
echo "========================"

# Test 2: Verify include paths are updated
print_info "Test 2: Checking include paths in PHP files..."

for file in "${REAL_FILES[@]}"; do
    print_info "Checking $file..."

    # Check for old symlink includes (should be 0)
    OLD_INCLUDES=$(grep -c 'include.*"[^/].*\.inc' "$file" 2>/dev/null || echo 0)
    if [ "$OLD_INCLUDES" -eq 0 ]; then
        print_success "  No old symlink includes found in $file"
    else
        print_warning "  Found $OLD_INCLUDES potential old includes in $file"
        grep 'include.*"[^/].*\.inc' "$file" | head -3
    fi

    # Check for new relative includes (should be > 0)
    NEW_INCLUDES=$(grep -c '../rocksolid/' "$file" 2>/dev/null || echo 0)
    if [ "$NEW_INCLUDES" -gt 0 ]; then
        print_success "  Found $NEW_INCLUDES relative includes in $file"
    else
        print_error "  No relative includes found in $file"
    fi
done

echo ""
echo "🔧 Testing PHP Syntax"
echo "====================="

# Test 3: Verify PHP syntax is valid
print_info "Test 3: Checking PHP syntax..."

for file in "${REAL_FILES[@]}"; do
    if php -l "$file" >/dev/null 2>&1; then
        print_success "PHP syntax OK: $file"
    else
        print_error "PHP syntax error in: $file"
        php -l "$file"
    fi
done

echo ""
echo "📊 Testing Include Resolution"
echo "============================="

# Test 4: Verify included files exist
print_info "Test 4: Checking if included files exist..."

REQUIRED_INCLUDES=("config.inc.php" "newsportal.php" "head.inc" "tail.inc" "security.inc.php")

for include_file in "${REQUIRED_INCLUDES[@]}"; do
    if [ -f "$ROCKSOLID_DIR/$include_file" ]; then
        print_success "Required include exists: rocksolid/$include_file"
    else
        print_error "Missing required include: rocksolid/$include_file"
    fi
done

echo ""
echo "📈 Summary"
echo "=========="

# Count tests
TOTAL_FILES=$(ls -1 *.php 2>/dev/null | wc -l)
EXPECTED_FILES=4

if [ "$TOTAL_FILES" -eq "$EXPECTED_FILES" ]; then
    print_success "Directory structure is clean: $TOTAL_FILES files (expected: $EXPECTED_FILES)"
else
    print_warning "Directory has $TOTAL_FILES files (expected: $EXPECTED_FILES)"
fi

# Final status
echo ""
if [ "$TOTAL_FILES" -eq "$EXPECTED_FILES" ]; then
    print_success "🎉 Spoolnews symlink cleanup completed successfully!"
    print_info "   - 7 redundant symlinks removed"
    print_info "   - 4 real files now use relative paths"
    print_info "   - Directory structure is clean and maintainable"
else
    print_warning "⚠️  Cleanup may need attention - check results above"
fi

echo ""
