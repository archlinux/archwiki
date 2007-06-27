<?php

$wgHooks['ArticleSave'][] = 'FunnyDot::checkAntiSpamHash';

$wgExtensionCredits['other'][] = array(
    'name' => 'FunnyDot',
    'description' => 'Schutz vor Spam-Bots',
    'author' => 'Pierre Schmitz',
    'url' => 'http://www.archlinux.de',
);

class FunnyDot {

public static function checkAntiSpamHash()
	{
	global $wgAntiSpamHash, $wgAntiSpamTimeout, $wgAntiSpamWait;

	$now = time();

	if (!empty($_COOKIE['AntiSpamTime']) && !empty($_COOKIE['AntiSpamHash']))
		{
		$time = intval($_COOKIE['AntiSpamTime']);

		if ($_COOKIE['AntiSpamHash'] != sha1($time.$wgAntiSpamHash))
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

}

?>