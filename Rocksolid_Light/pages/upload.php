<?php

$logfile = $logdir . '/files.log';

// Use centralized authentication - requests.inc.php handles auth requirements
// If we reach this point, user is authenticated
$logged_in = $GLOBALS['is_authenticated'];
$current_user = $GLOBALS['current_user'];

$name = '';
if (! isset($_POST['username'])) {
    $_POST['username'] = $current_user;
}
$name = trim($_POST['username']);

$title .= ' - Upload file';
include "../rocksolid/head.inc";
echo '<h1 class="np_thread_headline">';
echo '<a href="'.$file_files.'">files</a> / ';
echo htmlspecialchars($current_user) . '</h1>';
echo '<table cellpadding="0" cellspacing="0" class="np_buttonbar"><tr>';
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
if (isset($_FILES['photo'])) {
    // Optional security checks - controlled by config
    $upload_error = null;

    if (isset($CONFIG['validate_file_uploads']) && $CONFIG['validate_file_uploads']) {

        // File size check
        if (isset($CONFIG['max_upload_size']) && $_FILES["photo"]["size"] > $CONFIG['max_upload_size']) {
            $upload_error = "File too large. Maximum size: " . number_format($CONFIG['max_upload_size']/1024/1024, 1) . "MB";
        }

        // File type validation (if configured)
        if (!$upload_error && isset($CONFIG['allowed_file_types']) && is_array($CONFIG['allowed_file_types'])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $detected_type = finfo_file($finfo, $_FILES["photo"]["tmp_name"]);
            finfo_close($finfo);

            if (!in_array($detected_type, $CONFIG['allowed_file_types'])) {
                $upload_error = "File type '$detected_type' not allowed on this server.";
            }
        }

        // Filename length check
        if (!$upload_error && strlen($_FILES["photo"]["name"]) > 255) {
            $upload_error = "Filename too long. Maximum 255 characters.";
        }
    }

    // Log upload attempts for monitoring
    if (isset($CONFIG['log_file_uploads']) && $CONFIG['log_file_uploads']) {
        error_log("File upload attempt: " . $_FILES["photo"]["name"] . " (" . $_FILES["photo"]["size"] . " bytes) from " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " user: " . strtolower($_POST['username']));
    }

    if ($upload_error) {
        echo '<div style="color: red; font-weight: bold;">' . htmlspecialchars($upload_error) . '</div>';
    } else {
        $_FILES['photo']['name'] = preg_replace('/[^a-zA-Z0-9\.]/', '_', $_FILES['photo']['name']);

        // Prevent empty names
        if (empty($_FILES['photo']['name']) || $_FILES['photo']['name'] === '.') {
            $_FILES['photo']['name'] = 'upload_' . time() . '.bin';
        }

        // User is authenticated by centralized system
        $userdir = $spooldir . '/upload/' . strtolower($_POST['username']);
        $upload_to = $userdir . '/' . $_FILES['photo']['name'];
        if (is_file($upload_to)) {
            echo $_FILES['photo']['name'] . ' already exists in your folder';
        } else {
            if (! is_dir($userdir)) {
                mkdir($userdir, 0755, true);
            }
            $success = move_uploaded_file($_FILES['photo']['tmp_name'], $upload_to);
            if ($success) {
                file_put_contents($logfile, "\n" . format_log_date() . " Saved: " . strtolower($_POST['username']) . "/" . $_FILES['photo']['name'], FILE_APPEND);
                echo 'Saved ' . $_FILES['photo']['name'] . ' to your files folder';

                // Optional: Track upload statistics
                if (isset($CONFIG['track_uploads']) && $CONFIG['track_uploads']) {
                    $upload_log = $spooldir . '/logs/uploads.log';
                    if (!is_dir($spooldir . '/logs')) {
                        mkdir($spooldir . '/logs', 0755, true);
                    }
                    $upload_entry = date('Y-m-d H:i:s') . " | " .
                                   ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . " | " .
                                   $_FILES["photo"]["name"] . " | " .
                                   $_FILES["photo"]["size"] . " | " .
                                   strtolower($_POST['username']) . " | files_upload\n";
                    file_put_contents($upload_log, $upload_entry, FILE_APPEND | LOCK_EX);
                }
            } else {
                echo 'There was an error saving ' . $_FILES['photo']['name'];
            }
        }
    }
    echo '<br ><br >';
}

// User is authenticated by centralized system - show upload form
echo '<table border="0" align="center" cellpadding="0" cellspacing="1">';

// Determine max upload size for display
$max_upload_display = "2MB"; // Default display
if (isset($CONFIG['validate_file_uploads']) && $CONFIG['validate_file_uploads'] && isset($CONFIG['max_upload_size'])) {
    $max_upload_display = number_format($CONFIG['max_upload_size']/1024/1024, 1) . "MB";
}

echo '<form name="form1" method="post" action="'.$file_upload.'" enctype="multipart/form-data">';
echo '<tr><td class="upload_logged_in_msg"><strong>Logged in as ' . secure_input($_POST['username'], 'html') . '<br >(max size=' . $max_upload_display . ')</strong></td></tr>';
echo '<td><input name="command" type="hidden" id="command" value="Upload" readonly="readonly"></td>';
echo '<input type="hidden" name="key" value="' . password_hash($CONFIG['thissitekey'] . $name, PASSWORD_DEFAULT) . '">';
echo '<input type="hidden" name="username" value="' . secure_input($_POST['username'], 'html') . '">';
echo '<input type="hidden" name="password" value="">';
echo '<tr><td><input type="file" name="photo" id="fileSelect" value="fileSelect" accept="image/*,audio/*,text/*,application/*"></td>';
echo '<td>&nbsp;<input type="submit" name="Submit" value="Upload"></td>';
echo '</form>';
echo '</tr>';
echo '</table>';
echo '</body></html>';
?>