<?php
header("Expires: ".gmdate("D, d M Y H:i:s",time()+(3600*24))." GMT");
$group=$_REQUEST["group"];
$id=$_REQUEST["id"];
$attachment=$_REQUEST["attachment"];
include "lib/config.inc.php";
require("$file_newsportal");
require_once(__DIR__ . '/lib/security.inc.php');

// Add security headers
add_security_headers();
if (!isset($attachment))
  $attachment=0;
$message=message_read($id,$attachment,$group);
//print_r($message->header);
ob_clean();
if (!$message) {
  header ("HTTP/1.0 404 Not Found");
  echo "The Attachment doesn't exists";
} else {
  header("Content-Disposition: inline; filename=" . $message->header->content_type_name[$attachment]);
  header("Content-type: ".$message->header->content_type[$attachment]);
  message_show("",$id,$attachment,$message);
}