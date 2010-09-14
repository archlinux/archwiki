<?php

$wgExtensionCredits['other'][] = array(
	'name' => 'FunnyQuestion',
	'version' => '1.0',
	'description' => 'Challenge-response authentication',
	'author' => 'Pierre Schmitz',
	'url' => 'https://www.archlinux.de'
);

if ($wgGroupPermissions['*']['edit']) {
	$wgHooks['EditPage::showEditForm:fields'][] = 'FunnyQuestion::addFunnyQuestionToEditPage';
	$wgHooks['EditFilter'][] = 'FunnyQuestion::checkFunnyQuestionOnEditPage';
}

if ($wgGroupPermissions['*']['createaccount'] && (empty($wgAuth) || $wgAuth->canCreateAccounts())) {
	$wgHooks['UserCreateForm'][] = 'FunnyQuestion::addFunnyQuestionToUserCreateForm';
	$wgHooks['AbortNewAccount'][] = 'FunnyQuestion::checkFunnyQuestionOnAbortNewAccount';
}


class FunnyQuestion {

private static $defaultQuestion = array(
	"What is the Ultimate Answer to the Ultimate Question of Life, The Universe, and Everything?" => "42");

private static function normalizeAnswer($answer) {
	return preg_replace('/[^a-z0-9]/', '', strtolower($answer));
}

private static function getFunnyQuestion() {
	global $IP, $wgFunnyQuestionHash, $wgFunnyQuestions;

	!isset($wgFunnyQuestions) && $wgFunnyQuestions = self::$defaultQuestion;
	!isset($wgFunnyQuestionHash) && $wgFunnyQuestionHash = $IP;
	$question = array_rand($wgFunnyQuestions);
	$answer = self::normalizeAnswer($wgFunnyQuestions[$question]);
	$time = time();
	# make sure the user is not able to tell us the question to answer
	$hash = sha1($time.$question.$answer.$wgFunnyQuestionHash);

	return '<div>
			<label for="FunnyAnswerField"><strong>'.$question.'</strong></label>
			<input id="FunnyAnswerField" type="text" name="FunnyAnswer" size="'.strlen($answer).'" value="" />
			<input type="hidden" name="FunnyQuestionTime" value="'.$time.'" />
			<input type="hidden" name="FunnyQuestionHash" value="'.$hash.'" />
		</div>';
}

private static function checkFunnyQuestion() {
	global $IP, $wgFunnyQuestionHash, $wgFunnyQuestions, $wgFunnyQuestionTimeout, $wgFunnyQuestionWait;

	# set some sane defaults
	# can be overridden in LocalSettings.php
	!isset($wgFunnyQuestions) && $wgFunnyQuestions = self::$defaultQuestion;
	!isset($wgFunnyQuestionHash) && $wgFunnyQuestionHash = $IP;
	!isset($wgFunnyQuestionTimeout) && $wgFunnyQuestionTimeout = 3600;
	!isset($wgFunnyQuestionWait) && $wgFunnyQuestionWait = 2;

	if (!empty($_POST['FunnyQuestionTime'])
	&& !empty($_POST['FunnyQuestionHash'])
	&& !empty($_POST['FunnyAnswer'])) {
		$now = time();
		$time = $_POST['FunnyQuestionTime'];
		$hash = $_POST['FunnyQuestionHash'];
		$answer = self::normalizeAnswer($_POST['FunnyAnswer']);
	} else {
		return false;
	}

	if ($now - $time > $wgFunnyQuestionTimeout) {
		return false;
	} elseif ($now - $time < $wgFunnyQuestionWait) {
		return false;
	}

	foreach (array_keys($wgFunnyQuestions) as $question) {
		if ($hash == sha1($time.$question.$answer.$wgFunnyQuestionHash)) {
			return true;
		}
	}

	return false;
}


public static function addFunnyQuestionToEditPage($editpage, $output) {
	global $wgUser;

	if (!$wgUser->isLoggedIn()) {
		$editpage->editFormTextAfterWarn .= self::getFunnyQuestion();
	}
	return true;
}

public static function checkFunnyQuestionOnEditPage($editpage, $text, $section, $error) {
	global $wgUser;

	if (!$wgUser->isLoggedIn() && !self::checkFunnyQuestion()) {
		$error = '<div class="errorbox">Your answer was wrong!</div><br clear="all" />';
	}
	return true;
}


public static function addFunnyQuestionToUserLoginForm($template) {
	$template->set('header', self::getFunnyQuestion());
	return true;
}

public static function checkFunnyQuestionOnAbortLogin($user, $password, $retval) {
	# LoginForm::ABBORT is not yet supported by MediaWiki
	$retval = LoginForm::ILLEGAL;
	return self::checkFunnyQuestion();
}


public static function addFunnyQuestionToUserCreateForm($template) {
	$template->set('header', self::getFunnyQuestion());
	return true;
}

public static function checkFunnyQuestionOnAbortNewAccount($user, $message) {
	if (!self::checkFunnyQuestion()) {
		$message = '<div class="errorbox">Your answer was wrong!</div><br clear="all" />';
		return false;
	} else {
		return true;
	}
}

}

?>
