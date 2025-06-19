# Rocksolid Light Setup Configuration Guide

## Overview
This guide helps you configure Rocksolid Light's empty setup fields with appropriate values. The configuration is accessible at `http://your-domain/common/setup.php`.

## Quick Setup Checklist

### ✅ **Essential Configuration Fields**

#### **Remote Server Configuration**
- **Remote server**: `news.example.com` → Replace with your news provider
- **Remote port**: `119` (standard NNTP) or `563` (SSL)
- **Remote SSL port**: `563` (recommended for security)
- **Username/Password**: Credentials from your news provider

#### **Local Server Settings**
- **Enable local NNTP**: `1` (recommended)
- **Local server IP**: `127.0.0.1` (localhost)
- **Local port**: `119`
- **Local SSL port**: `563` (optional)
- **Server auth user**: `rslight` (auto-created)
- **Server auth password**: Use generated secure password

#### **Site Identity**
- **Site title**: `Your News Site Name`
- **Title full**: `Your Site - NNTP Web Interface`
- **Organization**: `Your Organization Name`
- **Server path**: `@yourdomain.com`
- **Pathhost**: `yoursite` (short identifier)

#### **Security Settings**
- **Site key**: Use `rslight/scripts/generate_site_key.sh` to generate
- **Anonymous password**: Generate secure password
- **Hide email**: `1` (recommended for privacy)

### ✅ **Recommended Default Values**

```bash
# Basic site configuration
rslight_title = "Rocksolid Light News Server"
title_full = "Rocksolid Light - NNTP Web Interface"
organization = "Your Organization Name"
postfooter = "Posted via Rocksolid Light"

# Security and privacy
hide_email = "1"
anonuser = "1"
auto_create = "1"
verify_email = "1"
rate_limit = "10"

# System paths (adjust for your system)
php_exec = "/usr/bin/php"
webserver_user = "www-data"  # or "apache", "nginx"
tac = "/tmp"

# Post management
expire_days = "90"  # Keep posts for 3 months
auto_return = "1"   # Return to group after posting
```

### ✅ **Advanced Configuration**

#### **Tor/SOCKS Support** (Optional)
```bash
socks_host = "127.0.0.1"
socks_port = "9050"
```

#### **SpamAssassin Integration** (Optional)
```bash
spamassassin = "1"
spamc = "/usr/bin/spamc"
spamgroup = "spam"
```

#### **Email Verification Bypass**
```bash
no_verify = ".i2p .onion localhost"
```

## 🔧 **Configuration Steps**

### 1. **Generate Secure Keys**
```bash
rslight/scripts/generate_site_key.sh
```
This generates:
- Site security key
- Anonymous user password
- Local server password

### 2. **Access Setup Page**
- Navigate to: `http://your-domain/common/setup.php`
- Enter admin password
- Fill in the configuration fields

### 3. **Essential Fields to Update**
1. **Remote server details** - Your news provider info
2. **Site identity** - Titles, organization, domain
3. **Security keys** - Use generated secure values
4. **System paths** - Adjust for your server setup

### 4. **Test Configuration**
- Save configuration
- Test posting and reading
- Check error logs for issues

## 📋 **Common Configuration Examples**

### **Public News Server**
```bash
anonuser = "1"          # Allow anonymous posting
readonly = ""           # Allow posting
rate_limit = "5"        # Moderate rate limiting
verify_email = "1"      # Require email verification
```

### **Private/Internal Server**
```bash
anonuser = ""           # Require authentication
auto_create = ""        # Manual user creation
rate_limit = ""         # No rate limiting
verify_email = ""       # No email requirement
```

### **Read-Only Archive**
```bash
readonly = "1"          # No posting allowed
anonuser = ""           # No anonymous access
expire_days = "0"       # Keep all posts
```

## ⚠️ **Security Recommendations**

1. **Always change default passwords**
2. **Use SSL/TLS when possible** (`remote_ssl = "563"`)
3. **Generate unique site key** (use `rslight/scripts/generate_site_key.sh`)
4. **Enable email verification** for public sites
5. **Set appropriate rate limits** to prevent abuse
6. **Hide email addresses** (`hide_email = "1"`)

## 🔍 **Troubleshooting**

### **Empty Fields Issue**
- **Cause**: Configuration file has empty values
- **Solution**: Use the updated `rslight.inc.php` with default values

### **Setup Page Not Loading**
- Check file permissions on config files
- Verify admin password in `admin.inc.php`
- Check web server error logs

### **Configuration Not Saving**
- Ensure config directory is writable
- Check PHP error logs
- Verify admin key matches

## 📁 **Files Modified**
- `/rslight/rslight.inc.php` - Main configuration
- `/rslight/scripts/setuphelper.php` - Field descriptions
- `rslight/scripts/generate_site_key.sh` - Security key generator

## 🚀 **Next Steps After Configuration**
1. Test the web interface
2. Configure news groups
3. Set up user accounts
4. Test posting and reading
5. Configure backup and maintenance

---
**Configuration completed**: Your Rocksolid Light setup should now have meaningful default values and clear descriptions for all fields!
