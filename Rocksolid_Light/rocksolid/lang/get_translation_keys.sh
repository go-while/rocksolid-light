#!/bin/bash
FILE="*.lang"
test -n "$1" && test -e "$1" && FILE="${1}"
echo "checking translations... $FILE"

while IFS="" read -r langfile; do
	echo -e "\n# Lines: $(wc -l "$langfile")";
	cut -d"]" -f1 "$langfile" |cut -d"[" -f1,2|grep "^\\\$"
done< <(ls "$FILE")
