<?php
define( 'MEDIAWIKI', true );
require ('LocalSettings.php');

$time = time();

setCookie('AntiSpamTime', $time);
setCookie('AntiSpamHash', sha1($time.$wgAntiSpamHash));

header("Cache-Control: no-cache, must-revalidate");
header('Content-type: image/png');
$im = imagecreatetruecolor(1, 1);
imagepng($im);
imagedestroy($im);
exit();

?>