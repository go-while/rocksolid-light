#!/bin/bash
while IFS="" read file; do
	egrep -e 'text_.*\[' "$file"
done< <(find ../../ -iname "*.php")
