<?php
# To use a modified config for other 'sections', copy
# this ENTIRE file to a new file in this directory
# named as the section name followed by .inc.php
# So for a section named 'rocksolid', it's rocksolid.inc.php

return [
# REMOTE server configuration
'remote_server' => 'The remote news server you connect to for syncing (e.g., news.example.com)',
'remote_port' => 'Remote server port (usually 119 for NNTP)',
'remote_ssl' => 'Remote SSL server port (usually 563, blank to disable SSL)',
'remote_auth_user' => 'Username to authenticate to remote server (if required)',
'remote_auth_pass' => 'Password to authenticate to remote server (if required)',
'socks_host' => 'IP address of your SOCKS4A server (e.g., 127.0.0.1 for Tor)',
'socks_port' => 'Port for your SOCKS4A server (e.g., 9050 for Tor)',

# LOCAL server configuration
'enable_nntp' => 'Enable local NNTP server (1=yes, blank=no)',
'local_server' => 'Local server IP address (127.0.0.1 for localhost)',
'local_port' => 'Local server port (usually 119)',
'local_ssl_port' => 'Local server SSL port (usually 563, blank for no SSL)',
'enable_all_networks' => 'Bind local server to all interfaces (1=yes, blank=localhost only)',
'server_auth_user' => 'Username for local server authentication (auto-created)',
'server_auth_pass' => 'Password for local server user (choose a strong password)',

# Site configuration
'site_shortname' => 'Short name for your site (used in paths and references)',
'rslight_title' => 'The tagline displayed at the top of web pages',
'title_full' => 'The full site title shown in browser tab',
'hide_email' => 'Truncate email addresses in From header (1=yes, blank=no)',
'server_path' => 'Domain for Message-ID header (include @, e.g., @example.com)',
'email_tail' => 'Domain to add to usernames without @ (e.g., @invalid.invalid)',
'anonusername' => 'Username for anonymous posting (auto-created)',
'anonuserpass' => 'Password for anonymous user (choose a secure password)',
'timezone' => 'Timezone offset from GMT (+5, -3, etc. or 0 for UTC)',
'default_content' => 'Default page to display (/rocksolid/index.php recommended)',
'readonly' => 'Make site read-only (1=yes, blank=allow posting)',
'post_server' => 'Posting server hostname (for outgoing posts, blank for local only)',
'post_port' => 'Posting server port (usually 119, blank for local only)',
'anonuser' => 'Allow anonymous posting (1=yes, blank=require authentication)',
'organization' => 'Organization name for outgoing message headers',
'postfooter' => 'Text to append to posted messages (blank for none)',
'synchronet' => 'Enable Synchronet compatibility (1=yes, blank=no)',
'rate_limit' => 'Maximum posts per user per hour (number or blank for no limit)',
'auto_create' => 'Auto-create user accounts when posting (1=yes, blank=no)',
'verify_email' => 'Require email verification for new users (1=yes, blank=no)',
'no_verify' => 'Domains that skip email verification (space separated)',
'auto_return' => 'Return to group after posting (1=yes, blank=stay on post page)',
'overboard_noshow' => 'Groups to exclude from overboard (space separated)',

# Spamassassin configuration
'spamassassin' => 'Enable SpamAssassin checking (1=yes, blank=no)',
'spamc' => 'Path to spamc executable (/usr/bin/spamc or just spamc)',
'spamgroup' => 'Newsgroup for spam messages (e.g., spam)',

# System executables
'php_exec' => 'Path to PHP executable (/usr/bin/php or just php)',
'tac' => 'Path to PHP session files (for user count, /tmp recommended)',
'webserver_user' => 'Web server user (www-data, apache, nginx, etc.)',

# NOCEM configuration
'enable_nocem' => 'Enable NoCeM spam filtering (1=yes, blank=no)',
'nocem_groups' => 'Groups to monitor for NoCeM messages (space separated)',

# Miscellaneous settings
'expire_days' => 'Days to keep posts (0=never expire, 90=3 months recommended)',
'pathhost' => 'Hostname for XRef headers (short site name)',
'article_database' => 'Store articles in database (1=database, blank=traditional spool)',
'open_clients' => 'IP addresses allowed to post without auth (space separated)',
'thissitekey' => 'Random security key for your site (16+ characters, change this!)'
];
?>
