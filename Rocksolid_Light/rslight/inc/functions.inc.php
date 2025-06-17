<?php
/*
 * NNTP<->HTTP Gateway
 * Download: https://news.novabbs.com/get
 *
 * Based on Newsportal by Florian Amrhein
 *
 * E-Mail: retroguy@novabbs.com
 * Web: https://news.novabbs.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */


$alphabet = array('K', 'g', 'A', 'D', 'R', 'V', 's', 'L', 'Q', 'w');
$alphabetsForNumbers = array(
    array('K', 'g', 'A', 'D', 'R', 'V', 's', 'L', 'Q', 'w'),
    array('M', 'R', 'o', 'F', 'd', 'X', 'z', 'a', 'K', 'L'),
    array('H', 'Q', 'O', 'T', 'A', 'B', 'C', 'D', 'e', 'F'),
    array('T', 'A', 'p', 'H', 'j', 'k', 'l', 'z', 'x', 'v'),
    array('f', 'b', 'P', 'q', 'w', 'e', 'K', 'N', 'M', 'V'),
    array('i', 'c', 'Z', 'x', 'W', 'E', 'g', 'h', 'n', 'm'),
    array('O', 'd', 'q', 'a', 'Z', 'X', 'C', 'b', 't', 'g'),
    array('p', 'E', 'J', 'k', 'L', 'A', 'S', 'Q', 'W', 'T'),
    array('f', 'W', 'C', 'G', 'j', 'I', 'O', 'P', 'Q', 'D'),
    array('A', 'g', 'n', 'm', 'd', 'w', 'u', 'y', 'x', 'r')
);

function check_unread_mail()
{
    global $CONFIG, $spooldir;
    if (isset($_COOKIE['mail_name'])) {
        $name = strtolower($_COOKIE['mail_name']);
        $database = $spooldir . '/mail.db3';
        if (is_file($database)) {
            $dbh = head_mail_db_open($database);
            $query = $dbh->prepare('SELECT * FROM messages where rcpt_to=:rcpt_to');
            $query->execute([
                'rcpt_to' => $name
            ]);
            $newmail = false;
            while (($row = $query->fetch()) !== false) {
                if (($row['rcpt_viewed'] != 'true') && ($row['to_hide'] != 'true')) {
                    $newmail = true;
                }
            }
            $dbh = null;
            return $newmail;
        } else {
            return false;
        }
    }
}

function head_mail_db_open($database, $table = 'messages')
{
    try {
        $dbh = new PDO('sqlite:' . $database);
    } catch (PDOException $e) {
        echo 'Connection failed: ' . $e->getMessage();
        exit();
    }
    $dbh->exec("CREATE TABLE IF NOT EXISTS messages(
     id INTEGER PRIMARY KEY,
     msgid TEXT UNIQUE,
     mail_from TEXT,
     mail_viewed TEXT,
     rcpt_to TEXT,
     rcpt_viewed TEXT,
     rcpt_target TEXT,
     date TEXT,
     subject TEXT,
     message TEXT,
     from_hide TEXT,
     to_hide TEXT)");
    return ($dbh);
}


/*
 * opens the connection to the NNTP-Server
 *
 * $server: adress of the NNTP-Server
 * $port: port of the server
 */
function nntp_open($nserver = 0, $nport = 0)
{
    global $text_error, $CONFIG;
    global $server, $port;

    // echo "<br>NNTP OPEN<br>";
    if (! isset($CONFIG['enable_nntp']) || $CONFIG['enable_nntp'] != true) {
        $CONFIG['server_auth_user'] = $CONFIG['remote_auth_user'];
        $CONFIG['server_auth_pass'] = $CONFIG['remote_auth_pass'];
    }
    $authorize = ((isset($CONFIG['server_auth_user'])) && (isset($CONFIG['server_auth_pass'])) && ($CONFIG['server_auth_user'] != ""));
    if ($nserver == 0) {
        $nserver = $server;
    }
    if ($nport == 0) {
        $nport = $port;
    }
    $ns = @fsockopen($nserver, $nport);

    // if the connection to the news server fails, inform the user and stop processing.
    if ($ns == false) {
        echo '<center><p>' . $text_error["error:"] . " " . $text_error["connection_failed"] . '.</p>';
        echo '<br>';
        echo '<p>Please wait a few moments and try again. If you see the same error, notify the owner that their Message Server is offline.</p>';
        echo '</center>';
        return false;
        // exit(0);
    }

    $weg = line_read($ns); // kill the first line
    if (substr($weg, 0, 2) != "20") {
        echo "<p>" . $text_error["error:"] . $weg . "</p>";
        fclose($ns);
        $ns = false;
    } else {
        if ($ns != false) {
            fputs($ns, "MODE reader\r\n");
            $weg = line_read($ns); // and once more
            if ((substr($weg, 0, 2) != "20") && ((! $authorize) || ((substr($weg, 0, 3) != "480") && ($authorize)))) {
                echo "<p>" . $text_error["error:"] . $weg . "</p>";
                fclose($ns);
                $ns = false;
            }
        }
        if ((isset($CONFIG['server_auth_user'])) && (isset($CONFIG['server_auth_pass'])) && ($CONFIG['server_auth_user'] != "")) {
            fputs($ns, "AUTHINFO USER " . $CONFIG['server_auth_user'] . "\r\n");
            $weg = line_read($ns);
            fputs($ns, "AUTHINFO PASS " . $CONFIG['server_auth_pass'] . "\r\n");
            $weg = line_read($ns);
            /* Only check auth if reading and posting same server */
            // NNTP Response NOT 281 (Authorization failed)
            if (substr($weg, 0, 3) != "281" && ! (isset($post_server)) && ($post_server != "")) {
                echo "<p>" . $text_error["error:"] . "</p>";
                echo "<p>" . $text_error["auth_error"] . "</p>";
            }
        }
    }
    if ($ns == false) {
        echo "<p>" . $text_error["connection_failed"] . "</p>";
    }
    return $ns;
}

function nntp2_open($nserver = 0, $nport = 0)
{
    global $text_error, $CONFIG, $debug_log, $config_name;

    $authorize = ((isset($CONFIG['remote_auth_user'])) && (isset($CONFIG['remote_auth_pass'])) && ($CONFIG['remote_auth_user'] != ""));
    if ($nserver == 0) {
        $nserver = $CONFIG['remote_server'];
    }
    if ($nport == 0) {
        $nport = $CONFIG['remote_port'];
    }

    // Check memcache circuit breaker if enabled
    $cache_key = "nntp_dead_" . $nserver . "_" . $nport;
    if (function_exists('memcache_get') && isset($CONFIG['memcache_server'])) {
        $memcache = new Memcache;
        if (@$memcache->connect($CONFIG['memcache_server'], isset($CONFIG['memcache_port']) ? $CONFIG['memcache_port'] : 11211)) {
            $dead_until = $memcache->get($cache_key);
            if ($dead_until && time() < $dead_until) {
                debug_log("\n" . format_log_date() . " " . $config_name . " DEBUG: NNTP server marked as dead until " . date('Y-m-d H:i:s', $dead_until), $debug_log);
                return false;
            }
            $memcache->close();
        }
    }

    debug_log("\n" . format_log_date() . " " . $config_name . " DEBUG: Attempting NNTP2 connection to " . $nserver . ":" . $nport, $debug_log);

    $ns = false;

    // SECURE CONNECTION LOGIC - Respect user's security requirements

    // 1. SSL-ONLY MODE: If SSL is configured, NEVER fallback to plaintext
    if (isset($CONFIG['remote_ssl']) && $CONFIG['remote_ssl']) {
        $ssl_port = $CONFIG['remote_ssl'];
        debug_log("\n" . format_log_date() . " " . $config_name . " DEBUG: SSL-only mode - connecting to " . $nserver . ":" . $ssl_port, $debug_log);
        $ns = @fsockopen("ssl://" . $nserver, $ssl_port, $error, $errorString, 30);

        if ($ns) {
            debug_log("\n" . format_log_date() . " " . $config_name . " DEBUG: SSL connection successful", $debug_log);
        } else {
            error_log_always("\n" . format_log_date() . " " . $config_name . " ERROR: SSL connection failed to " . $nserver . ":" . $ssl_port . " - " . $errorString . " (NO FALLBACK - SSL required)", $debug_log);
            return false; // FAIL HARD - never downgrade from SSL to plaintext
        }
    }
    // 2. SOCKS-ONLY MODE: If SOCKS is configured, NEVER bypass proxy
    else if (isset($CONFIG['socks_host']) && $CONFIG['socks_host'] !== '') {
        debug_log("\n" . format_log_date() . " " . $config_name . " DEBUG: SOCKS-only mode - all traffic must go through proxy", $debug_log);

        // Check SOCKS proxy circuit breaker
        $socks_cache_key = "socks_dead_" . $CONFIG['socks_host'] . "_" . $CONFIG['socks_port'];
        $socks_dead = false;
        if (function_exists('memcache_get') && isset($CONFIG['memcache_server'])) {
            $memcache = new Memcache;
            if (@$memcache->connect($CONFIG['memcache_server'], isset($CONFIG['memcache_port']) ? $CONFIG['memcache_port'] : 11211)) {
                $socks_dead_until = $memcache->get($socks_cache_key);
                if ($socks_dead_until && time() < $socks_dead_until) {
                    $socks_dead = true;
                    debug_log("\n" . format_log_date() . " " . $config_name . " ERROR: SOCKS proxy marked as dead until " . date('Y-m-d H:i:s', $socks_dead_until) . " (NO BYPASS - proxy required)", $debug_log);
                }
                $memcache->close();
            }
        }

        if ($socks_dead) {
            return false; // FAIL HARD - never bypass proxy
        }

        // Test SOCKS proxy availability
        debug_log("\n" . format_log_date() . " " . $config_name . " DEBUG: Testing SOCKS proxy " . $CONFIG['socks_host'] . ":" . $CONFIG['socks_port'], $debug_log);
        $socks_test = @fsockopen($CONFIG['socks_host'], $CONFIG['socks_port'], $socks_error, $socks_errstr, 3);
        if (!$socks_test) {
            // Mark SOCKS as dead and FAIL HARD
            if (function_exists('memcache_get') && isset($CONFIG['memcache_server'])) {
                $memcache = new Memcache;
                if (@$memcache->connect($CONFIG['memcache_server'], isset($CONFIG['memcache_port']) ? $CONFIG['memcache_port'] : 11211)) {
                    $socks_dead_time = time() + 300; // 5 minutes
                    $memcache->set($socks_cache_key, $socks_dead_time, 0, 300);
                    $memcache->close();
                }
            }
            error_log_always("\n" . format_log_date() . " " . $config_name . " ERROR: SOCKS proxy failed: " . $socks_errstr . " (NO BYPASS - proxy required)", $debug_log);
            return false; // FAIL HARD - never bypass proxy
        }
        fclose($socks_test);

        // Use SOCKS proxy
        debug_log("\n" . format_log_date() . " " . $config_name . " DEBUG: Connecting through SOCKS proxy", $debug_log);
        $ns = fsocks4asockopen($CONFIG['socks_host'], $CONFIG['socks_port'], $nserver, $nport);

        if ($ns) {
            debug_log("\n" . format_log_date() . " " . $config_name . " DEBUG: SOCKS connection successful", $debug_log);
        } else {
            error_log_always("\n" . format_log_date() . " " . $config_name . " ERROR: SOCKS connection failed to " . $nserver . ":" . $nport . " (NO BYPASS - proxy required)", $debug_log);
            return false; // FAIL HARD - never bypass proxy
        }
    }
    // 3. STANDARD MODE: No special security requirements
    else {
        debug_log("\n" . format_log_date() . " " . $config_name . " DEBUG: Standard TCP connection to " . $nserver . ":" . $nport, $debug_log);
        $ns = @fsockopen('tcp://' . $nserver . ":" . $nport, null, $error, $errorString, 30);

        if ($ns) {
            debug_log("\n" . format_log_date() . " " . $config_name . " DEBUG: TCP connection successful", $debug_log);
        } else {
            debug_log("\n" . format_log_date() . " " . $config_name . " ERROR: TCP connection failed to " . $nserver . ":" . $nport . " - " . $errorString, $debug_log);
        }
    }

    // If connection failed, mark server as dead in memcache
    if (!$ns) {
        if (function_exists('memcache_get') && isset($CONFIG['memcache_server'])) {
            $memcache = new Memcache;
            if (@$memcache->connect($CONFIG['memcache_server'], isset($CONFIG['memcache_port']) ? $CONFIG['memcache_port'] : 11211)) {
                $dead_time = time() + 300; // Mark as dead for 5 minutes
                $memcache->set($cache_key, $dead_time, 0, 300);
                $memcache->close();
                debug_log("\n" . format_log_date() . " " . $config_name . " DEBUG: Marked NNTP server as dead until " . date('Y-m-d H:i:s', $dead_time), $debug_log);
            }
        }
        error_log_always("\n" . format_log_date() . " " . $config_name . " ERROR: Connection failed to " . $nserver, $debug_log);
        return false;
    }

    // Connection successful, proceed with NNTP handshake
    $weg = line_read($ns); // kill the first line
    if (substr($weg, 0, 2) != "20") {
        echo "<p>" . $text_error["error:"] . $weg . "</p>";
        if ($ns) {
            fclose($ns);
        }
        $ns = false;
    } else {
        if ($ns != false) {
            fputs($ns, "MODE reader\r\n");
            $weg = line_read($ns); // and once more
            if ((substr($weg, 0, 2) != "20") && ((! $authorize) || ((substr($weg, 0, 3) != "480") && ($authorize)))) {
                echo "<p>" . $text_error["error:"] . $weg . "</p>";
                fclose($ns);
                $ns = false;
            }
        }
        if ((isset($CONFIG['remote_auth_user'])) && (isset($CONFIG['remote_auth_pass'])) && ($CONFIG['remote_auth_user'] != "")) {
            fputs($ns, "AUTHINFO USER " . $CONFIG['remote_auth_user'] . "\r\n");
            $weg = line_read($ns);
            fputs($ns, "AUTHINFO PASS " . $CONFIG['remote_auth_pass'] . "\r\n");
            $weg = line_read($ns);
            /* Only check auth if reading and posting same server */
            if (substr($weg, 0, 3) != "281" && ! (isset($post_server)) && ($post_server != "")) {
                echo "<p>" . $text_error["error:"] . "</p>";
                echo "<p>" . $text_error["auth_error"] . "</p>";
            }
        }
    }
    if ($ns == false) {
        echo "<p>" . $text_error["connection_failed"] . "</p>";
    }
    return $ns;
}

function fsocks4asockopen($proxyHostname, $proxyPort, $targetHostname, $targetPort)
{
    $sock = fsockopen($proxyHostname, $proxyPort);
    if ($sock === false) {
        return false;
    }
    fwrite($sock, pack("CCnCCCCC", 0x04, 0x01, $targetPort, 0x00, 0x00, 0x00, 0x01, 0x00) . $targetHostname . pack("C", 0x00));
    $response = fread($sock, 16);
    $values = unpack("xnull/Cret/nport/Nip", $response);
    if ($values["ret"] == 0x5a) {
        return $sock;
    } else {
        fclose($sock);
        return false;
    }
}

/*
 * Close a NNTP connection
 *
 * $ns: the handle of the connection
 */
function nntp_close(&$ns)
{
    if ($ns != false) {
        fputs($ns, "QUIT\r\n");
        fclose($ns);
    }
}

/*
 * Validates an email adress
 *
 * $address: a string containing the email-address to be validated
 *
 * returns true if the address passes the tests, false otherwise.
 */
function validate_email($address)
{
    global $validate_email;
    $return = true;
    if (($validate_email >= 1) && ($return == true)) {
        /* Need to clean up this regex to work properly with preg_match
        $return = (preg_match('^[-!#$%&\'*+\\./0-9=?A-Z^_A-z{|}~]+'.'@'.
               '[-!#$%&\'*+\\/0-9=?A-Z^_A-z{|}~]+\.'.
               '[-!#$%&\'*+\\./0-9=?A-Z^_A-z{|}~]+$',$address));
        */
        $return = 1;
    }
    if (($validate_email >= 2) && ($return == true)) {
        $addressarray = address_decode($address, "garantiertungueltig");
        $return = checkdnsrr($addressarray[0]["host"], "MX");
        if (! $return) {
            $return = checkdnsrr($addressarray[0]["host"], "A");
        }
    }
    return ($return);
}

/*
 * decodes a block of 7bit-data in uuencoded format to it's original
 * 8bit format.
 * The headerline containing filename and permissions doesn't have to
 * be included.
 *
 * $data: The uuencoded data as a string
 *
 * returns the 8bit data as a string
 *
 * Note: this function is very slow and doesn't recognize incorrect code.
 */
function uudecode_line($line)
{
    $data = substr($line, 1);
    $length = ord($line[0]) - 32;
    $decoded = "";
    for ($i = 0; $i < (strlen($data) >> 2); $i++) {
        $pack = substr($data, $i << 2, 4);
        $upack = "";
        $bitmaske = 0;
        for ($o = 0; $o < 4; $o++) {
            $g = ((ord($pack[3 - $o]) - 32));
            if ($g == 64) {
                $g = 0;
            }
            $bitmaske = $bitmaske | ($g << (6 * $o));
        }
        $schablone = 255;
        for ($o = 0; $o < 3; $o++) {
            $c = ($bitmaske & $schablone) >> ($o << 3);
            $schablone = ($schablone << 8);
            $upack = chr($c) . $upack;
        }
        $decoded .= $upack;
    }
    $decoded = substr($decoded, 0, $length);
    return $decoded;
}

/*
 * decodes uuencoded Attachments.
 *
 * $data: the encoded data
 *
 * returns the decoded data
 */
function uudecode($data)
{
    $d = explode("\n", $data);
    $u = "";
    for ($i = 0; $i < count($d) - 1; $i++)
        $u .= uudecode_line($d[$i]);
    return $u;
}

/*
 * returns the mimetype of an filename
 *
 * $name: the complete filename of a file
 *
 * returns a string containing the mimetype
 */
function get_mimetype_by_filename($name)
{
    $ending = strtolower(strrchr($name, "."));
    switch ($ending) {
        case ".jpg":
        case ".jpeg":
            $type = "image/jpeg";
            break;
        case ".gif":
            $type = "image/gif";
            break;
        case ".png":
            $type = "image/png";
            break;
        case ".bmp":
            $type = "image/bmp";
            break;
        default:
            $type = "text/plain";
    }
    return $type;
}

function get_mimetype_by_string($filedata)
{
    if (function_exists('finfo_open')) {
        $f = finfo_open();
        return finfo_buffer($f, $filedata, FILEINFO_MIME_TYPE);
    } else {
        return false;
    }
}

/*
 * Test, if the access to a group is allowed. This is true, if $testgroup is
 * false or the groupname is in groups.txt
 *
 * $groupname: name of the group to be checked
 *
 * returns true, if access is allowed
 */
function testGroup($groupname)
{
    global $CONFIG, $testgroup, $file_groups, $config_dir;
    $groupname = strtolower($groupname);
    if ($testgroup) {
        $gf = fopen($file_groups, "r");
        while (! feof($gf)) {
            $read = trim(line_read($gf));
            $read = preg_replace('/\t/', ' ', $read);
            $read = strtolower($read);
            $pos = strpos($read, " ");
            if ($pos != false) {
                if (substr($read, 0, $pos) == trim($groupname)) {
                    return true;
                }
            } else {
                if ($read == trim($groupname)) {
                    return true;
                }
            }
        }
        fclose($gf);
        if ($groupname == $CONFIG['spamgroup']) {
            return true;
        } else {
            /* Find section */
            if (get_section_by_group($groupname, false)) {
                return true;
            } else {
                return false;
            }
        }
    } else {
        return true;
    }
}

// Common menu and section functions
// This file contains shared functions used across the application

// Read <config_dir>/menu.conf and return as array
function get_section_menu_array()
{
    global $config_dir;
    $menudata = file($config_dir . '/menu.conf');
    $newmenu = array();
    foreach ($menudata as $menuentry) {
        if (!preg_match("/^[a-zA-Z0-9]/", $menuentry)) { // Not an entry. Ignore
            continue;
        } else {
            $newmenu[] = $menuentry;
        }
    }
    return $newmenu;
}

function get_section_by_group($groupname, $all_sections = false)
{
    global $config_dir;

    // Debug output
    if (defined('DEBUG_SECTION_LOOKUP')) {
        echo "DEBUG: Looking for group '$groupname'\n";
    }

    $menulist = get_section_menu_array();
    // Get first group in Newsgroups
    $groupname = preg_split("/( |\,)/", $groupname, 2);
    $groupname = $groupname[0];

    if (defined('DEBUG_SECTION_LOOKUP')) {
        echo "DEBUG: Cleaned groupname: '$groupname'\n";
    }

    foreach ($menulist as $menu) {
        $menuitem = explode(':', $menu);
        if ($menuitem[1] == '0') {
            if (! $all_sections) {
                continue;
            }
        }
        $section = "";
        $groups_file = $config_dir . '/' . $menuitem[0] . "/groups.txt";

        if (defined('DEBUG_SECTION_LOOKUP')) {
            echo "DEBUG: Checking section '{$menuitem[0]}', file: $groups_file\n";
        }

        if (!file_exists($groups_file)) {
            if (defined('DEBUG_SECTION_LOOKUP')) {
                echo "DEBUG: File does not exist: $groups_file\n";
            }
            continue; // Skip sections without groups.txt files
        }
        $gldata = file($groups_file);
        if ($gldata === false) {
            if (defined('DEBUG_SECTION_LOOKUP')) {
                echo "DEBUG: Cannot read file: $groups_file\n";
            }
            continue; // Skip if file can't be read
        }
        foreach ($gldata as $gl) {
            $group_name = preg_split("/( |\t)/", $gl, 2);
            $group_name_clean = trim($group_name[0]);

            if (defined('DEBUG_SECTION_LOOKUP')) {
                echo "DEBUG: Comparing '$groupname' with '$group_name_clean'\n";
            }

            if (strtolower(trim($groupname)) == strtolower(trim($group_name_clean))) {
                if (defined('DEBUG_SECTION_LOOKUP')) {
                    echo "DEBUG: MATCH FOUND in section '{$menuitem[0]}'\n";
                }
                $section = $menuitem[0];
                return $section;
            }
        }
    }

    if (defined('DEBUG_SECTION_LOOKUP')) {
        echo "DEBUG: No section found for group '$groupname'\n";
    }

    return false;
}

function testGroups($newsgroups)
{
    $groups = explode(",", $newsgroups);
    $count = count($groups);
    $return = "";
    $o = 0;
    for ($i = 0; $i < $count; $i++) {
        if (testgroup($groups[$i]) && (! function_exists("npreg_group_has_write_access") || npreg_group_has_write_access($groups[$i]))) {
            if ($o > 0)
                $return .= ",";
            $o++;
            $return .= $groups[$i];
        }
    }
    return ($return);
}

/*
 * read one line from the NNTP-server
 */
function line_read(&$ns)
{
    global $debug_log, $config_name;
    if ($ns != false) {
        // Add timeout handling to prevent infinite hangs
        $read = array($ns);
        $write = null;
        $except = null;
        $timeout = 10; // 10 second timeout

        if (stream_select($read, $write, $except, $timeout) > 0) {
            $t = str_replace("\n", "", str_replace("\r", "", fgets($ns, 1200)));
            return $t;
        } else {
            // Timeout occurred
            if (isset($debug_log) && isset($config_name)) {
                debug_log("\n" . format_log_date() . " " . $config_name . " DEBUG: line_read() timeout after " . $timeout . " seconds", $debug_log);
            }
            return false; // Return false on timeout
        }
    }
    return false;
}

/*
 * Split an internet-address string into its parts. An address string could
 * be for example:
 * - user@host.domain (Realname)
 * - "Realname" <user@host.domain>
 * - user@host.domain
 *
 * The address will be split into user, host (incl. domain) and realname
 *
 * $adrstring: The string containing the address in internet format
 * $defaulthost: The name of the host which should be returned if the
 * address-string doesn't contain a hostname.
 *
 * returns an hash containing the fields "mailbox", "host" and "personal"
 */
function address_decode($adrstring, $defaulthost)
{
    $parsestring = trim($adrstring);
    $len = strlen($parsestring);
    $at_pos = strpos($parsestring, '@'); // find @
    $ka_pos = strpos($parsestring, "("); // find (
    $kz_pos = strpos($parsestring, ')'); // find )
    $ha_pos = strpos($parsestring, '<'); // find <
    $hz_pos = strpos($parsestring, '>'); // find >
    $space_pos = strpos($parsestring, ')'); // find ' '
    $email = "";
    $mailbox = "";
    $host = "";
    $personal = "";
    if ($space_pos != false) {
        if (($ka_pos != false) && ($kz_pos != false)) {
            $personal = substr($parsestring, $ka_pos + 1, $kz_pos - $ka_pos - 1);
            $email = trim(substr($parsestring, 0, $ka_pos - 1));
        }
    } else {
        $email = $adrstring;
    }
    if (($ha_pos != false) && ($hz_pos != false)) {
        $email = trim(substr($parsestring, $ha_pos + 1, $hz_pos - $ha_pos - 1));
        $personal = substr($parsestring, 0, $ha_pos - 1);
    }
    if ($at_pos != false) {
        $mailbox = substr($email, 0, strpos($email, '@'));
        $host = substr($email, strpos($email, '@') + 1);
    } else {
        $mailbox = $email;
        $host = $defaulthost;
    }
    $personal = trim($personal);
    if (substr($personal, 0, 1) == '"')
        $personal = substr($personal, 1);
    if (substr($personal, strlen($personal) - 1, 1) == '"')
        $personal = substr($personal, 0, strlen($personal) - 1);
    $result["mailbox"] = trim($mailbox);
    $result["host"] = trim($host);
    if ($personal != "")
        $result["personal"] = $personal;
    $complete[] = $result;
    return ($complete);
}

/*
 * Read the groupnames from groups.txt, and get additional informations
 * of the groups from the newsserver
 *
 * when load=0, returns cached group list
 * when load=1, checks if the cache should be used, and returns nothing
 * when force_reload=true, rebuilds group list cache
 */
function groups_read($server, $port, $load = 0, $force_reload = false)
{
    global $gl_age, $file_groups, $spooldir, $config_name, $cache_index, $debug_log;
    // is there a cached version, and is it actual enough?
    $cachefile = $spooldir . '/' . $config_name . '-groups.dat';
    // if cache is new enough, don't recreate it
    clearstatcache(TRUE, $cachefile);
    if (! $force_reload && $load == 1 && file_exists($cachefile) && (filemtime($cachefile) + $cache_index > time())) {
        return;
    }
    if (! $force_reload && file_exists($cachefile) && $load == 0) {
        // cached file exists and is new enough, so lets read it out.
        $file = fopen($cachefile, "r");
        $data = "";
        while (! feof($file)) {
            $data .= fgets($file, 1000);
        }
        fclose($file);
        $newsgroups = secure_unserialize($cachefile, ['newsgroupType'], false);
        if ($newsgroups === false) {
            // Fallback to secure unserialize with data validation
            try {
                $newsgroups = secure_unserialize($data);
                if (!is_array($newsgroups)) {
                    $newsgroups = false;
                }
            } catch (Exception $e) {
                $newsgroups = false;
            }
        }
    } else {
        // force a refresh of the group list
        $ns = nntp_open($server, $port);
        if ($ns == false) {
            return false;
        }
        // $gf=fopen($file_groups,"r");
        $gfdata = file($file_groups);
        // if we want to mark groups with new articles with colors, we will later
        // need the format of the overview
        $overviewformat = thread_overview_read($ns);
        foreach ($gfdata as $gf) {
            $gruppe = new newsgroupType();
            $tmp = preg_replace('/\t/', ' ', trim($gf));
            if (substr($tmp, 0, 1) == ":") {
                $gruppe->text = substr($tmp, 1);
                $newsgroups[] = $gruppe;
            } elseif (strlen($tmp) > 0) {
                // is there a description in groups.txt?
                $gr = explode(" ", $tmp, 2);
                if (isset($gr[1])) { // Yes
                    $gruppe->name = $gr[0];
                    $desc = $gr[1];
                } else { // No
                    // no, get it from the newsserver.
                    $gruppe->name = $tmp;
                    if (is_file($spooldir . '/' . $tmp . '-title')) {
                        $response = file_get_contents($spooldir . '/' . $tmp . '-title');
                        $desc = strrchr($response, "\t");
                    } else {
                        $desc = "-";
                    }
                }
                if (strcmp($desc, "") == 0) {
                    $desc = "-";
                }
                $gruppe->description = $desc;
                debug_log("\n" . format_log_date() . " " . $config_name . " DEBUG: Sending GROUP command for " . $gruppe->name, $debug_log);
                fputs($ns, "GROUP " . $gruppe->name . "\r\n");
                $t = explode(" ", line_read($ns));

                // Handle timeout for GROUP command
                if ($t === false || !isset($t[0])) {
                    important_log("TIMEOUT: No response to GROUP command for " . $gruppe->name, $debug_log);
                    continue;
                }

                debug_log("GROUP response for " . $gruppe->name . ": " . implode(" ", $t), $debug_log);

                if ($t[0] == "211") {
                    $gruppe->count = $t[1];
                } else {
                    nntp_close($ns);
                    $ns = nntp_open($server, $port);
                    if ($ns == false) {
                        return false;
                    }
                    debug_log("Retry GROUP command for " . $gruppe->name, $debug_log);
                    fputs($ns, "GROUP " . $gruppe->name . "\r\n");
                    $t = explode(" ", line_read($ns));

                    // Handle timeout for retry GROUP command
                    if ($t === false || !isset($t[0])) {
                        important_log("TIMEOUT: No response to retry GROUP command for " . $gruppe->name, $debug_log);
                        continue;
                    }

                    debug_log("Retry GROUP response for " . $gruppe->name . ": " . implode(" ", $t), $debug_log);
                    if ($t[0] == "211")
                        $gruppe->count = $t[1];
                    else
                        continue;
                }
                // mark group with new articles with colors
                if ($gl_age) {
                    fputs($ns, 'XOVER ' . $t[3] . "\r\n");
                    $tmp = explode(" ", line_read($ns));
                    if ($tmp[0] == "224") {
                        $tmp = line_read($ns);
                        if ($tmp != ".") {
                            $head = thread_overview_interpret($tmp, $overviewformat, $gruppe->name);
                            $tmp = line_read($ns);
                            $gruppe->age = $head->date;
                        }
                    }
                }
                if ((strcmp(trim($gruppe->name), "") != 0) && (substr($gruppe->name, 0, 1) != "#")) {
                    $newsgroups[] = $gruppe;
                }
            }
        }
        nntp_close($ns);
        // write the data to the cachefile
        file_put_contents($cachefile, serialize($newsgroups));
    }
    if ($load == 0) {
        return $newsgroups;
    } else {
        return;
    }
}

function groups_show($gruppen)
{
    global $gl_age, $frame, $spooldir, $config_dir, $config_name, $logdir, $debug_log, $CONFIG, $OVERRIDES, $spoolnews, $spooldir;
    if ($gruppen == false) {
        return;
    }
    global $file_thread, $text_groups;
    $logfile = $logdir . '/debug.log';
    if (file_exists($config_dir . '/cache.inc.php')) {
        include $config_dir . '/cache.inc.php';
    }
    $c = count($gruppen);
    $acttype = "keins";
    echo '<table class="np_groups_table"><tr class="np_thread_head"><td class="grouplist_thread_head_latest">Latest</td>';
    echo '<td class="grouplist_thread_head_subject">Newsgroup</td>';
    echo '<td class="grouplist_thread_head_messages">Messages</td>';
    echo '<td class="grouplist_thread_head_lastarticle" >Last Message</td></tr>';
    $subs = array();
    $nonsubs = array();
    $user = null;

    // Get registered user settings
    $cookie_mail_name = $_COOKIE['mail_name'];
    if (isset($_COOKIE['mail_name']) && $_COOKIE['mail_name'] == $CONFIG['anonusername']) {
        unset($cookie_mail_name);
    }
    if (isset($cookie_mail_name)) {
        if ($userdata = get_user_mail_auth_data($cookie_mail_name)) {
            $userfile = $spooldir . '/' . strtolower($cookie_mail_name) . '-articleviews.dat';
            $user_config = secure_unserialize($config_dir . '/userconfig/' . strtolower($cookie_mail_name) . '.config');
            if ($user_config === false) {
                $user_config = [];
            }

            // User blocklist
            $blocked_userfile = $spooldir . '/' . strtolower($_COOKIE['mail_name']) . '-blocked_posters.dat';
            if (file_exists($blocked_userfile)) {
                $blocked_user_config = secure_unserialize($blocked_userfile);
                if ($blocked_user_config === false) {
                    $blocked_user_config = [];
                }
            }
        }
    }

    for ($i = 0; $i < $c; $i++) {
        unset($groupdisplay);
        $g = $gruppen[$i];
        if (isset($g->text)) {
            if ($acttype != "text") {
                $acttype = "text";
            }
        } else {
            if ($acttype != "group") {
                $acttype = "group";
            }
            if (! isset($userdata[$g->name])) {
                if (isset($user_config['hide_unsub']) && $user_config['hide_unsub'] == 'hide') {
                    continue;
                }
            }
            unset($lastarticleinfo);
            $found = 0;
            // Get last article info from article database
            // First check memcache
            if ($enable_cache) {
                $groups_cache_file = $spooldir . '/tmp/' . $g->name . '-groups-cache.dat';
                $groupfile = $spooldir . '/' . $g->name . '-lastarticleinfo.dat';
                $cache_time = filemtime($groups_cache_file);
                $groupfile_time = filemtime($groupfile);
                $memcache_key = $cache_key_prefix . '_' . 'lastarticleinfo-' . $g->name;
            }
            if ($enable_cache && ($cache_time > $groupfile_time)) { // Use cache if newer than last group data.dat update
                $groupfile = $spooldir . '/' . $g->name . '-lastarticleinfo.dat';
                $lar = cache_get($memcache_key, $memcacheD);
                if ($lar) {
                    // Try to unserialize cached data safely
                    try {
                        $lastarticleinfo = secure_unserialize($lar);
                        if (!is_array($lastarticleinfo)) {
                            $lastarticleinfo = false;
                        }
                    } catch (Exception $e) {
                        $lastarticleinfo = false;
                    }
                    if ($lastarticleinfo && file_exists($groupfile) && filemtime($groupfile) <= $lastarticleinfo['date']) {
                        if ($enable_cache_logging) {
                            file_put_contents($cache_log, "\n" . logging_prefix() . " (cache hit) " . $memcache_key, FILE_APPEND);
                        }
                        $found = 1;
                    } else {
                        unset($lastarticleinfo);
                    }
                }
            }
            if (! isset($lastarticleinfo['date'])) {
                if ($CONFIG['article_database'] == '1') {
                    $database = $spooldir . '/' . $g->name . '-articles.db3';
                    $article_dbh = article_db_open($database);
                    if ($article_dbh !== false) {
                        $article_query = $article_dbh->prepare('SELECT * FROM articles ORDER BY CAST(date AS int) DESC LIMIT 5');
                        $article_query->execute();
                        while ($row = $article_query->fetch()) {
                            if ($row['date'] > time()) {
                                continue;
                            }
                            $found = 1;
                            break;
                        }
                        $article_dbh = null;                    } else {
                        // Database connection failed, try fallback to cached data
                        debug_log("Failed to open article database for group: " . $g->name . ", trying fallback methods", $debug_log);

                        // Try to load from cache or file as fallback
                        if ($enable_cache) {
                            $memcache_key = $cache_key_prefix . '_' . 'lastarticleinfo-' . $g->name;
                            $lar = cache_get($memcache_key, $memcacheD);
                            if ($lar) {
                                try {
                                    $lastarticleinfo = secure_unserialize($lar);
                                    if (is_array($lastarticleinfo) && isset($lastarticleinfo['date'])) {
                                        $found = 1;
                                        $row = $lastarticleinfo; // Set row for later use in display
                                        debug_log("Successfully loaded cached article info for group: " . $g->name, $debug_log);
                                    }
                                } catch (Exception $e) {
                                    $lastarticleinfo = false;
                                }
                            }
                        }

                        // If cache failed, try direct file fallback
                        if ($found == 0) {
                            $groupfile = $spooldir . '/' . $g->name . '-lastarticleinfo.dat';
                            if (file_exists($groupfile)) {
                                $file_data = file_get_contents($groupfile);
                                if ($file_data) {
                                    try {
                                        $lastarticleinfo = secure_unserialize($file_data);
                                    if (is_array($lastarticleinfo) && isset($lastarticleinfo['date'])) {
                                            $found = 1;
                                            $row = $lastarticleinfo; // Set row for later use in display
                                            debug_log("Successfully loaded file-based article info for group: " . $g->name, $debug_log);
                                        }
                                    } catch (Exception $e) {
                                        $lastarticleinfo = false;
                                    }
                                }
                            }
                        }

                        // If all fallbacks failed, log for debugging
                        if ($found == 0) {
                            debug_log("No fallback article info available for group: " . $g->name, $debug_log);
                        }
                    }
                } else {
                    $database = $spooldir . '/articles-overview.db3';
                    $overview_dbh = overview_db_open($database);
                    if ($overview_dbh !== false) {
                        $overview_query = $overview_dbh->prepare('SELECT * FROM overview WHERE newsgroup=:newsgroup ORDER BY CAST(date AS int) DESC LIMIT 5');
                        $overview_query->execute([
                            'newsgroup' => $g->name
                        ]);
                        while ($row = $overview_query->fetch()) {
                            if ($row['date'] > time()) {
                                continue;
                            }
                            $found = 1;
                            break;
                        }
                        $overview_dbh = null;                    } else {
                        // Overview database connection failed, try fallback methods
                        debug_log("Failed to open overview database for group: " . $g->name . ", trying fallback methods", $debug_log);

                        // Try to load from cache or file as fallback (same logic as article database)
                        if ($enable_cache) {
                            $memcache_key = $cache_key_prefix . '_' . 'lastarticleinfo-' . $g->name;
                            $lar = cache_get($memcache_key, $memcacheD);
                            if ($lar) {
                                try {
                                    $lastarticleinfo = secure_unserialize($lar);
                                    if (is_array($lastarticleinfo) && isset($lastarticleinfo['date'])) {
                                        $found = 1;
                                        $row = $lastarticleinfo; // Set row for later use
                                        debug_log("Successfully loaded cached overview info for group: " . $g->name, $debug_log);
                                    }
                                } catch (Exception $e) {
                                    $lastarticleinfo = false;
                                }
                            }
                        }

                        // If cache failed, try direct file fallback
                        if ($found == 0) {
                            $groupfile = $spooldir . '/' . $g->name . '-lastarticleinfo.dat';
                            if (file_exists($groupfile)) {
                                $file_data = file_get_contents($groupfile);
                                if ($file_data) {
                                    try {
                                        $lastarticleinfo = secure_unserialize($file_data);
                                        if (is_array($lastarticleinfo) && isset($lastarticleinfo['date'])) {
                                            $found = 1;
                                            $row = $lastarticleinfo; // Set row for later use
                                            debug_log("Successfully loaded file-based overview info for group: " . $g->name, $debug_log);
                                        }
                                    } catch (Exception $e) {
                                        $lastarticleinfo = false;
                                    }
                                }
                            }
                        }

                        // Log when all fallbacks fail for debugging
                        if ($found == 0) {
                            debug_log("No fallback overview info available for group: " . $g->name, $debug_log);
                        }
                    }
                }
                if ($found == 1) {
                    $lastarticleinfo = $row;
                    if ($enable_cache) {
                        touch($groupfile, $lastarticleinfo['date']);
                        $nicole = cache_delete($memcache_key, $memcacheD);
                        cache_add($memcache_key, serialize($row), $cache_ttl, $memcacheD);
                        if ($enable_cache_logging) {
                            if ($nicole) {
                                file_put_contents($cache_log, "\n" . logging_prefix() . " (cache update) $memcache_key", FILE_APPEND);
                            } else {
                                file_put_contents($cache_log, "\n" . logging_prefix() . " (cache write) $memcache_key", FILE_APPEND);
                            }
                        }
                    }
                }
                touch($groups_cache_file);
            }
            $new = false;
            $new_style_on = '';
            $new_style_off = '';
            if (isset($userdata[$g->name]) && ($userdata[$g->name] < $lastarticleinfo['date'])) {
                $new_style_on = '<b><i>*';
                $new_style_off = '</i></b>';
                $new = true;
            }
            /* Display group name and description */
            if (isset($userdata[$g->name])) {
                $lineclass = "np_thread_line2";
            } else {
                $lineclass = "np_thread_line1";
            }
            if ($new) {
                $latest_link = '&time=' . $userdata[$g->name];
            } else {
                $latest_link = '';
            }
            $groupdisplay = '<tr class="' . $lineclass . '"><td style="text-align: center;" class="' . $lineclass . '">';
            $groupdisplay .= '<a href="?page=overboard&thisgroup=' . _rawurlencode($g->name) . $latest_link . '">';
            if ((isset($_SESSION['theme'])) && file_exists('../common/themes/' . $_SESSION['theme'] . '/images/latest.png')) {
                $latest_image = '../common/themes/' . $_SESSION['theme'] . '/images/latest.png';
            } else {
                $latest_image = '../common/images/latest.png';
            }
            if ($new) {
                if ((isset($_SESSION['theme'])) && file_exists('../common/themes/' . $_SESSION['theme'] . '/images/new-articles.png')) {
                    $latest_image = '../common/themes/' . $_SESSION['theme'] . '/images/new-articles.png';
                } else {
                    $latest_image = '../common/images/new-articles.png';
                }
                $groupdisplay .= '<img class="' . $lineclass . '_icon" src="' . $latest_image . '" title="New articles">';
            } else {
                $groupdisplay .= '<img class="' . $lineclass . '_icon" src="' . $latest_image . '" title="Recent articles">';
            }
            $groupdisplay .= '</a>';
            $groupdisplay .= '</td>';

            $groupdisplay .= '<td class="' . $lineclass . '">';
            $groupdisplay .= '<span class="np_group_line_text">';
            $groupdisplay .= '<a ';
            $groupdisplay .= 'href="' . $file_thread . '&group=' . urlencode($g->name) . '"><span class="np_group_line_text">' . $new_style_on . group_display_name($g->name) . $new_style_off . "</span></a>\n";
            if ($new) {
                echo '</i></b>';
            }
            $groupdisplay .= '</span>';
            if ($g->description != "-") {
                $groupdisplay .= '<br><p class="np_group_desc">' . $g->description . '</p>';
            }
            if (isset($userdata[$g->name])) {
                $groupdisplay .= '<p class="np_group_user_tools">';
                $groupdisplay .= '<a class="np_group_user_tools" href="index.php?unsub=' . urlencode($g->name) . '">(unsubscribe)</a>';
                if ($new) {
                    $groupdisplay .= '&nbsp;<a class="np_group_user_tools" href="index.php?mark_read=' . urlencode($g->name) . '">(mark read)</a>';
                }
                $groupdisplay .= '</p>';
            } else {
                if (isset($user_config['hide_unsub']) && $user_config['hide_unsub'] == 'hide') {
                    continue;
                } else {
                    $groupdisplay .= '<p class="np_group_user_tools">';
                    $groupdisplay .= '<a class="np_group_user_tools" href="index.php?subscribe=' . urlencode($g->name) . '">(subscribe)</a>';
                    $groupdisplay .= '</p>';
                }
            }
            /* Display article count */
            $groupdisplay .= '</td><td class="' . $lineclass . '">';
            if ($gl_age && isset($g->age)) {
                $datecolor = thread_format_date_color($g->age);
            } else {
                $datecolor = "";
            }
            if ($datecolor != "") {
                $groupdisplay .= '<div class="' . $datecolor . '">' . $g->count . '</div>';
            } else {
                $groupdisplay .= '<div class="group_display_message_count_old">' . $g->count . '</div>';
            }
            /* Display latest article info */
            $groupdisplay .= '</td><td class="' . $lineclass . '"><div class="grouplist_td_thread_start_author_info">';

            if ($found == 1) {
                $fromline = address_decode(headerDecode($lastarticleinfo['name']), "nowhere");
                if (! isset($fromline[0]["host"])) {
                    $fromline[0]["host"] = "";
                }
                $name_from = $fromline[0]["mailbox"] . "@" . $fromline[0]["host"];
                if (! isset($fromline[0]["personal"])) {
                    $poster_name = $fromline[0]["mailbox"];
                } else {
                    $poster_name = $fromline[0]["personal"];
                }
                if (trim($poster_name) == '') {
                    $fromoutput = explode("<", html_entity_decode($lastarticleinfo['name']));
                    if (strlen($fromoutput[0]) < 1) {
                        $poster_name = $fromoutput[1];
                    } else {
                        $poster_name = $fromoutput[0];
                    }
                }
                $lastarticleinfo['name'] = $poster_name;

                $block = false;
                foreach ($blocked_user_config as $key => $value) {
                    $blockme = '/' . addslashes($key) . '/';
                    if (preg_match($blockme, $name_from)) {
                        $block = true;
                        break;
                    }
                }
                $lastarticleinfo['subject'] = htmlentities(preg_replace('/_/', ' ', mb_decode_mimeheader($lastarticleinfo['subject'])));
                $groupdisplay .= '<span class="grouplist_thread_start_author_info">';
                if ($block) {
                    $url = '?page=article-flat&id=' . $lastarticleinfo['number'] . '&group=' . urlencode($g->name) . '#' . $lastarticleinfo['number'];
                    $groupdisplay .= get_date_interval(date("D, j M Y H:i T", $lastarticleinfo['date']));
                    $groupdisplay .= '<br>by: ';
                    $groupdisplay .= "(blocked user)";
                } else {
                    $url = '?page=article-flat&id=' . $lastarticleinfo['number'] . '&group=' . urlencode($g->name) . '#' . $lastarticleinfo['number'];
                    $groupdisplay .= '<a href="' . $url . '" title="' . $lastarticleinfo['subject'] . '">' . get_date_interval(date("D, j M Y H:i T", $lastarticleinfo['date'])) . '</a>';
                    $groupdisplay .= '<br>by: ';
                    $groupdisplay .= create_name_link($lastarticleinfo['name'], $name_from);
                }
                $groupdisplay .= '</span>';
            } else {
                unset($lastarticleinfo);
            }
            $groupdisplay .= '</div>';
        }
        if (isset($groupdisplay)) {
            $groupdisplay .= "\n";
            flush();
            if (isset($userdata[$g->name])) {
                $subs[] = $groupdisplay;
            } else {
                $nonsubs[] = $groupdisplay;
            }
        }
    }
    foreach ($subs as $sub) {
        echo $sub;
    }
    foreach ($nonsubs as $nonsub) {
        echo $nonsub;
    }
    echo "</table><table>";
    echo '<tr><td class="np_show_hide_toggle">';
    if (isset($user_config['hide_unsub']) && $user_config['hide_unsub'] == 'hide') {
        echo '&nbsp;Unsubscribed groups are HIDDEN.';
        echo '&nbsp;Select groups from <a href="/common/grouplist.php">Grouplist</a> to add groups';
    }
    if (isset($userdata)) {
        show_groups_hide_toggle();
    }
    echo '</td></tr>';
    echo '</table>';
}

function show_groups_hide_toggle()
{
    global $user_config;
    echo '<form method="post" action="' . $_SERVER['REQUEST_URI'] . '">';
    echo '&nbsp;Unsubscribed Groups: ';
    if ($user_config['hide_unsub'] == 'hide') {
        echo '<input type="radio" name="hide_unsub" value="show">Show';
        echo '&nbsp;';
        echo '<input type="radio" name="hide_unsub" value="hide" checked>Hide';
        echo '&nbsp;';
    } else {
        echo '<input type="radio" name="hide_unsub" value="show" checked>Show';
        echo '&nbsp;';
        echo '<input type="radio" name="hide_unsub" value="hide">Hide';
        echo '&nbsp;';
    }
    echo '<input class="np_button_link" type="submit" value="Reload" name="reload">';
    echo '</form >';
}

/*
 * This function is deprecated and not used anymore.
 * It was used to display groups in a block format.
 * It is kept here for reference, but should not be used in new code.
 *
 * @deprecated
/*
 * print the group names from an array to the webpage
 */
/*
function groups_show_frames($gruppen)
{
    global $gl_age, $frame, $spooldir;
    if ($gruppen == false) {
        return;
    }
    global $file_thread, $text_groups;
    $c = count($gruppen);
    echo '<div class="np_index_groupblock">';
    $acttype = "keins";
    for ($i = 0; $i < $c; $i++) {
        $g = $gruppen[$i];
        if (isset($g->text)) {
            if ($acttype != "text") {
                $acttype = "text";
                if ($i > 0) {
                    echo '</div>';
                }
                echo '<div class="np_index_grouphead">';
            }
            echo $g->text;
        } else {
            if ($acttype != "group") {
                $acttype = "group";
                if ($i > 0) {
                    echo '</div>';
                }
                echo '<div class="np_index_groupblock">';
            }
            echo '<div class="np_index_group">';
            echo '<b>DEBUG<a ';
            echo 'target="' . $frame['content'] . '" ';
            echo 'href="' . $file_thread . '&group=' . _rawurlencode($g->name) . '">' . group_display_name($g->name) . "</a></b>\n";
            if ($gl_age) {
                $datecolor = thread_format_date_color($g->age);
            }
            echo '<small>(';
            if ($datecolor != "") {
                echo '<font color="' . $datecolor . '">' . $g->count . '</font>';
            } else {
                echo $g->count;
            }
            echo ')</small>';
            if ($g->description != "-") {
                echo '<br><small>' . $g->description . '</small>';
            }
            echo '</div>';
        }
        echo "\n";
        flush();
    }
    echo "</div></div>\n";
}
*/


/*
 * gets a list of available articles in the group $groupname
 */
/*
 * function getArticleList(&$ns,$groupname) {
 * fputs($ns,"LISTGROUP $groupname \r\n");
 * $line=line_read($ns);
 * $line=line_read($ns);
 * while(strcmp($line,".") != 0) {
 * $articleList[] = trim($line);
 * $line=line_read($ns);
 * }
 * if (!isset($articleList)) $articleList="-";
 * return $articleList;
 * }
 */

/*
 * Decode quoted-printable or base64 encoded headerlines
 *
 * $value: The to be decoded line
 *
 * returns the decoded line
 */
function headerDecode($value)
{
    $value = preg_replace_callback('/(=\?[^\?]+\?Q\?)([^\?]+)(\?=)/i', function ($matches) {
        return $matches[1] . str_replace('_', '=20', $matches[2]) . $matches[3];
    }, $value);
    return mb_decode_mimeheader($value);
}

/*
 * calculates an Unix timestamp out of a Date-Header in an article
 *
 * $value: Value of the Date: header
 *
 * returns an Unix timestamp
 */
function getTimestamp($value)
{
    global $CONFIG;

    return strtotime($value);
}

function parse_header($hdr, $number = "")
{
    for ($i = count($hdr) - 1; $i > 0; $i--)
        if (preg_match("/^(\x09|\x20)/", $hdr[$i]))
            $hdr[$i - 1] = $hdr[$i - 1] . " " . ltrim($hdr[$i]);
    $header = new headerType();
    $header->isAnswer = false;
    for ($count = 0; $count < count($hdr); $count++) {
        $variable = substr($hdr[$count], 0, strpos($hdr[$count], " "));
        $value = trim(substr($hdr[$count], strpos($hdr[$count], " ") + 1));
        switch (strtolower($variable)) {
            case "from:":
                $fromline = address_decode(headerDecode($value), "nowhere");
                if (! isset($fromline[0]["host"])) {
                    $fromline[0]["host"] = "";
                }
                $header->from = $fromline[0]["mailbox"] . "@" . $fromline[0]["host"];
                $header->username = $fromline[0]["mailbox"];
                if (! isset($fromline[0]["personal"])) {
                    $header->name = "";
                } else {
                    $header->name = $fromline[0]["personal"];
                }
                break;
            case "message-id:":
                $header->id = $value;
                break;
            case "subject:":
                $header->subject = headerDecode($value);
                break;
            case "newsgroups:":
                $header->newsgroups = $value;
                break;
            case "organization:":
                $header->organization = headerDecode($value);
                break;
            case "content-transfer-encoding:":
                $header->content_transfer_encoding = trim(strtolower($value));
                break;
            case "content-disposition:":
                $getname = preg_split("/name\=/", $value, 2);
                if (isset($getname[1])) {
                    $header->content_type_name = array(
                        $getname[1]
                    );
                }
                break;
            case "content-type:":
                $header->content_type = array();
                $subheader = explode(";", $value);
                $header->content_type[0] = strtolower(trim($subheader[0]));
                for ($i = 1; $i < count($subheader); $i++) {
                    $gleichpos = strpos($subheader[$i], "=");
                    if ($gleichpos) {
                        $subvariable = trim(substr($subheader[$i], 0, $gleichpos));
                        $subvalue = trim(substr($subheader[$i], $gleichpos + 1));
                        if (($subvalue[0] == '"') && ($subvalue[strlen($subvalue) - 1] == '"')){
                            $subvalue = substr($subvalue, 1, strlen($subvalue) - 2);
                        }
                        switch ($subvariable) {
                            case "charset":
                                $header->content_type_charset = array(
                                    strtolower($subvalue)
                                );
                                break;
                            case "name":
                                $header->content_type_name = array(
                                    $subvalue
                                );
                                break;
                            case "boundary":
                                $header->content_type_boundary = $subvalue;
                                break;
                            case "format":
                                $header->content_type_format = array(
                                    $subvalue
                                );
                        }
                    }
                }
                break;
            case "references:":
                $ref = trim($value);
                while (strpos($ref, "> <") != false) {
                    $header->references[] = substr($ref, 0, strpos($ref, " "));
                    $ref = substr($ref, strpos($ref, "> <") + 2);
                }
                $header->references[] = trim($ref);
                break;
            case "date:":
                $header->date = getTimestamp(trim($value));
                break;
            case "followup-to:":
                $header->followup = trim($value);
                break;
            case "x-newsreader:":
            case "x-mailer:":
            case "x-rslight-to:":
                $header->rslight_to = trim($value);
                break;
            case "x-rslight-site:":
                $header->rslight_site = trim($value);
                break;
            case "user-agent:":
                $header->user_agent = trim($value);
                break;
            case "x-face:": // not ready
                // echo "<p>-".base64_decode($value)."-</p>";
                break;
            case "x-no-archive:":
                $header->xnoarchive = strtolower(trim($value));
        }
    }
    if (! isset($header->content_type[0])) {
        $header->content_type[0] = "text/plain";
    }
    if (! isset($header->content_transfer_encoding)) {
        $header->content_transfer_encoding = "8bit";
    }
    if ($number != ""){
        $header->number = $number;
    }
    return $header;
}

/*
 * convert the charset of a text
 */
function recode_charset($text, $source = false, $dest = false)
{
    global $iconv_enable, $www_charset;
    if ($dest == false) {
        $dest = $www_charset;
    }
    if (($iconv_enable) && ($source != false)) {
        $return = iconv($source, $dest . "//TRANSLIT", $text);
        if ($return != "") {
            return $return;
        } else {
            return $text;
        }
    } else {
        return $text;
    }
}

function decode_body($body, $encoding)
{
    $bodyzeile = "";
    switch ($encoding) {
        case "base64":
            $body = base64_decode($body);
            break;
        case "quoted-printable":
            $body = Quoted_printable_decode($body);
            $body = str_replace("=\n", "", $body);
            // default:
            // $body=str_replace("\n..\n","\n.\n",$body);
    }

    return $body;
}

/*
 * makes URLs clickable
 *
 * $text: A text-line probably containing links.
 *
 * the function returns the text-line with HTML-Links to the links or
 * email-adresses.
 */
function html_parse($text)
{
    global $frame_externallink;
    if ((isset($frame_externallink)) && ($frame_externallink != "")) {
        $target = ' TARGET="' . $frame_externallink . '" ';
    } else {
        $target = ' ';
    }
    $ntext = "";
    // split every line into it's words
    $words = explode(" ", $text);
    $n = count($words);
    $is_link = 0;
    for ($i = 0; $i < $n; $i++) {
        $word = $words[$i];
        // add the spaces between the words
        if ($i > 0) {
            $ntext .= " ";
        }
        $ntext .= $word;
    }
    return ($ntext);
}

function rewrite_body($text)
{
    global $config_dir;
    if (file_exists($config_dir . '/rewrite_body.inc.php')) {
        include($config_dir . '/rewrite_body.inc.php');
    }
    return $text;
}

function display_links_in_body($text)
{
    global $config_dir;
    preg_match_all('/(https?|ftp|scp|news|gopher|gemini|telnet):\/\/[a-zA-Z0-9.?%=\-\+\;\:\,\~\@\!\(\)\$\#&_\/]+/', $text, $matches);
    $isquote = false;
    foreach ($matches[0] as $match) {
        if (! $match) {
            continue;
        }
        // Get rid of unwanted trailing characters
        $match = rtrim(htmlspecialchars_decode($match), '/>,".');
        $match = htmlspecialchars($match);
        $linkurl = preg_replace("/(<|>)/", '', htmlspecialchars_decode($match));
        $url = preg_replace("/(<|>)/", ' ', $match);
        $pattern = preg_quote($url);
        $pattern = "!$pattern!";
        $text = preg_replace($pattern, '<a href="' . $linkurl . '" rel="nofollow" target="_blank">' . $url . '</a>', $text, 1);
    }
    $text = rewrite_body($text);

    $vlad = explode('<br>', $text);
    foreach ($vlad as $line) {
        $line = preg_replace("/<\/?p>/", "", $line);
        $line = preg_replace("/\&gt;/", ">", $line);
        $line = rtrim($line);
        $depth = 0;
        for ($i = 0; $i < strlen($line); $i++) {
            if ($line[$i] == ' ') {
                continue;
            }
            if ($line[$i] == '>') {
                $depth++;
                continue;
            }
            break;
        }
        if ($depth < 9) {
            echo '<span class="quote_level_' . $depth . '">';
        } else {
            echo '<span class="quote_level_' . $depth - 7 . '">';
        }
        echo $line . '</span><br>';
    }
}

/*
 * read the header of an article in plaintext into an array
 * $articleNumber can be the number of an article or its message-id.
 */
function readPlainHeader(&$ns, $group, $articleNumber)
{
    global $text_error, $debug_log, $config_name;
    debug_log("\n" . format_log_date() . " " . $config_name . " DEBUG: readPlainHeader sending GROUP command for " . $group, $debug_log);
    fputs($ns, "GROUP $group\r\n");
    $line = line_read($ns);

    // Handle timeout for GROUP command
    if ($line === false) {
        important_log("\n" . format_log_date() . " " . $config_name . " TIMEOUT: No response to GROUP command in readPlainHeader for " . $group, $debug_log);
        return false;
    }

    debug_log("\n" . format_log_date() . " " . $config_name . " DEBUG: readPlainHeader GROUP response for " . $group . ": " . $line, $debug_log);
    debug_log("\n" . format_log_date() . " " . $config_name . " DEBUG: readPlainHeader sending HEAD command for article " . $articleNumber, $debug_log);
    fputs($ns, "HEAD $articleNumber\r\n");
    $line = line_read($ns);

    // Handle timeout for HEAD command
    if ($line === false) {
        important_log("\n" . format_log_date() . " " . $config_name . " TIMEOUT: No response to HEAD command in readPlainHeader", $debug_log);
        return false;
    }

    debug_log("\n" . format_log_date() . " " . $config_name . " DEBUG: readPlainHeader HEAD response: " . $line, $debug_log);
    if (substr($line, 0, 3) != "221") {
        echo $text_error["article_not_found"];
        $header = false;
    } else {
        $line = line_read($ns);
        $body = "";
        while (strcmp(trim($line), ".") != 0) {
            $body .= $line . "\n";
            $line = line_read($ns);
        }
        return explode("\n", str_replace("\r\n", "\n", $body));
    }
}

/*
 * cancel an article on the newsserver
 *
 * DO NOT USE THIS FUNCTION, IF YOU DON'T KNOW WHAT YOU ARE DOING!
 *
 * $ns: The handler of the NNTP-Connection
 * $group: The group of the article
 * $id: the Number of the article inside the group or the message-id
 */
function message_cancel($subject, $from, $newsgroups, $ref, $body, $id)
{
    global $server, $port, $send_poster_host, $CONFIG, $text_error;
    global $www_charset;
    flush();
    $ns = nntp_open($server, $port);
    if ($ns != false) {
        fputs($ns, "POST\r\n");
        $weg = line_read($ns);
        fputs($ns, 'Subject: ' . quoted_printable_encode($subject) . "\r\n");
        fputs($ns, 'From: ' . $from . "\r\n");
        fputs($ns, 'Newsgroups: ' . $newsgroups . "\r\n");
        fputs($ns, "Mime-Version: 1.0\r\n");
        fputs($ns, "Content-Type: text/plain; charset=" . $www_charset . "\r\n");
        fputs($ns, "Content-Transfer-Encoding: 8bit\r\n");
        if ($send_poster_host) {
            fputs($ns, 'X-HTTP-Posting-Host: ' . gethostbyaddr(getenv("REMOTE_ADDR")) . "\r\n");
        }
        if ($ref != false) {
            fputs($ns, 'References: ' . $ref . "\r\n");
        }
        if (isset($CONFIG['organization'])){
            fputs($ns, 'Organization: ' . quoted_printable_encode($CONFIG['organization']) . "\r\n");
        }
        fputs($ns, "Control: cancel " . $id . "\r\n");
        $body = str_replace("\n.\r", "\n..\r", $body);
        $body = str_replace("\r", '', $body);
        $b = explode("\n", $body);
        $body = "";
        for ($i = 0; $i < count($b); $i++) {
            if ((strpos(substr($b[$i], 0, strpos($b[$i], " ")), ">") != false) | (strcmp(substr($b[$i], 0, 1), ">") == 0)) {
                $body .= textwrap(stripSlashes($b[$i]), 78, "\r\n") . "\r\n";
            } else {
                $body .= textwrap(stripSlashes($b[$i]), 74, "\r\n") . "\r\n";
            }
        }
        fputs($ns, "\r\n" . $body . "\r\n.\r\n");
        $message = line_read($ns);
        nntp_close($ns);
    } else {
        $message = $text_error["post_failed"];
    }
    return $message;
}

function rslight_encrypt($data, $key)
{
    $encryption_key = base64_decode($key);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length('aes-256-cbc'));
    $encrypted = openssl_encrypt($data, 'aes-256-cbc', $encryption_key, 0, $iv);
    return base64_encode($encrypted . '::' . $iv);
}

function _rawurlencode($string)
{
    $string = rawurlencode(str_replace('+', '%2B', $string));
    return $string;
}

function _rawurldecode($string)
{
    // Decode the string, replacing %2B with + to handle spaces correctly
    // This is necessary because rawurlencode encodes + as %2B, but we want to keep + as a space
    // when decoding.
    // Note: rawurldecode is used to decode the string, but we need to ensure + is treated correctly.
    // rawurldecode will decode %2B back to +, so we replace it with the correct character.
    // This is a workaround for the fact that rawurldecode does not decode + to space.
    $string = rawurldecode(str_replace('%2B', '+', $string));
    return $string;
}

function rslight_decrypt($data, $key)
{
    $encryption_key = base64_decode($key);
    list($encrypted_data, $iv) = explode('::', base64_decode($data), 2);
    return openssl_decrypt($encrypted_data, 'aes-256-cbc', $encryption_key, 0, $iv);
}

function group_display_name($gname)
{
    global $config_dir;
    $namelist = file($config_dir . "rename.conf", FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($namelist as $name) {
        if ($name[0] == '#') {
            continue;
        }
        $nameitem = explode(':', $name);
        if (! strcmp(trim($nameitem[0]), trim($gname))) {
            return $nameitem[1];
        }
    }
    return $gname;
}

function verify_logged_in($name)
{
    global $CONFIG, $spooldir, $auth_log, $debug_log;

    $keyfile = $spooldir . '/keys.dat';
    try {
        $keys = secure_unserialize(file_get_contents($keyfile));
        if (!is_array($keys)) {
            $keys = array();
        }
    } catch (Exception $e) {
        $keys = array();
    }

    $logged_in = false;
    $ip_pass = false;

    // For checking session expire stuff
    if (!isset($_SESSION['start_stamp'])) {
        $_SESSION['start_stamp'] = time();
    }

    if (! isset($_SESSION['start_address'])) {
        $_SESSION['start_address'] = $_SERVER['REMOTE_ADDR'];
        $ip_pass = true;
        file_put_contents($auth_log, "\n" . logging_prefix() . " IP address SET for: " . $name, FILE_APPEND);
    } else {
        if ($_SERVER['REMOTE_ADDR'] != $_SESSION['start_address']) {
            $ip_pass = false;
            file_put_contents($auth_log, "\n" . logging_prefix() . " IP address changed for: " . $name, FILE_APPEND);
        } else {
            $ip_pass = true;
            file_put_contents($auth_log, "\n" . logging_prefix() . " IP address OK for: " . $name, FILE_APPEND);
        }
    }
    if ($ip_pass && (isset($_SESSION['pass']) && $_SESSION['pass'] === true)) {
        $logged_in = true;
        file_put_contents($auth_log, "\n" . logging_prefix() . " SESSION PASS OK for: " . $name, FILE_APPEND);
    } else {
        $logged_in = false;
        file_put_contents($auth_log, "\n" . logging_prefix() . " SESSION PASS false or expired for: " . $name, FILE_APPEND);
    }
    if ($CONFIG['anonuser'] == '1') {
        $logged_in = false;
    }
    return $logged_in;
}

function set_user_logged_in_cookies($name, $keys)
{

    global $debug_log, $CONFIG;
    $name = trim($name);
    $name_lc = strtolower($name);

    if ($name == $CONFIG['anonusername']) {
        return false;
    }

    if (!get_user_config($name_lc, 'encryptionkey')) {
        $key = openssl_random_pseudo_bytes(44);
        set_user_config($name_lc, 'encryptionkey', base64_encode($key));
        debug_log("Created encryptionkey for: " . $name, $debug_log);
    }

    $auth_expire = 14400;
    $authkey = password_hash($name_lc . $keys[0] . get_user_config($name, 'encryptionkey'), PASSWORD_DEFAULT);
    $pkey = hash('crc32', get_user_config($name, 'encryptionkey'));
    set_user_config(strtolower($name), "pkey", $pkey);
    $_SESSION['pass'] = true;
    // FIXME/TODO inline javascript...
    ?>
    <script type="text/javascript">
        if (navigator.cookieEnabled)
            var authcookie = "<?php echo $authkey; ?>";
        var savename = "<?php echo stripslashes($name); ?>";
        var auth_expire = "<?php echo $auth_expire; ?>";
        var name_expire = "7776000";
        var pkey = "<?php echo $pkey; ?>";
        document.cookie = "mail_auth=" + authcookie + "; max-age=" + auth_expire + "; path=/";
        document.cookie = "mail_name=" + savename + "; max-age=" + name_expire + "; path=/";
        document.cookie = "pkey=" + pkey + "; max-age=" + name_expire + "; path=/";
    </script>
<?php
    return true;
}
function get_date_for_client_timezone($date)
{
    global $text_header, $CONFIG, $OVERRIDES;
    if (isset($_COOKIE['tzo'])) {
        $offset = $_COOKIE['tzo'];
    } else {
        $offset = intval($CONFIG['timezone']);
    }
    if (isset($_COOKIE['tzid']) && (isset($OVERRIDES['timezone_to_local_format']) && $OVERRIDES['timezone_to_local_format'] == 'none')) {
        $datetime = new DateTime(date($text_header["date_format"], $date));
        $client_time = new DateTimeZone($_COOKIE['tzid']);
        $datetime->setTimezone($client_time);
        $displaydate =  $datetime->format('D, j M Y H:i');
    } else {
        $datetime = new DateTime(date($text_header["date_format"], $date), new DateTimeZone('UTC'));
        $datetime->add(DateInterval::createFromDateString($offset . ' minutes'));
        if ($offset != 0) {
            $offset_hours = ($offset / 60) * 100;
            $displaydate = $datetime->format('D, j M Y H:i') . " " . sprintf('%05d', $offset_hours);
        } else {
            $offset_hours = ($offset / 60) * 100;
            $displaydate = $datetime->format($text_header["date_format"]);
        }
    }
    unset($datetime);
    return $displaydate;
}

function check_bbs_auth($username, $password, $sockip = null)
{
    global $config_dir, $spooldir, $CONFIG, $auth_log;

    $logfile = $auth_log;
    if ($username == '' && $password == '') {
        return false;
    }

    $workpath = $config_dir . "users/";
    $username = trim(strtolower($username));
    $userFilename = $workpath . $username;
    $banned_list = file($config_dir . '/banned_users.conf');
    $keyFilename = $config_dir . "/userconfig/" . $username;

    foreach ($banned_list as $banned) {
        if ($banned[0] == '#') {
            continue;
        }
        if (strtolower(trim($username)) == strtolower(trim($banned))) {
            file_put_contents($logfile, "\n" . logging_prefix($sockip) . " AUTH Failed for: " . $username . ' (user is banned)', FILE_APPEND);
            return false;
        }
    }

    // Create accounts for $anonymous and $CONFIG['server_auth_user'] if not exist
    if ($username == strtolower($CONFIG['anonusername'])) {
        if (filemtime($config_dir . "rslight.inc.php") > filemtime($userFilename)) {
            if ($userFileHandle = fopen($userFilename, 'w+')) {
                fwrite($userFileHandle, password_hash($CONFIG['anonuserpass'], PASSWORD_DEFAULT));
                fclose($userFileHandle);
            }
        }
    }
    if ($username == strtolower($CONFIG['server_auth_user'])) {
        if (filemtime($config_dir . "rslight.inc.php") > filemtime($userFilename)) {
            if ($userFileHandle = fopen($userFilename, 'w+')) {
                fwrite($userFileHandle, password_hash($CONFIG['server_auth_pass'], PASSWORD_DEFAULT));
                fclose($userFileHandle);
            }
        }
    }

    if (trim($username) == strtolower($CONFIG['anonusername']) && $CONFIG['anonuser'] != true) {
        file_put_contents($logfile, "\n" . logging_prefix($sockip) . " AUTH Failed for: " . $username . ' (' . $CONFIG["anonusername"] . ' is disabled)', FILE_APPEND);
        return FALSE;
    }

    if ($userFileHandle = fopen($userFilename, 'r')) {
        $userFileInfo = fread($userFileHandle, filesize($userFilename));
        fclose($userFileHandle);
        if (password_verify($password, $userFileInfo)) {
            touch($userFilename);
            $ok = TRUE;
        } else {
            if (trim($password) == '') {
                file_put_contents($logfile, "\n" . logging_prefix($sockip) . " AUTH Failed for: " . $username . ' (no password)', FILE_APPEND);
            } else {
                file_put_contents($logfile, "\n" . logging_prefix($sockip) . " AUTH Failed for: " . $username . ' (password incorrect)', FILE_APPEND);
            }
            return FALSE;
        }
    } else {
        $ok = FALSE;
    }
    if ($ok) {
        if ($username != $CONFIG['server_auth_user']) {
            file_put_contents($logfile, "\n" . logging_prefix($sockip) . " AUTH OK for: " . $username, FILE_APPEND);
            if (isset($_SESSION)) {
                $_SESSION['start_address'] = $_SERVER['REMOTE_ADDR'];
                file_put_contents($logfile, "\n" . logging_prefix($sockip) . " SET IP address for: " . $username, FILE_APPEND);
            }
        }
        return TRUE;
    } else {
        if (isset($CONFIG['auto_create']) && $CONFIG['auto_create'] == true) {
            if ($userFileHandle = @fopen($userFilename, 'w+')) {
                fwrite($userFileHandle, password_hash($password, PASSWORD_DEFAULT));
                fclose($userFileHandle);
                chmod($userFilename, 0666);
            }
            $newkey = base64_encode(openssl_random_pseudo_bytes(44));
            if ($userFileHandle = @fopen($keyFilename, 'w+')) {
                fwrite($userFileHandle, 'encryptionkey:' . $newkey);
                fclose($userFileHandle);
                chmod($userFilename, 0666);
            }
            file_put_contents($logfile, "\n" . logging_prefix($sockip) . " AUTH OK for: " . $username . ' (auto created user)', FILE_APPEND);
            $_SESSION['start_address'] = $_SERVER['REMOTE_ADDR'];
            return TRUE;
        } else {
            file_put_contents($logfile, "\n" . logging_prefix($sockip) . " AUTH Failed for: " . $username, FILE_APPEND);
            return FALSE;
        }
    }
}

function check_encryption_groups($request)
{
    global $config_dir;
    $groupsFilename = $config_dir . "encryption_ok.txt";
    if ($groupsFileHandle = @fopen($groupsFilename, 'r')) {
        while (! feof($groupsFileHandle)) {
            $buffer = fgets($groupsFileHandle);
            $buffer = str_replace(array(
                "\r",
                "\n"
            ), '', $buffer);
            if (! strcmp($buffer, $request)) {
                fclose($groupsFileHandle);
                return TRUE;
            }
        }
        fclose($groupsFileHandle);
    } else {
        return FALSE;
    }
}

// Sets a user's config value. $newval = false removes the setting entirely
function set_user_config($username, $request, $newval)
{
    global $config_dir;
    $userconfigpath = $config_dir . "userconfig/";
    $username = strtolower($username);
    $userFilename = $userconfigpath . $username;
    $userData = file($userFilename, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $userFileHandle = fopen($userFilename, 'w');

    $found = 0;
    foreach ($userData as $data) {
        if (strpos($data, $request . ':') !== FALSE) {
            if ($newval !== false) {
                fputs($userFileHandle, $request . ':' . $newval . "\r\n");
            }
            $found = 1;
        } else {
            fputs($userFileHandle, $data . "\r\n");
        }
    }
    if ($found == 0) {
        fputs($userFileHandle, $request . ':' . $newval . "\r\n");
    }
    fclose($userFileHandle);
    return;
}

function get_user_config($username, $request)
{
    global $config_dir;
    $userconfigpath = $config_dir . "userconfig/";
    $username = strtolower($username);
    $userFilename = $userconfigpath . $username;

    if ($userFileHandle = @fopen($userFilename, 'r')) {
        while (! feof($userFileHandle)) {
            $buffer = fgets($userFileHandle);
            if (strpos($buffer, $request . ':') !== FALSE) {
                $userdataline = $buffer;
                fclose($userFileHandle);
                $userdatafound = explode(':', $userdataline);
                return trim($userdatafound[1]);
            }
        }
        fclose($userFileHandle);
        return FALSE;
    } else {
        return FALSE;
    }
}

function is_multibyte($s)
{
    return mb_strlen($s, 'utf-8') < strlen($s);
}

function check_spam($subject, $from, $newsgroups, $ref, $body, $msgid, $useheaders = false)
{
    global $msgid_generate, $msgid_fqdn, $spooldir, $logdir;
    global $CONFIG;
    $spamdir = $spooldir . '/spam';
    if (! is_dir($spamdir)) {
        mkdir($spamdir);
    }
    $logfile = $logdir . '/spam.log';
    $spamfile = tempnam($spooldir, 'spam-');
    if ($useheaders) {
        // Add headers
        $head = '';
        if (trim($subject) != '') {
            $head .= 'Subject: ' . $subject . "\r\n";
        }
        if (trim($from) != '') {
            $head .= 'From: ' . $from . "\r\n";
        }
        if (trim($newsgroups) != '') {
            $head .= 'Newsgroups: ' . $newsgroups . "\r\n";
        }
        if (trim($ref) != '') {
            $head .= 'References: ' . $ref . "\r\n";
        }
        if (trim($msgid) != '') {
            $head .= 'Message-ID: ' . $msgid . "\r\n";
        }
        $message = $head . "\r\n" . $body;
    } else {
        $message = $body;
    }
    file_put_contents($spamfile, $message);
    $spamcommand = $CONFIG['spamc'] . ' -E < ' . $spamfile;
    ob_start();
    passthru($spamcommand, $res);
    $spamresult = ob_get_contents();
    ob_end_clean();
    $spam_fail = 1;
    foreach (explode(PHP_EOL, $spamresult) as $line) {
        $line = str_replace(array(
            "\n\r",
            "\n",
            "\r"
        ), '', $line);
        if (strpos($line, 'X-Spam-Checker-Version:') !== FALSE) {
            $spamcheckerversion = $line;
            $spam_fail = 0;
        }
        if (strpos($line, 'X-Spam-Level:') !== FALSE) {
            $spamlevel = $line;
        }
        if ((strpos($line, "X-Spam-Flag: YES") === 0) && ($res !== 1)) {
            $res = 1;
        }
    }
    unlink($spamfile);
    if ($res === 1) {
        file_put_contents($logfile, "\n" . logging_prefix() . " spamc:\tSPAM\t" . $msgid . "\t" . $newsgroups . "\t" . preg_replace('/\t/', ' ', $from), FILE_APPEND);
        file_put_contents($spamdir . '/' . $msgid, $spamresult);
    } else {
        file_put_contents($logfile, "\n" . logging_prefix() . " spamc:\tHAM\t" . $msgid . "\t" . $newsgroups . "\t" . preg_replace('/\t/', ' ', $from), FILE_APPEND);
    }
    return array(
        'res' => $res,
        'spamresult' => $spamresult,
        'spamcheckerversion' => $spamcheckerversion,
        'spamlevel' => $spamlevel,
        'spam_fail' => $spam_fail
    );
}

function repair_broken_group($group)
{
    global $debug_log, $spooldir;
    $rslight_file = $spooldir . '/' . $group . '-rslight_info.txt';
    $newsportal_file = $spooldir . '/' . $group . '-info.txt';

    if (file_exists($rslight_file) && file_exists($newsportal_file)) {
        $rslight_info = file_get_contents($rslight_file);
        $newsportal_info = file($newsportal_file);
        $newsportal_info = trim($newsportal_info[1]);
        $newsportal_start = explode(" ", $newsportal_info);
        $rslight_start = explode(" ", $rslight_info);
        if ($newsportal_start[0] != $rslight_start[0] || (($rslight_start[2] - $newsportal_start[2]) > 10)) {
            file_put_contents($debug_log, "\n" . format_log_date() . " GROUP MISMATCH: " . $group . " rslight: " . $rslight_info . " newsportal: " . $newsportal_info . " Repairing...", FILE_APPEND);
            wipe_newsportal_spool_info($group);
        }
    }
}

function wipe_newsportal_spool_info($group)
{
    global $spooldir; $file_search;
    $gpath = $spooldir . '/' . $group;
    @unlink($gpath . '-cache.txt');
    @unlink($gpath . '-data.dat');
    @unlink($gpath . '-firstarticleinfo.dat');
    @unlink($gpath . '-lastarticleinfo.dat');
    @unlink($gpath . '-info.txt');
    @unlink($gpath . '-overboard.dat');
}

function create_name_link($name, $data = null, $truncate = true)
{
    global $CONFIG;
    $name = preg_replace('/\"/', '', $name);

    if ($truncate) {
        $trimlength = 20;
    } else {
        $trimlength = null;
    }

    if ($data) {
        $data = urlencode(base64_encode($data));
    }
    if ((strpos($name, '...@') !== false && (isset($CONFIG['hide_email']) && $CONFIG['hide_email'] == true)) && ! $data) {
        $return = '<span class="create_name_link">' . substr(htmlspecialchars($name), 0, $trimlength) . '</span>';
    } else {
        if (isset($_COOKIE['mail_name'])) {
            $return = '<a href="'.$file_search.'&command=search&searchpoint=Poster&terms=' . urlencode($name) . '&data=' . $data . '" title="Search or Block by user"><span class="visited">' . substr(htmlspecialchars($name), 0, $trimlength) . '</span></a>';
        } else {
            $return = '<a href="'.$file_search.'&command=search&searchpoint=Poster&terms=' . urlencode($name) . '&data=' . $data . '" title="Search by user"><span class="visited">' . substr(htmlspecialchars($name), 0, $trimlength) . '</span></a>';
        }
    }
    return ($return);
}

function truncate_email($address)
{
    $before_at = explode('@', $address);
    $namelen = strlen($before_at[0]);
    if ($namelen > 3) {
        $endname = $namelen - 3;
        if ($endname > 8) {
            $endname = 8;
        }
        if ($endname < 3) {
            $endname++;
        }
        if ($endname < 3) {
            $endname++;
        }
    } else {
        $endname = $namelen;
    }
    return substr($before_at[0], 0, $endname) . '...' . substr($address, $namelen, strlen($address));
}

function get_date_interval($value)
{
    $current = time();
    $datetime1 = date_create($value);
    $datetime2 = date_create("@$current");
    $interval = date_diff($datetime1, $datetime2);
    if (! $interval) {
        return '(date error)';
    }
    $years = $interval->format('%y') . " Years ";
    $months = $interval->format('%m') . " Months ";
    $days = $interval->format('%d') . " Days ";
    $hours = $interval->format('%h') . " Hours ";
    $minutes = $interval->format('%i') . " Minutes ";
    if ($interval->format('%y') == 1) {
        $years = $interval->format('%y') . " Year ";
    }
    if ($interval->format('%m') == 1) {
        $months = $interval->format('%m') . " Month ";
    }
    if ($interval->format('%d') == 1) {
        $days = $interval->format('%d') . " Day ";
    }
    if ($interval->format('%h') == 1) {
        $hours = $interval->format('%h') . " Hour ";
    }
    if ($interval->format('%i') == 1) {
        $minutes = $interval->format('%i') . " Minute ";
    }
    if ($interval->format('%y') == 0) {
        $years = '';
    }
    if ($interval->format('%m') == 0) {
        $months = '';
    }
    if ($interval->format('%d') == 0) {
        $days = '';
    }
    if ($interval->format('%h') == 0) {
        $hours = '';
    }
    if ($interval->format('%i') == 0) {
        $minutes = '';
    }
    $variance = $interval->format($years . $months . $days . $hours . $minutes . ' ago');
    if (strlen($variance) < 5) {
        $variance = " now";
    }
    return $variance;
}

function get_newsgroups_by_msgid($msgid, $noarray = false)
{
    global $spooldir, $config_dir, $logdir, $CONFIG;
    if (file_exists($config_dir . '/cache.inc.php')) {
        include $config_dir . '/cache.inc.php';
    }
    if ($enable_cache) {
        $memcache_key = $cache_key_prefix . '_' . 'get_newsgroups_by_msgid-' . $msgid;
        if ($getgroups = cache_get($memcache_key, $memcacheD)) {
            try {
                $groups = secure_unserialize($getgroups);
                if (!is_array($groups)) {
                    $groups = false;
                }
            } catch (Exception $e) {
                $groups = false;
            }
            if ($groups && $enable_cache_logging) {
                file_put_contents($cache_log, "\n" . logging_prefix() . " (cache hit) $memcache_key", FILE_APPEND);
            }
        }
    }
    if (! isset($groups)) {
        $database = $spooldir . '/articles-overview.db3';
        $table = 'overview';
        $overview_dbh = overview_db_open($database, $table);
        $overview_stmt = $overview_dbh->prepare("SELECT newsgroup FROM overview WHERE msgid=:msgid");
        $overview_stmt->bindParam(':msgid', $msgid);
        $overview_stmt->execute();

        $found = false;
        $groups = array();
        while ($row = $overview_stmt->fetch()) {
            $groups[] = $row['newsgroup'];
            $found = true;
        }
        if (! $found) {
            $groups = null;
        }
        $overview_dbh = null;
        if ($groups && $enable_cache) {
            $nicole = cache_add($memcache_key, serialize($groups), $cache_ttl, $memcacheD);
            if ($enable_cache_logging && $nicole) {
                file_put_contents($cache_log, "\n" . logging_prefix() . " (cache write) $memcache_key", FILE_APPEND);
            }
        }
    }
    if ($noarray) {
        return (implode(",", $groups));
    } else {
        return ($groups);
    }
}

function create_xref_from_msgid($msgid, $thisgroup = null, $thisnumber = null)
{
    global $spooldir, $CONFIG;
    $database = $spooldir . '/articles-overview.db3';
    $table = 'overview';
    $overview_dbh = overview_db_open($database, $table);
    $overview_stmt = $overview_dbh->prepare("SELECT * FROM overview WHERE msgid=:msgid");
    $overview_stmt->bindParam(':msgid', $msgid);
    $overview_stmt->execute();

    $found = false;
    $xref = "Xref: " . $CONFIG['pathhost'];
    while ($row = $overview_stmt->fetch()) {
        if ($row['newsgroup'] == $thisgroup && $thisgroup != null) {
            $found = true;
        }
        $xref .= ' ' . $row['newsgroup'] . ':' . $row['number'];
    }
    if (! $found) {
        $xref .= ' ' . $thisgroup . ':' . $thisnumber;
    }
    $overview_dbh = null;
    return ($xref);
}

function get_search_snippet($body, $content_type = '', $content_transfer_encoding = null)
{
    if ($content_transfer_encoding == 'base64') {
        $body = base64_decode($body);
    }
    if ($content_transfer_encoding == 'quoted-printable') {
        $body = quoted_printable_decode($body);
    }
    if ($content_type !== '') {
        $content_type = explode(';', $content_type);
        $mysnippet = recode_charset($body, $content_type[0]);
    } else {
        $mysnippet = $body;
    }
    if ($bodyend = strrpos($mysnippet, "\n---\n")) {
        $mysnippet = substr($mysnippet, 0, $bodyend);
    } else {
        if ($bodyend = strrpos($mysnippet, "\n-- ")) {
            $mysnippet = substr($mysnippet, 0, $bodyend);
        } else {
            if ($bodyend = strrpos($mysnippet, "\n.")) {
                $mysnippet = substr($mysnippet, 0, $bodyend);
            }
        }
    }
    $mysnippet = preg_replace('/\n.{0,5}>(.*)/', '', $mysnippet);

    $snipstart = strpos($mysnippet, ":\n");
    if (substr_count(trim(substr($mysnippet, 0, $snipstart)), "\n") < 2) {
        $mysnippet = substr($mysnippet, $snipstart + 1);
    } else {
        $mysnippet = substr($mysnippet, 0);
    }
    return $mysnippet;
}

function get_history_status($msgid, $group)
{
    global $spooldir, $logfile;
    $database = $spooldir . '/history.db3';
    $table = 'history';
    $history_dbh = history_db_open($database, $table);

    $returnval = false;
    $history_stmt = $history_dbh->prepare("SELECT * FROM $table WHERE msgid=:msgid AND newsgroup=:newsgroup");
    $history_stmt->bindParam(':msgid', $msgid);
    $history_stmt->bindParam(':newsgroup', $group);
    $history_stmt->execute();
    while ($row = $history_stmt->fetch()) {
        if ($row['msgid'] == $msgid) {
            $returnval = $row;
            break;
        }
    }
    $history_dbh = null;
    return $returnval;
}

function mail_db_open($database, $table = 'messages')
{
    try {
        $dbh = new PDO('sqlite:' . $database);

        // Apply database optimizations for performance
        if (class_exists('DatabaseOptimizer')) {
            $optimizer = new DatabaseOptimizer(false); // Disable monitoring for production
            $optimizer->optimizeDatabase($dbh, 'mail');
        }
    } catch (PDOException $e) {
        echo 'Connection failed: ' . $e->getMessage();
        exit();
    }
    $dbh->exec("CREATE TABLE IF NOT EXISTS messages(
     id INTEGER PRIMARY KEY,
     msgid TEXT UNIQUE,
     mail_from TEXT,
     mail_viewed TEXT,
     rcpt_to TEXT,
     rcpt_viewed TEXT,
     rcpt_target TEXT,
     date TEXT,
     subject TEXT,
     message TEXT,
     from_hide TEXT,
     to_hide TEXT)");
    return ($dbh);
}

function history_db_open($database, $table = 'history')
{
    try {
        $dbh = new PDO('sqlite:' . $database);

        // Apply database optimizations for performance
        if (class_exists('DatabaseOptimizer')) {
            $optimizer = new DatabaseOptimizer(false); // Disable monitoring for production
            $optimizer->optimizeDatabase($dbh, 'history');
        }
    } catch (PDOException $e) {
        echo 'Connection failed: ' . $e->getMessage();
        exit();
    }
    $dbh->exec("CREATE TABLE IF NOT EXISTS history(
            id INTEGER PRIMARY KEY,
            newsgroup TEXT,
			number TEXT,
			msgid TEXT,
			status TEXT,
			statusdate TEXT,
			statusreason TEXT,
			statusnotes TEXT,
			unique (newsgroup, msgid),
			unique (newsgroup, number))");
    $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS id_status on ' . $table . '(status)');
    $stmt->execute();
    $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS id_newsgroup on ' . $table . '(newsgroup)');
    $stmt->execute();
    $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS id_msgid on ' . $table . '(msgid)');
    $stmt->execute();
    $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS id_newsgroup_number on ' . $table . '(newsgroup,number)');
    $stmt->execute();
    $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS id_statusdate on ' . $table . '(statusdate)');
    $stmt->execute();
    return ($dbh);
}

function overview_db_open($database, $table = 'overview')
{
    try {
        $dbh = new PDO('sqlite:' . $database);

        // Apply database optimizations for performance
        if (class_exists('DatabaseOptimizer')) {
            $optimizer = new DatabaseOptimizer(false); // Disable monitoring for production
            $optimizer->optimizeDatabase($dbh, 'overview');
        }
    } catch (PDOException $e) {
        error_log_always('Overview database connection failed: ' . $e->getMessage(), $GLOBALS['debug_log']);
        return false;
    }
    $dbh->exec("CREATE TABLE IF NOT EXISTS overview(
     id INTEGER PRIMARY KEY,
     newsgroup TEXT,
     number TEXT,
     msgid TEXT,
     date TEXT,
     datestring TEXT,
     name TEXT,
     subject TEXT,
     refs TEXT,
     bytes TEXT,
     lines TEXT,
     xref TEXT,
     unique (newsgroup, msgid),
     unique (newsgroup, number))");
    $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS id_date on ' . $table . '(date)');
    $stmt->execute();
    $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS id_newsgroup on ' . $table . '(newsgroup)');
    $stmt->execute();
    $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS id_msgid on ' . $table . '(msgid)');
    $stmt->execute();
    $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS id_newsgroup_number on ' . $table . '(newsgroup,number)');
    $stmt->execute();
    $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS id_name on ' . $table . '(name)');
    $stmt->execute();
    return ($dbh);
}

function article_db_open($database, $table = 'articles')
{
    global $spooldir, $logdir, $config_name;
    $logfile = $logdir . '/debug.log';
    $spoolpath = "/" . preg_replace("/\//", "\/", $spooldir) . "/";
    $group = preg_replace("/\-articles\.db3/", "", $database);
    $group = preg_replace($spoolpath, "", $group);
    $group = preg_replace("/\//", "", $group);
    if (! preg_match('/\-articles\.db3\-new/', $database)) {
        if (! get_section_by_group($group, true)) {
            // Log section configuration issues for debugging
            debug_log("Group '$group' not found in section configuration, cannot create database", $logfile);
            return false;
        }
    }
    try {
        $dbh = new PDO('sqlite:' . $database);

        // Apply database optimizations for performance
        if (class_exists('DatabaseOptimizer')) {
            $optimizer = new DatabaseOptimizer(false); // Disable monitoring for production
            $optimizer->optimizeDatabase($dbh, 'article');
        }
    } catch (PDOException $e) {
        error_log_always('Article database connection failed: ' . $e->getMessage(), $GLOBALS['debug_log']);
        return false;
    }
    $dbh->exec("CREATE TABLE IF NOT EXISTS articles(
     id INTEGER PRIMARY KEY,
     newsgroup TEXT,
     number TEXT UNIQUE,
     msgid TEXT UNIQUE,
     date TEXT,
     name TEXT,
     subject TEXT,
     search_snippet TEXT,
     article TEXT)");

    $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS db_number on ' . $table . '(number)');
    $stmt->execute();
    $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS db_date on ' . $table . '(date)');
    $stmt->execute();
    $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS db_msgid on ' . $table . '(msgid)');
    $stmt->execute();
    $stmt = $dbh->query('CREATE INDEX IF NOT EXISTS db_name on ' . $table . '(name)');
    $stmt->execute();

    $dbh->exec("CREATE VIRTUAL TABLE IF NOT EXISTS search_fts USING fts5(
     newsgroup,
     number,
     msgid,
     date,
     name,
     subject,
     search_snippet)");
    $dbh->exec("CREATE TRIGGER IF NOT EXISTS after_articles_insert AFTER INSERT ON $table BEGIN
	INSERT INTO search_fts(newsgroup, number, msgid, date, name, subject, search_snippet) VALUES(new.newsgroup, new.number, new.msgid, new.date, new.name, new.subject, new.search_snippet);
	END;");
    $dbh->exec("CREATE TRIGGER IF NOT EXISTS after_articles_delete AFTER DELETE ON $table BEGIN
	DELETE FROM search_fts WHERE msgid = old.msgid;
	END;");
    return ($dbh);
}

function np_get_db_article($article, $group, $makearray = 1, $dbh = null)
{
    global $config_dir, $path, $groupconfig, $config_name, $logdir, $spooldir;
    $logfile = $logdir . '/newsportal.log';

    if (file_exists($config_dir . '/cache.inc.php')) {
        include $config_dir . '/cache.inc.php';
    }

    $msg2 = "";
    $closeme = 0;
    $ok_article = 0;
    // Check memcache
    if ($enable_cache) {
        $article_key = $cache_key_prefix . '_' . 'article.db3-' . $group . ':' . $article;
        if ($msg2 = gzuncompress(cache_get($article_key, $memcacheD))) {
            $ok_article = 1;
            if ($enable_cache_logging) {
                file_put_contents($cache_log, "\n" . logging_prefix() . " (cache hit) $article_key", FILE_APPEND);
            }
        }
    }
    if (! $ok_article) {
        $database = $spooldir . '/' . $group . '-articles.db3';
        if (! $dbh) {
            if (! is_file($database)) {
                return FALSE;
            }
            $dbh = article_db_open($database);
            $closeme = 1;
        }
        // By Message-ID
        if (! is_numeric($article)) {
            $stmt = $dbh->prepare("SELECT * FROM articles WHERE msgid like :terms");
            $stmt->bindParam(':terms', $article);
            $stmt->execute();
            while ($found = $stmt->fetch()) {
                $msg2 = $found['article'];
                $ok_article = 1;
                break;
            }
        } else {
            $stmt = $dbh->prepare("SELECT * FROM articles WHERE number = :terms");
            $stmt->bindParam(':terms', $article);
            $stmt->execute();
            while ($found = $stmt->fetch()) {
                $msg2 = $found['article'];
                $ok_article = 1;
                break;
            }
        }
        if ($ok_article == 1 && $enable_cache) {
            $nicole = cache_add($article_key, gzcompress($msg2), $cache_ttl, $memcacheD);
            if ($enable_cache_logging && $nicole) {
                file_put_contents($cache_log, "\n" . logging_prefix() . " (cache write) $article_key", FILE_APPEND);
            }
        }
    }
    if ($closeme == 1) {
        $dbh = null;
    }
    if ($ok_article !== 1) {
        // file_put_contents($logfile, "\n".format_log_date()." ".$config_name." DEBUG: ".$article." from ".$group." not found in database", FILE_APPEND);
        return FALSE;
    }
    // file_put_contents($logfile, "\n".format_log_date()." ".$config_name." DEBUG: fetched: ".$article." from ".$group, FILE_APPEND);
    if ($makearray == 1) {
        $thisarticle = preg_split("/\r\n|\n|\r/", trim($msg2));
        array_pop($thisarticle);
        return $thisarticle;
    } else {
        return trim($msg2);
    }
}

function get_poster_name($name)
{
    $fromline = address_decode($name, "nowhere");
    if (! isset($fromline[0]["host"]))
        $fromline[0]["host"] = "";
    $name_from = $fromline[0]["mailbox"] . "@" . $fromline[0]["host"];
    $name_username = $fromline[0]["mailbox"];
    if (! isset($fromline[0]["personal"])) {
        $poster_name = $fromline[0]["mailbox"];
    } else {
        $poster_name = $fromline[0]["personal"];
    }
    if (trim($poster_name) == '') {
        $fromoutput = explode("<", html_entity_decode($name));
        if (strlen($fromoutput[0]) < 1) {
            $poster_name = $fromoutput[1];
        } else {
            $poster_name = $fromoutput[0];
        }
    }
    $thisposter['name'] = $poster_name;
    $thisposter['from'] = $name_from;
    return ($thisposter);
}

/*
 * This function returns false on success
 * or return value contains error info
 * 'added' etc.
 */
function save_config_value($configfile, $name, $value, $value_unique = false)
{
    global $spooldir, $logdir;
    $return_val = false;
    $tempfile = tempnam($spooldir, 'rslight-');
    if (file_exists($tempfile)) {
        unlink($tempfile);
    }
    $lines = file($configfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $found = false;
    foreach ($lines as $line) {
        $current = explode(':', $line);
        if ($value_unique && (strcmp($current[1], $value) == 0)) {
            // Found value. Write once
            if (! $found) {
                file_put_contents($tempfile, $name . ":" . $value . "\n", FILE_APPEND);
            }
            $found = true;
            continue;
        }
        if (strcmp($current[0], $name) == 0) {
            // $name matches option. Overwrite
            file_put_contents($tempfile, $name . ":" . $value . "\n", FILE_APPEND);
            $found = true;
        } else {
            // $name does not match option. Keep current line
            file_put_contents($tempfile, $line . "\n", FILE_APPEND);
        }
    }
    if (! $found) {
        // $name not found in options. Add to file.
        file_put_contents($tempfile, $name . ":" . $value . "\n", FILE_APPEND);
    }
    copy($tempfile, $configfile);
    unlink($tempfile);
    return $return_val;
}

function get_config_file_value($configfile, $request)
{
    if ($configFileHandle = @fopen($configfile, 'r')) {
        while (! feof($configFileHandle)) {
            $buffer = fgets($configFileHandle);
            if (strpos($buffer, $request . ':') !== FALSE) {
                $dataline = $buffer;
                fclose($configFileHandle);
                $datafound = explode(':', $dataline);
                return trim($datafound[1]);
            }
        }
        fclose($configFileHandle);
        return FALSE;
    } else {
        return FALSE;
    }
}

// This function is specific to $config_dir configuration values
function get_config_value($configfile, $request)
{
    global $config_dir;

    if ($configFileHandle = @fopen($config_dir . '/' . $configfile, 'r')) {
        while (! feof($configFileHandle)) {
            $buffer = fgets($configFileHandle);
            if (strpos($buffer, $request . ':') !== FALSE) {
                $dataline = $buffer;
                fclose($configFileHandle);
                $datafound = explode(':', $dataline);
                return trim($datafound[1]);
            }
        }
        fclose($configFileHandle);
        return FALSE;
    } else {
        return FALSE;
    }
}

function disable_page_by_user_agent($client_device, $useragent, $script = "Page")
{
    global $logdir, $config_name;
    if (! $client_device) {
        $client_device = get_client_user_agent_info();
    }
    $client_device = strtolower($client_device);
    if ($client_device == $useragent) {
        $logfile = $logdir . '/device.log';
        file_put_contents($logfile, "\n" . logging_prefix() . " " . $config_name . " " . $script . " disabled for '" . $useragent . "' Exiting...", FILE_APPEND);
        if ($client_device == "bot") {
            $_SESSION['bot'] = true;
        }
        return true;
    } else {
        return false;
    }
}

/* This function sends internet email
 * $subject and $body are strings
 * $mail_to is an email address to send to
 * $mail_from is an email address to use as from
 * $mail_name is the name to use with $mail_from when sending
 *   required if setting $mail_from
 * DEFAULT is Admin address for to (phpmailer.inc.php)
 * DEFAULT is standard From address for from (phpmailer.inc.php)
 */
function send_internet_email($subject, $body, $mail_to = false, $mail_from = false, $mail_name = false)
{
    global $CONFIG, $config_dir, $spooldir, $keys;
    global $debug_log, $mail_log;

    include($config_dir . '/phpmailer.inc.php');
    if (class_exists('PHPMailer')) {
        $mail = new PHPMailer();
    } else {
        $mail = new PHPMailer\PHPMailer\PHPMailer();
    }

    if (!$mail) {
        return false;
    }

    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );

    $mail->IsSMTP();
    # uncomment below to enable debugging
    # $mail->SMTPDebug = 3;

    $mail->CharSet = 'UTF-8';
    $mail->Host = $mailer['host'];
    $mail->SMTPAuth = true;

    $mail->Port = $mailer['port'];
    $mail->Username = $mailer['username'];
    $mail->Password = $mailer['password'];
    $mail->SMTPSecure = 'tls';


    if ($mail_from != false) {
        $mail->setFrom($mail_from, $mail_name);
    } else {
        $mail->setFrom($mail_user . '@' . $mail_domain, $mail_name); // Default Admin
        $mail_from = $mail_user . '@' . $mail_domain;
    }

    if ($mail_to != false) {
        $mail->addAddress($mail_to);
    } else {
        $mail->addAddress($mail_admin_user . '@' . $mail_admin_domain, $mail_admin_name); // Default Admin
        $mail_to = $mail_admin_user . '@' . $mail_admin_domain;
    }

    $mail->Subject = $subject;

    foreach ($mail_custom_header as $key => $value) {
        $mail->addCustomHeader($key, $value);
    }

    $mail->Body = $body;

    if (!$mail->send()) {
        file_put_contents($mail_log, "\n" . format_log_date() . ' FAILED to send mail from: ' . $mail_from . ' to: ' . $mail_to . 'Error: ' . $mail->ErrorInfo, FILE_APPEND);
        return true;
    } else {
        file_put_contents($mail_log, "\n" . format_log_date() . ' SENT mail from: ' . $mail_from . ' to: ' . $mail_to, FILE_APPEND);
        return false;
    }
}

/* get_user_mail_auth_data is poorly named but
 * it retrieves newsgroup status per user info
 * for subscribe/unsubscribe/read/unread
 */
function get_user_mail_auth_data($user)
{
    global $spooldir;
    $userdata = array();
    $user = strtolower($user);
    $pkey_config = get_user_config($user, "pkey");
    if (! isset($_COOKIE['pkey'])) {
        $_COOKIE['pkey'] = null;
    }
    $pkey_cookie = $_COOKIE['pkey'];
    if ((! isset($_COOKIE['pkey'])) || $pkey_config == false || $pkey_cookie == false) {
        return false;
    }
    if ($pkey_config == $pkey_cookie) {
        $userfile = $spooldir . '/' . $user . '-articleviews.dat';
        if (is_file($userfile)) {
            try {
                $userdata = secure_unserialize(file_get_contents($userfile));
                if (!is_array($userdata)) {
                    $userdata = array();
                }
            } catch (Exception $e) {
                $userdata = array();
            }
            if (isset($userdata['DO.NOT.DELETE'])) {
                $userdata['DO.NOT.DELETE'] = time();
            }
        } else {
            $userdata['DO.NOT.DELETE'] = time();
        }
        return $userdata;
    }
    return false;
}

function verify_gpg_signature($res, $signed_text)
{
    $result = gnupg_verify($res, $signed_text, false);
    if ($result == false) {
        return false;
    }
    if ((($result[0]['summary'] > 3)) || $result[0]['validity'] == 2) {
        return false; // Bad signature
    } else {
        return true; // Good signature
    }
}

function mb_wordwrap($string, $width = 75, $break = "\n", $cut = false)
{
    $string = (string) $string;
    if ($string === '') {
        return '';
    }
    $break = (string) $break;
    if ($break === '') {
        trigger_error('Break string cannot be empty', E_USER_ERROR);
    }
    $width = (int) $width;
    if ($width === 0 && $cut) {
        trigger_error('Cannot force cut when width is zero', E_USER_ERROR);
    }
    if (strlen($string) === mb_strlen($string)) {
        return wordwrap($string, $width, $break, $cut);
    }
    $stringWidth = mb_strlen($string);
    $breakWidth = mb_strlen($break);
    $result = '';
    $lastStart = $lastSpace = 0;
    for ($current = 0; $current < $stringWidth; $current++) {
        $char = mb_substr($string, $current, 1);
        $possibleBreak = $char;
        if ($breakWidth !== 1) {
            $possibleBreak = mb_substr($string, $current, $breakWidth);
        }
        if ($possibleBreak === $break) {
            $result .= mb_substr($string, $lastStart, $current - $lastStart + $breakWidth);
            $current += $breakWidth - 1;
            $lastStart = $lastSpace = $current + 1;
            continue;
        }
        if ($char === ' ') {
            if ($current - $lastStart >= $width) {
                $result .= mb_substr($string, $lastStart, $current - $lastStart) . $break;
                $lastStart = $current + 1;
            }
            $lastSpace = $current;
            continue;
        }
        if ($current - $lastStart >= $width && $cut && $lastStart >= $lastSpace) {
            $result .= mb_substr($string, $lastStart, $current - $lastStart) . $break;
            $lastStart = $lastSpace = $current;
            continue;
        }
        if ($current - $lastStart >= $width && $lastStart < $lastSpace) {
            $result .= mb_substr($string, $lastStart, $lastSpace - $lastStart) . $break;
            $lastStart = $lastSpace = $lastSpace + 1;
            continue;
        }
    }
    if ($lastStart !== $current) {
        $result .= mb_substr($string, $lastStart, $current - $lastStart);
    }
    return $result;
}

function is_moderated($newsgroups)
{
    global $CONFIG, $OVERRIDES, $spooldir;
    $moderated_groups_file = $spooldir . '/moderated_groups.dat';
    $unmoderated_groups_file = $spooldir . '/unmoderated_groups.dat';
    $moderated_groups = array();
    $unmoderated_groups = array();

    $ngroups = preg_split("/[\s,]+/", $newsgroups);
    foreach ($ngroups as $group) {
        if (file_exists($moderated_groups_file)) {
            $moderated_groups = file($moderated_groups_file, FILE_IGNORE_NEW_LINES);
            if (in_array($group, $moderated_groups)) {
                return true;
            }
        }
        if (file_exists($unmoderated_groups_file)) {
            $unmoderated_groups = file($unmoderated_groups_file, FILE_IGNORE_NEW_LINES);
            if (in_array($group, $unmoderated_groups)) {
                return false;
            }
        }
    }
    if ($CONFIG['remote_server'] == '') {
        return false;
    }
    $ns = nntp2_open();
    if (! $ns) {
        return false;
    }

    foreach ($ngroups as $group) {
        fputs($ns, "list active $group\r\n");
        while ($weg = line_read($ns)) {
            if (strcmp($weg, ".") == 0) {
                nntp_close($ns);
                return false;
            }
            if (strpos($weg, $group . ' ') !== false) {
                if (str_ends_with($weg, 'm')) {
                    nntp_close($ns);
                    if (! in_array($newsgroups, $moderated_groups)) {
                        file_put_contents($moderated_groups_file, $group . "\n", FILE_APPEND);
                    }
                    return true;
                } else {
                    nntp_close($ns);
                    if (! in_array($newsgroups, $unmoderated_groups)) {
                        file_put_contents($unmoderated_groups_file, $group . "\n", FILE_APPEND);
                    }
                    return false;
                }
            }
        }
    }
    nntp_close($ns);
    return false;
}

function get_next_article_number($group)
{
    $ok_article = get_article_list($group);
    if (!is_array($ok_article)) {
        return 1;
    }
    sort($ok_article);
    $local = $ok_article[key(array_slice($ok_article, -1, 1, true))];
    if (! is_numeric($local)) {
        $local = 0;
    }
    $local = $local + 1;
    if ($local < 1) {
        $local = 1;
    }
    while (is_deleted_post($group, $local)) {
        $local++;
    }
    return $local;
}

function get_article_list($thisgroup)
{
    global $spooldir;
    $database = $spooldir . "/articles-overview.db3";
    $table = 'overview';
    $dbh = overview_db_open($database, $table);
    $stmt = $dbh->prepare("SELECT * FROM $table WHERE newsgroup=:thisgroup ORDER BY number");
    $stmt->execute([
        'thisgroup' => $thisgroup
    ]);
    $ok_article = array();
    while ($found = $stmt->fetch()) {
        $ok_article[] = $found['number'];
    }
    $dbh = null;
    return (array_unique($ok_article));
}

function check_duplicate_msgid($msgid, $group)
{
    global $spooldir, $logdir;

    $found = false;

    $database = $spooldir . '/articles-overview.db3';
    $table = 'overview';
    $dbh = overview_db_open($database, $table);
    $stmt = $dbh->prepare("SELECT * FROM $table WHERE msgid=:msgid AND newsgroup=:newsgroup");
    $stmt->bindParam(':msgid', $msgid);
    $stmt->bindParam(':newsgroup', $group);
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        if ($row['msgid'] == $msgid) {
            $found = true;
        }
    }
    $dbh = null;

    $database = $spooldir . '/history.db3';
    $table = 'history';
    $dbh = history_db_open($database, $table);
    $stmt = $dbh->prepare("SELECT * FROM $table WHERE msgid=:msgid AND newsgroup=:newsgroup");
    $stmt->bindParam(':msgid', $msgid);
    $stmt->bindParam(':newsgroup', $group);
    $stmt->execute();
    while ($row = $stmt->fetch()) {
        if ($row['msgid'] == $msgid) {
            $found = true;
        }
    }
    $dbh = null;

    if ($found) {
        file_put_contents($logdir . '/debug.log', "\n" . format_log_date() . " FOUND Duplicate " . $msgid, FILE_APPEND);
    }

    return $found;
}

function insert_article_from_array($this_article, $check_duplicates = true)
{
    global $CONFIG, $config_name, $config_dir, $spooldir, $logdir;
    $logfile = $logdir . '/spoolnews.log';
    $group = $this_article['group'];
    $grouppath = $spooldir . '/articles/' . preg_replace('/\./', '/', $group);

    if ($check_duplicates) {
        if (check_duplicate_msgid($this_article['mid'], $group)) {
            echo "\n(newsportal)Duplicate Message-ID for: " . $group . ":" . $this_article['mid'];
            file_put_contents($logfile, "\n" . logging_prefix() . " " . $config_name . " Duplicate Message-ID for: " . $group . ":" . $this_article['mid'], FILE_APPEND);
            return "441 Insert failed (duplicate)\r\n";
        }
    }

    // Open articles Database
    if ($CONFIG['article_database'] == '1') {
        $article_dbh = article_db_open($spooldir . '/' . $group . '-articles.db3');
        if (! $article_dbh) {
            return "441 Cannot open " . $spooldir . '/' . $group . "-articles.db3\r\n";
        }
        $article_sql = 'INSERT OR IGNORE INTO articles(newsgroup, number, msgid, date, name, subject, article, search_snippet) VALUES(?,?,?,?,?,?,?,?)';
        $article_stmt = $article_dbh->prepare($article_sql);
    }
    // Open overview database
    $database = $spooldir . '/articles-overview.db3';
    $table = 'overview';
    $overview_dbh = overview_db_open($database, $table);
    if (! $overview_dbh) {
        $article_dbh = null;
        return "441 Cannot open " . $database . "\r\n";
    }
    $overview_sql = 'INSERT OR IGNORE INTO overview(newsgroup, number, msgid, date, datestring, name, subject, refs, bytes, lines, xref) VALUES(?,?,?,?,?,?,?,?,?,?,?)';
    $overview_stmt = $overview_dbh->prepare($overview_sql);

    if (!isset($this_article['references'])) {
        $this_article['references'] = "";
    }
    // Overview
    $overview_stmt->execute([
        $group,
        $this_article['local'],
        $this_article['mid'],
        $this_article['epochdate'],
        $this_article['stringdate'],
        $this_article['from'],
        $this_article['subject'],
        $this_article['references'],
        $this_article['bytes'],
        $this_article['lines'],
        $this_article['xref']
    ]);
    $overview_dbh = null;
    $references = "";
    // Articles
    if ($CONFIG['article_database'] == '1') {
        $article_stmt->execute([
            $group,
            $this_article['local'],
            $this_article['mid'],
            $this_article['epochdate'],
            $this_article['from'],
            $this_article['subject'],
            $this_article['article'],
            $this_article['snippet']
        ]);
        if (file_exists($grouppath . "/" . $this_article['local'])) {
            unlink($grouppath . "/" . $this_article['local']);
        }
        $article_dbh = null;
    } else {
        $article_date = $this_article['epochdate'];
        if ($article_date > time()) { // REVIEW ?+86400
            $article_date = time();
        }
        touch($grouppath . "/" . $this_article['local'], $article_date);
    }
    echo "\nSpooling: " . $group . " " . $this_article['local'];
    file_put_contents($logfile, "\n" . logging_prefix() . " " . $config_name . " Spooling: " . $group . ":" . $this_article['local'] . " " . $this_article['mid'], FILE_APPEND);
    $status = "spooled";
    $statusdate = time();
    $statusreason = "imported";
    $statusnotes = '';
    add_to_history($group, $this_article['local'], $this_article['mid'], $status, $statusdate, $statusreason, $statusnotes);
    return "240 Article Inserted " . $this_article['mid'] . "\r\n";
}

function is_deleted_post($group, $number)
{
    global $spooldir;
    $database = $spooldir . '/history.db3';
    $table = 'history';
    $dbh = history_db_open($database, $table);
    $stmt = $dbh->prepare("SELECT * FROM $table WHERE newsgroup=:newsgroup AND number=:newsnum");
    $stmt->bindParam(':newsgroup', $group);
    $stmt->bindParam(':newsnum', $number);
    $stmt->execute();
    $status = false;
    while ($row = $stmt->fetch()) {
        if ($row['status'] == "deleted") {
            $status = "430 Article Deleted";
            break;
        }
    }
    $dbh = null;
    return $status;
}

function add_to_history($group, $number, $msgid, $status, $statusdate, $statusreason = null, $statusnotes = null)
{
    global $spooldir;
    $history = $spooldir . '/history.db3';
    $history_dbh = history_db_open($history);
    $history_sql = 'INSERT OR REPLACE INTO history(newsgroup, number, msgid, status, statusdate, statusreason, statusnotes) VALUES(?,?,?,?,?,?,?)';
    $history_stmt = $history_dbh->prepare($history_sql);
    $history_stmt->execute([
        $group,
        $number,
        $msgid,
        $status,
        $statusdate,
        $statusreason,
        $statusnotes
    ]);
    $history_dbh = null;
}

function clear_history_by_group($group)
{
    global $spooldir;
    $history = $spooldir . '/history.db3';
    $history_dbh = history_db_open($history);
    $clear_stmt = $history_dbh->prepare("DELETE FROM history WHERE newsgroup=:group");
    $clear_stmt->bindParam(':group', $group);
    $clear_stmt->execute();
    $history_dbh = null;
}

/* get_data_from_msgid uses overview database */
/* get_db_data_from_msgid uses overview database */
function get_db_data_from_msgid($msgid, $group)
{
    global $spooldir, $config_dir, $logdir;
    if (file_exists($config_dir . '/cache.inc.php')) {
        include $config_dir . '/cache.inc.php';
    }

    if ($enable_cache) {
        $row_cache = $cache_key_prefix . '_' . 'get_db_data_from_msgid-' . $msgid;
        $cached_data = cache_get($row_cache, $memcacheD);
        if ($cached_data) {
            try {
                $row = secure_unserialize(gzuncompress($cached_data));
                if ($row !== false) {
                    if ($enable_cache_logging) {
                        file_put_contents($cache_log, "\n" . logging_prefix() . " (cache hit) $row_cache", FILE_APPEND);
                    }
                    return $row;
                }
            } catch (Exception $e) {
                // Cache corruption, continue with database lookup
            }
        }
    }

    $database = $spooldir . '/' . $group . '-articles.db3';
    if (! is_file($database)) {
        return false;
    }
    $articles_dbh = article_db_open($database);
    $articles_query = $articles_dbh->prepare('SELECT * FROM articles WHERE msgid=:messageid');
    $articles_query->execute([
        'messageid' => $msgid
    ]);
    $found = 0;
    while ($row = $articles_query->fetch()) {
        $found = 1;
        break;
    }
    $dbh = null;
    if ($found) {
        if ($enable_cache) {
            $nicole = cache_add($row_cache, gzcompress(serialize($row)), $cache_ttl, $memcacheD);
            if ($enable_cache_logging && $nicole) {
                file_put_contents($cache_log, "\n" . logging_prefix() . " (cache write) $row_cache", FILE_APPEND);
            }
        }
        return $row;
    } else {
        return false;
    }
}

function get_group_array_from_msgid($msgid)
{
    global $spooldir;
    $database = $spooldir . '/articles-overview.db3';
    $overview_dbh = overview_db_open($database);
    $overview_query = $overview_dbh->prepare('SELECT * FROM overview WHERE msgid=:messageid');
    $overview_query->execute([
        'messageid' => $msgid
    ]);
    $newsgroups = array();
    $found = false;
    while ($row = $overview_query->fetch()) {
        $newsgroups[] = $row['newsgroup'];
        $found = true;
    }
    $dbh = null;
    if ($found) {
        return $newsgroups;
    } else {
        return false;
    }
}

/* get_data_from_msgid uses overview database */
/* get_db_data_from_msgid uses overview database */
function get_data_from_msgid($msgid, $thisgroup = null)
{
    global $spooldir, $config_dir, $logdir, $CONFIG;
    if (file_exists($config_dir . '/cache.inc.php')) {
        include $config_dir . '/cache.inc.php';
    }

    if ($CONFIG['article_database'] == '1' && isset($thisgroup)) {
        return get_db_data_from_msgid($msgid, $thisgroup);
    }

    if ($enable_cache) {
        $row_cache = $cache_key_prefix . '_' . 'get_data_from_msgid-' . $msgid;
        $cached_data = cache_get($row_cache, $memcacheD);
        if ($cached_data) {
            try {
                $row = secure_unserialize(gzuncompress($cached_data));
                if ($row !== false && isset($row['msgid'])) {
                    if ($enable_cache_logging) {
                        file_put_contents($cache_log, "\n" . logging_prefix() . " (cache hit) $row_cache", FILE_APPEND);
                    }
                    return $row;
                }
            } catch (Exception $e) {
                // Cache corruption, continue with database lookup
            }
        } else {
            file_put_contents($cache_log, "\n" . logging_prefix() . " (cache update) $row_cache", FILE_APPEND);
            cache_delete($row_cache, $memcacheD);
        }
    }

    $database = $spooldir . '/articles-overview.db3';
    $articles_dbh = overview_db_open($database);
    if ($thisgroup != null) {
        $articles_query = $articles_dbh->prepare('SELECT * FROM overview WHERE msgid=:messageid AND newsgroup=:newsgroup');
        $articles_query->execute([
            'messageid' => $msgid,
            'newsgroup' => $thisgroup
        ]);
    } else {
        $articles_query = $articles_dbh->prepare('SELECT * FROM overview WHERE msgid=:messageid');
        $articles_query->execute([
            'messageid' => $msgid
        ]);
    }
    $found = 0;
    while ($row = $articles_query->fetch()) {
        $found = 1;
        break;
    }
    $dbh = null;
    if ($found) {
        if ($enable_cache) {
            $nicole = cache_add($row_cache, gzcompress(serialize($row)), $cache_ttl, $memcacheD);
            if ($enable_cache_logging && $nicole) {
                file_put_contents($cache_log, "\n" . logging_prefix() . " (cache write) $row_cache", FILE_APPEND);
            }
        }
        return $row;
    } else {
        return false;
    }
}

function prune_dir_by_days($path, $days)
{
    if ($filenames = array_diff(scandir($path), array(
        '..',
        '.'
    ))) {
        foreach ($filenames as $file) {
            $filelastmodified = filemtime($path . $file);
            if ((time() - $filelastmodified) > $days * 86400) {
                if (is_file($path . $file)) {
                    unlink($path . $file);
                }
            }
        }
    } else {
        return false;
    }
    return true;
}

function check_registered_email_addresses($email)
{
    global $config_dir;
    $users = scandir($config_dir . "/userconfig");
    foreach ($users as $user) {
        if (strcmp(get_user_config($user, 'email'), $email) == 0) {
            return $user;
        }
    }
    return false;
}

function send_admin_message($admin, $from, $subject, $message)
{
    global $config_dir, $spooldir;
    if (($to = get_config_value('aliases.conf', strtolower($admin))) == false) {
        $to = strtolower($admin);
    }
    $to = trim($to);
    $from = $to;
    $database = $spooldir . '/mail.db3';
    $dbh = mail_db_open($database);
    if (! $dbh) {
        echo "Database error\n";
        return false;
    }
    $date = time();
    $msgid = '<' . md5(strtolower($to) . strtolower($from) . strtolower($subject) . strtolower($message)) . '>';
    $sql = 'INSERT OR IGNORE INTO messages(msgid, mail_from, rcpt_to, rcpt_target, date, subject, message, from_hide, to_hide, mail_viewed, rcpt_viewed) VALUES(?,?,?,?,?,?,?,?,?,?,?)';
    $stmt = $dbh->prepare($sql);
    $target = "local";
    $mail_viewed = "true";
    $rcpt_viewed = null;
    $q = $stmt->execute([
        $msgid,
        $from,
        $to,
        $target,
        $date,
        $subject,
        $message,
        null,
        null,
        false,
        false
    ]);

    $dbh = null;
    return true;
}

function check_remote_for_msgid($msgid)
{
    global $logfile, $debug_log, $CONFIG;
    if ($ns = nntp2_open()) {
        file_put_contents($debug_log, "\n" . format_log_date() . ' Searching ' . $CONFIG['remote_server'] . ':' . $CONFIG['remote_port'] . ' for ' . $msgid, FILE_APPEND);
        fputs($ns, "head " . $msgid . "\r\n");
        $response = line_read($ns);
        if (strcmp(substr($response, 0, 3), "223") != 0) {
            file_put_contents($debug_log, "\n" . format_log_date() . " NOT Found " . $msgid . ' on ' . $CONFIG['remote_server'] . ':' . $CONFIG['remote_port'] . '', FILE_APPEND);
            $return = false;
        } else {
            file_put_contents($debug_log, "\n" . format_log_date() . " Found " . $msgid . ' on ' . $CONFIG['remote_server'] . ':' . $CONFIG['remote_port'] . '', FILE_APPEND);
            $return = true;
        }
        fputs($ns, "quit\r\n");
        nntp_close($ns);
    } else {
        file_put_contents($debug_log, "\n" . format_log_date() . ' Cannot connect to ' . $CONFIG['remote_server'] . ':' . $CONFIG['remote_port'] . ' server', FILE_APPEND);
        $return = false;
    }
    return ($return);
}

function delete_message($messageid, $group = null, $overview_dbh = null)
{
    global $logfile, $logdir, $config_dir, $spooldir, $CONFIG, $webserver_group;
    if (file_exists($config_dir . '/cache.inc.php')) {
        include $config_dir . '/cache.inc.php';
    }
    if ($group == null) {
        $grouplist = get_newsgroups_by_msgid($messageid);
    } else {
        $grouplist[0] = $group;
    }

    /* Find section */
    foreach ($grouplist as $group) {
        $config_name = get_section_by_group($group, true);
        if (! $config_name) {
            file_put_contents($logfile, "\n" . logging_prefix() . " Group not found: " . $group, FILE_APPEND);
            continue;
        }
        if ($CONFIG['article_database'] == '1') {
            $database = $spooldir . '/' . $group . '-articles.db3';
            $articles_dbh = article_db_open($database);
            if ($articles_dbh) {
                $articles_stmt = $articles_dbh->prepare('DELETE FROM articles WHERE msgid=:messageid');
                $articles_stmt->execute([
                    'messageid' => $messageid
                ]);
                $articles_dbh = null;
            } else {
                file_put_contents($logfile, "\n" . logging_prefix() . " " . $config_name . " Failed to access: " . $database, FILE_APPEND);
                continue;
            }
        }
        // Handle overview and history
        if ($overview_dbh == null) {
            $database = $spooldir . '/articles-overview.db3';
            $overview_dbh = overview_db_open($database);
            if (! $overview_dbh) {
                file_put_contents($logfile, "\n" . logging_prefix() . " " . $config_name . " FAILED opening " . $database, FILE_APPEND);
                return false;
            }
            $close_ovdb = true;
        }
        $overview_stmt_del = $overview_dbh->prepare('DELETE FROM overview WHERE newsgroup=:newsgroup AND msgid=:msgid');
        $overview_query = $overview_dbh->prepare('SELECT * FROM overview WHERE newsgroup=:newsgroup AND msgid=:msgid');
        $overview_query->execute([
            ':newsgroup' => $group,
            ':msgid' => $messageid
        ]);
        $grouppath = preg_replace('/\./', '/', $group);
        $status = "deleted";
        $statusdate = time();
        $statusreason = "nocem";
        $statusnotes = null;
        $found = false;
        while ($row = $overview_query->fetch()) {
            if (isset($row['number'])) {
                file_put_contents($logfile, "\n" . logging_prefix() . " " . $config_name . " DELETING: " . $messageid . " IN: " . $group, FILE_APPEND);
            }
            $found = true;
            if (is_file($spooldir . '/articles/' . $grouppath . '/' . $row['number'])) {
                unlink($spooldir . '/articles/' . $grouppath . '/' . $row['number']);
            }
            delete_message_from_overboard($config_name, $group, $messageid);
            add_to_history($group, $row['number'], $row['msgid'], $status, $statusdate, $statusreason, $statusnotes);
            thread_cache_removearticle($group, $row['number']);
            $overview_stmt_del->execute([
                ':newsgroup' => $group,
                ':msgid' => $messageid
            ]);
            // Delete article from memcache
            if ($enable_cache) {
                $article_key = $cache_key_prefix . '_' . 'article.db3-' . $group . ':' . $row['number'];
                $result = cache_delete($article_key, $memcacheD);
                if ($enable_cache_logging) {
                    if ($result) {
                        file_put_contents($cache_log, "\n" . logging_prefix() . " Deleted $article_key", FILE_APPEND);
                    } else {
                        file_put_contents($cache_log, "\n" . logging_prefix() . " Failed to delete (or not found) $article_key", FILE_APPEND);
                    }
                }
            }
        }
        if ($found == true) {
            file_put_contents($logfile, "\n" . logging_prefix() . " " . $config_name . " DELETED: " . $messageid . " IN: " . $group, FILE_APPEND);
        } else {
            file_put_contents($logfile, "\n" . logging_prefix() . " " . $config_name . " " . $messageid . " not found in: " . $group, FILE_APPEND);
        }
    }
    if ($close_ovdb) {
        $overview_dbh = null;
    }
    return true;
}

// This function returns FALSE if article is OK
// Else returns a string with reason for failure
function check_article_integrity($rawmessage, $artdate = false)
{
    global $CONFIG, $logfile, $config_name;
    $returnval = false;
    $count_rawmessage = count($rawmessage);
    $message = new messageType();
    $rawheader = array();
    $i = 0;
    while ($rawmessage[$i] != "") {
        $rawheader[] = $rawmessage[$i];
        $i++;
        if ($i > count($rawmessage) - 1) {
            break;
        }
    }
    // Parse the Header:
    $message->header = parse_header($rawheader);

    if (!$artdate) {
        $artdate = $message->header->date;
    }
    // Check if date is in future (allow up to 60 seconds in future)
    if ($artdate > (time() + 60)) {
        $returnval = "437 401 Skipping message (date in future): " . $message->header->id . " (" . date('M d H:i:s', $artdate) . ")";
        return $returnval;
    }
    // Date is probably 1 Jan 1970
    if ($artdate < 100) {
        $returnval = "437 402 Skipping message (date too old): " . $message->header->id . " (" . date('M d H:i:s', $artdate) . ")";
        return $returnval;
    }
    // Now we know if the message is a mime-multipart message:
    $content_type = explode("/", $message->header->content_type[0]);
    if ($content_type[0] == "multipart") {
        $message->header->content_type = array();
        // We have multible bodies, so we split the message into its parts
        $boundary = "--" . $message->header->content_type_boundary;
        // lets find the first part
        while ($rawmessage[$i] != $boundary) {
            $i++;
            if ($i > $count_rawmessage) {
                $returnval = " Skipping malformed message: " . $message->header->id;
                return $returnval;
            }
        }
    }
    return $returnval;
}

/* Remove or replace characters in a string */
function sanitize_header($text)
{
    return preg_replace("/\`/", "'", $text);
}

function wrap_post($body)
{
    global $wrap_width;
    $lines = preg_split("/\n/", $body);
    $wrapped = '';
    foreach ($lines as $line) {
        $line = rtrim($line);
        if (trim($line) == '') {
            $wrapped .= "\n";
            continue;
        }
        if ($line[0] == '>') {
            $depth = 0;
            while (isset($line[$depth]) && $line[$depth] == '>') {
                $depth++;
                if ($depth > 30) {
                    break;
                }
            }
            if (strlen($line) > $wrap_width) {
                // HERE is where we wrap quoted lines (not so easy)
                $start = substr($line, 0, $depth + 1);
                $end = substr($line, $depth + 1);
                $line_wrapped = $start . mb_wordwrap($end, $wrap_width);
                $line_wrapped = preg_split("/\n/", $line_wrapped);
                foreach ($line_wrapped as $lw) {
                    if ($lw[0] != '>') {
                        $i = 0;

                        while ($i < $depth) {
                            $wrapped .= '>';
                            $i++;
                        }
                        $wrapped .= ' ';
                    }
                    $wrapped .= $lw . "\n";
                }
            } else {
                $wrapped .= $line . "\n";
            }
        } else {
            if (strlen($line) > $wrap_width) {
                // HERE is where we wrap NON quoted lines (easy)
                $wrapped .= mb_wordwrap($line, $wrap_width) . "\n";
            } else {
                $wrapped .= $line . "\n";
            }
        }
    }
    return $wrapped;
}

function delete_message_from_overboard($config_name, $group, $messageid)
{
    global $spooldir;
    $cachefile = $spooldir . "/" . $config_name . "-overboard.dat";
    if (is_file($cachefile)) {
        try {
            $cached_overboard = secure_unserialize(file_get_contents($cachefile));
            if (!is_array($cached_overboard)) {
                $cached_overboard = array();
            }
        } catch (Exception $e) {
            $cached_overboard = array();
        }
        if (isset($cached_overboard['msgids'][$messageid])) {
            if ($target == $cached_overboard['msgids'][$messageid]) { // REVIEW
                unset($cached_overboard['threads'][$target['date']]);
                unset($cached_overboard['msgids'][$messageid]);
                unset($cached_overboard['threadlink'][$messageid]);
                file_put_contents($cachefile, serialize($cached_overboard));
            }
        }
    }
    $cachefile = $spooldir . "/" . $group . "-overboard.dat";
    if (is_file($cachefile)) {
        try {
            $cached_overboard = secure_unserialize(file_get_contents($cachefile));
            if (!is_array($cached_overboard)) {
                $cached_overboard = array();
            }
        } catch (Exception $e) {
            $cached_overboard = array();
        }
        if (isset($cached_overboard['msgids'][$messageid])) {
            if ($target == $cached_overboard['msgids'][$messageid]) {  // REVIEW
                unset($cached_overboard['threads'][$target['date']]);
                unset($cached_overboard['msgids'][$messageid]);
                unset($cached_overboard['threadlink'][$messageid]);
                file_put_contents($cachefile, serialize($cached_overboard));
            }
        }
    }
}

function cache_add($cache_key, $data, $cache_ttl, $memcacheD = null)
{
    global $enable_cache, $cache_dir, $cache_log, $low_spool_disk_space;
    global $config_name, $min_spool_disk_space;
    $cache_key = base64_encode($cache_key);
    if ($enable_cache == 'memcached') {
        if ($memcacheD) {
            if ($nicole = $memcacheD->add($cache_key, $data, $cache_ttl)) {
                return $nicole;
            }
        }
    }
    if ($enable_cache == 'diskcache') {
        if ($low_spool_disk_space) {
            file_put_contents($cache_log, "\n" . logging_prefix() . " " . $config_name . " Low Disk Space (less than " . $min_spool_disk_space . "Gb available for cache). Pausing diskcache", FILE_APPEND);
            return false;
        }
        if ($nicole = file_put_contents($cache_dir . '/' . $cache_key, $data)) {
            return $nicole;
        }
    }
    return false;
}

function cache_delete($cache_key, $memcacheD = null)
{
    global $enable_cache, $cache_dir;
    $cache_key = base64_encode($cache_key);
    if ($enable_cache == 'memcached') {
        if ($memcacheD) {
            if ($nicole = $memcacheD->delete($cache_key)) {
                return $nicole;
            }
        }
    }
    if ($enable_cache == 'diskcache') {
        if (file_exists($cache_dir . '/' . $cache_key)) {
            return unlink($cache_dir . '/' . $cache_key);
        }
    }
    return false;
}

function cache_get($cache_key, $memcacheD = null)
{
    global $enable_cache, $cache_dir;
    $cache_key = base64_encode($cache_key);
    if ($enable_cache == 'memcached') {
        if ($memcacheD) {
            if ($nicole = $memcacheD->get($cache_key)) {
                return $nicole;
            }
        }
    }
    if ($enable_cache == 'diskcache') {
        if (file_exists($cache_dir . '/' . $cache_key)) {
            return file_get_contents($cache_dir . '/' . $cache_key);
        }
    }
    return false;
}

function change_identity($uid, $gid)
{
    global $CONFIG;
    if (! posix_setgid($gid)) {
        //print "Unable to setgid to " . $gid . "!\n";
        print "Cannot change to user '" . $CONFIG['webserver_user'] . "'\n";
        exit();
    }

    if (! posix_setuid($uid)) {
        //print "Unable to setuid to " . $uid . "!\n";
        print "Cannot change to user '" . $CONFIG['webserver_user'] . "'\n";
        exit();
    }
}

?>