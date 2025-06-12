#!/bin/bash
echo "checking translations..."

while IFS="" read -r langfile; do
	echo -e "\n# Lines: $(wc -l "$langfile")";
	cut -d"]" -f1 "$langfile" |cut -d"[" -f1 | sort|uniq -c|egrep "\\$"
done< <(ls *.lang)
