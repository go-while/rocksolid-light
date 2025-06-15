echo "=== FINAL ENCODING VERIFICATION 1/3 ===" && \
    echo "1. Checking for corrupted UTF-8 characters (�):" && \
    find ../lang -name "*.lang" -exec grep -l "�" {} \; 2>/dev/null \
    | wc -l && echo "2. Checking for HTML entities:" && \
    find ../lang -name "*.lang" -exec grep -l "&[a-z]*;" {} \; 2>/dev/null | wc -l && \
    echo "3. Checking for suspicious encoding patterns (Ã sequences):" && \
    (grep -l "Ã[^A-Za-z ]" *.lang 2>/dev/null | wc -l || echo "0")

TOTAL_FILES=$(ls ../lang/*.lang 2>/dev/null)
CORRUPT_CHARS=$(grep -l "�" ../lang/*.lang 2>/dev/null)
HTML_ENTS=$(grep -l "&[a-z]*;" ../lang/*.lang 2>/dev/null)
ENCODING_ARTIFACTS=$(grep -l "Ã[^A-Za-z ]" *.lang 2>/dev/null)
echo "=== FINAL ENCODING VERIFICATION 2/2 ===" && \
    echo "Total language files: $(echo -n $TOTAL_FILES|wc -l)" && \
    echo "Files with corrupted characters: $(echo -n $CORRUPT_CHARS|wc -l)" && \
    echo "Files with HTML entities: $(echo -n $HTML_ENTS|wc -l)" && \
    echo "Files with suspicious encoding patterns: $(echo -n $ENCODING_ARTIFACTS|wc -l)"

if [ -z "$CORRUPT_CHARS" ] && [ -z "$HTML_ENTS" ] && [ -z "$ENCODING_ARTIFACTS" ]; then
    echo "✅ Encoding verification PASSED: No corrupted characters, HTML entities, or suspicious encoding patterns found."
else
    echo "❌ Encoding verification FAILED: Issues found in the following files:"
    if [ -n "$CORRUPT_CHARS" ]; then
        echo "Files with corrupted characters:"
        echo "$CORRUPT_CHARS"
    fi
    if [ -n "$HTML_ENTS" ]; then
        echo "Files with HTML entities:"
        echo "$HTML_ENTS"
    fi
    if [ -n "$ENCODING_ARTIFACTS" ]; then
        echo "Files with suspicious encoding patterns:"
        echo "$ENCODING_ARTIFACTS"
    fi
fi
