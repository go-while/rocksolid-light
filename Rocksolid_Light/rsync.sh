
SRV=root@dns2.usenet-server.com

# pages
rsync -va --progress pages/ $SRV:/var/www/html/pages/ &

# common/
rsync -va --progress common/head.inc common/header.php $SRV:/var/www/html/common/ &

# rocksolid/lib
rsync -va --progress rocksolid/lib/head.inc $SRV:/var/www/html/rocksolid/lib/ &

# rocksolid/file
rsync -va --progress rocksolid/index.php  $SRV:/var/www/html/rocksolid/ &

# ./file
rsync -va --progress index.php  $SRV:/var/www/html/ &

# cron
rsync -va --progress rslight/scripts/cron.php rslight/scripts/send.php \
	$SRV:/etc/rslight/scripts/ &

# rslight/inc
rsync -va --progress rslight/inc/ $SRV:/etc/rslight/inc/ &

# pages
#rsync -va --progress pages/ $SRV:/var/www/html/pages/
rsync -va --progress rocksolid/lib/config.inc.php root@dns2.usenet-server.com:/var/www/html/rocksolid/lib/
rsync -va --progress common/config.inc.php root@dns2.usenet-server.com:/var/www/html/common/
