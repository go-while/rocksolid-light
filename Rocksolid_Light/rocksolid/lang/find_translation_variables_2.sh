#!/bin/bash
find ../../ -name "*.php" -not -path "./lang/*" -print0 | xargs -0 grep -o '\$text_[a-zA-Z_]*\["[^"]*"\]' |cut -d: -f2-|sort|uniq
