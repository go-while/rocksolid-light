# New Pages System - Duplicate Code Analysis

## Overview
All files in `pages/` have been moved here to centralize page logic. Each page was previously accessible via symlinks from different directories. This analysis documents the duplicate code patterns found across all page files.

## Common Include Patterns

### Pattern 1: Standard Pages (Relative Includes)
**Files:** article.php, article-flat.php, search.php, post.php, thread.php, overboard.php, language_demo.php, language_selector.php

**Common Header Block:**
```php
<?php
header("Expires: " . gmdate("D, d M Y H:i:s", time() + (120)) . " GMT");
header("Cache-Control: max-age=120");
header("Pragma: cache");

include "lib/config.inc.php";
include "$file_newsportal";  // or include ("$file_newsportal");
require_once(__DIR__ . '/lib/security.inc.php');

// Add security headers
add_security_headers();
```

### Pattern 2: Spoolnews Pages (Absolute Includes)
**Files:** files.php, mail.php, upload.php, user.php

**Common Header Block:**
```php
<?php
session_start();  // Sometimes present

require_once(__DIR__ . '/../rocksolid/lib/config.inc.php');
require_once(__DIR__ . '/../rocksolid/newsportal.php');
require_once(__DIR__ . '/../rocksolid/lib/security.inc.php');

// Add security headers
add_security_headers();
```

### Pattern 3: Register Page (Mixed Approach)
**File:** register.php

**Header Block:**
```php
<?php
require __DIR__ . "/../rocksolid/lib/config.inc.php";
require __DIR__ . "/../rocksolid/newsportal.php";
include "alphabet.inc.php";
require __DIR__ . "/../rocksolid/logging_control.php";
require __DIR__ . "/../rocksolid/lib/security.inc.php";

// Add security headers
add_security_headers();
```

## Duplicate Code Issues

### 1. **Header Management Duplication**
- Every file sets its own cache headers
- Expiration times vary: 30s, 100s, 120s, 3600s, 24h
- Same session_start() calls scattered
- Same security header calls

### 2. **Include Path Inconsistency**
- Some use relative paths: `"lib/config.inc.php"`
- Some use absolute paths: `__DIR__ . '/../rocksolid/lib/config.inc.php'`
- Different approaches to include vs require_once
- Inconsistent newsportal.php loading

### 3. **Security Function Duplication**
- Every file calls `add_security_headers()`
- Every file includes security.inc.php
- Some call `session_start()` manually
- Authentication checks scattered

### 4. **Session Management Duplication**
- Session access tracking repeated:
```php
if (! isset($_SESSION['last_access']) || (time() - $_SESSION['last_access']) > 60) {
    $_SESSION['last_access'] = time();
}
```

### 5. **Configuration Loading Duplication**
- All files load config.inc.php
- Many load $file_newsportal
- Some have additional config includes

## Security Concerns

### 1. **Path Traversal Risk**
- Mixed include approaches create path confusion
- Some files assume working directory context
- Relative paths vulnerable to directory changes

### 2. **Include Order Dependencies**
- Security functions may not be loaded when needed
- Session handling depends on include order
- Configuration may be loaded multiple times

## Cleanup Opportunities

### 1. **Unified Page Headers**
All pages could use a common header that handles:
- Cache headers (configurable per page)
- Security headers
- Session management
- Basic includes

### 2. **Standardized Include Paths**
- Use absolute paths consistently
- Centralize include logic
- Eliminate duplicate config loading

### 3. **Common Security Bootstrap**
- Single point for security initialization
- Consistent session handling
- Unified authentication flow

## Current Symlink Mapping

```
./common/register.php -> ../pages/register.php
./rocksolid/article-flat.php -> ../pages/article-flat.php
./rocksolid/article.php -> ../pages/article.php
./rocksolid/overboard.php -> ../pages/overboard.php
./rocksolid/post.php -> ../pages/post.php
./rocksolid/search.php -> ../pages/search.php
./rocksolid/thread.php -> ../pages/thread.php
./spoolnews/files.php -> ../pages/files.php
./spoolnews/mail.php -> ../pages/mail.php
./spoolnews/upload.php -> ../pages/upload.php
./spoolnews/user.php -> ../pages/user.php
```

## Recommendations

1. **Create pages.php router** - Single entry point with secure page mapping
2. **Standardize includes** - Common bootstrap for all pages
3. **Eliminate duplicates** - Move common code to shared functions
4. **Secure routing** - Hardcoded page mapping without user input parsing
5. **Maintain compatibility** - Preserve existing functionality during transition

## Implementation Notes

The proposed pages.php router should:
- Use hardcoded array mapping: `'article' => 'article.php'`
- Sanitize `$_GET['page']` parameter safely
- Include common headers/security once
- Maintain existing cache/session behavior
- Be includable from config.inc.php when not in CRON_CONTEXT
