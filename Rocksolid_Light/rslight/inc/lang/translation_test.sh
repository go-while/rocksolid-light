#!/bin/bash
echo "=== COMPLETE LANGUAGE FILE STANDARDIZATION SUMMARY ===" && echo "" && echo "Total files: $(ls *.lang | wc -l)" && echo "Files with 81 lines: $(wc -l *.lang | grep " 81 " | wc -l)" && echo "Files with non-81 lines: $(wc -l *.lang | grep -v " 81 " | grep -v "insgesamt" | wc -l)"
