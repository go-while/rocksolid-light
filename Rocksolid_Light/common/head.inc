<?php

$backtrace = debug_backtrace();
$parent = isset($backtrace[0]['file']) ? $backtrace[0]['file'] : 'Direct execution';
echo "common/head.inc included by: " . basename($parent) . "<br>\n";


echo '<!DOCTYPE html>';
echo '<html>';
echo '<head>';
echo '<title>'.htmlspecialchars($title).'</title>';
echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';

if(file_exists($config_dir.'/googleanalytics.conf')) {
  include $config_dir.'/googleanalytics.conf';
}

if (file_exists(__DIR__ . '/mods/header.php')) {
  include __DIR__ . "/mods/header.php";
} else {
  include __DIR__ . "/header.php";
}
echo '<hr>';
