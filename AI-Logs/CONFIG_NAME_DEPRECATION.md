# $config_name Deprecation Plan

## The Problem

The `$config_name` variable based on `basename(getcwd())` is fundamentally incompatible with modern routing systems. It assumes the PHP script is physically located in the section directory, but the router system executes from the root web directory.

### Current Behavior:
- **Legacy URLs**: `http://site.com/rocksolid/article.php` → `$config_name = "rocksolid"` ✅
- **Router URLs**: `http://site.com/rocksolid/?page=article-flat` → `$config_name = "html"` ❌

## Critical Discovery: $config_path Dependency Chain

### 🔍 Root Cause Found During Overboard Migration:

**File**: `rocksolid/lib/config.inc.php`
**Problem**: Critical configuration variables depend on deprecated `$config_name`

```php
// ORIGINAL (BROKEN IN ROUTER):
$config_path = $config_dir . $config_name . "/";   // e.g., /etc/rslight/html/
$file_groups = $config_path . "groups.txt";        // e.g., /etc/rslight/html/groups.txt

// PROBLEM: When using router system:
// $config_name = "html" (wrong!)
// $config_path = "/etc/rslight/html/" (wrong path!)
// $file_groups = "/etc/rslight/html/groups.txt" (file doesn't exist!)
```

### 🚨 Cascading Failure Pattern:
1. **Router loads from wrong directory** (`html` instead of section)
2. **Groups file not found** → `$file_groups` empty
3. **Overboard page fails silently** → no content displayed
4. **All section-specific configs fail** → site functionality broken

### ✅ Emergency Fix Applied:
**File**: `rocksolid/lib/config.inc.php`
**Solution**: Moved config path initialization after common config loads

```php
// FIXED: Set config_path without using deprecated $config_name
// For router system, use the main config directory 
$config_path = $config_dir . "/";               // e.g., /etc/rslight/
$script_path = $config_dir . "/scripts/";       // e.g., /etc/rslight/scripts/
$file_groups = $config_path . "groups.txt";     // e.g., /etc/rslight/groups.txt
```

**Result**: 
- ✅ Groups file found: `/etc/rslight/groups.txt`
- ✅ Overboard page loading: Title fixed
- ✅ Configuration variables properly set
- ✅ Router compatibility restored

### 📝 Key Insight:
The `$config_name` deprecation affects **core system infrastructure**, not just UI/logging. Variables like `$config_path`, `$script_path`, and derived paths must be refactored **before** full `$config_name` removal.

### 🎯 Updated Priority:
1. **CRITICAL**: Fix `$config_path` and derived variables (DONE ✅)
2. **HIGH**: Test all pages using these variables  
3. **MEDIUM**: Replace remaining `$config_name` usage
4. **LOW**: Remove `$config_name` declarations entirely

## Impact Analysis

### 251+ Occurrences Found:
1. **Configuration Loading** (45 occurrences)
   - `$config_file = $config_dir . $config_name . '.inc.php'`
   - Section-specific config loading

2. **Logging & Debugging** (120+ occurrences)
   - Used as identifier in log messages
   - Most prevalent usage pattern

3. **Path Construction** (40+ occurrences)
   - `$spooldir . "/" . $config_name . "/"`
   - Database and cache file paths

4. **Navigation & UI** (30+ occurrences)
   - Breadcrumb generation
   - Site branding
   - Section-specific displays

5. **Section Matching** (15+ occurrences)
   - `trim($section) === $config_name`
   - **CRITICAL**: This is causing the redirect bug!

## Immediate Fix Required

**File**: `pages/article-flat.php` lines 56-65
**Problem**: `$config_name = "html"` != `$findsection = "rocksolid"` causes broken redirects

```php
// BROKEN LOGIC:
if (($findsection) && trim($findsection) !== $config_name) {
    // This always triggers when using router because:
    // $config_name = "html" (from getcwd())
    // $findsection = "rocksolid" (from get_section_by_group())
}
```

## Phase 1: Emergency Fix (Immediate) ✅ COMPLETED

**Goal**: Stop the broken redirects
**Approach**: Fix the redirect logic to work with router system

### ✅ Solution Implemented:
**File**: `pages/article-flat.php`
**Fix**: Added router detection to skip problematic redirect

```php
// EMERGENCY FIX: Skip redirect when using router system
$using_router = isset($_GET['page']) || strpos($_SERVER['REQUEST_URI'], '?page=') !== false;

if (($findsection) && trim($findsection) !== $config_name && !$using_router) {
    // Only redirect for legacy direct file access, not router URLs
    // ... redirect logic ...
}
```

### ✅ Result:
- **Before**: `302 Found` with malformed redirect URL
- **After**: `200 OK` with correct page content
- **Production tested**: Both `?page=article-flat` and `?page=faq` working correctly

**The immediate crisis is resolved!** 🎯

## Phase 2: Systematic Replacement (Staged)

### Replace $config_name with Context-Aware Functions:

1. **`get_current_section()`** - Determine section from context
   ```php
   function get_current_section() {
       // Try URL path first (router context)
       if (isset($_SERVER['REQUEST_URI'])) {
           if (preg_match('#/([^/]+)/#', $_SERVER['REQUEST_URI'], $matches)) {
               $section = $matches[1];
               if (is_valid_section($section)) return $section;
           }
       }

       // Fallback to directory method (legacy context)
       $dir_section = basename(getcwd());
       if (is_valid_section($dir_section)) return $dir_section;

       // Default fallback
       return 'rslight';
   }
   ```

2. **`get_section_config_file($section)`** - Get config file for section
3. **`get_section_spool_dir($section)`** - Get spool directory for section
4. **`get_logging_identifier()`** - Get identifier for logging

### Replacement Strategy:
- Replace 10-15 occurrences at a time
- Test thoroughly after each batch
- Start with least critical areas (logging)
- End with most critical areas (config loading)

## Phase 3: Complete Removal (Final)

- Remove all `$config_name = basename(getcwd())` declarations
- Remove related fallback logic
- Update documentation

## Risk Assessment

### HIGH RISK:
- **Config loading**: Could break entire system
- **Database paths**: Could cause data loss
- **Section matching**: Affects content routing

### MEDIUM RISK:
- **Logging**: Affects debugging capability
- **Navigation**: Affects user experience

### LOW RISK:
- **UI branding**: Cosmetic issues only

## Testing Strategy

1. **Unit Tests**: Test new functions in isolation
2. **Integration Tests**: Test router + legacy compatibility
3. **Production Tests**: Gradual rollout with monitoring

## Timeline

- **Phase 1**: 1-2 days (emergency fix)
- **Phase 2**: 2-3 weeks (systematic replacement)
- **Phase 3**: 1 week (cleanup and documentation)

**This is a fundamental architectural change that must be done carefully!**
