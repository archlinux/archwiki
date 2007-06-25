<?php

require ('includes/WebStart.php');

$time = time();

setCookie('AntiSpamTime', $time);
setCookie('AntiSpamHash', sha1($time.$wgAntiSpamHash));

header('HTTP/1.1 200 OK');
header("Cache-Control: no-cache, must-revalidate");
header('Content-Type: image/png');
header('Content-Length: 69');

/** transparent png (1px*1px) */
echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC');

exit;

?>