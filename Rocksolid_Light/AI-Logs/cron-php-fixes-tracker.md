# Cron.php Fixes Tracker

**Date:** June 14, 2025
**Issue:** Cron script failing with multiple include path and variable initialization errors
**Goal:** Fix all cron.php issues to make it run successfully

## ✅ **FINAL STATUS: SUCCESS!**
**The cron.php script is now working successfully and completing all tasks!**

**Final Test Results:**
```
Updated user count ✅
Sending articles ✅
Refreshed spoolnews ✅
Expired articles ✅
RSS Feeds updated ✅
Refreshed grouplist ✅
Log files rotated ✅
Keys rotated ✅
Removed old files ✅
```

**Latest Fix:** Modified common/head.inc and common/header.php, as well as common/grouplist.php to use __DIR__ in their includes to ensure consistent path resolution regardless of execution context.

**Execution Command:** `cd /etc/rslight/scripts && php cron.php`

## Problem Analysis

### Original Error Log:
```
PHP Warning:  include(config.inc.php): Failed to open stream: No such file or directory in /etc/rslight/scripts/cron.php on line 3
PHP Warning:  include(): Failed opening 'config.inc.php' for inclusion (include_path='.:/usr/share/php') in /etc/rslight/scripts/cron.php on line 3
Warning: security.inc.php not found in any expected location
PHP Warning:  include(rocksolid/newsportal.php): Failed to open stream: No such file or directory in /etc/rslight/scripts/cron.php on line 15
PHP Warning:  include(): Failed opening 'rocksolid/newsportal.php' for inclusion (include_path='.:/usr/share/php') in /etc/rslight/scripts/cron.php on line 15
PHP Warning:  Undefined variable $config_dir in /etc/rslight/scripts/cron.php on line 16
PHP Warning:  Undefined variable $spooldir in /etc/rslight/scripts/cron.php on line 21
PHP Warning:  Undefined variable $config_name in /etc/rslight/scripts/cron.php on line 22
PHP Warning:  Undefined variable $logdir in /etc/rslight/scripts/cron.php on line 25
PHP Fatal error:  Uncaught Error: Call to undefined function get_section_menu_array() in /etc/rslight/scripts/cron.php:39
```

### Root Causes:
1. **Path Resolution Issues**:
   - Script runs from `/etc/rslight/scripts/` but includes expect different working directory
   - Web interface files had incorrect path to security.inc.php (pointing to rocksolid/security.inc.php instead of rocksolid/lib/security.inc.php)
2. **Missing Variables**: `$config_dir`, `$spooldir`, `$config_name`, `$logdir` not initialized
3. **Missing Functions**: `get_section_menu_array()` not available due to failed includes

## Solution Strategy

### Phase 1: Symlink Structure (✅ COMPLETED)
- Created `/etc/rslight/lib -> /var/www/html/rocksolid/lib/`
- Created `/etc/rslight/common -> /var/www/html/common/`
- This allows clean relative paths from cron.php

### Phase 2: Fix Include Paths (✅ IN PROGRESS)

#### Fix 1: Config Include (✅ COMPLETED)
- **Issue**: `include "config.inc.php"` on line 3 fails
- **Solution**: Changed to `include "../lib/config.inc.php"`
- **Status**: ✅ Implemented and synced to server
- **Execution**: Must run from `/etc/rslight/scripts/` directory (not `/etc/rslight/`)
- **Result**: ✅ CONFIG LOADING SUCCESSFULLY - no more config include errors

### Current Status After Fix 1:
```
Warning: security.inc.php not found in any expected location
PHP Warning:  Undefined variable $CONFIG in /etc/rslight/scripts/count_users.php on line 19
PHP Warning:  Undefined variable $spooldir in /etc/rslight/scripts/count_users.php on line 20
Updated user count
PHP Fatal error:  Uncaught Error: Call to undefined function format_log_date() in /etc/rslight/scripts/rslight-lib.php:1439
```

**✅ MAJOR PROGRESS**: Config include working, script now runs much further!

### Current Focus: First 3 Errors (✅ COMPLETED)
~~When running `cd /etc/rslight/scripts && php cron.php`:~~
1. ✅ `Warning: security.inc.php not found in any expected location` - **FIXED**
2. ✅ `PHP Warning: Undefined variable $CONFIG in count_users.php on line 19` - **FIXED**
3. ✅ `PHP Warning: Trying to access array offset on value of type null in count_users.php on line 19` - **FIXED**

#### Fix 2: Security Include (✅ COMPLETED)
- **Issue**: `security_loader.inc.php` exists but can't find `security.inc.php`
- **Solution**: Added `__DIR__ . '/../lib/security.inc.php'` to security_loader.inc.php search paths
- **Status**: ✅ FIXED - security warning gone

#### Fix 3: count_users.php Variable Issues (✅ COMPLETED)
- **Issue**: count_users.php runs as separate script, loses $CONFIG from main cron.php
- **Solution**: Fixed count_users.php to use `../lib/config.inc.php` as first option
- **Status**: ✅ FIXED - CONFIG and array offset errors gone

### Phase 3: Web Interface Scripts Fixed (✅ COMPLETED)

#### Fix 5: Web Interface Security Includes (✅ COMPLETED)
- **Issue**: Many web interface files had incorrect paths to security.inc.php
- **Solution**: Updated all paths to point to `rocksolid/lib/security.inc.php` instead of `rocksolid/security.inc.php`
- **Status**: ✅ FIXED - All includes now correctly reference the lib directory
- **Testing**: ✅ All fixed files validated with PHP lint check on server - No syntax errors detected

##### Updated Files in Common Directory:
- ✅ /common/faq.php - Updated security include path
- ✅ /common/register.php - Fixed security include path (removed duplicate include)
- ✅ /common/setup.php - Updated security include path
- ✅ /common/alphabet.inc.php - Fixed security include path
- ✅ /common/header.php - Already had the correct include path
- ✅ /common/grouplist.php - Already had the correct include path

##### Updated Files in Rocksolid Directory:
- ✅ /rocksolid/language_selector.php - Updated security include path
- ✅ /rocksolid/article.php - Fixed security include path
- ✅ /rocksolid/attachment.php - Updated security include path
- ✅ /rocksolid/article-flat.php - Fixed security include path
- ✅ /rocksolid/thread.php - Updated security include path
- ✅ /rocksolid/post.php - Fixed security include path
- ✅ /rocksolid/index.php - Updated security include path
- ✅ /rocksolid/decrypt.php - Fixed security include path
- ✅ /rocksolid/overboard.php - Updated security include path
- ✅ /rocksolid/search.php - Fixed security include path
- ✅ /rocksolid/newsportal.php - Already had correct include path

#### Fix 4: Newsportal Include (✅ COMPLETED)
- **Issue**: `include "../lib/newsportal.php"` - but newsportal.php is not in lib/
- **Solution**: Implemented dynamic web root calculation to ensure proper includes
- **Status**: ✅ FIXED - newsportal.php now loaded with correct path

#### Fix 5: Additional Includes (✅ COMPLETED)
- **Issue**: `$config_dir` undefined when trying to include rslight-lib.php and gpg.conf
- **Solution**: Ensured config.inc.php properly sets these variables
- **Status**: ✅ FIXED - All required variables now available

### Phase 3: Variable Initialization (✅ COMPLETED)
- All required variables are properly set by config.inc.php
- Added fallbacks where necessary for: `$config_dir`, `$spooldir`, `$config_name`, `$logdir`

## All Issues Resolved (✅ COMPLETED)

1. ✅ **Config Include Path**: Fixed relative paths to use `../lib/config.inc.php`
2. ✅ **Security Include Path**: Updated paths in 10+ files across common/ and rocksolid/ directories
3. ✅ **Variable Availability**: Ensured variables are properly defined and initialized
4. ✅ **Function Availability**: Fixed includes to make all required functions available
5. ✅ **Working Directory Support**: Scripts now run correctly from /etc/rslight/scripts/
6. ✅ **Web Interface Files**: Updated all security includes in web interface files

## Files Modified

### Cron Scripts:
- `/rslight/scripts/cron.php`: Updated config include path
- `/rslight/scripts/count_users.php`: Fixed config include path
- `/rslight/scripts/send.php`, `/rslight/scripts/spoolnews.php`, etc.: Updated all scripts with correct include paths

### Web Interface Files:
- `/common/faq.php`, `/common/register.php`, `/common/setup.php`, `/common/alphabet.inc.php`: Fixed security include paths
- `/rocksolid/language_selector.php`, `/rocksolid/article.php`, `/rocksolid/attachment.php`, etc.: Updated all security include paths

### Server Changes:
- Created symlinks: `/etc/rslight/lib` → `/var/www/html/rocksolid/lib/` and `/etc/rslight/common` → `/var/www/html/common/`
- Updated all scripts with correct include paths
- Verified all files with PHP lint checks

## Next Steps for Future Enhancement (Optional)

1. **Refactor Install Scripts**: Update install/upgrade scripts to create symlinks automatically
2. **Documentation**: Add documentation about the symlink requirements to the installation docs
3. **Path Resolution System**: Consider implementing a more robust path resolution system that doesn't rely on specific directory structures

## Testing Commands

```bash
# Test current state
ssh root@dns2.usenet-server.com "cd /etc/rslight && ./cron.sh"

# Verify symlinks
ssh root@dns2.usenet-server.com "ls -la /etc/rslight/lib /etc/rslight/common"

# Check if config loads
ssh root@dns2.usenet-server.com "cd /etc/rslight/scripts && php -r 'include \"../lib/config.inc.php\"; echo \"Config loaded successfully\\n\";'"
```

---
**Status**: 🔄 In Progress - Phase 2, Fix 1 completed
**Next Action**: Test Fix 1 and identify remaining issues
