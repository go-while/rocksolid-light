<?php
if(!defined('RSLIGHT_CONFIG_LOADED')) {
    die("Access denied.");
}

// TODO decrypt.php NEEDS TESTING
if(!file_exists($auth_inc) || !is_readable($auth_inc)) {
  die("Authentication include file not found or not readable: $auth_inc");
}
include_once($auth_inc);

// register parameters
$id=$_REQUEST["id"];
$group=$_REQUEST["group"];


$thread_show["replies"]=true;
$thread_show["lastdate"]=false;
$thread_show["threadsize"]=false;


$message=message_read($id,0,$group);
if (!$message) {
  header ("HTTP/1.0 404 Not Found");
  $subject=$title; // TODO $title unused or lost?
  $title.=' - Article not found'; // TODO $title unused or lost?
  if($ns!=false)
  nntp_close($ns);
} else {
  $subject=htmlspecialchars($message->header->subject);
  header("Last-Modified: ".date("r", $message->header->date));
  $title.= ' - '.$subject; // TODO $title unused or lost?
}

// has the user read-rights on this article?
if((function_exists("npreg_group_has_read_access") &&
    !npreg_group_has_read_access($group)) ||
    (function_exists("npreg_group_is_visible") &&
    !npreg_group_is_visible($group))) {
  die("access denied");
}
?>

<h1 class="np_article_headline"><?php echo htmlspecialchars(group_display_name($group)." / ".$subject) ?></h1>

<table cellpadding="0" cellspacing="0" width="100%" class="np_buttonbar"><tr>
<?php
  echo '<td class="np_button"><a class="np_button" href="'.
  $file_thread.'&group='.urlencode($group).'">'.$text_article["back_to_group"].'</a></td>';
  if ((!$CONFIG['readonly']) && ($message) &&
      (!function_exists("npreg_group_has_write_access") ||
             npreg_group_has_write_access($group))) {
                echo '<td class="np_button"> <a class="np_button" href="'.
                  $file_post.'?type=reply&id='.urlencode($id).
                  '&group='.urlencode($group).'">'.$text_article["button_answer"].
                '</a></td>';
    } else {
   if(function_exists(npreg_user_is_moderator) && npreg_user_is_moderator($group)) {
     echo '<td class="np_button"><a class="np_button" href="'.$file_cancel.'?type=reply&id='.urlencode($id).
          '&group='.urlencode($group).'">'.$text_article["button_cancel"].'</a></td>';
    }

?>
<td width="100%">&nbsp;</td></tr></table>

<?php
  if (!$message){
    // article not found
    echo $text_error["article_not_found"];
  } else {
    if($article_showthread) {
      $thread=thread_cache_load($group);
    }
  }  //echo "<br>";
  $ok = check_bbs_auth($_POST['decryptuser'], $_POST['decryptpass']);
  if ($ok === TRUE) {
	  $key = get_user_config($_POST['decryptuser'],'encryptionkey');
  	message_decrypt($key,$group,$id,0,$message);
  } else {
    echo "Failed to authenticate";
  }
//    if($article_showthread)
//      message_thread($message->header->id,$group,$thread);

  }
?>
