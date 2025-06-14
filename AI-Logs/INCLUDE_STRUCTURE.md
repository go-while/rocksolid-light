# Rocksolid Light - Structural Overview of actual INCLUDES

## Root Entry Point: `/index.php`

```php
<?php
session_start();
include "common/config.inc.php";                    // → CONFIG CHAIN
$CONFIG = include($config_dir.'/rslight.inc.php');  // → RSLIGHT CONFIG CHAIN
```

### Include Chain from `/index.php`:

1. **`common/config.inc.php`**
   - Sets up basic configuration variables
   - Defines `$config_dir`, `$config_file`, etc.

2. **`$config_dir/rslight.inc.php`** (via variable)
   - Main application configuration
   - Returns CONFIG array

3. **Referenced but not included directly:**
   - `common/header.php` (loaded via iframe)
   - `common/mods/header.php` (alternative, loaded via iframe)
   - Menu and content pages (loaded via iframe)

---

## Key Discovered Patterns:

### Pattern 1: Iframe Architecture
- Main index.php uses iframes to load components
- Each iframe loads its own includes independently
- This creates isolated include contexts

### Pattern 2: Config Chain
```
index.php → common/config.inc.php → $config_dir/rslight.inc.php
```

### Pattern 3: Conditional Includes
- Checks for `mods/` directory versions first
- Falls back to standard files if mods don't exist

---

## Next to trace:
- [ ] `common/config.inc.php` include chain
- [ ] `common/header.php` include chain
- [ ] `rocksolid/index.php` include chain
- [ ] Cron scripts include chain
