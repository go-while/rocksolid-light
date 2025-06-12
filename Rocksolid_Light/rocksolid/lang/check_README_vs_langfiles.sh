#!/bin/bash
ERRORS=0; EXISTS=0;
while IFS="" read -r langfile; do
 if [ -e "$langfile" ]; then
	test "$1" = "-verbose" && echo "$langfile ✅ PASS: exists"
	let EXISTS="EXISTS+1"
 else
	 echo "$langfile ❌ FAIL: not found";
	 let ERRORS="ERRORS+1"
 fi
done< <(grep PASS README.md |grep "\.lang"|cut -d" " -f2)
echo "$0: ERRORS: $ERRORS | EXISTS: $EXISTS";
