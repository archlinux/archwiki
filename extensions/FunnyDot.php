<?php

$wgHooks['EditPage::showEditForm:fields'][] = 'FunnyDot::addAntiSpamCheck';
$wgHooks['EditFilter'][] = 'FunnyDot::checkAntiSpamHash';

$wgExtensionCredits['other'][] = array(
    'name' => 'FunnyDot',
    'description' => 'Schutz vor Spam-Bots',
    'author' => 'Pierre Schmitz',
    'url' => 'http://www.archlinux.de',
);

class FunnyDot {

public static function addAntiSpamCheck($editpage, $outputpage)
	{
	global $wgAntiSpamHash, $wgUser;

	if (!$wgUser->isLoggedIn())
		{
		$outputpage->addHTML('<div style="background-image:url(FunnyDotImage.php);background-repeat:no-repeat;visibility:hidden;width:1px;height:1px;">&nbsp;</div>');

		$time = time();
		$hash = sha1($time.$wgAntiSpamHash);
		setCookie('AlternateAntiSpamTime', $time);
		setCookie('AlternateAntiSpamHashTail', substr($hash, 4));

		$outputpage->addHTML('<div style="display:none;"><label for="AlternateAntiSpamHashHeadField">Sicherheitscode bestätigen: <strong>'.substr($hash, 0, 4).'</strong></label>&nbsp;<input id="AlternateAntiSpamHashHeadField" type="text" name="AlternateAntiSpamHashHead" size="4" value="" /></div>');
		}

	return true;
	}

public static function checkAntiSpamHash($editpage, $text, $section, $error)
	{
	global $wgAntiSpamHash, $wgAntiSpamTimeout, $wgAntiSpamWait, $wgUser;

	if (!$wgUser->isLoggedIn())
		{
		if (!empty($_COOKIE['AntiSpamTime']) && !empty($_COOKIE['AntiSpamHash']))
			{
			$time = $_COOKIE['AntiSpamTime'];
			$hash = $_COOKIE['AntiSpamHash'];
			}
		elseif (!empty($_COOKIE['AlternateAntiSpamTime']) && !empty($_COOKIE['AlternateAntiSpamHashTail']) && !empty($_POST['AlternateAntiSpamHashHead']))
			{
			$time = $_COOKIE['AlternateAntiSpamTime'];
			$hash = $_POST['AlternateAntiSpamHashHead'].$_COOKIE['AlternateAntiSpamHashTail'];
			}
		else
			{
			sleep($wgAntiSpamWait);
			$error = '<div class="mw-warning error">Ungültige Formulardaten empfangen. Stelle sicher, daß Cookies für diese Domain angenommen werden.</div>';
			return true;
			}

		$now = time();

		if ($hash != sha1($time.$wgAntiSpamHash))
			{
			sleep($wgAntiSpamWait);
			$error = '<div class="mw-warning error">Fehlerhafte Formulardaten empfangen. Überprüfe den Sicherheitscode!</div>';
			}
		elseif ($now - $time > $wgAntiSpamTimeout)
			{
			$error = '<div class="mw-warning error">Deine Zeit ist abgelaufen. Schicke das Formular bitte erneut ab, und zwar innherlab der nächsten '.$wgAntiSpamTimeout.' Sekunden.</div>';
			}
		elseif ($now - $time < $wgAntiSpamWait)
			{
			sleep($wgAntiSpamWait);
			$error = '<div class="mw-warning error">Du warst zu schnell. Schicke das Formular bitte erneut ab. Laße Dir diesmal mindestens '.$wgAntiSpamWait.' Sekunden Zeit.</div>';
			}
		}

	return true;
	}

}

?>