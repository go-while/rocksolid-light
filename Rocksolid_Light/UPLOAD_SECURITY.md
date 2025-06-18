# RockSolid Light - Enhanced Upload Security

## Overview

This version includes optional security enhancements for file uploads. All security features are **disabled by default** to maintain backward compatibility with existing installations.

## Security Features Available

### 1. File Upload Validation
- **File type validation** - Restrict uploads to specific MIME types
- **File size limits** - Prevent oversized uploads
- **Filename validation** - Check filename length and format

### 2. Upload Monitoring
- **Error log integration** - Log upload attempts to PHP error log
- **Upload statistics** - Track uploads to dedicated log file
- **Access logging** - Monitor file downloads (via .htaccess)

### 3. Directory Protection
- **Script execution prevention** - .htaccess prevents execution of uploaded files
- **Sensitive file blocking** - Blocks access to configuration and log files
- **Optional authentication** - Require login for file downloads

## Configuration

Edit `rocksolid/lib/config.inc.php` and uncomment the desired security settings:

```php
// Enable file upload validation
$CONFIG['validate_file_uploads'] = true;

// Set maximum upload size (5MB)
$CONFIG['max_upload_size'] = 5 * 1024 * 1024;

// Allow only specific file types
$CONFIG['allowed_file_types'] = [
    'image/jpeg', 'image/png', 'image/gif', 'image/webp',
    'text/plain', 'application/pdf'
];

// Enable upload logging
$CONFIG['log_file_uploads'] = true;

// Enable upload statistics tracking
$CONFIG['track_uploads'] = true;
```

## Security Levels

### Level 1: Basic Monitoring (Recommended)
```php
$CONFIG['log_file_uploads'] = true;
$CONFIG['track_uploads'] = true;
```

### Level 2: Size and Type Validation
```php
$CONFIG['validate_file_uploads'] = true;
$CONFIG['max_upload_size'] = 5 * 1024 * 1024;
$CONFIG['allowed_file_types'] = ['image/jpeg', 'image/png', 'text/plain'];
```

### Level 3: Full Security (Restrictive)
Enable all options above plus:
- Configure .htaccess authentication
- Monitor upload logs regularly
- Regular security audits

## File Locations

- **Upload directory**: `spool/upload/`
- **Security config**: `.htaccess` (prevents script execution)
- **Upload logs**: `spool/logs/uploads.log` (if tracking enabled)
- **Error logs**: PHP error log (if logging enabled)

## Backward Compatibility

- **Default behavior unchanged** - All security features disabled by default
- **Existing uploads unaffected** - No changes to file paths or naming
- **Optional adoption** - Administrators choose their security level
- **Gradual rollout** - Test features before full deployment

## Recommendations

1. **Start with monitoring** - Enable logging to understand usage patterns
2. **Test in development** - Verify security settings don't break workflows
3. **Gradual restrictions** - Implement file type/size limits gradually
4. **Monitor logs** - Review upload attempts for suspicious activity
5. **Regular updates** - Keep allowed file types current with needs

## Troubleshooting

### Upload Failures
- Check PHP error log for validation failures
- Verify file types are in allowed list
- Confirm file size is under limit

### Permission Issues
- Ensure `spool/logs/` directory is writable
- Check .htaccess configuration for conflicts
- Verify web server has read access to upload files

## Security Notes

- File validation is not foolproof - malicious files can disguise their type
- Regular security audits are recommended
- Consider antivirus scanning for high-risk environments
- Monitor upload logs for suspicious patterns
- Keep allowed file types as restrictive as practical

## Support

For security questions or issues, consult the RockSolid Light documentation or community forums.
