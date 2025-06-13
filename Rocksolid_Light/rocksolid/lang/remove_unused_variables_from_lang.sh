#!/bin/bash

# Script to remove unused translation variables from .lang files
# Usage: ./remove_unused_variables_from_lang.sh <langfile>
# This script removes the 10 unused variables identified in the codebase analysis

# Check if argument provided
if [ $# -eq 0 ]; then
    echo "Usage: $0 <langfile>"
    echo "Example: $0 english.lang"
    echo ""
    echo "This script removes unused translation variables from language files."
    echo "It removes 10 variables that are not used in the PHP codebase:"
    echo "  - \$text_error[\"spool_error\"]"
    echo "  - \$text_groups[\"description\"]"
    echo "  - \$text_groups[\"newsgroup\"]"
    echo "  - \$text_post[\"email\"]"
    echo "  - \$text_post[\"message_posted\"]"
    echo "  - \$text_post[\"remember\"]"
    echo "  - \$text_register[\"must_register_group\"]"
    echo "  - \$text_register[\"must_register_post\"]"
    echo "  - \$text_register[\"no_access_post\"]"
    echo "  - \$text_thread[\"button_top\"]"
    exit 1
fi

LANGFILE="$1"

# Check if file exists
if [ ! -f "$LANGFILE" ]; then
    echo "Error: File '$LANGFILE' not found"
    exit 1
fi

# Check if file is a .lang file
if [[ ! "$LANGFILE" =~ \.lang$ ]]; then
    echo "Warning: File '$LANGFILE' does not have .lang extension"
    echo "Continue anyway? (y/N)"
    read -r response
    if [[ ! "$response" =~ ^[Yy]$ ]]; then
        echo "Aborted"
        exit 1
    fi
fi

# Create backup
BACKUP_FILE="${LANGFILE}.backup.$(date +%Y%m%d_%H%M%S)"
cp "$LANGFILE" "$BACKUP_FILE"
echo "Backup created: $BACKUP_FILE"

# Count lines before
LINES_BEFORE=$(wc -l < "$LANGFILE")

# Define the unused variables to remove (these are not used in the PHP codebase)
UNUSED_VARIABLES=(
    '\$text_error\["spool_error"\]'
    '\$text_groups\["description"\]'
    '\$text_groups\["newsgroup"\]'
    '\$text_post\["email"\]'
    '\$text_post\["message_posted"\]'
    '\$text_post\["remember"\]'
    '\$text_register\["must_register_group"\]'
    '\$text_register\["must_register_post"\]'
    '\$text_register\["no_access_post"\]'
    '\$text_thread\["button_top"\]'
)

echo "Removing unused variables from $LANGFILE..."

# Remove each unused variable
REMOVED_COUNT=0
for var in "${UNUSED_VARIABLES[@]}"; do
    # Use grep to check if variable exists in file
    if grep -q "$var" "$LANGFILE"; then
        # Remove the line containing this variable
        sed -i "/$var/d" "$LANGFILE"
        echo "  Removed: $(echo "$var" | sed 's/\\//g')"
        ((REMOVED_COUNT++))
    else
        echo "  Not found: $(echo "$var" | sed 's/\\//g')"
    fi
done

# Count lines after
LINES_AFTER=$(wc -l < "$LANGFILE")
LINES_REMOVED=$((LINES_BEFORE - LINES_AFTER))

echo ""
echo "=== REMOVAL SUMMARY ==="
echo "File: $LANGFILE"
echo "Lines before: $LINES_BEFORE"
echo "Lines after: $LINES_AFTER"
echo "Lines removed: $LINES_REMOVED"
echo "Variables removed: $REMOVED_COUNT"
echo "Backup: $BACKUP_FILE"

if [ $REMOVED_COUNT -gt 0 ]; then
    echo ""
    echo "✅ Successfully removed $REMOVED_COUNT unused variables"
    echo "The file now contains only variables that are actually used in the PHP codebase"

    # Verify the result
    if command -v ./verify_lang_keys.sh &> /dev/null; then
        echo ""
        echo "Running verification check..."
        ./verify_lang_keys.sh "$LANGFILE"
        if [ $? -eq 0 ]; then
            echo "✅ Verification passed - all required keys are still present"
        else
            echo "❌ Verification failed - restoring backup"
            cp "$BACKUP_FILE" "$LANGFILE"
            echo "Original file restored from backup"
        fi
    fi
else
    echo "ℹ️ No unused variables found in this file"
fi

echo ""
echo "Done."
