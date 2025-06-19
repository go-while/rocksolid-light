<?php
/*
 * rslight NNTP<->HTTP Gateway
 * Download: https://news.novabbs.com/getrslight
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

function message_parse($rawmessage)
{
    global $attachment_delete_alternative, $attachment_uudecode, $www_charset;
    global $iconv_enable;
    // Read the header of the message:
    $count_rawmessage = count($rawmessage);
    $message = new messageType();
    $rawheader = array();
    $i = 0;
    while ($rawmessage[$i] != "") {
        $rawheader[] = $rawmessage[$i];
        $i++;
    }
    // Parse the Header:
    $message->header = parse_header($rawheader);
    // Now we know if the message is a mime-multipart message:
    $content_type = explode("/", $message->header->content_type[0]);
    if ($content_type[0] == "multipart") {
        $message->header->content_type = array();
        // We have multible bodies, so we split the message into its parts
        $boundary = "--" . $message->header->content_type_boundary;
        // lets find the first part
        while ($rawmessage[$i] != $boundary) {
            $i++;
            // Missing boundary line?
            if (!$rawmessage[$i]) {
                break;
            }
        }
        $i++;
        $part = array();
        while ($i <= $count_rawmessage) {
            if (($rawmessage[$i] == $boundary) || ($i == $count_rawmessage - 1) || ($rawmessage[$i] == $boundary . '--')) {
                $partmessage = message_parse($part);
                // merge the content-types of the message with those of the part
                for ($o = 0; $o < count($partmessage->header->content_type); $o++) {
                    $message->header->content_type[] = $partmessage->header->content_type[$o];
                    $message->header->content_type_charset[] = $partmessage->header->content_type_charset[$o];
                    $message->header->content_type_name[] = $partmessage->header->content_type_name[$o];
                    $message->header->content_type_format[] = $partmessage->header->content_type_format[$o];
                    $message->body[] = $partmessage->body[$o];
                }
                $part = array();
            } else {
                if ($i < $count_rawmessage)
                    $part[] = $rawmessage[$i];
            }
            if ($rawmessage[$i] == $boundary . '--')
                break;
            $i++;
        }
        // Is this a multipart/alternative multipart-message? Do we have to
        // delete all non plain/text parts?
        if (($attachment_delete_alternative) && ($content_type[1] == "alternative")) {
            $plaintext = false;
            for ($o = 0; $o < count($message->header->content_type); $o++) {
                if ($message->header->content_type[$o] == "text/plain")
                    $plaintext = true; // we found at least one text/plain
            }
            if ($plaintext) { // now we can delete the other parts
                for ($o = 0; $o < count($message->header->content_type); $o++) {
                    if ($message->header->content_type[$o] != "text/plain") {
                        unset($message->header->content_type[$o]);
                        unset($message->header->content_type_name[$o]);
                        unset($message->header->content_type_charset[$o]);
                        unset($message->header->content_type_format[$o]);
                        unset($message->body[$o]);
                    }
                }
            }
        }
    } else {
        // No mime-attachments in the message:
        $body = "";
        $uueatt = 0; // as default we have no uuencoded attachments
        // Handle inline attachments
        for ($i++; $i < $count_rawmessage; $i++) {
            // do we have an inlay uuencoded file?
            if ((strtolower(substr($rawmessage[$i], 0, 10)) != "begin 644 ") || ($attachment_uudecode == false)) {
                $body .= $rawmessage[$i] . "\n";
                // yes, it seems, we have!
            } else {
                $real = explode("begin 644 ", $rawmessage[$i]);
                if (trim($real[1]) != "") {
                    $old_i = $i;
                    $uue_infoline_raw = $rawmessage[$i];
                    $uue_infoline = explode(" ", $uue_infoline_raw);
                    $uue_data = "";
                    $i++;
                    $no_end = 0;
                    while ($rawmessage[$i] != "end") {
                        if (strlen(trim($rawmessage[$i])) > 2)
                            $uue_data .= $rawmessage[$i] . "\n";
                        $i++;
                        if ($i > $count_rawmessage) {
                            $no_end = 1;
                            break;
                        }
                    }
                    // now write the data in an attachment
                    if ($no_end != 1) {
                        $uueatt++;
                        $message->body[$uueatt] = uudecode($uue_data);
                        $message->header->content_type_name[$uueatt] = "";
                        for ($o = 2; $o < count($uue_infoline); $o++)
                            $message->header->content_type_name[$uueatt] .= $uue_infoline[$o];
                        $message->header->content_type[$uueatt] = get_mimetype_by_string($message->body[$uueatt]);
                    }
                } else {
                    $body .= $rawmessage[$i] . "\n";
                }
            }
        }
        // Do not enable if supporting inline atachments
        // if ($message->header->content_type[0]=="text/plain") {

        $body = decode_body($body, $message->header->content_transfer_encoding);
        if (isset($message->header->content_type_charset)) {
            $body = recode_charset($body, $message->header->content_type_charset[0], $www_charset);
        }
        if ($body == "") {
            $body = " ";
        }
        $message->body[0] = $body;
    }
    if (! isset($message->header->content_type_charset)) {
        $message->header->content_type_charset = array(
            $www_charset
        );
    }
    if (! isset($message->header->content_type_name)) {
        $message->header->content_type_name = array(
            "unnamed"
        );
    }
    if (! isset($message->header->content_type_format)) {
        $message->header->content_type_format = array(
            "fixed"
        );
    }
    for ($o = 0; $o < count($message->body); $o++) {
        if (! isset($message->header->content_type_charset[$o])) {
            $message->header->content_type_charset[$o] = $www_charset;
        }
        if (! isset($message->header->content_type_name[$o])) {
            $message->header->content_type_name[$o] = "unnamed";
        }
        if (! isset($message->header->content_type_format[$o])) {
            $message->header->content_type_format[$o] = "fixed";
        }
    }
    return $message;
}

/*
 * read an article from the newsserver or the spool-directory
 *
 * $id: the Message-ID of an article
 * $bodynum: the number of the attachment:
 * -1: return only the header without any bodies or attachments.
 * 0: the body
 * 1: the first attachment...
 *
 * The function returns an article as an messageType or false if the article
 * doesn't exists on the newsserver or doesn't contain the given
 * attachment.
 */
function message_read($id, $bodynum = 0, $group = "")
{
    echo "DEBUG message_read($id, $bodynum, $group)<br>\n";
    global $CONFIG, $config_dir, $config_name, $cache_articles, $spooldir, $spoolpath, $logdir, $debug_log, $text_error, $ns;
    if(empty($config_name)) {
        $config_name = 'rocksolid'; // fallback for cases where getcwd() fails
    }
    $logfile = $logdir . '/newsportal.log';
    if (! testGroup($group)) {
        echo $text_error["read_access_denied"];
        return;
    }
    if (! is_numeric($id)) {
        echo "" . $text_error["invalid_id"] . " " . htmlspecialchars($id) . "<br>";
        return false;
    }
    // MEMCACHE if ($id, 0, $group)
    if ($bodynum == 0 && $group != "" && $enable_cache !== false) {
        $cache_key = $cache_key_prefix . '_' . 'message_read-' . $id . '-0-' . $group;
        $message_data = cache_get($cache_key, $memcacheD);
        if ($message_data) {
            $decompressed = gzuncompress($message_data);
            if ($decompressed !== false && preg_match('/^[adbois]:[0-9]+/', $decompressed)) {
                try {
                    $message = secure_unserialize($decompressed);
                } catch (Exception $e) {
                    error_log("Cache unserialize failed: " . $e->getMessage());
                    $message = false;
                }
                if ($message) {
                    if ($enable_cache_logging) {
                        file_put_contents($cache_log, "\n" . logging_prefix() . " (cache hit) $cache_key", FILE_APPEND);
                    }
                    return $message;
                }
            }
        }
    }
    $message = new messageType();
    if ((isset($cache_articles)) && ($cache_articles == true)) {
        // Try to load a cached article
        if ((preg_match('/^[0-9]+$/', $id)) && ($group != '')) {
            // If the group is set, we use the group name as part of the filename
            $filename = $group . '_' . $id;
        } else {
            $filename = base64_encode($id);
        }
        $cachefilename_header = $spooldir . "/" . $filename . '.header';
        $cachefilename_body = $spooldir . "/" . $filename . '.body';
        if (file_exists($cachefilename_header)) {
            $cachefile = fopen($cachefilename_header, "r");
            $header_data = fread($cachefile, filesize($cachefilename_header));
            fclose($cachefile);
            if (preg_match('/^[adbois]:[0-9]+/', $header_data)) {
                try {
                    $message->header = secure_unserialize($header_data);
                } catch (Exception $e) {
                    error_log("Header cache unserialize failed: " . $e->getMessage());
                    unset($message->header);
                }
            } else {
                unset($message->header);
            }
        } else {
            unset($message->header);
        }
        // Is a non-existing attachment of an article requested?
        if ((isset($message->header)) && ($bodynum != -1) && (! isset($message->header->content_type[$bodynum]))) {
            return false;
        }
        if ((file_exists($cachefilename_body . $bodynum)) && ($bodynum != -1)) {
            $cachefile = fopen($cachefilename_body . $bodynum, "r");
            $message->body[$bodynum] = fread($cachefile, filesize($cachefilename_body . $bodynum));
            fclose($cachefile);
        }
    }
    if ((! isset($message->header)) || ((! isset($message->body[$bodynum])) && ($bodynum != -1))) {
        // Pull article from spool if exists, else from server
        if (trim($group) == '') {
            return false;
        }
        unset($rawmessage);
        if ($CONFIG['article_database'] == '1') {
            $rawmessage = np_get_db_article($id, $group, 1);
        } else {
            $articlepath = $spoolpath . preg_replace('/\./', '/', $group) . "/" . $id;
            if (file_exists($articlepath)) {
                $rawmessage_fh = fopen($articlepath, "r");
                $rawmessage = array();
                $line = rtrim(fgets($rawmessage_fh), PHP_EOL);
                while (! feof($rawmessage_fh)) {
                    if (strcmp($line, ".") == 0) {
                        break;
                    }
                    $rawmessage[] = $line;
                    $line = rtrim(fgets($rawmessage_fh), PHP_EOL);
                }
                fclose($rawmessage_fh);
            }
        }
        if (! isset($rawmessage) || $rawmessage === FALSE) {
            if (! isset($ns)) {
                $ns = nntp_open();
            }
            if ($group != "") {
                fputs($ns, "GROUP " . $group . "\r\n");
                $line = line_read($ns);

                // Handle timeout for GROUP command
                if ($line === false) {
                    file_put_contents($debug_log, "\n" . format_log_date() . " " . $config_name . " TIMEOUT: No response to GROUP command in message retrieval", FILE_APPEND);
                    return false;
                }
            }
            fputs($ns, 'ARTICLE ' . $id . "\r\n");
            $line = line_read($ns);

            // Handle timeout for ARTICLE command
            if ($line === false) {
                file_put_contents($debug_log, "\n" . format_log_date() . " " . $config_name . " TIMEOUT: No response to ARTICLE command", FILE_APPEND);
                return false;
            }

            if (substr($line, 0, 3) != "220") {
                // requested article doesn't exist on the newsserver. Now we
                // should check if the thread stored in the spool-directory
                // also doesnt't contain that article...
                thread_cache_removearticle($group, $id);
                // This is most likely a bot calling an old article, not a bug
                //    file_put_contents($debug_log, "\n" . logging_prefix() . " " . $config_name . " Unable to retrieve: " . $group . ":" . $id . " from local server. Removing...", FILE_APPEND);
                return false;
            }
            $rawmessage = array();
            $line = line_read($ns);

            // Handle timeout for initial article data read
            if ($line === false) {
                file_put_contents($debug_log, "\n" . format_log_date() . " " . $config_name . " TIMEOUT: No article data received", FILE_APPEND);
                return false;
            }

            while (strcmp($line, ".") != 0) {
                $rawmessage[] = $line;
                $line = line_read($ns);

                // Handle timeout in article reading loop
                if ($line === false) {
                    file_put_contents($debug_log, "\n" . format_log_date() . " " . $config_name . " TIMEOUT: Reading article data interrupted", FILE_APPEND);
                    break; // Exit loop on timeout, use partial data
                }
            }
        }
        $message = message_parse($rawmessage);
        if (preg_match('/^[0-9]+$/', $id)) {
            // If the group is set, we use the group name as part of the filename
            $message->header->number = $id;
        }
        // write header, body and attachments to the cache
        if ((isset($cache_articles)) && ($cache_articles == true)) {
            $cachefile = fopen($cachefilename_header, "w");
            if ($cachefile) {
                fputs($cachefile, serialize($message->header));
            }
            fclose($cachefile);
            for ($i = 0; $i < count($message->header->content_type); $i++) {
                if (isset($message->body[$i])) {
                    $cachefile = fopen($cachefilename_body . $i, "w");
                    fwrite($cachefile, $message->body[$i]);
                    fclose($cachefile);
                }
            }
        }
    }
    // MEMCACHE if ($id, 0, $group)
    if ($enable_cache) {
        $nicole = cache_add($cache_key, gzcompress(serialize($message)), $cache_ttl, $memcacheD);
        if ($enable_cache_logging && $nicole) {
            file_put_contents($cache_log, "\n" . logging_prefix() . " (cache write) " . $cache_key, FILE_APPEND);
        }
    }
    return $message;
}

function textwrap($text, $wrap = 80, $break = "\n", $maxlen = false)
{
    $len = strlen($text);
    if ($len > $wrap) {
        $h = ''; // massaged text
        $lastWhite = 0; // position of last whitespace char
        $lastChar = 0; // position of last char
        $lastBreak = 0; // position of last break
        // while there is text to process
        while ($lastChar < $len && (($maxlen == false) || (strlen($h) < $maxlen))) {
            $char = substr($text, $lastChar, 1); // get the next character
            // if we are beyond the wrap boundry and there is a place to break
            if (($lastChar - $lastBreak > $wrap) && ($lastWhite > $lastBreak)) {
                $h .= substr($text, $lastBreak, ($lastWhite - $lastBreak)) . $break;
                $lastChar = $lastWhite + 1;
                $lastBreak = $lastChar;
            }
            // You may wish to include other characters as valid whitespace...
            if ($char == ' ' || $char == chr(13) || $char == chr(10)) {
                $lastWhite = $lastChar; // note the position of the last whitespace
            }
            $lastChar = $lastChar + 1; // advance the last character position by one
        }
        $h .= substr($text, $lastBreak); // build line
    } else {
        $h = $text; // in this case everything can fit on one line
    }
    return $h;
}

/*
 * Displays a (Sub)-Thread. Is used in article.php
 *
 * $id: Message-ID (not number!) of an article in the thread
 * $group: name of the newsgroup
 */
function message_thread($id, $group, $thread, $highlightids = false)
{
    $current = $id;
    // set the highlightid, if not set
    if (! $highlightids)
        $highlightids = array(
            $current
        );
    flush();
    // find the first article in the subthread of $id
    while (isset($thread[$id]->references)) {
        foreach ($thread[$id]->references as $reference) {
            if ((trim($reference) != '') && (isset($thread[$reference]))) {
                $id = $reference;
                continue 2;
            }
        }
        break;
    }
    $liste = array();
    $liste[] = $id;
    $tmp = 0;
    thread_show_head(0);
    echo thread_show_recursive($thread, $liste, 1, "", $group, 0, 100, $tmp, $highlightids, 0);
    thread_show_tail();
}

/*
 * Print the header of a message to the webpage
 *
 * $head: the header of the message as an headerType
 * $group: the name of the newsgroup, is needed for the links to post.php3
 * and the header.
 */
function show_header($head, $group, $local_poster = false)
{
    global $article_show, $text_header, $file_article, $file_search, $attachment_show;
    global $file_attachment, $anonym_address, $CONFIG, $OVERRIDES;
    global $sitelink, $config_name;

    if (isset($OVERRIDES['short_headers']) && $OVERRIDES['short_headers'] == true) {
        show_header_short($head, $group, $local_poster);
        return;
    }

    // Copy MID to clipboard (requires js)
    echo '<script>';
    echo 'function CopyToClipboard(id)';
    echo '{';
    echo 'var r = document.createRange();';
    echo 'r.selectNode(document.getElementById(id));';
    echo 'window.getSelection().removeAllRanges();';
    echo 'window.getSelection().addRange(r);';
    echo "document.execCommand('copy');";
    echo 'window.getSelection().removeAllRanges();';
    echo '}';
    echo '</script> ';

    echo '<div class="np_article_header">';
    if ($article_show["Subject"]) {
        echo '<div class="plain_header_subject">';
        echo $text_header["subject"] . htmlspecialchars(mb_substr($head->subject, 0, strlen($head->subject))) . "<br>";
        echo '</div>';
    }
    if ($article_show["From"]) {
        echo '<div class="plain_header_from">';
        echo $text_header["from"];
        if ($head->from == $anonym_address) {
            // this is the anonymous address, so only show the name
            echo htmlspecialchars($head->name);
        } else {
            if ($article_show["From_link"])
                echo '<a href="mailto:' . htmlspecialchars($head->from) . '">';
            if (isset($article_show["From_rewrite"]))
                echo preg_replace('/{$article_show["From_rewrite"][0]}/', $article_show["From_rewrite"][1], htmlspecialchars($head->from));
            $before_at = explode('@', $head->from);
            $namelen = strlen($before_at[0]);
            if ($namelen > 3) {
                $endname = $namelen - 3;
                if ($endname > 8)
                    $endname = 8;
                if ($endname < 3)
                    $endname++;
                if ($endname < 3)
                    $endname++;
            } else {
                $endname = $namelen;
            }
            if ($article_show["From_link"])
                echo '</a>';
            echo '<span class="visited">';
            if ($local_poster) {
                echo '<i>';
            }
            if ($head->name != "") {
                echo create_name_link($head->name, $head->from, false);
            } else {
                if (isset($CONFIG['hide_email']) && $CONFIG['hide_email'] == true) {
                    echo htmlspecialchars(truncate_email($head->from));
                } else {
                    echo htmlspecialchars($head->from);
                }
            }
            if ($local_poster) {
                echo '</i>';
            }
            echo '</span>';
        }
        echo "<br>";
        echo '</div>';
    }
    if ($article_show["Date"]) {
        echo '<div class="plain_header_date">';
        // Try to use client timezone else default to UTC
        $displaydate = get_date_for_client_timezone($head->date) . "<br>";
        echo $text_header["date"] . $displaydate;
        echo '</div>';
    }
    if ($article_show["Newsgroups"]) {
        echo '<div class="plain_header_newsgroups">';
        echo $text_header["newsgroups"] . htmlspecialchars(str_replace(',', ', ', $head->newsgroups)) . "<br>\n";
        echo '</div>';
    }
    if (isset($head->followup) && ($article_show["Followup"]) && ($head->followup != "")) {
        echo '<div class="plain_header_followup-to">';
        echo $text_header["followup"] . htmlspecialchars($head->followup) . "<br>\n";
        echo '</div>';
    }
    if ((isset($head->organization)) && ($article_show["Organization"]) && ($head->organization != "")) {
        echo '<div class="plain_header_organization">';
        echo $text_header["organization"] . html_parse(htmlspecialchars($head->organization)) . "<br>\n";
        echo '</div>';
    }
    if ($article_show["Message-ID"]) {
        echo '<div class="plain_header_message-id">';
        echo ' ' . $text_header["message-id"] . htmlspecialchars($head->id) . "<br>\n";
        echo '</div>';
    }
    if (($article_show["References"]) && (isset($head->references[0]))) {
        echo '<div class="plain_header_references">';
        echo $text_header["references"];
        for ($i = 0; $i <= count($head->references) - 1; $i++) {
            $ref = $head->references[$i];
            echo ' ' . '<a href="' . $file_article . '&group=' . urlencode($group) . '&id=' . urlencode($ref) . '">' . ($i + 1) . '</a>';
        }
        echo "<br>";
        echo '</div>';
    }
    if (isset($head->user_agent)) {
        echo '<div class="plain_header_user-agent">';
        if ((isset($article_show["User-Agent"])) && ($article_show["User-Agent"])) {
            echo $text_header["user-agent"] . htmlspecialchars($head->user_agent) . "<br>\n";
        } else {
            echo "<!-- User-Agent: " . htmlspecialchars($head->user_agent) . " -->\n";
        }
        echo '</div>';
    }
    if ((isset($attachment_show)) && ($attachment_show == true) && (isset($head->content_type[1]))) {
        echo '<div class="plain_header_attachments">';
        echo $text_header["attachments"];
        for ($i = 1; $i < count($head->content_type); $i++) {
            if (! strcmp($head->content_type[$i], "text/html")) {
                $contype = "HTML Version";
            } else {
                $contype = $head->content_type_name[$i];
            }
            echo '<a href="' . $file_attachment . '&group=' . urlencode($group) . '&' . 'id=' . urlencode($head->number) . '&' . 'attachment=' . $i . '">' . $contype . '</a> (' . $head->content_type[$i] . ')';
            if ($i < count($head->content_type) - 1) {
                // If this is not the last attachment, add a comma
                echo ', ';
            }
        }
        echo '</div>';
    }

    echo '<textarea id="' . $head->id . 'copy"';
    echo ' style="position: fixed; height: 0; overflow: hidden; border:none;">' . htmlspecialchars($head->id) . '</textarea>';
    echo '<textarea id="' . $head->number . 'copy"';
    echo ' style="position: fixed; height: 0; overflow: hidden; border:none;">' . '?page=article-flat&id=' . $head->number . '&group=' . urlencode($group) . '#' . $head->number . '</textarea>';

    echo '<form><span class="short_header_javascript_links">';
    if ($article_show["trigger_headers"]) {
        echo '<input type="checkbox" class="np_header_button_checkbox" id="trigger_headers_' . $head->id . '" title="Show headers" name="showheaders" value="showheaders">';
        echo '<span class="display_headers_on">' . display_full_headers($head->number, $group, $head->name, $head->from) . '</span>';
        echo '<span class="display_headers_notice_short_header"> show headers</span>';
    }
?>
    &nbsp;
    <a href="<?php echo '?page=article-flat&id=' . urlencode($head->id); ?>"
        onclick="CopyToClipboard('<?php echo $head->id . 'copy'; ?>');return false;"
        style="text-decoration: none" title="Copy message-id to clipboard"><i>copy
            mid</i></a>


    &nbsp;
    <a href="<?php echo '?page=article-flat&id=' . $head->number . '&group=' . urlencode($group) . '#' . $head->number; ?>"
        onclick="CopyToClipboard('<?php echo $head->number . 'copy'; ?>');return false;"
        style="text-decoration: none" title="Copy article link to clipboard"><i>copy
            link</i></a>
<?php
    echo '</span></form>';
    echo '</div>';
}

function show_header_short($head, $group, $local_poster = false)
{
    global $article_show, $text_header, $file_article, $file_thread, $attachment_show;
    global $file_attachment, $CONFIG, $config_name, $sitelink;
    global $OVERRIDES;

    /* Mobile specific settings */
    if (get_client_user_agent_info() == 'mobile') {
        $OVERRIDES['short_header_show_from_in_subject'] = false;
    }

    if (isset($OVERRIDES['short_header_show_from_in_subject']) && $OVERRIDES['short_header_show_from_in_subject'] == true) {
        show_header_short_with_subject($head, $group, $local_poster);
        return;
    }

    if (password_verify($CONFIG['thissitekey'] . $head->id, $head->rslight_site)) {
        $local_poster = true;
    }

    echo '<div class="np_article_header">';

    if ($head->name != "") {
        $displayname = create_name_link($head->name, $head->from, false);
    } else {
        if (isset($CONFIG['hide_email']) && $CONFIG['hide_email'] == true) {
            $displayname = htmlspecialchars(truncate_email($head->from));
        } else {
            $displayname = htmlspecialchars($head->from);
        }
    }

    if ($local_poster) {
        $displayname = '<span class="short_header_from_local_poster">' . $displayname . '</span>';
    }

    // Try to use client timezone else default to UTC
    $displaydate = get_date_for_client_timezone($head->date);

    echo '<div class="short_header_subject">';
    echo htmlspecialchars(mb_substr($head->subject, 0, strlen($head->subject))) . "<br>";
    echo '</div>';
    echo '<div class="short_header_from">';
    echo "<b>From: </b>" . $displayname;
    echo '</div>';
    // Try to use client timezone else default to UTC
    echo '<span class="short_header_date">';
    echo '<b>Date: </b>' . $displaydate;
    echo '</span><br>';

    echo '<span class="short_header_newsgroups">';
    echo '<b>Newsgroups: </b>';
    $ngroups = preg_replace("/\,|\ /", "\t", $head->newsgroups);
    $ngroups = explode("\t", $ngroups);
    // echo "&nbsp;";
    foreach ($ngroups as $onegroup) {
        if (get_section_by_group($onegroup)) {
            echo '<a href="' . $file_thread . '&group=' . urlencode($onegroup) . '" title="Visit ' . $onegroup . '"> ' . $onegroup . " </a>";
        } else {
            echo " " . $onegroup . " ";
        }
    }
    echo '</span>';

    // Copy MID to clipboard (requires js)
    echo '<script>';
    echo 'function CopyToClipboard(id)';
    echo '{';
    echo 'var r = document.createRange();';
    echo 'r.selectNode(document.getElementById(id));';
    echo 'window.getSelection().removeAllRanges();';
    echo 'window.getSelection().addRange(r);';
    echo "document.execCommand('copy');";
    echo 'window.getSelection().removeAllRanges();';
    echo '}';
    echo '</script> ';

    if (isset($head->followup) && ($article_show["Followup"]) && ($head->followup != "")) {
        echo '<div class="short_header_followup-to">';
        echo '<b>' . $text_header["followup"] . '</b>';
        $ngroups = preg_replace("/\,|\ /", "\t", $head->followup);
        $ngroups = explode("\t", $ngroups);
        foreach ($ngroups as $onegroup) {
            if (get_section_by_group($onegroup)) {
                echo '<a href="' . $file_thread . '&group=' . urlencode($onegroup) . '" title="Visit ' . $onegroup . '"> ' . $onegroup . " </a>";
            } else {
                echo " " . $onegroup . " ";
            }
        }
        echo '</div>';
    }

    echo '<textarea id="' . $head->id . 'copy"';
    echo ' style="position: fixed; height: 0; overflow: hidden; border:none;">' . htmlspecialchars($head->id) . '</textarea>';
    echo '<textarea id="' . $head->number . 'copy"';
    echo ' style="position: fixed; height: 0; overflow: hidden; border:none;">' . '?page=article-flat&id=' . $head->number . '&group=' . urlencode($group) . '#' . $head->number . '</textarea>';

    echo '<form><span class="short_header_javascript_links">';
    if ($article_show["trigger_headers"]) {
        echo '<input type="checkbox" class="np_header_button_checkbox" id="trigger_headers_' . $head->id . '" title="Show headers" name="showheaders" value="showheaders">';
        echo '<span class="display_headers_on">' . display_full_headers($head->number, $group, $head->name, $head->from) . '</span>';
        echo '<span class="display_headers_notice_short_header"> show headers</span>';
    }
?>
    &nbsp;
    <a href="<?php echo '?page=article-flat&id=' . urlencode($head->id); ?>"
        onclick="CopyToClipboard('<?php echo $head->id . 'copy'; ?>');return false;"
        style="text-decoration: none" title="Copy message-id to clipboard"><i>copy
            mid</i></a>


    &nbsp;
    <a href="<?php echo '?page=article-flat&id=' . $head->number . '&group=' . urlencode($group) . '#' . $head->number; ?>"
        onclick="CopyToClipboard('<?php echo $head->number . 'copy'; ?>');return false;"
        style="text-decoration: none" title="Copy article link to clipboard"><i>copy
            link</i></a>
    <?php
    echo '</span></form>';

    // Display References in short headers if enabled in overrides.inc.php
    if ((isset($OVERRIDES['short_header_references'])) && ($OVERRIDES['short_header_references'] == true) && (isset($head->references[0]))) {
        echo '<div class="short_header_references">';
        echo $text_header["references"];
        for ($i = 0; $i <= count($head->references) - 1; $i++) {
            $ref = $head->references[$i];
            echo ' ' . '<a href="' . $file_article . '&group=' . urlencode($group) . '&id=' . urlencode($ref) . '">' . ($i + 1) . '</a>';
        }
        echo '</div>';
    }

    if ((isset($attachment_show)) && ($attachment_show == true) && (isset($head->content_type[1]))) {
        echo '<div class="short_header_attachments">';
        echo $text_header["attachments"];
        for ($i = 1; $i < count($head->content_type); $i++) {
            if (! strcmp($head->content_type[$i], "text/html")) {
                $contype = "HTML Version";
            } else {
                $contype = $head->content_type_name[$i];
            }
            echo '<a href="' . $file_attachment . '&group=' . urlencode($group) . '&' . 'id=' . urlencode($head->number) . '&' . 'attachment=' . $i . '">' . $contype . '</a> (' . $head->content_type[$i] . ')';
            if ($i < count($head->content_type) - 1) {
                // If this is not the last attachment, add a comma
                echo ', ';
            }
        }
        echo '</div>';
    }
    echo '</div>';
}

function show_header_short_with_subject($head, $group, $local_poster = false)
{
    global $article_show, $text_header, $file_article, $file_thread, $attachment_show;
    global $file_attachment, $CONFIG, $config_name, $sitelink;
    global $OVERRIDES;

    if (password_verify($CONFIG['thissitekey'] . $head->id, $head->rslight_site)) {
        $local_poster = true;
    }

    echo '<div class="np_article_header">';

    // Display javascript links and time interval
    echo '<textarea id="' . $head->id . 'copy"';
    echo ' style="position: fixed; height: 0; overflow: hidden; border:none;">' . htmlspecialchars($head->id) . '</textarea>';
    echo '<textarea id="' . $head->number . 'copy"';
    echo ' style="position: fixed; height: 0; overflow: hidden; border:none;">' . '?page=article-flat&id=' . $head->number . '&group=' . urlencode($group) . '#' . $head->number . '</textarea>';

    echo '<table class="show_header_short_with_subject_table">';

    if ($head->name != "") {
        $displayname = create_name_link($head->name, $head->from, false);
    } else {
        if (isset($CONFIG['hide_email']) && $CONFIG['hide_email'] == true) {
            $displayname = htmlspecialchars(truncate_email($head->from));
        } else {
            $displayname = htmlspecialchars($head->from);
        }
    }

    if ($local_poster) {
        $displayname = '<span class="short_header_from_local_poster">' . $displayname . '</span>';
    }

    // Try to use client timezone else default to UTC
    $displaydate = get_date_for_client_timezone($head->date);

    // Display Subject and From
    echo '<tr><td class="short_header_with_subject_left">';
    echo '<span class="short_header_subject_with_subject">';
    echo htmlspecialchars(mb_substr($head->subject, 0, strlen($head->subject)));
    echo '</span>';
    echo '</td><td class="short_header_with_subject_right">';
    echo '<span class="short_header_from_with_subject"><b>From: </b>' . $displayname . '</span>';
    echo '</td>';

    // Display Newsgroups and Date
    echo '<tr><td class="short_header_with_subject_left">';
    echo '<span class="short_header_newsgroups_with_subject">';
    echo 'Newsgroups: ';
    $ngroups = preg_replace("/\,|\ /", "\t", $head->newsgroups);
    $ngroups = explode("\t", $ngroups);
    // echo "&nbsp;";
    foreach ($ngroups as $onegroup) {
        if (get_section_by_group($onegroup)) {
            echo '<a href="' . $file_thread . '&group=' . urlencode($onegroup) . '" title="Visit ' . $onegroup . '"> ' . $onegroup . " </a>";
        } else {
            echo " " . $onegroup . " ";
        }
    }
    echo '</span>';
    echo '</td>';
    echo '<td class="short_header_with_subject_right">';
    echo '<span class="short_header_date_title_with_subject">';
    echo 'Date: ';
    //   echo '</span>';
    echo '<span class="short_header_date_with_subject">';
    //   echo '<b>Date: </b>';
    //   echo '</span>';
    echo $displaydate;
    echo '</span></span>';
    echo '</td></tr>';

    // Copy MID to clipboard (requires js)
    echo '<script>';
    echo 'function CopyToClipboard(id)';
    echo '{';
    echo 'var r = document.createRange();';
    echo 'r.selectNode(document.getElementById(id));';
    echo 'window.getSelection().removeAllRanges();';
    echo 'window.getSelection().addRange(r);';
    echo "document.execCommand('copy');";
    echo 'window.getSelection().removeAllRanges();';
    echo '}';
    echo '</script> ';

    // Display Followup-To
    if (isset($head->followup) && ($article_show["Followup"]) && ($head->followup != "")) {
        echo '<tr><td class="short_header_with_subject_left">';
        echo '<div class="short_header_followup-to">';
        echo 'Followup-To: ';
        $ngroups = preg_replace("/\,|\ /", "\t", $head->followup);
        $ngroups = explode("\t", $ngroups);
        foreach ($ngroups as $onegroup) {
            if (get_section_by_group($onegroup)) {
                echo '<a href="' . $file_thread . '&group=' . urlencode($onegroup) . '" title="Visit ' . $onegroup . '"> ' . $onegroup . " </a>";
            } else {
                echo " " . $onegroup . " ";
            }
        }
        echo '</div>';
        echo '</td><td></td></tr>';
    }

    // Display References in short headers if enabled in overrides.inc.php
    if ((isset($OVERRIDES['short_header_references'])) && ($OVERRIDES['short_header_references'] == true) && (isset($head->references[0]))) {
        echo '<tr><td class="short_header_with_subject_left">';
        echo '<div class="short_header_references_with_subject">';
        echo 'References: ';
        for ($i = 0; $i <= count($head->references) - 1; $i++) {
            $ref = $head->references[$i];
            echo ' ' . '<a href="' . $file_article . '&group=' . urlencode($group) . '&id=' . urlencode($ref) . '">' . ($i + 1) . '</a>';
        }
        echo '</div>';
        echo '</td><td></td></tr>';
    }

    if ((isset($attachment_show)) && ($attachment_show == true) && (isset($head->content_type[1]))) {
        echo '<tr><td class="short_header_with_subject_left">';
        echo '<div class="short_header_attachments_with_subject">';
        echo 'Attachments: ';
        for ($i = 1; $i < count($head->content_type); $i++) {
            if (! strcmp($head->content_type[$i], "text/html")) {
                $contype = "HTML Version";
            } else {
                $contype = $head->content_type_name[$i];
            }
            echo '<a href="' . $file_attachment . '&group=' . urlencode($group) . '&' . 'id=' . urlencode($head->number) . '&' . 'attachment=' . $i . '">' . $contype . '</a> (' . $head->content_type[$i] . ')';
            if ($i < count($head->content_type) - 1) {
                // If this is not the last attachment, add a comma
                echo ', ';
            }
        }
        echo '</div>';
        echo '</td></tr>';
    }

    // Display javascript tools
    echo '<tr><td class="short_header_with_subject_left">';
    echo '<span class="short_header_javascript_with_subject">';
    if ($article_show["trigger_headers"]) {
        echo '<input type="checkbox" class="np_header_button_checkbox" id="trigger_headers_' . $head->id . '" title="Show headers" name="showheaders" value="showheaders">';
        echo '<span class="display_headers_on">' . display_full_headers($head->number, $group, $head->name, $head->from) . '</span>';
        echo '<span class="display_headers_notice_short_header"> show headers</span>';
    }
    ?>
    &nbsp;
    <a href="<?php echo '?page=article-flat&id=' . urlencode($head->id); ?>"
        onclick="CopyToClipboard('<?php echo $head->id . 'copy'; ?>');return false;"
        style="text-decoration: none" title="Copy message-id to clipboard"><i>copy
            mid</i></a>

    &nbsp;
    <a href="<?php echo '?page=article-flat&id=' . $head->number . '&group=' . urlencode($group) . '#' . $head->number; ?>"
        onclick="CopyToClipboard('<?php echo $head->number . 'copy'; ?>');return false;"
        style="text-decoration: none" title="Copy article link to clipboard"><i>copy
            link</i></a>
<?php
    echo '</span>';
    echo '</td>';
    echo '<td class="short_header_with_subject_right">';
    echo '<span class="short_header_date_interval_with_subject">';
    echo '<b>posted: </b>' . get_date_interval(date("D, j M Y H:i T", $head->date));
    echo '</span>';
    echo '</td></tr>';

    echo '</table>';
    echo '</div>';
}

function copy_messageid()
{
    $messageid = "THIS IS THE MSGID";
    return $messageid;
}

function display_full_headers($article, $group, $name, $from, $getface = false)
{
    global $spoolpath, $CONFIG, $current_message;
    if (! isset($current_message)) {
        if ($CONFIG['article_database'] == '1') {
            $message = np_get_db_article($article, $group, 1);
        } else {
            $thisgroup = $spoolpath . "/" . preg_replace('/\./', '/', $group);
            if (! file_exists($group . "/" . $article)) {
                // Return something useful
            }
            $message = file($thisgroup . "/" . $article, FILE_IGNORE_NEW_LINES);
        }
    } else {
        $message = $current_message;
    }
    $sendface = null;
    $isface = 0;
    $return = '';
    foreach ($message as $line) {
        if (trim($line) == '') {
            break;
        }
        if ((strpos($line, ': ') === false) && $isface) {
            $sendface .= $line;
            continue;
        }
        if ((stripos($line, 'X-Face: ') === 0) && $getface) {
            $face = explode(': ', $line);
            $sendface = $face[1];
            $isface = 1;
            continue;
        }
        if (stripos($line, ': ') !== false) {
            $isface = 0;
        }
        if (stripos($line, 'Xref: ') === 0) {
            continue;
        }
        if (stripos($line, 'From: ') === 0) {
            $return .= 'From: ';
            if (isset($CONFIG['hide_email']) && $CONFIG['hide_email'] == true) {
                $return .= htmlspecialchars(truncate_email($from));
            } else {
                $return .= htmlspecialchars($from);
            }
            if ($name != "") {
                $return .= ' (' . htmlspecialchars($name) . ')';
            }
            $return .= '<br>';
            continue;
        }
        $return .= mb_decode_mimeheader(htmlspecialchars($line)) . '<br>';
    }
    if ($getface) {
        if (isset($sendface)) {
            $sendface = mb_decode_mimeheader($sendface);
            return ($sendface);
        } else {
            return FALSE;
        }
    }
    return ($return);
}

/*
 * decodes a body. Splits the content of $body into an array of several
 * lines, respecting the special decoding issues of format=flowed
 * articles. Each returned line consists of two fields: text and
 * the quote depth (depth)
 */
function decode_textbody($body, $format = "fixed")
{
    $tmp = new \stdClass();
    $body = explode("\n", $body);
    $nbody = array();
    $depth = 0;
    $paragraph = ""; // empty paragraph
    $lastline = "";
    for ($i = 0; $i < count($body) + 1; $i++) {
        // calculate the quote depth of the actual line
        $ndepth = 0;
        $tdepth = 0;
        for ($j = 0; $j <= strlen(@$body[$i]); $j++) {
            $tdepth = $j;
            if (@$body[$i][$j] == '>') {
                $ndepth++;
            } else {
                if ((@$body[$i][$j] != ' ') || (@$body[$i][$j - 1] == ' ') || ($j == 0)) {
                    break;
                }
            }
        }
        // generate a new paragraph?
        if (($i > 0) && (($ndepth != $depth) || $format != "flowed" || (@$paragraph[strlen($paragraph) - 1] != ' ')) || ($i == count($body))) {
            $tmp->text = $paragraph;
            $tmp->depth = $depth;
            $paragraph = "";
            if (phpversion() >= 5) { // clone the object to avoid reference issues
                $nbody[] = clone ($tmp);
            } else {
                $nbody[] = $tmp;
            }
        }
        if (@$body[$i] == "-- " && $format == "flowed")
            $body[$i] = "--";
        $paragraph .= substr(@$body[$i], $tdepth);
        $depth = $ndepth;
    }
    return $nbody;
}

/*
 * replaces multiple spaces in texts by &nbsp;es and convert special-chars
 * to their entities
 */
function text2html($text)
{
    // These features have been replaced by css
    return $text;
}

function nl2p($string, $line_breaks = true, $xml = false)
{
    $string = str_replace(array(
        '<p>',
        '</p>',
        '<br>',
        '<br>'
    ), '', $string);

    // It is conceivable that people might still want single line-breaks
    // without breaking into a new paragraph.
    if ($line_breaks == true) {
        return '<p>' . preg_replace(array(
            "/([\n]{2,})/i",
            "/([^>])\n([^<])/i"
        ), array(
            "</p>\n<p>",
            '$1<br' . ($xml == true ? ' /' : '') . '>$2'
        ), rtrim($string)) . '</p>';
    } else {
        return '<p>' . preg_replace(array(
            "/([\n]{2,})/i",
            "/([\r\n]{3,})/i",
            "/([^>])\n([^<])/i"
        ), array(
            "</p>\n<p>",
            "</p>\n<p>",
            '$1<br' . ($xml == true ? ' /' : '') . '>$2'
        ), rtrim($string)) . '</p>';
    }
}

/*
 * print an article to the webpage
 *
 * $group: The name of the newsgroup
 * $id: the ID of the article inside the group or the message-id
 * $attachment: The number of the attachment of the article.
 * 0 means the normal textbody.
 */
function message_show($group, $id, $attachment = 0, $article_data = false, $maxlen = false)
{

    global $file_article, $file_article_full, $OVERRIDES, $spooldir;
    global $text_header, $text_article, $article_showthread, $file_attachment, $attachment_show;
    global $block_xnoarchive, $article_graphicquotes, $groupname, $readmode, $poll;
    global $CONFIG, $current_message, $articleflat_chars_per_articles;
    $logfile = $spooldir . '/log/message_show.log';
    $func_name = "messages.inc.php message_show()";

    if( isset($_REQUEST['page']) && $_REQUEST['page']!=='attachment' ) {
        echo "\n<!-- [DEBUG inc/message.inc.php $func_name globals: file_article=$file_article, file_article_full=$file_article_full, override=".count($OVERRIDES).", spooldir=$spooldir; logfile=$logfile group: " . $group . ", id: '" . $id . "', attachment: '" . $attachment . "'] -->\n";
    }

    file_put_contents($logfile, "\n" . format_log_date() . " " . $func_name . " DEBUG: thread_load called for group: " . $groupname . " readmode: " . $readmode . " poll: " . ($poll ? 'true' : 'false'), FILE_APPEND);



    $current_message = np_get_db_article($id, $group, 1);

    if ($article_data == false) {
        $article_data = message_read($id, $attachment, $group);
    }
    if($article_data == null) {
        echo '<hr><p class="message_show_header_notice">Article #' . $id . ' not found in group ' . htmlspecialchars($group) . '</p><hr>';
        return "not-found";
    }
    $head = $article_data->header;
    $local_poster = false;
    if (! isset($head->rslight_site)) {
        $head->rslight_site = '';
    }
    if (password_verify($CONFIG['thissitekey'] . $head->id, $head->rslight_site)) {
        $local_poster = true;
    }
    $body = $article_data->body[$attachment];
    if ($head) {
        // User blocklist
        $blocked_user_config = array();
        $block = false;
        if (isset($_COOKIE['mail_name'])) {
            if ($userdata = get_user_mail_auth_data($_COOKIE['mail_name'])) {
                $userfile = $spooldir . '/' . strtolower($_COOKIE['mail_name']) . '-blocked_posters.dat';
                if (file_exists($userfile)) {
                    $blocked_user_config = secure_unserialize($userfile);
                }
                foreach ($blocked_user_config as $key => $value) {
                    $blockme = '/' . addslashes($key) . '/';
                    if (preg_match($blockme, $head->from)) {
                        $block = true;
                        break;
                    }
                }
            }
        }
        if ($block === true) {
            echo '<hr><p class="message_show_header_notice">(message #' . $head->number . ' hidden by your blocklist)</p><hr>';
            return "blocked";
        }

        if (($block_xnoarchive) && (isset($head->xnoarchive)) && ($head->xnoarchive == "yes")) {
            echo '<hr><p class="message_show_header_notice>' . $text_article["block-xnoarchive"] . '(article #' . $id . ')</p><hr>';
            return "no-archive";
        }

        // Any header checks to display notice in article display
        $notice = display_header_notice($head, $body);
        if (isset($head->content_type[0])) {
            if (!strpos($head->content_type[0], "/")) {
                echo '<hr><p class="message_show_header_notice">(message #' . $head->number . ' not displayed - malformed header)</p><hr>';
                return "blocked";
            }
        }

        // If attachment=0 newsportal expects it to be plain text
        if (($head->content_type[$attachment] == "text/plain") && ($attachment == 0)) {
            // If we can't find the actual text in 'zero', look for it just in case
            if (trim($body) === '') {  // There is no text in the text/plain body, it seems
                $plaintext = false;
                for ($o = 1; $o < count($head->content_type); $o++) {
                    if ($head->content_type[$o] == "text/plain") {
                        if (trim($article_data->body[$o]) != '') {
                            $plaintext = $o; // we found at least one text/plain
                            $body = $article_data->body[$o];
                            break;
                        }
                    }
                }
                if ($plaintext === false) {
                    $notice = '<hr><p class=np_ob_posted_date>(message #' . $head->number . ' - no text found in message body)</p><hr>';
                }
            }
            show_header($head, $group, $local_poster);
            echo $notice;
            // X-Face
            if (($face = display_full_headers($head->number, $group, $head->name, $head->from, true)) && ($OVERRIDES['disable_xface'] != true)) {
                $pngfile = '../tmp/face-' . hash('ripemd160', $face);
                if (file_exists($pngfile)) {
                    echo '<img align="right" src="' . $pngfile . '" alt="x-face">';
                } else {
                    $facefile = tempnam('../tmp', 'face-');
                    file_put_contents($facefile, $face);
                    $xbmfile = $facefile . '.xbm';
                    $uncompface = 'uncompface -X ' . $facefile . ' ' . $xbmfile;
                    shell_exec($uncompface);
                    if (function_exists('imagecreatefromxbm')) {
                        if (($xbm = imagecreatefromxbm($xbmfile)) !== false) {
                            imagepng($xbm, $pngfile);
                            imagedestroy($xbm);
                            echo '<img align="right" src="' . $pngfile . '">';
                        }
                    }
                    unlink($facefile);
                    unlink($xbmfile);
                }
            }

            // RSLIGHT Encryption
            $encrypted = false;
            if ((isset($article_data->header->rslight_to)) && (password_verify($CONFIG['thissitekey'] . $head->id, $head->rslight_site))) {
                echo 'This is an encrypted message for <b>' . $article_data->header->rslight_to . ' </b>';
                echo '<form action="decrypt.php?id=' . $id . '&group=' . $group . '" method="post">'; // TODO
                echo '<p>Enter Password: <input type="password" name="decryptpass" >&nbsp;';
                echo '<input type="hidden" name="decryptuser" value="' . $article_data->header->rslight_to . '">';
                echo '<input type="submit" value="Decrypt"></p>';
                echo '</form>';
                $encrypted = true;
            }
            if ($encrypted === false) {
                $body = nl2p(htmlspecialchars($body));
                $body = decode_textbody($body, $article_data->header->content_type_format[$attachment]);
            }
            /*
             * FIXME
             * if((isset($article_data->header->rslight_to)) && !(password_verify($CONFIG['thissitekey'].$head->id, $head->rslight_site))) {
             * echo "<b>rslight encrypted message.</b>";
             * $body="";
             * }
             */
            $depth = 0;
            if (isset($CONFIG['synchronet']) && ($CONFIG['synchronet'] == true)) {
                echo '<div class="np_article_body_synch">';
            } else {
                echo '<div class="np_article_body">';
            }
            $currentlen = 0; // needed if $maxlen is set
            for ($i = 0; $i <= count($body) && (($currentlen < $maxlen) || ($maxlen == false)); $i++) {
                // HTMLized Quotings instead of boring > ?
                if ($article_graphicquotes) {
                    // HTMLized Quotings
                    for ($j = $depth; $j < @$body[$i]->depth; $j++)
                        echo '<blockquote class="np_article_quote">';
                    for ($j = @$body[$i]->depth; $j < $depth; $j++)
                        echo '</blockquote>';
                    $t = @$body[$i]->text;
                    echo display_links_in_body($t);
                    $currentlen += strlen($t);
                    echo "\n";
                    $depth = @$body[$i]->depth;
                } else {
                    // Boring old Quotings with >
                    if ($body[$i]->depth == 0) {
                        if (trim($body[$i]->text) == '') {
                            // If the text is empty, we just print a line break
                            // This is needed to avoid empty lines in the article
                            $t = "<br>\n";
                        } else {
                            $t = @$body[$i]->text;
                        }
                    } else {
                        $t = '<i>' . str_repeat('&gt;', $body[$i]->depth) . ' ' . html_parse(text2html(textwrap($body[$i]->text, 72 - $body[$i]->depth, "\n" . str_repeat('>', $body[$i]->depth) . ' '))) . "</i><br>\n";
                    }
                    echo $t;
                    $currentlen += strlen($t);
                }
            }
            if ($maxlen != false && $currentlen >= $maxlen) {
                echo '<br><a href="' . $file_article_full . '&id=' . $id . '&group=' . urlencode($group) . '">' . $text_article["full_article"] . '</a>';
            }
            // If attachment is image embed into article
            if ((isset($attachment_show)) && ($attachment_show == true) && (isset($head->content_type[1]))) {
                echo $text_header["attachments"];
                for ($i = 1; $i < count($head->content_type); $i++) {
                    if (! strcmp($head->content_type[$i], "text/html")) {
                        $contype = "HTML Version";
                    } else {
                        $contype = $head->content_type_name[$i];
                    }
                    $type = explode('/', $head->content_type[$i]);
                    if (trim($type[0]) == "image") {
                        if ($i > 2) {
                            echo ', ';
                        }
                        echo '<a href="' . $file_attachment . '&group=' . urlencode($group) . '&' . 'id=' . urlencode($head->number) . '&' . 'attachment=' . $i . '">' . '<img src="' . $file_attachment . '&group=' . urlencode($group) . '&' . 'id=' . urlencode($head->number) . '&' . 'attachment=' . $i . '" title="' . $contype . '" alt="' . $contype . '" style="max-width: 20vw; max-height: 100px;"></a>&nbsp;';
                    } else {
                        if ($plaintext !== false && $plaintext != $i) {
                            if ($i > 2) {
                                echo ', ';
                            }
                            echo '<a href="' . $file_attachment . '&group=' . urlencode($group) . '&' . 'id=' . urlencode($head->number) . '&' . 'attachment=' . $i . '">' . $contype . '</a> (' . $head->content_type[$i] . ')';
                        }
                    }
                }
            }
            echo '</div>';
        } else {
            if ($attachment == 0) {
                show_header($head, $group, $local_poster);
                $notice = '<hr><p class=np_ob_posted_date>(message #' . $head->number . " - no 'text/plain' part found in this message)</p><hr>";
                echo $notice;
            } else {
                echo $body;
            }
        }
    }
}

function message_decrypt($key, $group, $id, $attachment = 0, $article_data = false, $maxlen = false)
{
    global $file_article, $file_article_full;
    global $text_header, $text_article, $article_showthread;
    global $block_xnoarchive, $article_graphicquotes;
    if ($article_data == false) {
        $article_data = message_read($id, $attachment, $group);
    }
    $head = $article_data->header;
    $body = $article_data->body[$attachment];
    if ($head) {
        if (($block_xnoarchive) && (isset($head->xnoarchive)) && ($head->xnoarchive == "yes")) {
            echo $text_article["block-xnoarchive"];
        } else if (($head->content_type[$attachment] == "text/plain") && ($attachment == 0)) {
            show_header($head, $group);
            // RSLIGHT Decrypt here
            $body = str_replace("-- RSLIGHT DAT START\n", "", $body);
            $body = str_replace("\n-- RSLIGHT DAT END", "", $body);
            $body = rslight_decrypt($body, $key);
            $body = decode_textbody($body, $article_data->header->content_type_format[$attachment]);
            $depth = 0;
            echo '<div class="np_article_body">';
            echo "(Copy text below to quote in reply)<br><br>";
            $currentlen = 0; // needed if $maxlen is set
            for ($i = 0; $i <= count($body) && (($currentlen < $maxlen) || ($maxlen == false)); $i++) {
                // HTMLized Quotings instead of boring > ?
                if ($article_graphicquotes) {
                    // HTMLized Quotings
                    for ($j = $depth; $j < $body[$i]->depth; $j++){
                        echo '<blockquote class="np_article_quote">';
                    }
                    for ($j = $body[$i]->depth; $j < $depth; $j++) {
                        echo '</blockquote>';
                    }
                    $t = html_parse(text2html($body[$i]->text)) . '<br>';
                    echo $t;
                    $currentlen += strlen($t);
                    echo "\n";
                    $depth = $body[$i]->depth;
                } else {
                    // Boring old Quotings with >
                    if ($body[$i]->depth == 0) {
                        if (trim($body[$i]->text) == '') {
                            // If the text is empty, we just print a line break
                            $t = "<br>\n";
                        } else {
                            $t = html_parse(text2html($body[$i]->text)) . "<br>\n";
                        }
                    } else {
                        $t = '<i>' . str_repeat('&gt;', $body[$i]->depth) . ' ' . html_parse(text2html(textwrap($body[$i]->text, 72 - $body[$i]->depth, "\n" . str_repeat('>', $body[$i]->depth) . ' '))) . "</i><br>\n";
                    }
                    echo $t;
                    $currentlen += strlen($t);
                }
            }
            echo '</div>';
            if ($maxlen != false && $currentlen >= $maxlen) {
                echo '<br><a href="' . $file_article_full . '&id=' . $id . '&group=' . urlencode($group) . '">' . $text_article["full_article"] . '</a>';
            }
        } else {
            echo $body;
        }
    }
}

/*
 * Shows the little menu on article-flat.php where you can select the
 * different pages with the articles on it
 */
function articleflat_pageselect($group, $id, $article_count, $first)
{
    global $CONFIG, $file_article, $file_framethread, $name;
    global $text_thread, $thread_show;
    $pages = ceil($article_count / $CONFIG['articleflat_articles_per_page']);
    $return = "";
    if ($article_count > $CONFIG['articleflat_articles_per_page']) {
        $return .= $text_thread["pages"];
    }
    for ($i = 0; $i < $pages; $i++) {
        if ($first != $i * $CONFIG['articleflat_articles_per_page'] + 1) {
            $return .= '<a class="np_pages_unselected" href="' . $file_article . '&group=' . urlencode($group) . '&amp;id=' . urlencode($id) . '&amp;first=' . ($i * $CONFIG['articleflat_articles_per_page'] + 1) . '&amp;last=' . ($i + 1) * $CONFIG['articleflat_articles_per_page'] . '#start">';
        } else {
            $return .= '<span class="np_pages_selected">';
        }
        $return .= $i + 1;
        if ($i == $pages - 1) {
            // $return.= $article_count;
        }
        if ($first != $i * $CONFIG['articleflat_articles_per_page'] + 1) {
            $return .= '</a>';
        } else {
            $return .= '</span>';
        }
    }
    return $return;
}

function display_header_notice($head, $body = null)
{
    global $OVERRIDES;
    $notice = false;

    // Header checks
    if (stripos($head->subject, "Re: ") === 0) {
        if (!isset($head->references)) {
            $notice = '<hr><p class=article_header_notice>(article missing references header)</p><hr>';
        }
    }

    // Body checks
    if ($body && isset($OVERRIDES['display_notice_for_body_checks']) && is_array($OVERRIDES['display_notice_for_body_checks'])) {
        foreach ($OVERRIDES['display_notice_for_body_checks'] as $check) {
            if (preg_match($check['regex'], $body)) {
                $notice .= '<hr><p class=article_header_notice>(' . $check['notice'] . ')</p><hr>';
            }
        }
    }
    return $notice;
}
