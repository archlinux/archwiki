<?php

if ( defined( 'MEDIAWIKI' ) ) {

global $wgHooks;
$wgHooks['ArticleSave'][] = 'checkAntiSpamHash';

$wgExtensionCredits['other'][] = array(
    'name' => 'FunnyDot',
    'description' => 'Schutz vor Spam-Bots',
    'author' => 'Pierre Schmitz',
    'url' => 'http://www.laber-land.de',
);

function hexVal($in)
	{
	$result = preg_replace('/[^0-9a-fA-F]/', '', $in);
	return (empty($result) ? 0 : $result);
	}

function checkAntiSpamHash()
	{
	global $wgAntiSpamHash, $wgAntiSpamTimeout, $wgAntiSpamWait;

	$now = time();

	if (!empty($_COOKIE['AntiSpamTime']) && !empty($_COOKIE['AntiSpamHash']))
		{
		$time = intval($_COOKIE['AntiSpamTime']);
		$hash = hexVal($_COOKIE['AntiSpamHash']);

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