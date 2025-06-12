#!/bin/bash
echo "$0"
ERRORS=0; EXISTS=0;
while IFS="" read -r langfile; do
 if [ -e "$langfile" ]; then
	test "$1" = "-verbose" && echo "$langfile ✅ PASS: exists"
	let EXISTS="EXISTS+1"
 else
	 echo "$langfile ❌ FAIL: file not found";
	 let ERRORS="ERRORS+1"
 fi
done< <(egrep "^\[.\]\ " README.md |grep "\.lang"|cut -d" " -f2)
echo "$0: ERRORS: $ERRORS | EXISTS: $EXISTS";
