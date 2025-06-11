<?php
include "config.inc.php";
include "newsportal.php";

$logfile = $logdir . '/files.log';

$keyfile = $spooldir . '/keys.dat';
if (file_exists($keyfile) && is_readable($keyfile)) {
    $keys_data = file_get_contents($keyfile);
    if ($keys_data !== false) {
        $keys = @unserialize($keys_data);
        // Validate that unserialize returned an array to prevent object injection
        if (!is_array($keys)) {
            $keys = array();
            // Log potential security issue
            error_log("Warning: Invalid data in keys file for upload", 0);
        }
    } else {
        $keys = array();
    }
} else {
    $keys = array();
}

$name = '';

$logged_in = false;
if (! isset($_POST['username'])) {
    $_POST['username'] = $_COOKIE['mail_name'];
}
$name = trim(strtolower($_POST['username']));

if (! isset($_POST['password'])) {
    $_POST['password'] = null;
}
if (! isset($_COOKIE['mail_auth'])) {
    $_COOKIE['mail_auth'] = null;
}

$logged_in = verify_logged_in(trim(strtolower($name)));
if (!$logged_in) {
    if ((password_verify($name . $keys[0] . get_user_config($name, 'encryptionkey'), $_COOKIE['mail_auth'])) || (password_verify($name . $keys[1] . get_user_config($name, 'encryptionkey'), $_COOKIE['mail_auth']))) {
        $logged_in = true;
    }
}

$title .= ' - Upload file';
include "head.inc";
echo '<h1 class="np_thread_headline">';
echo '<a href="../spoolnews/files.php" target=' . $frame['menu'] . '>files</a> / ';
echo htmlspecialchars($_COOKIE['mail_name']) . '</h1>';
echo '<table cellpadding="0" cellspacing="0" class="np_buttonbar"><tr>';
echo '<table cellpadding="0" cellspacing="0" class="np_buttonbar"><tr>';
// Browse button
echo '<td>';
echo '<form target="' . $frame['content'] . '" method="post" action="files.php">';
echo '<input name="command" type="hidden" id="command" value="Browse" readonly="readonly">';
echo '<button class="np_button_link" type="submit">Browse</button>';
echo '</form>';
echo '</td>';
// Upload button
echo '<td>';
echo '<form target="' . $frame['content'] . '" method="post" action="upload.php">';
echo '<input name="command" type="hidden" id="command" value="Upload" readonly="readonly">';
echo '<button class="np_button_link" type="submit">Upload</button>';
echo '</form>';
echo '</td>';
echo '<td width=100%></td></tr></table>';
echo '<hr>';
if (isset($_FILES['photo']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
    // Enhanced filename sanitization and validation
    $original_name = $_FILES['photo']['name'];
    $sanitized_name = preg_replace('/[^a-zA-Z0-9\._-]/', '_', $original_name);

    // Validate file extension - only allow safe file types
    $allowed_extensions = array('jpg', 'jpeg', 'png', 'gif', 'pdf', 'txt', 'doc', 'docx');
    $extension = strtolower(pathinfo($sanitized_name, PATHINFO_EXTENSION));

    if (!in_array($extension, $allowed_extensions)) {
        echo 'File type not allowed. Allowed types: ' . implode(', ', $allowed_extensions);
    } else {
        $_FILES['photo']['name'] = $sanitized_name;

        // Check auth here
        if ($logged_in) {
            // Prevent path traversal by validating username
            $safe_username = preg_replace('/[^a-zA-Z0-9_.-]/', '', strtolower($_POST['username']));
            $userdir = $spooldir . '/upload/' . $safe_username;
            $upload_to = $userdir . '/' . $_FILES['photo']['name'];

            if (is_file($upload_to)) {
                echo htmlspecialchars($_FILES['photo']['name']) . ' already exists in your folder';
            } else {
                if (! is_dir($userdir)) {
                    mkdir($userdir, 0755, true);
                }
                $success = move_uploaded_file($_FILES['photo']['tmp_name'], $upload_to);
                if ($success) {
                    file_put_contents($logfile, "\n" . format_log_date() . " Saved: " . $safe_username . "/" . $_FILES['photo']['name'], FILE_APPEND);
                    echo 'Saved ' . htmlspecialchars($_FILES['photo']['name']) . ' to your files folder';
                } else {
                    echo 'There was an error saving ' . htmlspecialchars($_FILES['photo']['name']);
                }
            }
        } else {
            echo 'Authentication Failed';
        }
    }
    echo '<br ><br >';
}

echo '<table border="0" align="center" cellpadding="0" cellspacing="1">';
if (! isset($_POST['username'])) {
    $_POST['username'] = '';
}
if (! isset($_POST['password'])) {
    $_POST['password'] = '';
}
if (! $logged_in && ! check_bbs_auth($_POST['username'], $_POST['password'])) {
    echo '<form name="form1" method="post" action="user.php" enctype="multipart/form-data">';
    echo '<table class="mail_table_login">';
    echo '<tr><td><strong>Please Login</strong></td></tr>';
    echo '<tr><td>Username:</td><td><input name="username" type="text" id="username" value="' . htmlspecialchars($_POST['username'], ENT_QUOTES, 'UTF-8') . '"></td></tr>';
    echo '<tr><td>Password:</td><td><input name="password" type="password" id="password"></td></tr>';
    echo '<input name="command" type="hidden" value="Login">';
    echo '<input name="source" type="hidden" id="source" value="Files:files.php">';
    echo '<input type="hidden" name="key" value="' . password_hash($CONFIG['thissitekey'] . $name, PASSWORD_DEFAULT) . '">';

    echo '<tr>';
    echo '<td><input type="submit" name="Submit" value="Login"></td>';
    echo '</tr>';
    echo '</table>';
    echo '</form>';
} else {
    echo '<form name="form1" method="post" action="upload.php" enctype="multipart/form-data">';
    echo '<tr><td class="upload_logged_in_msg"><strong>Logged in as ' . $_POST['username'] . '<br >(max size=2MB)</strong></td></tr>';
    echo '<td><input name="command" type="hidden" id="command" value="Upload" readonly="readonly"></td>';
    echo '<input type="hidden" name="key" value="' . password_hash($CONFIG['thissitekey'] . $name, PASSWORD_DEFAULT) . '">';
    echo '<input type="hidden" name="username" value="' . $_POST['username'] . '">';
    echo '<input type="hidden" name="password" value="' . $_POST['password'] . '">';
    echo '<tr><td><input type="file" name="photo" id="fileSelect" value="fileSelect" accept="image/*,audio/*,text/*,application/*"></td>
';
    echo '<td>&nbsp;<input type="submit" name="Submit" value="Upload"></td>';
    echo '</form>';
}
echo '</tr>';
echo '</table>';
echo '</body></html>';
?>