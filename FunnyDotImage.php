<?php

require ('includes/WebStart.php');

$time = time();

setCookie('AntiSpamTime', $time);
setCookie('AntiSpamHash', sha1($time.$wgAntiSpamHash));

$im = imagecreatetruecolor(1, 1);

ob_start();

header('HTTP/1.1 200 OK');
header("Cache-Control: no-cache, must-revalidate");
header('Content-Type: image/png');
header('Content-Length: '.ob_get_length());

imagepng($im);
imagedestroy($im);

while (ob_get_level() > 0)
	{
	ob_end_flush();
	}

exit;

?>