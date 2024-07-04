<?php
include ("paths.inc.php");
chdir($spoolnews_path);
include "config.inc.php";
include "newsportal.php";

$processUser = posix_getpwuid(posix_geteuid());
if ($processUser['name'] != $CONFIG['webserver_user']) {
    echo "You are running as: " . $processUser['name'] . "\n";
    echo 'Please run this scripts as: ' . $CONFIG['webserver_user'] . "\n";
    exit();
}

$keyfile = $spooldir . '/keys.dat';
$keys = unserialize(file_get_contents($keyfile));
$email_registry = $spooldir . '/email_registry.dat';

if (! isset($argv[1])) {
    $argv[1] = "-help";
}
if ($argv[1][0] == '-') {
    switch ($argv[1]) {
        case "-version":
            echo 'Version ' . $rslight_version . "\n";
            break;
        case "-create":
            if (! isset($argv[2]) || ! isset($argv[3]) || ! isset($argv[4])) {
                echo "Usage: -create username password email\n";
                exit();
            }
            echo "Creating User: " . $argv[2] . "\n";
            create_new($argv[2], $argv[3], $argv[4]);
            break;
        case "-newpass":
            if (! isset($argv[2]) || ! isset($argv[3])) {
                echo "Usage: -newpass username password\n";
                exit();
            }
            change_user_password($argv[2], $argv[3]);
            break;
            
        case "-newemail":
            if (! isset($argv[2]) || ! isset($argv[3])) {
                echo "Usage: -newemail username password\n";
                exit();
            } else {
                change_user_email($argv[2], $argv[3]);
                echo "Email changed for: " . $argv[2] . "\n";
                echo "Email: " . $argv[3] . "\n";
            }
            break;
        case "-banuser":
            if (! isset($argv[2])) {
                echo "Usage: -banuser username\n";
                exit();
            } else {
                ban_user($argv[2]);
                echo "User is banned: " . $argv[2] . "\n";
                echo "To unban, remove from $config_dir/banned_users.conf\n";
            }
            break;
        case "-delete":
            if (! isset($argv[2])) {
                echo "Usage: -delete username\n";
                exit();
            }
            echo "Removing User: " . $argv[2] . "\n";
            $deleted_users = $config_dir . '/users/deleted/';
            $deleted_config = $config_dir . '/userconfig/deleted/';
            if (! is_dir($deleted_users)) {
                mkdir($deleted_users);
            }
            if (! is_dir($deleted_config)) {
                mkdir($deleted_config);
            }
            if (file_exists($config_dir . '/users/' . strtolower($argv[2]))) {
                rename($config_dir . '/users/' . strtolower($argv[2]), $deleted_users . strtolower($argv[2]));
                if (file_exists($config_dir . '/userconfig/' . strtolower($argv[2]))) {
                    rename($config_dir . '/userconfig/' . strtolower($argv[2]), $deleted_config . strtolower($argv[2]));
                }
                if (file_exists($config_dir . '/userconfig/' . strtolower($argv[2] . '.config'))) {
                    rename($config_dir . '/userconfig/' . strtolower($argv[2] . '.config'), $deleted_config . strtolower($argv[2]));
                }
            } else {
                echo "User: " . $argv[2] . " not found.\n";
            }
            break;
        default:
            echo "-help: This help page\n";
            echo "-version: Display version\n";
            echo "-create: Create user account '-create username password email'\n";
            echo "-newpass: Change user password '-newpass username newpassword'\n";
            echo "-newemail: Change user email '-newemail username emailaddress'\n";
            echo "           Email address will remain listed as 'verified'\n";
            echo "           Be sure to verify the address is correct\n";
            echo "-banuser: Disable ability for user to log in '-banuser username'\n";
            echo "          This doesn't block the site, just posting and other user features\n";
            echo "-delete: Delete user account '-delete username'\n";
            echo "         Be careful with this. You will not be asked to confirm\n";
            echo "         Account files will be placed in a dir named 'deleted'\n";
            break;
    }
    exit();
} else {
    exit();
}

function ban_user($username) {
    global $config_dir;
    $banfile = $config_dir . '/banned_users.conf';
    $username = strtolower($username);
    $userfile = $config_dir . '/users/' . $username;
    if(! file_exists($userfile)) {
        echo "User:" . $username . " Not Found\r\n";
        return;
    } else {
        $lines = file($banfile);
        foreach ($lines as $k => $v) {
            if (!trim($v)) {
                unset($lines[$k]);
            } else {
                if(trim($v) == $username) {
                    echo "User:" . $username . " already banned.\n";
                    return;
                }
            }
        }
        $lines[] = $username;
        file_put_contents($banfile, "\n" . implode($lines) . "\n");
    }
}

function change_user_email($username, $email) {
    global $config_dir;
    $username = strtolower($username);
    $userfile = $config_dir . '/users/' . $username;
    if(! file_exists($userfile)) {
        echo "User:" . $username . " Not Found\r\n";
        return;
    } else {
        set_user_config($username, 'email', $email);
    }
}

function change_user_password($username, $password) {
    global $config_dir;
    $username = strtolower($username);
    $userfile = $config_dir . '/users/' . $username;
    if(! file_exists($userfile)) {
        echo "User:" . $username . " Not Found\r\n";
        return;
    } else {
        file_put_contents($userfile, password_hash($password, PASSWORD_DEFAULT));
        echo "Password changed for: " . $username . "\n";
        echo "Password: " . $password . "\n";
    }
}

function create_new($username, $password, $user_email)
{
    global $config_dir;
    $workpath = $config_dir . "users/";
    $keypath = $config_dir . "userconfig/";
    $username = strtolower($username);
    $userFilename = $workpath . $username;
    $keyFilename = $keypath . $username;

    if (file_exists($userFilename)) {
        echo "User:" . $username . " Already Exists\r\n";
        exit();
    }

    if ($userFileHandle = @fopen($userFilename, 'w+')) {
        fwrite($userFileHandle, password_hash($password, PASSWORD_DEFAULT));
        fclose($userFileHandle);
        chmod($userFilename, 0666);
    }
    $newkey = make_key($username);
    if ($userFileHandle = @fopen($keyFilename, 'w+')) {
        fwrite($userFileHandle, 'encryptionkey:' . $newkey . "\r\n");
        fwrite($userFileHandle, 'email:' . $user_email . "\r\n");
        fwrite($userFileHandle, "email_verified:true\r\n");
        fclose($userFileHandle);
        chmod($userFilename, 0666);
    }
    echo "User: " . $username . " Created\r\n";
    echo "Password: " . $password . "\n";
    echo "Email: " . $user_email . "\n";
    exit(0);
}

function make_key($username)
{
    $key = openssl_random_pseudo_bytes(44);
    return base64_encode($key);
}

