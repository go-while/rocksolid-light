<?php
echo '<center>';
$pubkeyfile = '../pubkey/server_pubkey.txt';
if(is_file($pubkeyfile)) {
    echo '<font size="1.5em">';
    echo '<a href="../pubkey/server_pubkey.txt" target=_blank>server_pubkey.txt</a>';
    echo '<br />';
    echo '</font>';
}
echo '<font size="1em">';
echo '<a href="https://github.com/novabbs/rocksolid-light" target=_blank><img src="/common/images/footer.png"></a><br />';
echo '<i>rocksolid light</i> '.$rslight_version;
echo '<br />';
echo '<a href="https://gitlab.com/novabbs/rocksolid-light" target=_blank>clearnet</a>';
echo '<a href="http://rslight.i2p/getrslight" target=_blank>i2p</a>';
echo '<a href="http://git.fwfwqtpi2ofmehzdxe3e2htqfmhwfciwivpnsztv7dvpuamhr72ktlqd.onion/novabbs/rocksolid-light" target=_blank>tor</a>';
echo '</font>';
echo '</center>';
?>
