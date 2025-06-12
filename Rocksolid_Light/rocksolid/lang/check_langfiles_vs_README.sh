#!/bin/bash
ERRORS=0, EXISTS=0;
while IFS="" read -r langfile; do
 RES=$(grep PASS README.md | grep "$langfile");
 E=$?
 test "$1" = "-verbose" && echo "RES: $RES"
 if [ $E -gt 0 ]; then
  echo "$langfile ❌ FAIL: not in README";
  let ERRORS="ERRORS+1"
 else
  test "$1" = "-verbose" && echo "$langfile ✅ PASS: found in README";
  let EXISTS="EXISTS+1"
 fi
done< <(ls *.lang)
echo "$0: ERRORS: $ERRORS | EXISTS: $EXISTS";
