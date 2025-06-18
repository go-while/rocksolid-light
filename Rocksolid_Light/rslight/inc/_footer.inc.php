<?php
/**
 * RockSolid Light - HTML Footer Include
 * Extracted from pages/pages.php for simple include usage
 */

// Prevent direct access
if (!defined('RSLIGHT_CONFIG_LOADED')) {
    die('Direct access not allowed. Include via config.inc.php');
}

// Close content div
echo '</div>'; // Close scroll div from header
$sessions_data = file_get_contents($spooldir . '/sessions.dat');
echo '<h1 class="np_thread_headline">' . $sessions_data . '</h1>';

echo '<div class="tail_footer">';
$pubkeyfile = '../pubkey/server_pubkey.txt';
if(is_file($pubkeyfile)) {
echo '<div class="tail_server_pubkey_txt">';
echo '  <a href="../pubkey/server_pubkey.txt" target=_blank>server_pubkey.txt</a>';
echo '  <br>';
echo '</div>';
} else {
echo '<div class="tail_server_pubkey_txt">';
echo '  ../pubkey/server_pubkey.txt missing-';
echo '  <br>';
echo '</div>';
}
echo '<div class="tail_links_text">';
echo '  <a href="https://github.com/go-while/rocksolid-light" target=_blank><img src="/common/images/footer.png" alt="logo">github</a><br>';
echo '  <i>rocksolid light</i> '.$rslight_version;
echo '  <br>';
echo '  <a href="https://gitlab.com/rslight-public/rocksolid-light" target=_blank>gitlab</a>';
echo '  &nbsp;';
echo '  <a href="http://git.fwfwqtpi2ofmehzdxe3e2htqfmhwfciwivpnsztv7dvpuamhr72ktlqd.onion/novabbs/rocksolid-light" target=_blank>tor</a>';
echo ' </div>';
// Close tail links text div
echo '<p><small>&copy; ' . date('Y') . ' RockSolid Light</small></p>';
echo '</div>';

echo '</div></body></html>';

//echo "[rslight/inc/_footer.inc.php: Footer included successfully]<br>\n";
?>
