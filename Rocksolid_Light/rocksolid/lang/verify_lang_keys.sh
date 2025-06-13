#!/bin/bash

# Script to verify language file keys match the codebase usage

REFERENCE_KEYS=$(cat << 'EOF'
$text_article["back_to_group"]
$text_article["block-xnoarchive"]
$text_article["button_answer"]
$text_article["button_cancel"]
$text_article["full_article"]
$text_article["refresh"]
$text_error["article_not_found"]
$text_error["auth_error"]
$text_error["connection_failed"]
$text_error["error:"]
$text_error["post_failed"]
$text_error["read_access_denied"]
$text_header["attachments"]
$text_header["date"]
$text_header["date_format"]
$text_header["followup"]
$text_header["from"]
$text_header["message-id"]
$text_header["newsgroups"]
$text_header["organization"]
$text_header["references"]
$text_header["subject"]
$text_header["user-agent"]
$text_post["button_back"]
$text_post["button_back2"]
$text_post["button_post"]
$text_post["captchafail"]
$text_post["captchainfo1"]
$text_post["captchainfo2"]
$text_post["error_newsserver"]
$text_post["error_readonly"]
$text_post["error_wrong_email"]
$text_post["followup_not_allowed"]
$text_post["group_head"]
$text_post["group_head_reply"]
$text_post["group_tail"]
$text_post["message"]
$text_post["message_posted2"]
$text_post["missing_email"]
$text_post["missing_message"]
$text_post["missing_name"]
$text_post["missing_subject"]
$text_post["name"]
$text_post["password"]
$text_post["quote"]
$text_post["wrote_prefix"]
$text_post["wrote_suffix"]
$text_register["no_access_group"]
$text_thread["author"]
$text_thread["button_grouplist"]
$text_thread["button_latest"]
$text_thread["button_overboard"]
$text_thread["button_search"]
$text_thread["button_write"]
$text_thread["date"]
$text_thread["lastmessage"]
$text_thread["no_articles"]
$text_thread["no_such_group"]
$text_thread["pages"]
$text_thread["subject"]
$text_thread["threadsize"]
EOF
)

if [ $# -eq 0 ]; then
    echo "Usage: $0 <langfile> -verbose"
    echo "Example: $0 english.lang -verbose"
    exit 1
fi

LANGFILE="$1"

if [ ! -f "$LANGFILE" ]; then
    echo "Error: File $LANGFILE not found"
    exit 1
fi

echo "=== Verifying $LANGFILE against codebase usage ==="

# Extract keys from language file
LANG_KEYS=$(grep -o '\$text_[^[]*\[[^]]*\]' "$LANGFILE" | sort | uniq)

# Compare keys
echo "Reference keys from codebase: $(echo "$REFERENCE_KEYS" | wc -l)"
echo "Keys in $LANGFILE: $(echo "$LANG_KEYS" | wc -l)"

# Find missing keys in language file
MISSING_IN_LANG=$(comm -23 <(echo "$REFERENCE_KEYS" | sort) <(echo "$LANG_KEYS" | sort))
if [ -n "$MISSING_IN_LANG" ]; then
    echo ""
    echo "❌ MISSING keys in $LANGFILE:"
    echo "$MISSING_IN_LANG"
    exit 1
else
    echo "✅ All required keys are present"
    test "$2" = "" && exit 0
fi

echo "$2"
if [ "$2" = "-verbose" ]; then
 # Find extra keys in language file
 EXTRA_IN_LANG=$(comm -13 <(echo "$REFERENCE_KEYS" | sort) <(echo "$LANG_KEYS" | sort))
 if [ -n "$EXTRA_IN_LANG" ]; then
     echo ""
     echo "⚠️  EXTRA keys in $LANGFILE (not used in codebase):"
     echo "$EXTRA_IN_LANG"
 else
     echo "✅ No unused keys found"
 fi
 
 # Final verdict
 if [ -z "$MISSING_IN_LANG" ] && [ -z "$EXTRA_IN_LANG" ]; then
     echo ""
     echo "✅ PASS: $LANGFILE matches codebase perfectly"
     exit 0
 else
     echo ""
     echo "❌ FAIL: $LANGFILE has discrepancies"
     exit 1
 fi
fi
