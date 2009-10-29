<?php

$wgExtensionCredits['other'][] = array(
	'name' => 'FunnyDot',
	'version' => '2.1',
	'description' => 'Automated CAPTCHA',
	'author' => 'Pierre Schmitz',
	'url' => 'https://www.archlinux.de'
);

if ($wgGroupPermissions['*']['edit']) {
	$wgHooks['EditPage::showEditForm:fields'][] = 'FunnyDot::addFunnyDotToEditPage';
	$wgHooks['EditFilter'][] = 'FunnyDot::checkFunnyDotOnEditPage';
}

if (empty($wgAuth)) {
	$wgHooks['UserLoginForm'][] = 'FunnyDot::addFunnyDotToUserLoginForm';
	$wgHooks['AbortLogin'][] = 'FunnyDot::checkFunnyDotOnAbortLogin';
}

if ($wgGroupPermissions['*']['createaccount'] && (empty($wgAuth) || $wgAuth->canCreateAccounts())) {
	$wgHooks['UserCreateForm'][] = 'FunnyDot::addFunnyDotToUserCreateForm';
	$wgHooks['AbortNewAccount'][] = 'FunnyDot::checkFunnyDotOnAbortNewAccount';
}

$wgSpecialPages['FunnyDotImage'] = 'SpecialFunnyDotImage';


class FunnyDot {

private static function getFunnyDot() {
	global $wgFunnyDotHash, $wgScript;

	!isset($wgFunnyDotHash) && $wgFunnyDotHash = '';
	$time = time();
	$hash = substr(sha1($time.$wgFunnyDotHash), 0, 4);

	return '<div style="background-image:url('.$wgScript.'?title=Special:FunnyDotImage&amp;FunnyDotTime='.$time.');visibility:hidden;position:absolute;z-index:-1">
			<label for="FunnyDotHashField">Please type in the following code: <strong>'.$hash.'</strong></label>
			<input id="FunnyDotHashField" type="text" name="FunnyDotHash" size="4" value="" />
			<input type="hidden" name="FunnyDotTime" value="'.$time.'" />
		</div>';
}

private static function checkFunnyDot() {
	global $wgFunnyDotHash, $wgFunnyDotTimeout, $wgFunnyDotWait;

	# set some sane defaults
	# can be overridden in LocalSettings.php
	!isset($wgFunnyDotHash) && $wgFunnyDotHash = '';
	!isset($wgFunnyDotTimeout) && $wgFunnyDotTimeout = 3600;
	!isset($wgFunnyDotWait) && $wgFunnyDotWait = 2;

	if (!empty($_POST['FunnyDotTime']) && (!empty($_COOKIE['FunnyDotHash']) || !empty($_POST['FunnyDotHash']))) {
		$now = time();
		$time = $_POST['FunnyDotTime'];
		$hash = !empty($_POST['FunnyDotHash']) ? $_POST['FunnyDotHash'] : $_COOKIE['FunnyDotHash'];
	} else {
		return false;
	}

	if ($hash != substr(sha1($time.$wgFunnyDotHash), 0, 4)) {
		return false;
	} elseif ($now - $time > $wgFunnyDotTimeout) {
		return false;
	} elseif ($now - $time < $wgFunnyDotWait) {
		return false;
	} else {
		return true;
	}
}


public static function addFunnyDotToEditPage($editpage, $output) {
	global $wgUser;

	if (!$wgUser->isLoggedIn()) {
		$editpage->editFormTextAfterWarn .= self::getFunnyDot();
	}
	return true;
}

public static function checkFunnyDotOnEditPage($editpage, $text, $section, $error) {
	global $wgUser;

	if (!$wgUser->isLoggedIn() && !self::checkFunnyDot()) {
		$error = '<div class="errorbox">Please type in the correct code!</div><br clear="all" />';
	}
	return true;
}


public static function addFunnyDotToUserLoginForm($template) {
	$template->set('header', self::getFunnyDot());
	return true;
}

public static function checkFunnyDotOnAbortLogin($user, $password, $retval) {
	# LoginForm::ABBORT is not yet supported by MediaWiki
	$retval = LoginForm::ILLEGAL;
	return self::checkFunnyDot();
}


public static function addFunnyDotToUserCreateForm($template) {
	$template->set('header', self::getFunnyDot());
	return true;
}

public static function checkFunnyDotOnAbortNewAccount($user, $message) {
	if (!self::checkFunnyDot()) {
		$message = '<div class="errorbox">Please type in the correct code!</div><br clear="all" />';
		return false;
	} else {
		return true;
	}
}

}


class SpecialFunnyDotImage extends UnlistedSpecialPage {

function __construct() {
	parent::__construct('FunnyDotImage');
}

function execute($par) {
	global $wgFunnyDotHash, $wgOut;

	# I will handle the output myself
	$wgOut->disable();

	!isset($wgFunnyDotHash) && $wgFunnyDotHash = '';

	if (!empty($_GET['FunnyDotTime'])) {
		setCookie('FunnyDotHash', substr(sha1($_GET['FunnyDotTime'].$wgFunnyDotHash), 0, 4), 0, '/', null, isset($_SERVER['HTTPS']), true);
	}

	header('HTTP/1.1 200 OK');
	header("Cache-Control: no-cache, must-revalidate, no-store");
	header('Content-Type: image/png');
	header('Content-Length: 135');

	# transparent png (1px*1px)
	echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAAXNSR0IArs4c6QAAAAlwSFlzAAALEwAACxMBAJqcGAAAAAd0SU1FB9gLFxMRGNZyzLoAAAACYktHRAD/h4/MvwAAAAtJREFUCB1j+M8AAAIBAQDFXxteAAAAAElFTkSuQmCC');
}

}

?>
