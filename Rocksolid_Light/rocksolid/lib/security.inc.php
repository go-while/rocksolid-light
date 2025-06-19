<?php
$backtrace = debug_backtrace();
$parent = isset($backtrace[0]['file']) ? $backtrace[0]['file'] : 'Direct execution';
echo "[rocksolid/lib/security.inc.php (STUB) included by: " . basename($parent) . "]<br>\n";
?>