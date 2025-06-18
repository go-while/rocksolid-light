<?php
define('CRON_CONTEXT', true);
$config_dir = '/etc/rslight';
$spooldir = '/var/spool/rslight';
$config_file = $config_dir . '/rslight.inc.php';
$CONFIG = include($config_file);
require_once('/etc/rslight/inc/functions.inc.php');

$group = 'rocksolid.shared.offtopic';
$findsection = get_section_by_group($group);
$config_name = basename(getcwd());

echo 'Group: ' . $group . PHP_EOL;
echo 'findsection: ' . ($findsection ? $findsection : 'NULL') . PHP_EOL;
echo 'config_name: ' . $config_name . PHP_EOL;
echo 'Match: ' . (trim($findsection) === $config_name ? 'YES' : 'NO') . PHP_EOL;
?>
