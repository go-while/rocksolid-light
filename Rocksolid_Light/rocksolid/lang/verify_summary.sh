#!/bin/bash
LANGKEYS_IN_CODE=61
echo "=== VERIFICATION SUMMARY ===" && \
	echo "Total files: $(ls *.lang | wc -l)" && \
	echo "Files with 61 keys: $(for file in *.lang; do ./verify_lang_keys.sh "$file" 2>/dev/null | \
	grep -c "Keys.*: 61"; done | grep -c 1)" && \
	echo "Files with other key counts: $(for file in *.lang; do 
	   keys=$(./verify_lang_keys.sh "$file" 2>/dev/null | \
	   grep "Keys.*:" | grep -o "[0-9]\+"); 
	   if [[ "$keys" != "$LANGKEYS_IN_CODE" ]]; then 
	      echo "$file: $keys keys"; 
	   fi; 
        done | wc -l)"
