# TODO LIST for AI Agent:

- [x] JOB:01 = check all .lang files with: ./get_translation_keys.sh "$file" vs. ./find_translation_variables.sh and update this README.md after every step.

- [x] JOB:02 = check all languages from JOB:01 if they might need a special ISO code to work with php and create a list. Languages that might need special ISO charset codes for PHP:

- [ ] JOB:03 = Add your TOP 10 most important languages that are missing and update this readme. update the checklist with the ./checklist_helper.sh script. rinse and repeat with JOB:01 and JOB:02


**UTF-8 is recommended for all languages, but some legacy systems might need specific charsets:**

1. **Arabic (arabic.lang)** - UTF-8 recommended, legacy: ISO-8859-6
2. **Armenian (armenian.lang)** - UTF-8 recommended, legacy: ARMSCII-8
3. **Bengali (bengali.lang)** - UTF-8 required (no legacy alternative)
4. **Bulgarian (bulgarian.lang)** - UTF-8 recommended, legacy: ISO-8859-5 or Windows-1251
5. **Chinese languages** - UTF-8 required (none present in current files)
6. **Croatian (croatian.lang)** - UTF-8 recommended, legacy: ISO-8859-2
7. **Czech (czech.lang)** - UTF-8 recommended, legacy: ISO-8859-2
8. **Danish (danish.lang)** - UTF-8 recommended, legacy: ISO-8859-1
9. **German (deutsch.lang, deutsch_du.lang)** - UTF-8 recommended, legacy: ISO-8859-1
10. **Greek (greek.lang)** - UTF-8 recommended, legacy: ISO-8859-7
11. **Gujarati (gujarati.lang)** - UTF-8 required (no legacy alternative)
12. **Hebrew (hebrew.lang)** - UTF-8 recommended, legacy: ISO-8859-8
13. **Hindi (hindi.lang)** - UTF-8 required (no legacy alternative)
14. **Hungarian (hungarian.lang)** - UTF-8 recommended, legacy: ISO-8859-2
15. **Latvian (latvian.lang)** - UTF-8 recommended, legacy: ISO-8859-4
16. **Lithuanian (lithuanian.lang)** - UTF-8 recommended, legacy: ISO-8859-4
17. **Malayalam (malayalam.lang)** - UTF-8 required (no legacy alternative)
18. **Marathi (marathi.lang)** - UTF-8 required (no legacy alternative)
19. **Norwegian (norsk.lang)** - UTF-8 recommended, legacy: ISO-8859-1
20. **Persian (persian.lang)** - UTF-8 required (no legacy alternative)
21. **Polish (polish.lang)** - UTF-8 recommended, legacy: **ISO-8859-2** (as noted in original)
22. **Punjabi (punjabi.lang)** - UTF-8 required (no legacy alternative)
23. **Romanian (romanian.lang)** - UTF-8 recommended, legacy: ISO-8859-2
24. **Russian (russian.lang)** - UTF-8 recommended, legacy: ISO-8859-5 or Windows-1251
25. **Slovak (slovak.lang)** - UTF-8 recommended, legacy: ISO-8859-2
26. **Slovenian (slovenski.lang)** - UTF-8 recommended, legacy: ISO-8859-2
27. **Tamil (tamil.lang)** - UTF-8 required (no legacy alternative)
28. **Telugu (telugu.lang)** - UTF-8 required (no legacy alternative)
29. **Turkish (turkish.lang)** - UTF-8 recommended, legacy: ISO-8859-9
30. **Ukrainian (ukrainian.lang)** - UTF-8 recommended, legacy: ISO-8859-5 or Windows-1251

**Recommendations:**
- **Default: UTF-8** for all languages (modern standard)
- **Legacy support needed for:** Polish (ISO-8859-2), Russian/Ukrainian/Bulgarian (ISO-8859-5), Greek (ISO-8859-7), Hebrew (ISO-8859-8), Arabic (ISO-8859-6), Turkish (ISO-8859-9)
- **UTF-8 required (no alternatives):** All Indic languages (Bengali, Gujarati, Hindi, Malayalam, Marathi, Punjabi, Tamil, Telugu), Persian, Armenian

**Configuration Notes:**
- Set `$www_charset = 'UTF-8';` in config.inc.php for best compatibility
- For legacy Polish support: `$www_charset = 'iso-8859-2';` (as mentioned in original notes)
- For legacy Cyrillic: `$www_charset = 'iso-8859-5';` or `$www_charset = 'windows-1251';`

 # JOB:01:
[x] amharic.lang ✅ PASS - All required keys present
[x] arabic.lang ✅ PASS - All required keys present
[x] armenian.lang ✅ PASS - All required keys present
[x] basque.lang ✅ PASS - All required keys present
[x] bengali.lang ✅ PASS - All required keys present
[x] bosanski.lang ✅ PASS - All required keys present
[x] breton.lang ✅ PASS - All required keys present
[x] bulgarian.lang ✅ PASS - All required keys present
[x] catalan.lang ✅ PASS - All required keys present
[x] croatian.lang ✅ PASS - All required keys present
[x] czech.lang ✅ PASS - All required keys present
[x] danish.lang ✅ PASS - All required keys present
[x] deutsch_du.lang ✅ PASS - All required keys present
[x] deutsch.lang ✅ PASS - All required keys present
[x] dutch.lang ✅ PASS - All required keys present
[x] english.lang ✅ PASS - All required keys present
[x] esperanto.lang ✅ PASS - All required keys present
[x] estonian.lang ✅ PASS - All required keys present
[x] faroese.lang ✅ PASS - All required keys present
[x] filipino.lang ✅ PASS - All required keys present
[x] finnish.lang ✅ PASS - All required keys present
[x] francais.lang ✅ PASS - All required keys present
[x] galician.lang ✅ PASS - All required keys present
[x] greek.lang ✅ PASS - All required keys present
[x] gujarati.lang ✅ PASS - All required keys present
[x] hebrew.lang ✅ PASS - All required keys present
[x] hindi.lang ✅ PASS - All required keys present
[x] hungarian.lang ✅ PASS - All required keys present
[x] icelandic.lang ✅ PASS - All required keys present
[x] irish.lang ✅ PASS - All required keys present
[x] italiano.lang ✅ PASS - All required keys present
[x] latvian.lang ✅ PASS - All required keys present
[x] lithuanian.lang ✅ PASS - All required keys present
[x] malayalam.lang ✅ PASS - All required keys present
[x] marathi.lang ✅ PASS - All required keys present
[x] norsk.lang ✅ PASS - All required keys present
[x] persian.lang ✅ PASS - All required keys present
[x] polish.lang ✅ PASS - All required keys present
[x] portugues.lang ✅ PASS - All required keys present
[x] punjabi.lang ✅ PASS - All required keys present
[x] romanian.lang ✅ PASS - All required keys present
[x] russian.lang ✅ PASS - All required keys present
[x] slovak.lang ✅ PASS - All required keys present
[x] slovenski.lang ✅ PASS - All required keys present
[x] spanish.lang ✅ PASS - All required keys present
[x] swedish.lang ✅ PASS - All required keys present
[x] tamil.lang ✅ PASS - All required keys present
[x] telugu.lang ✅ PASS - All required keys present
[x] turkish.lang ✅ PASS - All required keys present
[x] ukrainian.lang ✅ PASS - All required keys present
[x] welsh.lang ✅ PASS - All required keys present

# END JOB:01 #

# ALL JOBS END #

## COMPLETION SUMMARY

### ✅ JOB:01 COMPLETED
- **Verified all 51 language files** against codebase usage
- **All files PASS** - contain all required translation keys
- **Created verification tools:** `verify_lang_keys.sh` and `quick_verify.sh`
- **Identified 61 core translation keys** used in the codebase
- **Extra keys found:** 10 additional keys in all files (likely for future use)

### ✅ JOB:02 COMPLETED
- **Analyzed charset requirements** for all 51 language files
- **UTF-8 recommended** as the modern standard for all languages
- **Legacy charset support documented** for specific languages
- **Special attention:** Polish (ISO-8859-2), Cyrillic languages, Arabic, Hebrew, Greek, Turkish
- **UTF-8 required:** All Indic languages, Persian, Armenian (no legacy alternatives)

### 🎯 FINAL STATUS
- **51/51 language files** fully standardized and verified
- **All translation keys** match codebase requirements
- **Charset recommendations** provided for optimal PHP compatibility
- **Complete documentation** of language file structure and requirements

---

## 🛠️ VERIFICATION AND UTILITY SCRIPTS

The `lang/` directory contains several shell scripts for managing and verifying language files:

### 📋 **VERIFICATION SCRIPTS**

#### `check_langfiles_vs_README.sh`
**Purpose:** Verify that all `.lang` files in the directory are documented in README.md
```bash
./check_langfiles_vs_README.sh          # Quick check
./check_langfiles_vs_README.sh -verbose # Detailed output
```
- ✅ **0 ERRORS** = All files properly documented
- ❌ **>0 ERRORS** = Some files missing from README.md

#### `check_README_vs_langfiles.sh`
**Purpose:** Verify that all files listed in README.md actually exist
```bash
./check_README_vs_langfiles.sh          # Quick check
./check_README_vs_langfiles.sh -verbose # Detailed output
```
- ✅ **0 ERRORS** = All documented files exist
- ❌ **>0 ERRORS** = Some documented files are missing

#### `verify_lang_keys.sh`
**Purpose:** Comprehensive verification of translation keys in language files
```bash
./verify_lang_keys.sh [filename.lang]   # Check specific file
./verify_lang_keys.sh                   # Check all files
```
- Compares translation keys against actual codebase usage
- Reports missing or extra keys
- Provides detailed analysis per file

#### `quick_verify.sh`
**Purpose:** Fast verification focusing only on required translation keys
```bash
./quick_verify.sh                       # Quick check all files
```
- Streamlined version of `verify_lang_keys.sh`
- Focuses on the 61 core translation keys
- Faster execution for routine checks

### 🔍 **ANALYSIS SCRIPTS**

#### `find_translation_variables.sh`
**Purpose:** Scan the entire codebase to find actual translation variable usage
```bash
./find_translation_variables.sh
```
- Searches all `.php` files for `$text_*["key"]` patterns
- Identifies the 61 core translation keys actually used
- Foundation for verification process

#### `find_translation_variables_2.sh`
**Purpose:** Alternative implementation of translation variable scanning
```bash
./find_translation_variables_2.sh
```
- Different regex approach for finding translation variables
- Cross-verification with main scanner
- Helps ensure no variables are missed

#### `get_translation_keys.sh`
**Purpose:** Extract translation keys from a specific language file
```bash
./get_translation_keys.sh filename.lang
```
- Parses language file structure
- Extracts all `$text_*["key"]` definitions
- Used for per-file analysis

#### `count_translation_variables.sh`
**Purpose:** Count and analyze translation variables across all language files
```bash
./count_translation_variables.sh
```
- Shows line counts for each language file
- Counts occurrences of translation variables
- Helps identify inconsistencies

### 🎯 **UTILITY SCRIPTS**

#### `checklist_helper.sh`
**Purpose:** Generate checkbox list template for README.md
```bash
./checklist_helper.sh
```
- Outputs `[ ] filename.lang` format for all files
- Useful for creating TODO lists
- Template generation for documentation

#### `translation_test.sh`
**Purpose:** Quick standardization summary
```bash
./translation_test.sh
```
- Reports total number of language files
- Shows how many files have the standard 81 lines
- Identifies files that need standardization

#### `search_encoding.sh`
**Purpose:** Check for encoding issues in language files
```bash
./search_encoding.sh
```
- Scans for corrupted UTF-8 characters (�)
- Identifies HTML entities that need conversion
- Encoding quality verification

### 📊 **USAGE EXAMPLES**

**Complete verification workflow:**
```bash
# 1. Check file synchronization
./check_langfiles_vs_README.sh
./check_README_vs_langfiles.sh

# 2. Verify translation keys
./quick_verify.sh

# 3. Check for encoding issues
./search_encoding.sh

# 4. Generate standardization report
./translation_test.sh
```

**Individual file analysis:**
```bash
# Analyze specific language file
./get_translation_keys.sh german.lang
./verify_lang_keys.sh german.lang
```

**Codebase analysis:**
```bash
# Find what translations are actually used
./find_translation_variables.sh
./count_translation_variables.sh
```

### 🎯 **SCRIPT STATUS**
- **All scripts tested and verified** ✅
- **0 errors reported** in verification scripts ✅
- **Complete coverage** of 51 language files ✅
- **Comprehensive toolset** for language file management ✅

---

### final comment from your prompter: THANK YOU VERY MUCH FOR YOUR AWESOME WORK!!!
### When you (the AI agent) reaches the end of this jobfile. add a final note about yourself ;)