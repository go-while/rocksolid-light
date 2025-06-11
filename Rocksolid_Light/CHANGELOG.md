# RockSolid Light Newsportal - CHANGELOG

## PHP 8.2+ Compatibility Update - Patch-1 - 2025

### In Memory of Retro Guy

This update is dedicated to **Thomas "Thom" Miller (Retro Guy)** (1954-2025), the creator and lead developer of RockSolid Light, who passed away on April 26, 2025.

---

### Overview

Comprehensive PHP 8.2+ compatibility fixes, deprecated warning removals, and modernization for the RockSolid Light newsportal system, while maintaining backward compatibility and the original architecture.

---

## CHANGES IMPLEMENTED

### 1. PHP 8.2+ Compatibility & Modernization

**Files Modified:**
- `common/grouplist.php`
- `common/header.php`
- `common/register.php`
- `common/setup.php`
- `rocksolid/config.inc.php`
- `rocksolid/lib/post.inc.php`
- `rocksolid/lib/thread.inc.php`
- `rocksolid/lib/types.inc.php`
- `rocksolid/newsportal.php`
- `rocksolid/post.php`
- `rocksolid/search.php`
- `rslight/phpmailer.inc.php`
- `rslight/rslight.inc.php`

**Key Fixes:**
- Added missing parameters to `preg_replace()` and `preg_match()` for PHP 8.2+.
- Fixed undefined variable warnings.
- Added stub functions for optional permission system.
- Added missing class property declarations to prevent dynamic property warnings.
- Updated configuration defaults for posting servers.

---

### 2. PHPMailer 6.10.0 Integration

**Files Added:**
- `rslight/PHPMailer/PHPMailer.php`
- `rslight/PHPMailer/SMTP.php`
- `rslight/PHPMailer/Exception.php`
- `rslight/PHPMailer/LICENSE`
- `rslight/PHPMailer/latest-6.10.0`

**Files Modified:**
- `rslight/phpmailer.inc.php`
- `common/register.php`
- `rocksolid/newsportal.php`

**Key Fixes:**
- Integrated PHPMailer 6.10.0 with namespace support.
- Updated instantiation logic for compatibility with both legacy and namespaced PHPMailer.
- Updated include paths to use local PHPMailer installation.

---

### 3. Captcha System Removal

**Files Removed:**
- `rocksolid/lib/captcha/captcha.php`
- `rocksolid/lib/captcha/_test.1.php`
- `rocksolid/lib/captcha/README`
- `rocksolid/lib/captcha/COLLEGE.ttf`
- `rocksolid/lib/captcha/+cookie.patch`

**Reason:** The captcha system was never fully implemented or enabled, and all related files have been removed for clarity and maintainability.

---

### 4. Documentation

**Files Modified:**
- `README.md` (updated screenshots link and general documentation)

---

## TESTING & VALIDATION

- ✅ All modified files pass PHP 8.2+ syntax checking with no errors.
- ✅ Successfully tested on PHP 8.4.8.
- ✅ Backwards compatible to PHP 7.4+.
- ✅ No parse errors or deprecated warnings.
- ✅ Email and regex functionality fully operational.

---

## SUMMARY

- **Total Files Modified:** 13
- **Files Added:** 5 (PHPMailer)
- **Files Removed:** 5 (Captcha)
- **Major Issues Fixed:** PHP 8.2+ compatibility, PHPMailer upgrade, code modernization, removal of dead captcha code.

---

*Generated: June 11, 2025*
*PHP Version: 8.2+ Compatible*
*Backward Compatible: PHP 7.4+*