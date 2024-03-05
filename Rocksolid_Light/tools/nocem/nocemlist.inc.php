<?php
/*
 * First, configure this file to match your system and needs.
 *
 * Copy messages to add to NoCeM list to $workpath/incoming
 * One message per file.
 *
 * Then run nocemlist.php to create nocem.out and header.out files
 * You may view these files before sending if you wish to confirm
 * all is working properly.
 *
 * Then run nocempost.sh to send NoCeM message to news server
 * 
 * NOTE: If nocempost.sh does not exist, it will be created when
 * you run nocemlist.php
*/

// Where these scripts reside and messages are created: (end with '/')
// You must have write access to this directory
$workpath = "/home/user/nocem/";

$domain = "your_domain";
$organization = "your_organization";
$from = "from_address <from@example.com>";
$from_email = "from@example.com";
$contact = "your_email_address";

$gpglocaluser = "XXXXXXXX";
$nntpserver = "news.example.com";
$nntpuser = "nntpusername";
$nntppassword = "nntppassword";

// Your gpg signing key:
$signing_key = "XXXX XXXX XXXX XXXX XXXX  XXXX XXXX XXXX XXXX XXXX";
// URL to view/download key:
$key_location = "https://<key_url>";

// Comma separated list of newsgroups to send this message:
$spamgroup = "alt.test,misc.test";

// The hierarchies where these nocem may apply.
// Example: de.* or ALL
$hierarchies = "the alt.* and de.* hierarchies";
// $hierarchies = "ALL hierarchies";

// Add group to Followup-To header. Leave blank for no header
$followup_to = "news.admin.net-abuse.usenet";

// EDIT THE BELOW LINES to reflect your specific needs
// NOTE: You may comment out any lines below you do not want

// Where to find your statement about how messages get listed
$link_to_statement = "https://www.example.com/nocem/my_statement.html";

// Statement about the scope of your NoCeM messages:
$scope = 'The scope of these messages is '. $hierarchies;

// Statement about where to find details of your nocem:
$statement = "You may find information about how messages are listed here:\n" . $link_to_statement;


/* ***** Please do not change anything below this line ***** */

$spamdir = $workpath."incoming";
$nocem = $workpath."nocem.out";
$headerdat = $workpath."header.out";

/* This creates nocempost.sh */

$nocempost = "#!/bin/bash\n\n";
$nocempost .= "gpg2 --local-user $gpglocaluser --clearsign -a nocem.out\n\n";

$nocempost .= "newsserver=$nntpserver\n";
$nocempost .= 'rpost $newsserver '."-U $nntpuser -P $nntppassword <<%end\n";
$nocempost .= '$(<header.out)'."\n\n";

$nocempost .= '$(<nocem.out.asc)'."\n\n";

$nocempost .= '%end';

// If config is newer than nocempost.sh, trigger to recreate
$nocempost_filename = $workpath . '/nocempost.sh';

$nocemconfig_mtime = filemtime($workpath . '/nocemlist.inc.php');
if(file_exists($nocempost_filename)) {
    $nocempost_mtime = filemtime($nocempost_filename);
} else {
    $nocempost_mtime = 0;
}

if($nocemconfig_mtime > $nocempost_mtime) {
    $create_post = true;
} else {
    $create_post = false;
}
