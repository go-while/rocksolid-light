<?php
// TODO attachement.php should be moved to requests.inc.php (which atm does not exist)

// Security headers for file downloads
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

$group=$_REQUEST["group"];
$id=$_REQUEST["id"];
$attachment=$_REQUEST["attachment"];
//echo "<h1>Attachment Page</h1>";

if (!isset($attachment)){
  $attachment=0;
}

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