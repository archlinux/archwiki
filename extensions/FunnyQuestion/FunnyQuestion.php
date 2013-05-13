<?php

$wgExtensionCredits['other'][] = array(
	'name' => 'FunnyQuestion',
	'version' => '2.3',
	'description' => 'Challenge-response authentication',
	'author' => 'Pierre Schmitz',
	'url' => 'https://pierre-schmitz.com/'
);

global $wgFunnyQuestions;
$wgFunnyQuestions = array(
	'en' => array("What is the Ultimate Answer to the Ultimate Question of Life, The Universe, and Everything?" => "42"),
	'de' => array("Was ist die ultimaative Antwort nach dem Leben, dem Universum und dem ganzen Rest?" => "42")
);
$wgFunnyQuestionHash = '';
$wgFunnyQuestionTimeout = 3600;
$wgFunnyQuestionWait = 2;
$wgFunnyQuestionRemember = 3600*24;

$wgAutoloadClasses['FunnyQuestion'] = __DIR__ . '/FunnyQuestion.body.php';
$wgExtensionMessagesFiles['FunnyQuestion'] = __DIR__ . '/FunnyQuestion.i18n.php';

if ($wgGroupPermissions['*']['edit']) {
	$wgHooks['EditPage::showEditForm:fields'][] = 'FunnyQuestion::addFunnyQuestionToEditPage';
	$wgHooks['EditFilter'][] = 'FunnyQuestion::checkFunnyQuestionOnEditPage';
}

if ($wgGroupPermissions['*']['createaccount'] && (empty($wgAuth) || $wgAuth->canCreateAccounts())) {
	$wgHooks['UserCreateForm'][] = 'FunnyQuestion::addFunnyQuestionToUserCreateForm';
	$wgHooks['AbortNewAccount'][] = 'FunnyQuestion::checkFunnyQuestionOnAbortNewAccount';
}

?>
