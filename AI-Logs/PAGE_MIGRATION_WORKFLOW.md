# Page Migration Workflow: Legacy to Router System

## Overview

This document describes the systematic process for migrating legacy PHP pages to work with the new router system.

Each page needs to be converted from standalone execution to router-compatible execution.

### High Priority (Core Functionality):
- ✅ `pages/article-flat.php` - COMPLETED
- ✅ `pages/overboard.php` - COMPLETED (Major NNTP connection overhaul)
- ⏳ `pages/thread.php`
- ⏳ `pages/article.php`
- ⏳ `pages/search.php`
- ⏳ `pages/post.php`

## The Problem

Legacy pages were designed as standalone scripts with their own:
- Config includes (`include 'lib/config.inc.php'`)
- Header includes (`include 'lib/head.inc'`)
- Footer includes (`include 'lib/foot.inc'`)
- Cache headers (`header("Cache-Control: max-age=100")`)
- Function existence checks (`if (function_exists(...))`)

Router pages are loaded via `pages/pages.php` which:
- Already loaded all configs and functions
- Uses centralized header/footer system
- Handles cache headers centrally
- Guarantees function availability

## Step-by-Step Workflow

### Phase 1: Syntax Check & Backup
```bash
# Check current syntax
php -l pages/TARGET_PAGE.php

# Create backup (optional)
cp pages/TARGET_PAGE.php pages/TARGET_PAGE.php.backup
```

### Phase 1A: NNTP Connection Assessment (NEW)
**Critical for pages that use NNTP connections:**
```bash
# Check NNTP configuration
grep -E "remote_server|remote_ssl|socks_host" /etc/rslight/rslight/rslight.inc.php

# Test basic connectivity
telnet news.example.com 119

# Check logs for connection issues
tail -f /var/spool/rslight/log/debug.log
```

**Common NNTP issues:**
- SOCKS proxy configured but not running (Tor on port 9050)
- SSL port configured but server doesn't support SSL
- Connection refused due to firewall/network issues
- php-cli vs php-fpm different network access

### Phase 2: Remove Legacy Includes
Remove these patterns:
```php
// REMOVE:
include "lib/config.inc.php";
include "lib/head.inc";
include "lib/foot.inc";
include "../common/config.inc.php";
```

### Phase 3: Remove Cache Headers
Remove these patterns (router handles them):
```php
// REMOVE:
header("Expires: " . gmdate("D, d M Y H:i:s", time() + (100)) . " GMT");
header("Cache-Control: max-age=100");
header("Pragma: cache");
```

### Phase 4: Replace Header System
Replace:
```php
// OLD PATTERN:
if (function_exists('rslight_render_complete_header')) {
    rslight_render_complete_header($title);
} else {
    include "lib/head.inc";
}

// NEW PATTERN:
rslight_render_complete_header($title);
```

### Phase 5: Replace Footer System
Replace:
```php
// OLD PATTERN:
if (function_exists('rslight_render_complete_footer')) {
    rslight_render_complete_footer();
} else {
    include "lib/foot.inc";
}

// NEW PATTERN:
rslight_render_complete_footer();
```

### Phase 6: Remove Function Existence Checks
Remove all `function_exists()` checks since router guarantees function availability:
```php
// REMOVE:
if (function_exists('some_function')) {
    some_function();
} else {
    // fallback code
}

// REPLACE WITH:
some_function();
```

### Phase 7: Fix Variable Dependencies
Ensure required variables are available. Common missing variables:
- `$logdir` - logging directory
- `$spooldir` - spool directory
- `$config_dir` - config directory
- `$file_*` variables - URL paths

**CRITICAL LESSON: Variable Scope Issues**
Global variables in functions often fail in router context. Use **parameter passing** instead:

```php
// PROBLEMATIC (Global variables):
function display_threads($threads, $oldest) {
    global $snippetlength; // Often fails in router context
    $text = substr($content, 0, $snippetlength);
}

// FIXED (Parameter passing):
function display_threads($threads, $oldest, $snippetlength) {
    $text = substr($content, 0, $snippetlength);
}
```

Update function calls accordingly:
```php
// Update the calls:
$results = display_threads($this_overboard['threads'], $oldest, $snippetlength);
```

### Phase 8: Fix Broken Redirects
Replace problematic `$config_name` logic:
```php
// PROBLEMATIC:
if (($findsection) && trim($findsection) !== $config_name) {
    // redirect logic
}

// FIXED:
$using_router = isset($_GET['page']) || strpos($_SERVER['REQUEST_URI'], '?page=') !== false;
if (($findsection) && trim($findsection) !== $config_name && !$using_router) {
    // redirect logic only for legacy URLs
}
```

### Phase 9: Syntax Check & Test
```bash
# Check syntax after changes
php -l pages/TARGET_PAGE.php

# Deploy to production
./rsync.sh

# Test the page
curl -I "http://SITE.com/SECTION/?page=TARGET_PAGE&param=value"
```

## Common Issues & Solutions

### Issue 1: Missing Variables
**Error**: `Undefined variable $logdir`
**Solution**: Add variable declarations or remove usage

### Issue 2: Syntax Errors
**Error**: `unexpected identifier "href"`
**Solution**: Check for missing quotes, semicolons, concatenation

### Issue 3: Function Not Found
**Error**: `Call to undefined function _rawurldecode()`
**Solution**: Fix function names (`_rawurldecode` → `rawurldecode`)

### Issue 4: NNTP Connection Failures (NEW)
**Error**: "The connection to the Message Server failed"
**Root Causes**:
1. SOCKS proxy configured but not running
2. SSL connection to wrong port
3. Direct TCP connection blocked

**Solution**: Implement robust connection logic in `nntp2_open()`:
```php
// 1. Check memcache circuit breaker first
// 2. Try SSL connection if configured
// 3. Test SOCKS proxy health before using
// 4. Fallback to direct TCP connection
// 5. Mark failed servers in memcache (5 min timeout)
```

### Issue 5: Article Formatting Problems (NEW)
**Error**: Articles display as single line without formatting
**Solution**: Use proper text processing chain:
```php
// WRONG (preserves HTML/breaks):
$formatted_text = htmlentities($text);
$formatted_text = nl2br($formatted_text);

// CORRECT (clean one-line snippets):
$display .= strip_tags(html_parse(text2html(substr($text, 0, $snippetlength))));
```

### Issue 6: Variable Scope Failures (NEW)
**Error**: Variables like `$snippetlength` show as empty
**Root Cause**: Global variables don't work reliably in router context
**Solution**: Convert to parameter passing (see Phase 7)

### Issue 4: Router Rejection
**Error**: `RSLIGHT SECURITY: Invalid page request`
**Solution**: Ensure page is in `$RSLIGHT_PAGE_MAP` in `pages/pages.php`

### Issue 5: Header/Footer Not Working
**Error**: Page renders without proper styling
**Solution**: Ensure header/footer functions are called correctly

## Files Requiring Migration

### High Priority (Core Functionality):
- ✅ `pages/article-flat.php` - COMPLETED
- 🔄 `pages/overboard.php` - IN PROGRESS
- ⏳ `pages/thread.php`
- ⏳ `pages/article.php`
- ⏳ `pages/search.php`
- ⏳ `pages/post.php`

### Medium Priority (User Features):
- ⏳ `pages/register.php`
- ⏳ `pages/user.php`
- ⏳ `pages/mail.php`
- ⏳ `pages/files.php`
- ⏳ `pages/upload.php`

### Low Priority (Admin/Misc):
- ⏳ `pages/language_demo.php`
- ⏳ `pages/language_selector.php`
- ✅ `pages/faq.php` - COMPLETED
- ✅ `pages/header_test.php` - COMPLETED

## Major Breakthroughs (June 2025)

### NNTP Connection Overhaul
- **Problem**: SOCKS proxy configured but not running, SSL connections failing
- **Solution**: 3-tier fallback system (SSL → SOCKS → Direct TCP)
- **Features**: Circuit breaker, health checking, comprehensive logging
- **Impact**: Rock-solid connectivity for all article pages

### Variable Scope Resolution
- **Problem**: Global variables failing in router context (`$snippetlength` empty)
- **Solution**: Parameter passing instead of global declarations
- **Impact**: Reliable variable access across all migrated pages

### Article Formatting Fix
- **Problem**: Mangled text output, missing line breaks
- **Solution**: Proper text processing with `strip_tags(html_parse(text2html()))`
- **Impact**: Clean one-line snippets matching legacy behavior

## Quality Assurance

### Before Migration:
1. Document current page functionality
2. Test current page works correctly
3. Identify all external dependencies

### After Migration:
1. Syntax check passes
2. Page loads without 500 errors
3. Header/footer render correctly
4. All page functionality works
5. No broken links or missing assets

### Production Testing:
1. Test with real data
2. Test error conditions
3. Verify cache headers work correctly
4. Check mobile responsiveness

## Migration Checklist Template

For each page, use this checklist:

```
[ ] Phase 1: Syntax check & backup
[ ] Phase 1A: NNTP connection assessment (if applicable)
[ ] Phase 2: Remove legacy includes
[ ] Phase 3: Remove cache headers
[ ] Phase 4: Replace header system
[ ] Phase 5: Replace footer system
[ ] Phase 6: Remove function existence checks
[ ] Phase 7: Fix variable dependencies (use parameter passing!)
[ ] Phase 8: Fix broken redirects (if any)
[ ] Phase 9: Syntax check & test
[ ] QA: Page loads correctly
[ ] QA: All functionality works
[ ] QA: Article formatting matches legacy (if applicable)
[ ] QA: NNTP connections work reliably (if applicable)
[ ] QA: No broken links
```

## Success Criteria

A successfully migrated page should:
- ✅ Load via `?page=NAME` URLs
- ✅ Have consistent header/footer with other pages
- ✅ Respect cache settings from router
- ✅ Work identically to the legacy version
- ✅ Have clean, maintainable code
- ✅ Pass PHP syntax validation

**This systematic approach ensures reliable, repeatable migrations with minimal risk.**
