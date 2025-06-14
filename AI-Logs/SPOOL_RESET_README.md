# Rocksolid Light Spool Reset Tools

This directory contains several tools for resetting and cleaning the `/var/spool/rslight` directory easily and safely.

## 🛠️ Available Tools

### 1. `reset_spool.sh` - Full-Featured Interactive Reset Tool
**Recommended for most users**

A comprehensive bash script that provides multiple reset options with safety features:

- **Soft Reset**: Remove cache files and temporary data only
- **Article Reset**: Remove all articles but keep configuration
- **Full Reset**: Remove everything except keys and SSL certificates
- **Nuclear Reset**: Remove EVERYTHING (including keys)
- **Maintenance Options**: Run built-in cleanup functions
- **Disk Usage Analysis**: See what's taking up space

**Usage:**
```bash
sudo ./reset_spool.sh
```

**Features:**
- ✅ Automatic spool directory detection
- ✅ Service management (stops/starts web servers)
- ✅ Automatic backups before destructive operations
- ✅ Permission handling
- ✅ Interactive menu system
- ✅ Safety confirmations

### 2. `quick_reset.php` - PHP-Based Reset Using Built-in Functions
**For advanced users familiar with Rocksolid Light**

Uses the existing maintenance functions from Rocksolid Light:

- Clean orphaned group files
- Clear disk cache
- Reset specific groups
- Reset entire sections
- Remove groups completely
- Show group listings

**Usage:**
```bash
php quick_reset.php
```

**Features:**
- ✅ Uses proven Rocksolid Light functions
- ✅ Group-specific operations
- ✅ Section-wide operations
- ✅ Safe cleanup operations

### 3. `emergency_reset.sh` - Quick Emergency Reset
**For emergency situations when you just need it working**

A simple one-liner that performs the most common reset operations quickly:

**Usage:**
```bash
sudo ./emergency_reset.sh [spool_directory]
```

**Example:**
```bash
sudo ./emergency_reset.sh                    # Uses /var/spool/rslight
sudo ./emergency_reset.sh /custom/spool/dir  # Uses custom directory
```

**What it does:**
- Stops web services
- Removes article databases and cache files
- Recreates directory structure
- Sets proper permissions
- Starts web services

## 🎯 Which Tool Should You Use?

### Use `reset_spool.sh` when:
- You want a safe, guided experience
- You need to see disk usage first
- You want automatic backups
- You're not sure what level of reset you need
- **This is the recommended option for most users**

### Use `quick_reset.php` when:
- You want to reset specific groups or sections
- You prefer using the built-in Rocksolid Light functions
- You need fine-grained control
- You're comfortable with PHP scripts

### Use `emergency_reset.sh` when:
- The system is broken and you need it working quickly
- You know exactly what you want to do
- Other methods aren't working
- You're comfortable with command-line operations

## 🚨 Safety Recommendations

### Before Running Any Reset:

1. **Stop the cron job:**
   ```bash
   # Edit crontab and comment out spoolnews
   sudo crontab -e
   ```

2. **Backup important data:**
   ```bash
   sudo cp /var/spool/rslight/keys.dat /tmp/keys.dat.backup
   ```

3. **Check disk space:**
   ```bash
   df -h /var/spool/rslight
   du -sh /var/spool/rslight
   ```

### After Running a Reset:

1. **Verify keys.dat exists:**
   ```bash
   ls -la /var/spool/rslight/keys.dat
   ```

2. **Test web interface access**

3. **Restart cron job if needed**

4. **Run spoolnews manually to rebuild:**
   ```bash
   cd /path/to/rocksolid/rslight/scripts
   php spoolnews.php
   ```

## 📁 What Gets Reset

### Cache Files (Soft Reset):
- `*-cache.txt` - Thread cache files
- `*-cache.dat` - Group cache files
- `*-groups.dat` - Group list cache
- `*-lastarticleinfo.dat` - Last article info
- `*-overboard.dat` - Overboard cache
- `tmp/*` - Temporary files
- `lock/*` - Lock files

### Article Data (Article Reset):
- `*-articles.db3` - Article databases
- `articles-overview.db3` - Overview database
- `history.db3` - Article history
- `articles/` - Spool article directory
- `*-info.txt` - Thread info files
- `*-data.dat` - Thread data files

### Everything (Full/Nuclear Reset):
- All of the above plus configuration files
- Nuclear also removes `keys.dat` and SSL certificates

## 🔧 Common Use Cases

### "Articles aren't showing up"
```bash
sudo ./reset_spool.sh
# Choose option 1 (Soft Reset)
```

### "I want to start fresh with articles"
```bash
sudo ./reset_spool.sh
# Choose option 2 (Article Reset)
```

### "Something is completely broken"
```bash
sudo ./emergency_reset.sh
```

### "I want to reset just one newsgroup"
```bash
php quick_reset.php
# Choose option 3, enter group name
```

### "Remove a specific newsgroup completely"
```bash
php quick_reset.php
# Choose option 5, enter group name
# Then manually edit groups.txt to remove the group
```

## 🆘 Troubleshooting

### Permission Errors
```bash
# Fix ownership
sudo chown -R www-data:www-data /var/spool/rslight
# or for CentOS/RHEL:
sudo chown -R apache:apache /var/spool/rslight

# Fix permissions
sudo chmod -R 755 /var/spool/rslight
sudo chmod -R 777 /var/spool/rslight/{log,lock,upload,tmp}
```

### Missing keys.dat
```bash
cd /path/to/rocksolid/rslight
php initialize_keys.php
```

### Web Server Not Starting
```bash
# Check status
sudo systemctl status apache2  # or httpd/nginx

# Check logs
sudo journalctl -u apache2 -f
```

### Script Not Found Errors
Make sure you're running the scripts from the Rocksolid Light root directory where they were created.

## 📞 Support

If you encounter issues:

1. Check the Rocksolid Light logs: `/var/spool/rslight/log/`
2. Verify configuration files are intact
3. Ensure proper permissions are set
4. Test with a soft reset first before more destructive options

---

**⚠️ Always backup important data before running reset operations!**
