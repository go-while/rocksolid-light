#!/bin/bash
ERRORS=0; GOOD=0;
while IFS="" read -r langfile; do
RES=$(./verify_lang_keys.sh "$langfile";)
RET=$?
if [ $RET -gt 0 ]; then
	echo "ERROR in $RES";	
	let ERRORS="ERRORS+1"
else
	test "$1" = "-verbose" && echo "[✅] $RES"
	let GOOD="GOOD+1"
fi
done< <(ls *.lang)
echo "$0: ERRORS=$ERRORS GOOD=$GOOD LANGFILE=$(ls *.lang|wc -l)"
test $ERRORS -gt 0 && exit 1
exit 0
