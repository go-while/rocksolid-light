<?php
/*
 * Centralized Login Page for RockSolid Light
 * Handles all authentication and cookie setting
 */

// Prevent direct access - should only be accessed through router
if (!defined('PRE_LOAD_DONE')) {
    die('Direct access not allowed');
}

$logfile = $logdir . '/auth.log';

// Initialize variables
$error_message = '';
$success_message = '';
$redirect_url = '';
$username = '';
$password = '';

// Check if already logged in using EXACT original authentication logic
$already_logged_in = false;
$name = '';
/*
// Set up variables exactly like original auth.inc.php
if (! isset($_POST['username'])) {
    $_POST['username'] = $_COOKIE['mail_name'] ?? '';
}
$name = trim(strtolower($_POST['username']));
if (! isset($_POST['password'])) {
    $_POST['password'] = null;
}
if (! isset($_COOKIE['mail_auth'])) {
    $_COOKIE['mail_auth'] = null;
}

if (!empty($name)) {
    // Load keys for authentication
    $keyfile = $spooldir . '/keys.dat';
    $keys = secure_unserialize($keyfile, ['stdClass'], false);
    if (!is_array($keys)) {
        $keys = array();
    }

    // Use the EXACT original 3-tier authentication logic
    $logged_in = verify_logged_in($name);
    if (!$logged_in) {
        if (!empty($_COOKIE['mail_auth']) && ((password_verify($name . $keys[0] . get_user_config($name, 'encryptionkey'), $_COOKIE['mail_auth'])) || (password_verify($name . $keys[1] . get_user_config($name, 'encryptionkey'), $_COOKIE['mail_auth'])))) {
            $logged_in = true;
        }
    }

    if ($logged_in) {
        $already_logged_in = true;
        $username = $_COOKIE['mail_name'] ?? $name;
    }
}
*/

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
$logged_in = verify_logged_in(trim(strtolower($_POST['username'])));
if (!$logged_in) {
    if ((password_verify($name . $keys[0] . get_user_config($name, 'encryptionkey'), $_COOKIE['mail_auth'])) || (password_verify($name . $keys[1] . get_user_config($name, 'encryptionkey'), $_COOKIE['mail_auth']))) {
        $logged_in = true;
    } else {
        if (check_bbs_auth($_POST['username'], $_POST['password'])) {
            if ($ip_pass) {
                $_SESSION['pass'] = true;
            }
            set_user_logged_in_cookies(trim($_POST['username']), $keys);
            $logged_in = true;
        } else {
            echo 'Authentication Required';
        }
    }
}

// Handle logout
if (isset($_POST['command']) && $_POST['command'] == 'Logout') {
    // Clear all authentication cookies
    $past = time() - 3600;
    $auth_cookies = ['mail_auth', 'mail_name', 'pkey'];
    foreach ($auth_cookies as $cookie) {
        if (isset($_COOKIE[$cookie])) {
            setcookie($cookie, '', $past, '/', '', false, false);
            unset($_COOKIE[$cookie]);
        }
    }

    // Clear session
    if (isset($_SESSION)) {
        $_SESSION = array();
        session_destroy();
    }

    $success_message = "You have been logged out successfully.";
    $already_logged_in = false;
    error_log("User logout: " . ($username ?? 'unknown'));
}

// Handle login - Use EXACT original authentication logic
if (isset($_POST['command']) && $_POST['command'] == 'Login') {
    $redirect_url = $_POST['redirect_url'] ?? '';

    // Set up variables exactly like the original auth.inc.php
    if (! isset($_POST['username'])) {
        $_POST['username'] = $_COOKIE['mail_name'] ?? '';
    }
    $name = trim(strtolower($_POST['username']));
    if (! isset($_POST['password'])) {
        $_POST['password'] = null;
    }
    if (! isset($_COOKIE['mail_auth'])) {
        $_COOKIE['mail_auth'] = null;
    }

    // Load keys exactly like original
    $keyfile = $spooldir . '/keys.dat';
    $keys = secure_unserialize($keyfile, ['stdClass'], false);
    if (!is_array($keys)) {
        $keys = array();
    }

    // Use the EXACT original 3-tier authentication logic
    $logged_in = verify_logged_in(trim(strtolower($_POST['username'])));
    if (!$logged_in) {
        if (!empty($_COOKIE['mail_auth']) && ((password_verify($name . $keys[0] . get_user_config($name, 'encryptionkey'), $_COOKIE['mail_auth'])) || (password_verify($name . $keys[1] . get_user_config($name, 'encryptionkey'), $_COOKIE['mail_auth'])))) {
            $logged_in = true;
        } else {
            if (check_bbs_auth($_POST['username'], $_POST['password'])) {
                // Set session like original
                $_SESSION['pass'] = true;
                $_SESSION['username'] = $name;
                $_SESSION['start_address'] = $_SERVER['REMOTE_ADDR'];

                // Use original cookie setting method
                set_user_logged_in_cookies(trim($_POST['username']), $keys);
                $logged_in = true;
            } else {
                $error_message = 'Authentication Required';
                error_log("User login failed: " . ($_POST['username'] ?? 'unknown'));
            }
        }
    }

    if ($logged_in) {
        $success_message = "Login successful!";
        $already_logged_in = true;
        error_log("User login successful: " . ($_POST['username'] ?? 'unknown'));

        // Handle redirect
        if (!empty($redirect_url)) {
            $redirect_url = filter_var($redirect_url, FILTER_SANITIZE_URL);
            if (strpos($redirect_url, 'http') !== 0) {
                echo '<script type="text/javascript">
                    setTimeout(function() {
                        window.location.href = "' . htmlspecialchars($redirect_url, ENT_QUOTES) . '";
                    }, 1000);
                </script>';
            }
        }
    }
}

// Get redirect URL from request if not set
if (empty($redirect_url) && isset($_REQUEST['redirect_url'])) {
    $redirect_url = $_REQUEST['redirect_url'];
}
if (empty($redirect_url) && isset($_REQUEST['source'])) {
    $redirect_url = $_REQUEST['source'];
}

$title .= ' - Login';
?>

<h1 class="np_thread_headline">
    <a href="<?php echo $file_index; ?>">Home</a> / Login
</h1>

<?php if ($error_message): ?>
    <div class="error_message" style="color: red; font-weight: bold; margin: 10px 0; padding: 10px; border: 1px solid red; background-color: #ffe6e6;">
        <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<?php if ($success_message): ?>
    <div class="success_message" style="color: green; font-weight: bold; margin: 10px 0; padding: 10px; border: 1px solid green; background-color: #e6ffe6;">
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>

<?php if ($already_logged_in): ?>
    <div class="login_status">
        <h2>Welcome, <?php echo htmlspecialchars($_COOKIE['mail_name'] ?? $username); ?>!</h2>
        <p>You are currently logged in.</p>

        <?php if (!empty($redirect_url)): ?>
            <p><a href="<?php echo htmlspecialchars($redirect_url); ?>">Continue to your destination</a></p>
        <?php endif; ?>

        <div class="login_actions">
            <p>Available actions:</p>
            <ul>
                <li><a href="?page=user">User Configuration</a></li>
                <li><a href="?page=mail">Mail</a></li>
                <li><a href="?page=files">Files</a></li>
                <li><a href="?page=upload">Upload Files</a></li>
            </ul>
        </div>

        <form method="post" action="?page=login" style="margin-top: 20px;">
            <input type="hidden" name="command" value="Logout">
            <input type="submit" value="Logout" class="logout_button">
        </form>
    </div>
<?php else: ?>
    <div class="login_form">
        <h2>Please Login</h2>
        <form method="post" action="?page=login">
            <input type="hidden" name="command" value="Login">
            <?php if (!empty($redirect_url)): ?>
                <input type="hidden" name="redirect_url" value="<?php echo htmlspecialchars($redirect_url); ?>">
            <?php endif; ?>

            <table class="mail_table_login">
                <tr>
                    <td><label for="username">Username:</label></td>
                    <td><input type="text" name="username" id="username" value="<?php echo htmlspecialchars($username); ?>" required></td>
                </tr>
                <tr>
                    <td><label for="password">Password:</label></td>
                    <td><input type="password" name="password" id="password" required></td>
                </tr>
                <tr>
                    <td colspan="2">
                        <input type="submit" value="Login" class="login_button">
                    </td>
                </tr>
            </table>
        </form>

        <?php if (!empty($redirect_url)): ?>
            <p><small>You will be redirected after login: <?php echo htmlspecialchars($redirect_url); ?></small></p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<style>
.mail_table_login {
    margin: 20px auto;
    border-collapse: collapse;
}

.mail_table_login td {
    padding: 8px 12px;
    vertical-align: middle;
}

.mail_table_login input[type="text"],
.mail_table_login input[type="password"] {
    width: 200px;
    padding: 5px;
    border: 1px solid #ccc;
    border-radius: 3px;
}

.login_button, .logout_button {
    padding: 8px 16px;
    background-color: #007cba;
    color: white;
    border: none;
    border-radius: 3px;
    cursor: pointer;
}

.login_button:hover, .logout_button:hover {
    background-color: #005a8b;
}

.logout_button {
    background-color: #d73502;
}

.logout_button:hover {
    background-color: #b12c02;
}

.login_actions ul {
    list-style-type: none;
    padding: 0;
}

.login_actions li {
    margin: 5px 0;
}

.login_actions a {
    color: #007cba;
    text-decoration: none;
}

.login_actions a:hover {
    text-decoration: underline;
}
</style>
