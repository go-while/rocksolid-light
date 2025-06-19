#!/bin/bash

#############################################################################
# Rocksolid Light Spool Reset Script
# This script provides several options for resetting /var/spool/rslight
#############################################################################

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default spool directory
DEFAULT_SPOOL_DIR="/var/spool/rslight"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CONFIG_DIR="/var/rslight"

# Functions
print_header() {
    echo -e "${BLUE}============================================${NC}"
    echo -e "${BLUE}    Rocksolid Light Spool Reset Tool${NC}"
    echo -e "${BLUE}============================================${NC}"
    echo ""
}

print_warning() {
    echo -e "${RED}⚠️  WARNING: $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_step() {
    echo -e "${YELLOW}🔄 $1${NC}"
}

check_permissions() {
    if [[ $EUID -ne 0 ]]; then
        print_warning "This script requires root privileges to manage spool directories."
        print_info "Please run with sudo: sudo $0"
        exit 1
    fi
}

detect_spool_directory() {
    local spool_dir=""

    # Check common configuration locations
    if [[ -f "$CONFIG_DIR/rslight.inc.php" ]]; then
        spool_dir=$(grep -E "spooldir.*=" "$CONFIG_DIR/rslight.inc.php" 2>/dev/null | head -1 | cut -d'"' -f2 || echo "")
    fi

    if [[ -f "$SCRIPT_DIR/common/config.inc.php" ]]; then
        spool_dir=$(grep -E '\$spooldir.*=' "$SCRIPT_DIR/common/config.inc.php" 2>/dev/null | head -1 | cut -d'"' -f2 || echo "")
    fi

    # Check for placeholder values
    if [[ "$spool_dir" == "<spooldir>" ]] || [[ -z "$spool_dir" ]]; then
        spool_dir="$DEFAULT_SPOOL_DIR"
    fi

    # Verify directory exists
    if [[ ! -d "$spool_dir" ]]; then
        print_warning "Spool directory '$spool_dir' not found!"
        read -p "Enter the correct spool directory path: " spool_dir
        if [[ ! -d "$spool_dir" ]]; then
            print_warning "Directory '$spool_dir' does not exist. Creating it..."
            mkdir -p "$spool_dir"
        fi
    fi

    echo "$spool_dir"
}

stop_services() {
    print_step "Stopping Rocksolid Light services..."

    # Stop common web servers
    for service in apache2 httpd nginx; do
        if systemctl is-active --quiet $service 2>/dev/null; then
            print_info "Stopping $service..."
            systemctl stop $service || true
        fi
    done

    # Kill any running spoolnews processes
    if pgrep -f spoolnews.php >/dev/null; then
        print_info "Stopping spoolnews processes..."
        pkill -f spoolnews.php || true
        sleep 2
    fi

    # Kill any running maintenance processes
    if pgrep -f maintenance.php >/dev/null; then
        print_info "Stopping maintenance processes..."
        pkill -f maintenance.php || true
        sleep 2
    fi
}

start_services() {
    print_step "Starting web services..."

    # Start common web servers that were running
    for service in apache2 httpd nginx; do
        if systemctl is-enabled --quiet $service 2>/dev/null; then
            print_info "Starting $service..."
            systemctl start $service || true
        fi
    done
}

backup_spool() {
    local spool_dir="$1"
    local backup_dir="/tmp/rslight_backup_$(date +%Y%m%d_%H%M%S)"

    print_step "Creating backup of current spool..."
    mkdir -p "$backup_dir"

    # Backup important files only (not huge article databases)
    find "$spool_dir" -maxdepth 2 \( \
        -name "*.conf" -o \
        -name "*.txt" -o \
        -name "*.dat" -o \
        -name "keys.dat" -o \
        -name "*.log" \
    \) -exec cp {} "$backup_dir/" \; 2>/dev/null || true

    print_success "Backup created at: $backup_dir"
    echo "$backup_dir"
}

clean_option_1_soft_reset() {
    local spool_dir="$1"

    print_step "Performing soft reset (cache and temporary files only)..."

    # Remove cache files
    find "$spool_dir" -name "*-cache.txt" -delete 2>/dev/null || true
    find "$spool_dir" -name "*-cache.dat" -delete 2>/dev/null || true
    find "$spool_dir" -name "*-groups.dat" -delete 2>/dev/null || true
    find "$spool_dir" -name "*-lastarticleinfo.dat" -delete 2>/dev/null || true
    find "$spool_dir" -name "*-overboard.dat" -delete 2>/dev/null || true

    # Clean tmp directory
    rm -rf "$spool_dir/tmp/"* 2>/dev/null || true

    # Clean lock files
    rm -rf "$spool_dir/lock/"* 2>/dev/null || true

    # Clean upload temporary files
    find "$spool_dir/upload" -type f -mtime +1 -delete 2>/dev/null || true

    print_success "Soft reset completed"
}

clean_option_2_article_reset() {
    local spool_dir="$1"

    print_step "Performing article reset (removes all articles but keeps config)..."

    # Remove article databases
    find "$spool_dir" -name "*-articles.db3" -delete 2>/dev/null || true
    find "$spool_dir" -name "*-articles.db3-*" -delete 2>/dev/null || true

    # Remove overview database
    rm -f "$spool_dir/articles-overview.db3" 2>/dev/null || true

    # Remove history database
    rm -f "$spool_dir/history.db3" 2>/dev/null || true

    # Remove spool articles directory
    rm -rf "$spool_dir/articles/" 2>/dev/null || true

    # Remove thread info files
    find "$spool_dir" -name "*-info.txt" -delete 2>/dev/null || true
    find "$spool_dir" -name "*-data.dat" -delete 2>/dev/null || true

    # Also do soft reset
    clean_option_1_soft_reset "$spool_dir"

    print_success "Article reset completed"
}

clean_option_3_full_reset() {
    local spool_dir="$1"

    print_step "Performing full reset (removes everything except keys and core config)..."

    # Preserve essential files
    local temp_dir="/tmp/rslight_preserve_$$"
    mkdir -p "$temp_dir"

    # Preserve keys file
    [[ -f "$spool_dir/keys.dat" ]] && cp "$spool_dir/keys.dat" "$temp_dir/"

    # Preserve any SSL certificates
    [[ -d "$spool_dir/ssl" ]] && cp -r "$spool_dir/ssl" "$temp_dir/" 2>/dev/null || true

    # Remove everything
    rm -rf "$spool_dir"/*

    # Restore preserved files
    [[ -f "$temp_dir/keys.dat" ]] && cp "$temp_dir/keys.dat" "$spool_dir/"
    [[ -d "$temp_dir/ssl" ]] && cp -r "$temp_dir/ssl" "$spool_dir/" 2>/dev/null || true

    # Clean up temp
    rm -rf "$temp_dir"

    print_success "Full reset completed"
}

clean_option_4_nuclear() {
    local spool_dir="$1"

    print_step "Performing nuclear reset (removes EVERYTHING including keys)..."

    # Remove everything
    rm -rf "$spool_dir"/*

    print_success "Nuclear reset completed"
}

recreate_structure() {
    local spool_dir="$1"

    print_step "Recreating spool directory structure..."

    # Create essential directories
    mkdir -p "$spool_dir"/{articles,log,lock,upload,tmp}
    mkdir -p "$spool_dir/ssl"

    # Set proper permissions
    chown -R www-data:www-data "$spool_dir" 2>/dev/null || \
    chown -R apache:apache "$spool_dir" 2>/dev/null || \
    chown -R nginx:nginx "$spool_dir" 2>/dev/null || \
    print_info "Could not set web server ownership - you may need to do this manually"

    chmod -R 755 "$spool_dir"
    chmod -R 777 "$spool_dir"/{log,lock,upload,tmp} 2>/dev/null || true

    print_success "Directory structure recreated"
}

initialize_keys() {
    local spool_dir="$1"

    if [[ ! -f "$spool_dir/keys.dat" ]]; then
        print_step "Initializing keys.dat file..."
        if [[ -f "$SCRIPT_DIR/rslight/initialize_keys.php" ]]; then
            cd "$SCRIPT_DIR/rslight"
            php initialize_keys.php
            print_success "Keys initialized"
        else
            print_warning "Could not find initialize_keys.php - you may need to run this manually"
        fi
    fi
}

show_menu() {
    echo ""
    echo "Choose reset option:"
    echo ""
    echo "1) Soft Reset    - Remove cache files and temporary data only"
    echo "2) Article Reset - Remove all articles but keep configuration"
    echo "3) Full Reset    - Remove everything except keys and SSL certificates"
    echo "4) Nuclear Reset - Remove EVERYTHING (including keys and certificates)"
    echo ""
    echo "5) Custom cleanup using maintenance.php"
    echo "6) Show disk usage"
    echo "7) Refresh grouplist cache - Fix message count synchronization"
    echo "8) Exit"
    echo ""
}

run_maintenance_cleanup() {
    print_step "Running maintenance cleanup..."

    if [[ -f "$SCRIPT_DIR/rslight/scripts/maintenance.php" ]]; then
        cd "$SCRIPT_DIR/rslight/scripts"

        echo "Available maintenance options:"
        echo "1) Clean spool (remove orphaned group files)"
        echo "2) Clear disk cache"
        echo "3) Both"
        echo ""
        read -p "Choose option (1-3): " maint_choice

        case $maint_choice in
            1)
                php maintenance.php -clean
                ;;
            2)
                php maintenance.php -clear-diskcache
                ;;
            3)
                php maintenance.php -clean
                php maintenance.php -clear-diskcache
                ;;
            *)
                print_warning "Invalid option"
                return
                ;;
        esac

        print_success "Maintenance cleanup completed"
    else
        print_warning "Maintenance script not found at expected location"
    fi
}

show_disk_usage() {
    local spool_dir="$1"

    print_info "Disk usage analysis for $spool_dir:"
    echo ""

    if [[ -d "$spool_dir" ]]; then
        # Overall size
        echo "Total spool size:"
        du -sh "$spool_dir"
        echo ""

        # Breakdown by type
        echo "Size breakdown:"
        echo "Articles databases:"
        find "$spool_dir" -name "*-articles.db3*" -exec du -ch {} + 2>/dev/null | tail -1 || echo "  None found"

        echo "Articles directory:"
        [[ -d "$spool_dir/articles" ]] && du -sh "$spool_dir/articles" || echo "  None found"

        echo "Log files:"
        [[ -d "$spool_dir/log" ]] && du -sh "$spool_dir/log" || echo "  None found"

        echo "Cache files:"
        find "$spool_dir" -name "*cache*" -exec du -ch {} + 2>/dev/null | tail -1 || echo "  None found"

        echo "Upload directory:"
        [[ -d "$spool_dir/upload" ]] && du -sh "$spool_dir/upload" || echo "  None found"

        echo ""
        echo "Available disk space:"
        df -h "$spool_dir"
    else
        print_warning "Spool directory does not exist: $spool_dir"
    fi
}

refresh_grouplist_cache() {
    local spool_dir="$1"

    print_step "Refreshing grouplist cache to fix message count synchronization..."

    # Remove the main grouplist cache file
    rm -f "$spool_dir/grouplist-cache.txt" 2>/dev/null || true

    # Remove individual group cache files
    find "$spool_dir" -name "*-cache.txt" -delete 2>/dev/null || true
    find "$spool_dir" -name "*-cache.dat" -delete 2>/dev/null || true

    print_success "Cache files removed successfully"
    print_info "Cache will be rebuilt automatically on next web access to the grouplist page"
    print_info "Visit your Rocksolid Light grouplist in your browser to complete the refresh"
}

main() {
    print_header

    check_permissions

    local spool_dir
    spool_dir=$(detect_spool_directory)

    print_info "Using spool directory: $spool_dir"

    while true; do
        show_menu
        read -p "Enter your choice (1-8): " choice

        case $choice in
            1)
                print_warning "This will remove cache and temporary files only."
                read -p "Continue? (y/N): " confirm
                if [[ $confirm =~ ^[Yy]$ ]]; then
                    clean_option_1_soft_reset "$spool_dir"
                fi
                ;;
            2)
                print_warning "This will remove ALL articles and databases but keep configuration."
                read -p "Continue? (y/N): " confirm
                if [[ $confirm =~ ^[Yy]$ ]]; then
                    backup_spool "$spool_dir"
                    stop_services
                    clean_option_2_article_reset "$spool_dir"
                    recreate_structure "$spool_dir"
                    start_services
                fi
                ;;
            3)
                print_warning "This will remove everything except keys and SSL certificates."
                read -p "Continue? (y/N): " confirm
                if [[ $confirm =~ ^[Yy]$ ]]; then
                    backup_spool "$spool_dir"
                    stop_services
                    clean_option_3_full_reset "$spool_dir"
                    recreate_structure "$spool_dir"
                    initialize_keys "$spool_dir"
                    start_services
                fi
                ;;
            4)
                print_warning "⚠️ ⚠️  NUCLEAR OPTION: This will remove EVERYTHING including keys! ⚠️ ⚠️"
                print_warning "You will need to reconfigure everything from scratch!"
                read -p "Are you ABSOLUTELY sure? Type 'NUCLEAR' to confirm: " confirm
                if [[ $confirm == "NUCLEAR" ]]; then
                    backup_spool "$spool_dir"
                    stop_services
                    clean_option_4_nuclear "$spool_dir"
                    recreate_structure "$spool_dir"
                    initialize_keys "$spool_dir"
                    start_services
                fi
                ;;
            5)
                run_maintenance_cleanup
                ;;
            6)
                show_disk_usage "$spool_dir"
                ;;
            7)
                refresh_grouplist_cache "$spool_dir"
                ;;
            8)
                print_info "Exiting..."
                exit 0
                ;;
            *)
                print_warning "Invalid choice. Please select 1-8."
                ;;
        esac

        echo ""
        read -p "Press Enter to continue..."
    done
}

# Run main function
main "$@"
