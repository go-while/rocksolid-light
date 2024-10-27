<?php
echo '<div class="tail_footer">';
$pubkeyfile = '../pubkey/server_pubkey.txt';
if(is_file($pubkeyfile)) {
    echo '<div class="tail_server_pubkey_txt">';
    echo '<a href="../pubkey/server_pubkey.txt" target=_blank>server_pubkey.txt</a>';
    echo '<br>';
    echo '</div>';
}
echo '<div class="tail_links_text">';
echo '<a href="https://gitlab.com/rslight-public/rocksolid-light" target=_blank><img src="/common/images/footer.png" alt="logo"></a><br>';
echo '<i>rocksolid light</i> '.$rslight_version;
echo '<br>';
echo '<a href="https://gitlab.com/rslight-public/rocksolid-light" target=_blank>clearnet</a>';
echo '&nbsp;';
echo '<a href="http://git.fwfwqtpi2ofmehzdxe3e2htqfmhwfciwivpnsztv7dvpuamhr72ktlqd.onion/novabbs/rocksolid-light" target=_blank>tor</a>';
echo '</div>';
echo '</div>';