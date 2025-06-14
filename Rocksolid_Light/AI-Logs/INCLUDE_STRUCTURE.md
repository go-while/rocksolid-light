# Rocksolid Light - Structural Overview of actual INCLUDES

## 🚨 **ROOT CAUSE DISCOVERED: `basename(getcwd())` ARCHITECTURE**

The entire system is built around **filesystem-based multi-tenancy** where the directory name determines site identity, configuration, and branding.

---

## REAL WORLD EXECUTION FLOW (from wget test)

```
http://dns2.usenet-server.com/
  ↓ 302 Redirect (because $frames_on = false)
http://dns2.usenet-server.com/rocksolid/index.php
  ↓ Actual Entry Point
```

### Debug Output Shows:
```
debug newsportal 01
debug newsportal 02
debug newsportal 03
<!DOCTYPE html>
...
loaded .config.inc.php
```

---

## THE BASENAME(GETCWD()) PATTERN

**Found in 20+ files across the codebase:**

### 1. **Configuration Selection**
```php
// common/header.php & rocksolid/lib/config.inc.php
$config_name = basename(getcwd());  // "rocksolid", "spoolnews", etc.
if (file_exists($config_dir . $config_name . '.inc.php')) {
    $config_file = $config_dir . $config_name . '.inc.php';  // rocksolid.inc.php
} else {
    $config_file = $config_dir . 'rslight.inc.php';         // fallback
}
```

### 2. **Site Branding & Titles**
```php
// rocksolid/index.php
$title .= ' - ' . basename(getcwd());  // "Page - rocksolid"
echo '<h1>' . basename(getcwd()) . '</h1>';  // <h1>rocksolid</h1>
```

### 3. **Navigation Breadcrumbs**
```php
// overboard.php, search.php, thread.php, post.php, article.php
echo '<a href="' . $file_index . '">' . basename(getcwd()) . '</a> / ';
// Creates: "rocksolid / Some Page"
```

---

## ARCHITECTURAL IMPLICATIONS

### ✅ **What This System Intended:**
- Multi-site deployment from single codebase
- Directory-based site isolation
- Automatic config selection per site
- Per-directory branding

### ❌ **Why It Creates Problems:**

1. **Working Directory Dependency**
   - Everything breaks if `getcwd()` changes
   - Cron scripts run from `/etc/rslight/scripts/` (returns "scripts")
   - Include paths assume specific working directories

2. **No Proper Abstraction**
   - Filesystem structure directly drives business logic
   - Hard to test, deploy, or refactor

3. **Include Path Hell**
   - Relative paths work differently in different contexts
   - Scripts must maintain specific working directory assumptions

---

## EXECUTION CONTEXTS

### 1. **Web Context** (Working)
```
getcwd() = /var/www/html/rocksolid/
basename(getcwd()) = "rocksolid"
Config: rocksolid.inc.php (if exists) or rslight.inc.php
```

### 2. **Cron Context** (Problematic)
```
getcwd() = /etc/rslight/scripts/
basename(getcwd()) = "scripts"  // ❌ Wrong!
Config: scripts.inc.php (doesn't exist) or rslight.inc.php
```

### 3. **CLI Context** (Variable)
```
getcwd() = wherever you run the script
basename(getcwd()) = unpredictable
Config: unpredictable
```

---

## ROOT CAUSE OF INCLUDE WARS

The `basename(getcwd())` pattern is the **primary cause** of path resolution issues:

1. **Different working directories** = different site identities
2. **Different site identities** = different config loading
3. **Different config loading** = different path assumptions
4. **Different path assumptions** = broken includes

---

## CURRENT WORKAROUNDS (What Made It Work)

### 1. **Symlinks**
- `/etc/rslight/lib` → `/var/www/html/rocksolid/lib/`
- `/etc/rslight/common` → `/var/www/html/common/`
- Forces consistent relative paths across contexts

### 2. **Dynamic Web Root Calculation**
- Scripts calculate web root from symlinks
- Ensures newsportal.php loaded from correct location

### 3. **__DIR__ Usage**
- Files use `__DIR__` instead of relative paths where possible
- Reduces working directory dependency

---

## LEGACY SYSTEMS FOUND & REMOVED

### 1. **Iframe System** (Disabled)
- `$frames_on = false` in config
- Complex iframe layout code exists but unused
- Root `/index.php` just redirects to `/rocksolid/index.php`

### 2. **Mods System** (Non-existent)
- Code checks for `common/mods/` directory that doesn't exist
- Unnecessary `file_exists()` checks on every page load
- **REMOVED** dead code

### 3. **Session Framing** (Unused)
- `$_SESSION['isframed'] = 1` set but never read
- **REMOVED** dead code

---

## NEXT STEPS

### Phase 1: Document Current Working State ✅
- [x] Identify real entry point (rocksolid/index.php)
- [x] Understand basename(getcwd()) architecture
- [x] Map working directory contexts
- [x] Document workarounds that make it work

### Phase 2: Stabilize (In Progress)
- [ ] Add working directory assertions to critical scripts
- [ ] Document which scripts assume which working directories
- [ ] Create deployment checklist for symlinks
- [ ] Add monitoring for config loading failures

### Phase 3: Future Improvements (30+ Years)
- [ ] Replace basename(getcwd()) with proper site configuration
- [ ] Implement consistent path resolution system
- [ ] Remove filesystem-based multi-tenancy
- [ ] Modern configuration management

---

## CONCLUSION

**The include wars aren't random chaos** - they're the result of a filesystem-based multi-tenant architecture that assumes specific working directory contexts. The system works when those assumptions are met, breaks when they're not.

**Current approach is correct: Use symlinks and workarounds to maintain working directory assumptions rather than fighting the architecture.**
