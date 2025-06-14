# Rocksolid Light - Structural Overview of actual INCLUDES

## REAL WORLD EXECUTION FLOW (from wget test)

```
http://sub.web.local/
  ↓ 302 Redirect
http://sub.web.local/rocksolid/index.php
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

## Actual Entry Point: `/rocksolid/index.php`

**NOT** `/index.php` as initially thought! The root redirects to `/rocksolid/index.php`

### Key Discovery:
- The main entry point is actually `rocksolid/index.php`
- This explains why the include paths work differently than expected
- Debug statements show newsportal.php is loading early
- Config loading is confirmed with "loaded .config.inc.php"

---

## Root Entry Point: `/index.php` (UNUSED)

This file exists but is not the main entry point in production.

```php
<?php
session_start();
include "common/config.inc.php";                    // → CONFIG CHAIN
$CONFIG = include($config_dir.'/rslight.inc.php');  // → RSLIGHT CONFIG CHAIN
```

---

## ACTUAL Entry Point: `/rocksolid/index.php`

Need to trace this file instead since it's the real entry point.

---

## Key Architectural Findings:

### Pattern 1: Redirect Architecture
- Root domain redirects to `/rocksolid/index.php`
- This creates a different include context than expected

### Pattern 2: Debug Output Present
- System has debug statements showing execution flow
- Can be used to trace actual include order

### Pattern 3: Early Newsportal Loading
- Debug shows newsportal code executing very early
- Suggests newsportal.php is included near the top

---

## Next to trace:
- [x] Identify real entry point (rocksolid/index.php)
- [ ] Trace `rocksolid/index.php` include chain
- [ ] Map newsportal.php include chain
- [ ] Document config loading sequence
