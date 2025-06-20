<?php
if(!defined('RSLIGHT_CONFIG_LOADED')) {
    die("Access denied.");
}


$logfile = $spooldir . '/log/register.log';
$mail_log = $spooldir . '/log/mail.log';

# $workpath: Where to cache users (must be writable by calling program)
$workpath = $config_dir . "/users/";
$keypath = $config_dir . "/userconfig/";

$email_registry = $spooldir . '/email_registry.dat';

if (!file_exists($config_dir . '/phpmailer.inc.php')) {
    $CONFIG['verify_email'] = false;
}
if (isset($_POST['captchaimage']) && file_exists($_POST['captchaimage'])) {
    unlink($_POST['captchaimage']);
}
if (!isset($_POST['username'])) {
    $_POST['username'] = null;
}
if (!isset($_POST['key'])) {
    $_POST['key'] = null;
}
if (!isset($_POST['user_email'])) {
    $_POST['user_email'] = null;
}
$username_allowed_chars = "a-zA-Z0-9_.";
$clean_username = preg_replace("/[^$username_allowed_chars]/", "", $_POST['username']);

// Did this client arrive via a recent link from this file?
if ((password_verify($keys[0], $_POST['key'])) || (password_verify($keys[1], $_POST['key']))) {
    $auth_ok = true;
} else {
    $auth_ok = false;
    unset($_POST['command']);
}

if (isset($_POST['username'])) {
    $username = $_POST['username'];
}
if (isset($_POST['password'])) {
    $password = $_POST['password'];
}
if (isset($_POST['user_email'])) {
    $user_email = $_POST['user_email'];
}

// Nothing in $_POST. Show main form
if (!isset($_POST['command'])) {
    if (isset($_COOKIE["ts_limit"])) {
        echo "It appears you already have an active account<br>";
        echo "More than one account may not be created in 30 days<br>";
        echo '<br><a href="/">Return to Home Page</a>';
    } else {
        $captchaImage = '../tmp/captcha' . time() . '.png';
        $captchacode = prepareCaptcha($captchaImage);
        echo '<center>';
        echo '<form name="form1" method="post" action="'.$file_register.'">';
        echo '<input type="hidden" name="csrf_token" value="' . generate_csrf_token() . '">';
        echo '<table class="register_table_register_username">';
        echo '<tr>';
        echo '<td><strong>Register Username </strong></td>';
        echo '<td></td>';
        echo '</tr><tr>';
        echo '<td>Username: </td>';
        echo '<td><input name="username" type="text" id="username"value="' . secure_input($_POST['username'], 'html') . '" maxlength="30"></td>';
        echo '</tr><tr>';
        echo '<td>Email: </td>';
        echo '<td><input name="user_email" type="text" id="user_email" value="' . secure_input($_POST['user_email'], 'html') . '"></td>';
        echo '</tr><tr>';
        echo '<td>Password: </td>';
        echo '<td><input name="password" type="password" id="password"></td>';
        echo '</tr><tr>';
        echo '<td>Re-enter Password: </td>';
        echo '<td><input name="password2" type="password" id="password2"></td>';
        echo '</tr><tr>';
        echo '<td><img src="' . $captchaImage . '" ></td>';
        echo '<td><input name="captcha" type="text" id="captcha"></td>';
        echo '<input name="captchacode" type="hidden" id="captchacode" value="' . $captchacode . '">';
        echo '<input name="captchaimage" type="hidden" id="captchaimage" value="' . $captchaImage . '">';
        echo '<input name="command" type="hidden" id="command" value="Create"">';
        echo '<input name="key" type="hidden" value="' . password_hash($keys[0], PASSWORD_DEFAULT) . '">';
        echo '</tr><tr>';
        echo '<td>&nbsp;</td>';
        echo '<td><input type="submit" name="Submit" value="Create"></td>';
        echo '<td></td></tr>';
        echo '</table></form>';

        // RESET Password
        echo '<form name="resetpw" method="post" action="'.$file_register.'">';
        echo '<table class="register_table_forgot_password_button">';
        echo '<input name="captchacode" type="hidden" id="captchacode" value="' . $captchacode . '">';
        echo '<input name="captchaimage" type="hidden" id="captchaimage" value="' . $captchaImage . '">';
        echo '<input name="command" type="hidden" id="command" value="ResetPW">';
        echo '<input name="pwcommand" type="hidden" id="pwcommand" value="new"">';
        echo '<input name="key" type="hidden" value="' . password_hash($keys[0], PASSWORD_DEFAULT) . '">';
        echo '<tr>';
        echo '<td>';
        echo '<input type="submit" name="Submit" value="I forgot my password"></td>';
        echo '</tr>';
        echo '</table></form>';
        echo '</center>';
    }
    echo '</body>';
    echo '</html>';
    exit(0);
}

if (isset($_POST['command']) && $_POST['command'] == 'ResetPW') {
    // Verify CSRF token
    /*
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        echo '<div class="error">Security Error: Invalid form submission. Please try again.</div>';
        exit();
    }*/
    reset_password($username, $user_email);
    include $footer_inc;
    exit(0);
}

if (isset($_POST['command']) && $_POST['command'] == 'CreateNew') {
    // Verify CSRF token
    /*
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        echo '<div class="error">Security Error: Invalid form submission. Please try again.</div>';
        include $footer_inc;
        exit();
    }
    */
    create_new($username, $password, $user_email);
    include $footer_inc;
    exit(0);
}

if (isset($_POST['command']) && $_POST['command'] == 'ResetPWSendCode') {
    reset_password_send_code($username, $user_email);
    include $footer_inc;
    exit(0);
}

if (isset($_POST['command']) && $_POST['command'] == 'ChangePW') {
    accept_new_password($username, $password);
    exit(0);
}

# $hostname: '{POPaddress:port/pop3}INBOX'
$hostname = '{mail.example.com:110/pop3}INBOX';
# $external: Using external POP auth?
$external = 0;

$ok = FALSE;
$command = "Login";

$username = $_POST['username'];
$password = $_POST['password'];
$command = $_POST['command'];
$user_email = $_POST['user_email'];

$thisusername = $username;
$username = trim(strtolower($username));
$userFilename = $workpath . $username;
$keyFilename = $keypath . $username;

# Check all input
if (empty($_POST['username'])) {
    echo "Please enter a Username\r\n";
    echo '<form name="return1" method="post" action="'.$file_register.'">';
    echo '<input name="user_email" type="hidden" id="user_email" value="' . $user_email . '">';
    echo '<input type="submit" name="Submit" value="Back"></td></form>';
    include $footer_inc;
    exit(2);
}

if (strlen($clean_username) > 30) {
    echo "The maximum username length is 30 characters. You entered " . $clean_username . " which is " . strlen($cleanusername) . " characters long.<br >";
    echo '<form name="return1" method="post" action="'.$file_register.'">';
    echo '<input name="username" type="hidden" id="username" value="' . $clean_username . '" maxlength="22">';
    echo '<input name="user_email" type="hidden" id="user_email" value="' . $user_email . '">';
    echo '<input type="submit" name="Submit" value="Please try again"></td></form>';
    include $footer_inc;
    exit(2);
}

if ($clean_username != $_POST['username']) {
    echo "The username entered contains disallowed characters.<br >";
    echo "Allowed characters:<br >letters, numbers, underscore, hypen, full stop<br ><br >";
    echo '<form name="return1" method="post" action="'.$file_register.'">';
    echo '<input name="username" type="hidden" id="username" value="' . $clean_username . '">';
    echo '<input name="user_email" type="hidden" id="user_email" value="' . $user_email . '">';
    echo '<input type="submit" name="Submit" value="Please try again"></td></form>';
    include $footer_inc;
    exit(2);
}

if (filter_var($user_email, FILTER_VALIDATE_EMAIL) == false) {
    echo "Email address format appears incorrect\n";
    echo '<form name="return1" method="post" action="'.$file_register.'">';
    echo '<input name="username" type="hidden" id="username" value="' . $username . '">';
    echo '<input type="submit" name="Submit" value="Back"></td></form>';
    include $footer_inc;
    exit(2);
}

if ($CONFIG['verify_email']) {
    $user_domain = explode('@', $user_email);
    if ((checkdnsrr($user_domain[1] . '.', "MX") == false) && (checkdnsrr($user_domain[1] . '.', "A") == false)) {
        echo "Email domain appears to not exist\n";
        echo '<form name="return1" method="post" action="'.$file_register.'">';
        echo '<input name="username" type="hidden" id="username" value="' . $username . '">';
        echo '<input type="submit" name="Submit" value="Back"></td></form>';
        include $footer_inc;
        exit(2);
    }
}

if (($_POST['password'] !== $_POST['password2']) || $_POST['password'] == '') {
    echo "Your passwords entered do not match\r\n";
    echo '<form name="return1" method="post" action="'.$file_register.'">';
    echo '<input name="username" type="hidden" id="username" value="' . $username . '">';
    echo '<input name="user_email" type="hidden" id="user_email" value="' . $user_email . '">';
    echo '<input type="submit" name="Submit" value="Back"></td></form>';
    include $footer_inc;
    exit(2);
}

if (getExpressionResult($_POST['captchacode']) != $_POST['captcha']) {
    echo "Incorrect captcha response\r\n";
    echo '<form name="return1" method="post" action="'.$file_register.'">';
    echo '<input name="username" type="hidden" id="username" value="' . $username . '">';
    echo '<input name="user_email" type="hidden" id="user_email" value="' . $user_email . '">';
    echo '<input type="submit" name="Submit" value="Back"></td></form>';
    include $footer_inc;
    exit(2);
}

/* Check for existing email address */
$users = scandir($config_dir . "/userconfig");
foreach ($users as $user) {
    if (!is_file($config_dir . "/userconfig/" . $user)) {
        continue;
    }
    if (strcmp(get_user_config($user, 'mail'), $user_email) == 0) {
        echo "Email exists in database\r\n";
        echo '<form name="return1" method="post" action="'.$file_register.'">';
        echo '<input name="username" type="hidden" id="username" value="' . $username . '">';
        echo '<input type="submit" name="Submit" value="Back"></td></form>';
        include $footer_inc;
        exit(2);
    }
}

# Check email address attempts to avoid abuse
if (file_exists($email_registry)) {
    $tried_email = secure_unserialize($email_registry);
    if (isset($tried_email[$user_email])) {
        echo "Email address already used\r\n";
        echo '<form name="return1" method="post" action="'.$file_register.'">';
        echo '<input name="username" type="hidden" id="username" value="' . $username . '">';
        echo '<input type="submit" name="Submit" value="Back"></td></form>';
        include $footer_inc;
        exit(2);
    }
}
if (!preg_match("^[_a-z0-9-]+(\.[_a-z0-9-]+)*@[a-z0-9-]+(\.[a-z0-9-]+)*(\.[a-z0-9]{2,5})$^", $user_email)) {
    echo "Email must be in the form of an email address\r\n";
    echo '<form name="return1" method="post" action="'.$file_register.'">';
    echo '<input name="username" type="hidden" id="username" value="' . $username . '">';
    echo '<input type="submit" name="Submit" value="Back"></td></form>';
    include $footer_inc;
    exit(2);
}

# Does user file already exist?
if (($userFileHandle = @fopen($userFilename, 'r')) || (get_config_value('aliases.conf', strtolower($thisusername)) !== false)) {
    if ($command == "Create") {
        echo "User:" . $thisusername . " Already Exists\r\n";
        echo '<br ><a href="'.$file_register.'">Back</a>';
        include $footer_inc;
        exit(2);
    }
    $userFileInfo = fread($userFileHandle, filesize($userFilename));
    fclose($userFileHandle);

    # User/Pass is correct
    if (password_verify($password, $userFileInfo)) {
        touch($userFilename);
        $ok = TRUE;
    } else {
        $ok = FALSE;
    }
} else {
    $ok = FALSE;
}

# Ok to log in. User authenticated.
if ($ok) {
    echo "User:" . $thisusername . "\r\n";
    include $footer_inc;
    exit(0);
}

# Using external authentication
if ($external) {
    $mbox = @imap_open($hostname, $username, $password);
    if ($mbox) {
        $ok = TRUE;
        imap_close($mbox);
    }
}

# User is authenticated or to be created. Either way, create the file
if ($ok || ($command == "Create")) {
    create_account($username, $password, $user_email);
    include $footer_inc;
    exit(0);
} else {
    echo "Authentication Failed\r\n";
    include $footer_inc;
    exit(1);
}

// Here we send code by email to verify RESET of password
function reset_password_send_code($username, $user_email)
{
    send_reset_email($username, $user_email);
    include $footer_inc;
    exit(0);
}

function reset_password($username = null, $user_email = null)
{
    global $keys;

    if (isset($_POST['pwcommand']) && $_POST['pwcommand'] != 'new' && $_POST['pwcommand'] != 'retry') {
        if (getExpressionResult($_POST['captchacode']) != $_POST['captcha']) {
            echo "Incorrect captcha response2\r\n";
            echo '<form name="retrypw" method="post" action="'.$file_register.'">';
            echo '<input name="username" type="hidden" id="username" value="' . $username . '">';
            echo '<input name="user_email" type="hidden" id="user_email" value="' . $user_email . '"">';
            echo '<input name="command" type="hidden" id="command" value="ResetPW">';
            echo '<input name="pwcommand" type="hidden" id="pwcommand" value="retry"">';
            echo '<input name="key" type="hidden" value="' . password_hash($keys[0], PASSWORD_DEFAULT) . '">';
            echo '<input type="submit" name="Submit" value="Back"></td></form>';
            include $footer_inc;
            exit(2);
        }
    }
    if (isset($_POST['pwcommand']) && $_POST['pwcommand'] != 'retry') {
        if ($username != null && $user_email != null) {
            if (verify_reset_password($username, $user_email) == false) {
                return false;
            } else {
                // Proceed with password change process starting with email verification
                // We must create and send verification code, then return and handle that
                echo "Click to send Verification Code by email.\r\n";
                echo '<form name="sendcode" method="post" action="'.$file_register.'">';
                echo '<input name="username" type="hidden" id="username" value="' . $username . '">';
                echo '<input name="user_email" type="hidden" id="user_email" value="' . $user_email . '"">';
                echo '<input name="command" type="hidden" id="command" value="ResetPWSendCode">';
                echo '<input name="pwcommand" type="hidden" id="pwcommand" value="sendcode"">';
                echo '<input name="key" type="hidden" value="' . password_hash($keys[0], PASSWORD_DEFAULT) . '">';
                echo '<input type="submit" name="Submit" value="Send Verification Code"></td></form>';
                include $footer_inc;
                exit;
            }
        }
    }
    $captchaImage = '../tmp/captcha' . time() . '.png';
    $captchacode = prepareCaptcha($captchaImage);
    echo '<center>';
    echo '<form name="form1" method="post" action="'.$file_register.'">';
    echo '<table class="register_table_forgot_password">';
    echo '<tr>';
    echo '<td><strong>Reset Password </strong></td>';
    echo '<td></td>';
    echo '</tr><tr>';
    echo '<td>Username: </td>';
    echo '<td><input name="username" type="text" id="username"value="' . $username . '" maxlength="30"></td>';
    echo '</tr><tr>';
    echo '<td>Email: </td>';
    echo '<td><input name="user_email" type="text" id="user_email" value="' . $user_email . '"></td>';
    echo '</tr><tr>';
    echo '<td><img src="' . $captchaImage . '" ></td>';
    echo '<td><input name="captcha" type="text" id="captcha"></td>';
    echo '<input name="captchacode" type="hidden" id="captchacode" value="' . $captchacode . '">';
    echo '<input name="captchaimage" type="hidden" id="captchaimage" value="' . $captchaImage . '">';
    echo '<input name="command" type="hidden" id="command" value="ResetPW">';
    echo '<input name="pwcommand" type="hidden" id="pwcommand" value="process">';
    echo '<input name="key" type="hidden" value="' . password_hash($keys[0], PASSWORD_DEFAULT) . '">';
    echo '</tr><tr>';
    echo '<td>&nbsp;</td>';
    echo '<td><input type="submit" name="Submit" value="Reset my password"></td>';
    echo '</tr>';
    echo '</table>';
    echo '</form>';
    echo '</center>';
    return true;
}

function verify_reset_password($username, $user_email)
{
    global $keys, $logfile;
    if ($username != null && $user_email != null) {
        $get_userval = get_config_value('/userconfig/' . trim(strtolower($username)), 'email');
        if (strcmp(trim(strtolower($get_userval)), trim(strtolower($user_email))) != 0) {
            echo "Username or Email Not Found\r\n";
            echo '<form name="return1" method="post" action="'.$file_register.'">';
            echo '<input name="username" type="hidden" id="username" value="' . $username . '">';
            echo '<input name="user_email" type="hidden" id="user_email" value="' . $user_email . '">';
            echo '<input name="key" type="hidden" value="' . password_hash($keys[0], PASSWORD_DEFAULT) . '">';
            echo '<input name="captchacode" type="hidden" id="captchacode" value="' . secure_input($_POST['captchacode'], 'html') . '">';
            echo '<input name="captchaimage" type="hidden" id="captcha" value="' . secure_input($_POST['captcha'], 'html') . '">';
            echo '<td><input name="pwcommand" type="hidden" id="pwcommand" value="retry"></td>';
            echo '<input name="command" type="hidden" id="command" value="ResetPW">';
            echo '<input type="submit" name="Submit" value="Back"></td></form>';
            file_put_contents($logfile, "\n" . logging_prefix() . " CHANGE PASSWORD (Username or Email Not Found) for: " . $username, FILE_APPEND);
            return false;
        } else {
            return true;
        }
    } else {
        return false;
    }
}

function accept_new_password($username, $password)
{
    global $keys;
    $code = $_POST['code'];
    $password = $_POST['password'];
    $password2 = $_POST['password2'];
    $saved_code = file_get_contents(sys_get_temp_dir() . "/" . $username);
    $fail = false;
    if ((strcmp(trim($password), trim($password2))) !== 0) {
        $fail = "Your passwords entered do not match<br >";
    }
    if ((strcmp(trim($code), trim($saved_code))) !== 0) {
        $fail = "Code does not match. Try again.<br >";
    }

    if ($fail) {
        echo $fail;
        echo '<form name="create1" method="post" action="'.$file_register.'">';
        echo '<br >Enter CODE: ';
        echo '<input name="code" type="text" id="code" value="' . $code . '">&nbsp;';
        echo '<input name="username" type="hidden" id="username" value="' . $username . '">';
        echo '<br ><br >NEW Password: ';
        echo '<input name="password" type="password" id="password" value="' . $password . '">';
        echo '<br >Re-Enter Password: ';
        echo '<input name="password2" type="password" id="password2" value="' . $password2 . '">';
        echo '<input name="command" type="hidden" id="command" value="ChangePW">';
        echo '<input name="key" type="hidden" value="' . password_hash($keys[0], PASSWORD_DEFAULT) . '">';
        echo '<br ><br ><input type="submit" name="Submit" value="Click Here to Create NEW Password"></td></form>';
        include $footer_inc;
        exit;
    }
    change_user_password($username, $password);
    include $footer_inc;
    exit(0);
}

function change_user_password($username, $password)
{
    global $config_dir, $logfile;
    $username = strtolower($username);
    $userfile = $config_dir . '/users/' . $username;
    if (!file_exists($userfile)) {
        echo "User:" . $username . " Not Found\r\n";
        return;
    } else {
        file_put_contents($userfile, password_hash($password, PASSWORD_DEFAULT));
        echo "Password Changed for User: " . $username . "\n<br >";
        echo "NEW Password: " . $password . "\n";
        file_put_contents($logfile, "\n" . logging_prefix() . " Changed PASSWORD for: " . $username, FILE_APPEND);
    }
}

function send_reset_email($username, $user_email)
{
    global $CONFIG, $config_dir, $spooldir, $mail_log, $keys;

    $email = trim(strtolower($user_email));

    // $retry_delay will double after every send of email
    $retry_delay = 3; // How many minutes before allowing to re-send email

    $reset_file = $spooldir . '/email_reset_log.dat';
    if (file_exists($reset_file)) {
        $reset_log = secure_unserialize($reset_file);
    } else {
        $reset_log = array();
    }
    // Unset delay for email address after 1 day
    if (isset($reset_log[$email]['time']) && $reset_log[$email]['time'] < time() - 86400) {
        unset($reset_log[$email]);
    }

    if (isset($reset_log[$email]['count'])) {
        $retry_delay = $retry_delay * $reset_log[$email]['count'];
    }
    $retry_seconds = $retry_delay * 60;

    if (isset($reset_log[$email]['time']) && $reset_log[$email]['time'] > time() - $retry_seconds) {
        echo "Email may only be re-sent after " . $retry_delay . " minutes<br >";
        $remain = (($reset_log[$email]['time'] + $retry_seconds) - time());
        $remain = round($remain / 60, 1);
        echo "Please wait " . $remain . " minutes to re-send<br >";
        include $footer_inc;
        exit(0);
    }

    if ($username != null && $user_email != null) {
        $get_useremail = get_config_value('userconfig/' . trim(strtolower($username)), 'email');
        if (trim(strtolower($get_useremail)) != trim(strtolower($user_email))) {
            echo 'Username or Email address not found<br ><br >';
            echo $username . " : " . $get_useremail . " : " . $user_email;
            return false;
        }
        include($config_dir . '/phpmailer.inc.php');
        if (class_exists('PHPMailer')) {
            $mail = new PHPMailer();
        } else {
            $mail = new PHPMailer\PHPMailer\PHPMailer();
        }
    }
    echo 'Request Password Reset for: ' . $username . '<br><br >';

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
    $mail->Password = $mailer['password'];;
    $mail->SMTPSecure = 'tls';

    $mail->setFrom($mail_user . '@' . $mail_domain, $mail_name);
    $mail->addAddress($user_email);

    $mail->Subject = "Confirmation code for " . gethostname();

    if (isset($mail_custom_header)) {
        foreach ($mail_custom_header as $key => $value) {
            $mail->addCustomHeader($key, $value);
        }
    }

    $mycode = create_code($username);
    $msg = "A request to RESET YOUR PASSWORD on " . gethostname();
    $msg .= " has been made using " . $user_email . ".\n\n";
    $msg .= "IF YOU DID NOT REQUEST THIS, IGNORE THIS and the request will fail.\n\n";
    $msg .= "This is your PASSWORD CHANGE authorization code: " . $mycode . "\n\n";
    $msg .= "Note: replies to this email address are checked daily.";
    $mail->Body = wordwrap($msg, 70);

    echo '<center>';
    if (!$mail->send()) {
        echo 'The message could not be sent.';
        echo '<p>Error: ' . htmlentities($mail->ErrorInfo);
        file_put_contents($mail_log, "\n" . format_log_date() . ' FAILED to send mail from: ' . $mail_user . '@' . $mail_domain . ' to: ' . $user_email . 'Error: ' . $mail->ErrorInfo, FILE_APPEND);
    } else {
        file_put_contents($mail_log, "\n" . format_log_date() . ' SENT mail from: ' . $mail_user . '@' . $mail_domain . ' to: ' . $user_email, FILE_APPEND);
        echo 'An email has been sent to ' . $user_email . '<br >';
        echo 'Please enter the code from the email below:<br >';
        echo '<form name="create1" method="post" action="'.$file_register.'">';
        echo '<br >Enter CODE: ';
        echo '<input name="code" type="text" id="code">&nbsp;';
        echo '<input name="username" type="hidden" id="username" value="' . $username . '">';
        echo '<br ><br >NEW Password: ';
        echo '<input name="password" type="password" id="password" value="' . $password . '">';
        echo '<br >Re-Enter Password: ';
        echo '<input name="password2" type="password" id="password2" value="' . $password . '">';
        echo '<input name="command" type="hidden" id="command" value="ChangePW">';
        echo '<input name="user_email" type="hidden" id="user_email" value="' . $user_email . '">';
        echo '<input name="key" type="hidden" value="' . password_hash($keys[0], PASSWORD_DEFAULT) . '">';
        echo '<br ><br ><input type="submit" name="Submit" value="Click Here to Create NEW Password"></td>';
        echo '<br><br><a href="' . $CONFIG['default_content'] . '">Cancel and return to home page</a>';
        echo '</form>';

        $reset_log[$email]['time'] = time();
        if (isset($reset_log[$email]['count'])) {
            $reset_log[$email]['count'] = $reset_log[$email]['count'] * 2;
        } else {
            $reset_log[$email]['count'] = 1;
        }
        file_put_contents($reset_file, serialize($reset_log));
    }
    echo '</center>';
}

function create_account($username, $password, $user_email)
{
    global $CONFIG, $config_dir, $keys, $user_email, $mail_log, $email_registry;

    if ($CONFIG['verify_email'] == true) {
        include($config_dir . '/phpmailer.inc.php');
        if (class_exists('PHPMailer')) {
            $mail = new PHPMailer();
        } else {
            $mail = new PHPMailer\PHPMailer\PHPMailer();
        }
    }

    echo '<center>';
    echo 'Create account: ' . secure_input($_POST['username'], 'html') . '<br><br >';
    /* Generate email */
    # only check for no verification if the field has been populated
    if (!empty($CONFIG['no_verify'])) {
        $no_verify = explode(' ', $CONFIG['no_verify']);
        foreach ($no_verify as $no) {
            if (strlen($_SERVER['HTTP_HOST']) - strlen($no) === strrpos($_SERVER['HTTP_HOST'], $no)) {
                $CONFIG['verify_email'] = false;
            }
        }
    }
    if ($CONFIG['verify_email']) {
        # Log email address attempts to avoid abuse
        if (file_exists($email_registry)) {
            $tried_email = secure_unserialize($email_registry);
        }
        $tried_email[$user_email]['time'] = time();
        secure_serialize_file($email_registry, $tried_email, false);

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
        $mail->Password = $mailer['password'];;
        $mail->SMTPSecure = 'tls';

        $mail->setFrom($mail_user . '@' . $mail_domain, $mail_name);
        $mail->addAddress($user_email);

        $mail->Subject = "Confirmation code for " . gethostname();

        if (isset($mail_custom_header)) {
            foreach ($mail_custom_header as $key => $value) {
                $mail->addCustomHeader($key, $value);
            }
        }

        $mycode = create_code($username);
        $msg = "A request to create an account on " . gethostname();
        $msg .= " has been made using " . $user_email . ".\n\n";
        $msg .= "If you did not request this, please ignore and the request will fail.\n\n";
        $msg .= "This is your account creation code: " . $mycode . "\n\n";
        $msg .= "Note: replies to this email address are checked daily.";
        $mail->Body = wordwrap($msg, 70);

        if (!$mail->send()) {
            file_put_contents($mail_log, "\n" . format_log_date() . ' FAILED to send mail from: ' . $mail_user . '@' . $mail_domain . ' to: ' . $user_email . 'Error: ' . $mail->ErrorInfo, FILE_APPEND);
            echo 'The message could not be sent.';
            echo '<p>Error: ' . htmlentities($mail->ErrorInfo);
            echo '<br><br><a href="' . $CONFIG['default_content'] . '">Cancel and return to home page</a>';
            exit(1);
        } else {
            file_put_contents($mail_log, "\n" . format_log_date() . ' SENT mail from: ' . $mail_user . '@' . $mail_domain . ' to: ' . $user_email, FILE_APPEND);
            echo 'An email has been sent to ' . $user_email . '<br >';
            echo 'Please enter the code from the email below:<br >';
        }
    }
    echo '<form name="create1" method="post" action="'.$file_register.'">';
    if ($CONFIG['verify_email'] == true) {
        echo '<input name="code" type="text" id="code">&nbsp;';
    }
    echo '<input name="username" type="hidden" id="username" value="' . $username . '">';
    echo '<input name="password" type="hidden" id="password" value="' . $password . '">';
    echo '<input name="command" type="hidden" id="command" value="CreateNew">';
    echo '<input name="user_email" type="hidden" id="user_email" value="' . $user_email . '">';
    echo '<input name="key" type="hidden" value="' . password_hash($keys[0], PASSWORD_DEFAULT) . '">';
    echo '<input type="submit" name="Submit" value="Click Here to Create"></td>';
    echo '<br><br><a href="' . $CONFIG['default_content'] . '">Cancel and return to home page</a>';
    echo '</center>';
}

function create_new($username, $password, $user_email)
{
    global $config_dir, $CONFIG, $OVERRIDES, $keys, $workpath, $keypath, $logfile;
    include $config_dir . '/synchronet.conf';
    if (isset($_POST['code'])) {
        $code = $_POST['code'];
    } else {
        $code = false;
    }
    $userFilename = $workpath . $username;
    $keyFilename = $keypath . $username;
    @mkdir($workpath . 'new/');
    $verified = 0;
    $no_verify = explode(' ', $CONFIG['no_verify']);
    foreach ($no_verify as $no) {
        if (strlen($_SERVER['HTTP_HOST']) - strlen($no) === strrpos($_SERVER['HTTP_HOST'], $no)) {
            $CONFIG['verify_email'] = false;
        }
    }

    if ($CONFIG['verify_email'] == true) {
        $saved_code = file_get_contents(sys_get_temp_dir() . "/" . $username);
        if ((strcmp(trim($code), trim($saved_code))) !== 0) {
            echo "Code does not match. Try again.<br >";
            echo '<form name="create1" method="post" action="'.$file_register.'">';
            echo '<input name="code" type="text" id="code">&nbsp;';
            echo '<input name="username" type="hidden" id="username" value="' . $username . '">';
            echo '<input name="password" type="hidden" id="password" value="' . $password . '">';
            echo '<input name="command" type="hidden" id="command" value="CreateNew">';
            echo '<input name="user_email" type="hidden" id="user_email" value="' . $user_email . '">';
            echo '<input type="submit" name="Submit" value="Click Here to Create"></td>';
            echo '<input name="key" type="hidden" value="' . password_hash($keys[0], PASSWORD_DEFAULT) . '">';
            echo '<br><br><a href="' . $CONFIG['default_content'] . '">Cancel and return to home page</a>';
            exit(2);
        }
        $verified = 1;
    }

    // Create NEW account
    if ($userFileHandle = @fopen($userFilename, 'w+')) {
        fwrite($userFileHandle, password_hash($password, PASSWORD_DEFAULT));
        fclose($userFileHandle);
        chmod($userFilename, 0666);
        file_put_contents($logfile, "\n" . logging_prefix() . " Created NEW Account for: " . $username, FILE_APPEND);
    }
    // Create synchronet account (this is very incomplete. Ignore this)
    if (isset($synch_create) && $synch_create == true) {
        putenv("SBBSCTRL=$synch_path/ctrl");
        $result = shell_exec("$synch_path/exec/makeuser $username -P $password");
    }
    $newkey = make_key($username);
    if ($userFileHandle = @fopen($keyFilename, 'w+')) {
        fwrite($userFileHandle, 'encryptionkey:' . $newkey . "\r\n");
        fwrite($userFileHandle, 'email:' . $user_email . "\r\n");
        if ($verified == 1) {
            fwrite($userFileHandle, "email_verified:true\r\n");
        }

        // Save creation date and restrict rate_limit for new users if configured
        fwrite($userFileHandle, 'created:' . time() . "\r\n");
        fwrite($userFileHandle, "new_account:true\r\n");
        if (isset($OVERRIDES['new_users_rate_limit']) && $OVERRIDES['new_users_rate_limit'] > 0) {
            fwrite($userFileHandle, 'rate_limit:' . $OVERRIDES['new_users_rate_limit'] . "\r\n");
        }

        fclose($userFileHandle);
        chmod($userFilename, 0666);
    }
    if (file_exists(sys_get_temp_dir() . "/" . $username)) {
        unlink(sys_get_temp_dir() . "/" . $username);
    }
    echo '<center>';
    echo "User: " . $username . " Created<br>";
    if (isset($OVERRIDES['new_account_life'])) {
        echo "<br>Account Posting Limit per Hour<br>";
        echo " will be limited for the first<br>";
        echo $OVERRIDES['new_account_life'] . ' hour(s) after account creation<br>';
    }
    echo '<br ><a href="' . $CONFIG['default_content'] . '">Back</a>';
    echo '</center>';

    $mail_subject = '[' . gethostname() . '] New User Registration ';
    $mail_body = 'New user registration on ' . gethostname() . "\n\nUsername: " . $username . "\n\nEmail: " . $user_email;
    if(isset($OVERRIDES['send_admin_registration_email']) && $OVERRIDES['send_admin_registration_email'] == true) {
        send_internet_email($mail_subject, $mail_body);
    } else {
        send_admin_message('admin', 'admin', $mail_subject, $mail_body . "\n");
    }

}

function make_key($username)
{
    $key = openssl_random_pseudo_bytes(44);
    return base64_encode($key);
}

function create_code($username)
{
    $permitted_chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $code = substr(str_shuffle($permitted_chars), 0, 16);
    $userfile = sys_get_temp_dir() . "/" . $username;
    file_put_contents($userfile, $code);
    return $code;
}

function generateImage($text, $file)
{
    $im = @imagecreate(74, 25) or die("Cannot Initialize new GD image stream");
    $background_color = imagecolorallocate($im, 200, 200, 200);
    $text_color = imagecolorallocate($im, 0, 0, 0);
    imagestring($im, 5, 5, 5, $text, $text_color);
    imagepng($im, $file);
    imagedestroy($im);
}

function getIndex($alphabet, $letter)
{
    for ($i = 0; $i < count($alphabet); $i++) {
        $l = $alphabet[$i];
        if ($l === $letter)
            return $i;
    }
}

function getExpressionResult($code)
{
    global $alphabet, $alphabetsForNumbers;
    $userAlphabetIndex = getIndex($alphabet, substr($code, 0, 1));
    $number1 = (int) getIndex($alphabetsForNumbers[$userAlphabetIndex], substr($code, 1, 1));
    $number2 = (int) getIndex($alphabetsForNumbers[$userAlphabetIndex], substr($code, 2, 1));
    return $number1 + $number2;
}

function prepareCaptcha($captchaImage)
{
    global $alphabet, $alphabetsForNumbers;
    // generating expression
    $expression = (object) array(
        "n1" => rand(0, 9),
        "n2" => rand(0, 9)
    );
    generateImage($expression->n1 . ' + ' . $expression->n2 . ' =', $captchaImage);

    $usedAlphabet = rand(0, 9);
    $code = $alphabet[$usedAlphabet] . $alphabetsForNumbers[$usedAlphabet][$expression->n1] . $alphabetsForNumbers[$usedAlphabet][$expression->n2];
    return ($code);
}
