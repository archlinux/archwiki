<?php

if ( defined( 'MEDIAWIKI' ) ) {

global $wgHooks;
$wgHooks['ArticleSave'][] = 'checkAntiSpamHash';


function checkAntiSpamHash()
	{
	global $wgAntiSpamHash, $wgAntiSpamTimeout, $wgAntiSpamWait;

	$now = time();

	if (!empty($_COOKIE['AntiSpamTime']) && !empty($_COOKIE['AntiSpamHash']))
		{
		$time = intval($_COOKIE['AntiSpamTime']);
		$hash = $_COOKIE['AntiSpamHash'];

		if ($hash != sha1($time.$wgAntiSpamHash))
			{
			return false;
			}

		if ($now - $time > $wgAntiSpamTimeout)
			{
			return false;
			}
		elseif ($now - $time < $wgAntiSpamWait)
			{
			return false;
			}
		}
	else
		{
		return false;
		}

	return true;
	}

} # End invocation guard
?>