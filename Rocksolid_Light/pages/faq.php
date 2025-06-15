<?php

/*
 * CHANGE THE LINE BELOW TO 'installed = true once you have edited this filter
 */

// Set this to your administrative email address
$admin = "admin@example.com"; // TODO add to config.inc.php

require __DIR__ . "/../rocksolid/lib/config.inc.php";
require __DIR__ . "/../rocksolid/newsportal.php";
require __DIR__ . "/../rocksolid/logging_control.php";
require __DIR__ . "/../rocksolid/lib/security.inc.php";

// Add security headers
add_security_headers();

$title = "Privacy and FAQ";

// Use new router-based header system instead of head.inc
if (function_exists('rslight_render_complete_header')) {
    rslight_render_complete_header($title, 'faq');
} else {
    // Fallback to old system if router not loaded
    include "head.inc";
    echo '<meta http-equiv="content-type" content="text/html; charset=UTF-8">';
    echo '<title>Privacy and FAQ</title>';
    echo '</head><body>';
}

echo '<center>';

// Privacy
  echo '<h4 style="faq_titles">Privacy:</h4>';
  echo '<p class="faq_text">';
  echo 'This server does not place your ip address in the headers of messages.<br>';
  echo 'There is a header specific to each user that is obfuscated to allow others to block, but is not personally identifiable.<br >';
  echo 'Passwords are never stored on the server but email address is stored.<br >';
  echo 'We do not share any user data with anyone outside of our servers.<br >';
  echo '<br >';
  echo '</p>';

// Abuse
  echo '<h4 style="faq_titles">Abuse:</h4>';
  echo '<p class="faq_text">';
  echo 'Spamming, trolling, forging etc. will all be addressed once the admin becomes aware this is taking place.<br >';
  echo 'You may notify ' . $admin . ' with issues.<br >';
  echo 'Forging complaints must be sent by the one who is being forged.<br >';
  echo '<br >';

  echo 'Articles or users will not be removed for political/social/etc. opinions you (or I) don’t agree with.<br >';
  echo 'Please use the block filter for articles or users you do not wish to see.<br >';
  echo 'Articles considered illegal in the jurisdiction of the admin may be removed for legal reasons.<br >';
  echo '<br >';
  echo '</p>';

// Posting Restrictions
  echo '<h4 style="faq_titles">Posting Restrictions:</h4>';
  echo '<p class="faq_text">';
  echo 'Posting requires an account. No account is required for reading articles.<br >';
  echo 'Limits are placed on number of groups in crossposts, and all articles are filtered through a spam filter.<br >';

  echo 'Limits on the number of posts per hour may be imposed. If you reach this limit you should be notified how long to wait.<br >';
  echo 'Other restrictions may also be imposed including common crossposting abuse.<br >';
  echo '<br >';
  echo '</p>';

// Filtering
  echo '<h4 style="faq_titles">Filtering:</h4>';
  echo '<p class="faq_text">';
  echo 'Incoming messages are filtered by the backend NNTP server.<br >';
  echo 'This is meant to keep groups useable, and is NOT meant to censor speech.<br >';
  echo '</br >';
  echo '</p>';
  echo '<hr></br >';
  include "../spoolnews/tail.inc";

  echo '</body></html>';

