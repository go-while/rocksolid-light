<?php

$logfile = $logdir . '/files.log';

if (isset($_COOKIE['tzo'])) {
    $offset = $_COOKIE['tzo'];
} else {
    $offset = $CONFIG['timezone'];
}
if ((isset($_REQUEST['command']) && $_REQUEST['command'] == 'Show') && password_verify($CONFIG['thissitekey'], $_REQUEST['key'])) {
    $getfilename = $spooldir . '/upload/' . $_REQUEST['showfile'];
    ob_clean();
    header('Content-type: ' . $_REQUEST['contenttype']);
    header('Content-disposition: filename="' . $_REQUEST['showfilename'] . '"');
    file_put_contents($logfile, "\n" . format_log_date() . " Requesting: " . $_REQUEST['showfile'], FILE_APPEND);
    readfile($getfilename);
    exit(0);
}
$title .= ' - Browse files';

if (disable_page_by_user_agent($client_device, "bot", "Files")) {
    echo "<center>Page Disabled</center>";
    include $footer_inc;
    exit();
}

echo '<h1 class="np_thread_headline">';
echo '<a href="'.$file_files.'">files</a> / ';
if(isset($_COOKIE['mail_name'])) {
    echo htmlspecialchars($_COOKIE['mail_name']);
}
echo '</h1>';
echo '<table cellpadding="0" cellspacing="0" class="np_buttonbar"><tr>';
// Browse button
echo '<td>';
echo '<form target="' . $frame['content'] . '" method="post" action="'.$file_files.'">';
echo '<input name="command" type="hidden" id="command" value="Browse" readonly="readonly">';
echo '<button class="np_button_link" type="submit">Browse</button>';
echo '</form>';
echo '</td>';
// Upload button
echo '<td>';
echo '<form target="' . $frame['content'] . '" method="post" action="'.$file_upload.'">';
echo '<input name="command" type="hidden" id="command" value="Upload" readonly="readonly">';
echo '<button class="np_button_link" type="submit">Upload</button>';
echo '</form>';
echo '</td>';
echo '<td width=100%></td></tr></table>';
echo '<hr>';

$directory = $spooldir . '/upload/';
$users = array();
if (is_dir($directory)) {
    if ($user_dir = opendir($directory)) {
        while (($user_list = readdir($user_dir)) !== false) {
            if ($user_list == '.' || $user_list == '..') {
                continue;
            }
            $users[] = $user_list;
        }
        closedir($user_dir);
    }
}
sort($users);
$found = 0;
if (count($users) > 0) {
    echo "<strong><small>Select a public user directory to browse:</small></strong>";
    echo '<form name="browse" method="post" action="'.$file_files.'" enctype="multipart/form-data">';
    echo '<input name="command" type="hidden" id="command" value="Browse" readonly="readonly">';
    echo '<input type="hidden" name="key" value="' . password_hash($CONFIG['thissitekey'], PASSWORD_DEFAULT) . '">';
    echo '<select name="listbox">';
    foreach ($users as $user) {
        $num = count(scandir($spooldir . '/upload/' . $user . '/')) - 2;
        if ($user == $_POST['listbox']) {
            echo '<option value="' . $user . '" selected="selected">' . $user . ' (' . $num . ' files)</option>';
            $found = 1;
        } else {
            echo '<option value="' . $user . '">' . $user . ' (' . $num . ' files)</option>';
        }
    }
    echo '</select>';
    echo '<input type="submit" name="Submit" value="Browse">';
    echo '</form>';
}

if ($found == 1 && password_verify($CONFIG['thissitekey'], $_REQUEST['key'])) {
    display_user_files($_POST['listbox'], $offset);
}

function display_user_files($user, $offset)
{
    global $CONFIG, $spooldir, $text_header;
    $directory = $spooldir . '/upload/' . $user . '/';
    if (is_dir($directory)) {
        $files = scandir($directory);
    }
    natcasesort($files);
    echo '<table cellspacing="0" class="np_thread_table">';
    echo '<tr class="np_thread_head"><td class="np_thread_head">Filename</td><td>File Type</td><td>Date</td></tr>';
    $i = 0;
    foreach ($files as $file) {
        if ($file == '.' || $file == '..') {
            continue;
        }
        $lineclass = "np_thread_line" . (($i % 2) + 1);
        $thisfile = $spooldir . '/upload/' . $user . '/' . $file;
        // Use local timezone if possible
        $ts = new DateTime(date("D, j M Y H:i T", filectime($thisfile)), new DateTimeZone('UTC'));
        $ts->add(DateInterval::createFromDateString($offset . ' minutes'));

        if ($offset != 0) {
            $newdate = $ts->format('j M Y');
        } else {
            $newdate = $ts->format('j M Y T');
        }
        unset($ts);
        echo '<tr class="' . $lineclass . '">';
        $mime = mime_content_type($thisfile);
        // Link
        echo '<td class="' . $lineclass . '">';
        echo '<form action="'.$file_files.'" method="post" target="rslight_view">';
        echo '<button class="np_filename_button_link" type="submit">' . $file . '</button>';
        echo '<input type="hidden" name="showfile" value="' . $user . '/' . $file . '"/>';
        echo '<input type="hidden" name="showfilename" value="' . $file . '"/>';
        echo '<input type="hidden" name="key" value="' . password_hash($CONFIG['thissitekey'], PASSWORD_DEFAULT) . '">';
        echo '<input type="hidden" name="contenttype" value="' . $mime . '">';
        echo '<input name="command" type="hidden" id="command" value="Show" readonly="readonly">';
        echo '</form>';
        echo '</td>';
        echo '<td class="' . $lineclass . '"><span class="np_thread_line_text">' . $mime . '</span></td>';
        echo '<td class="' . $lineclass . '"><span class="np_thread_line_text">' . $newdate . '</span></td>';
        echo '</tr>';
        $i ++;
    }
    echo '</table>';
}