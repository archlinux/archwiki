<?php

$wgExtensionCredits['other'][] = array(
	'name' => 'FunnyQuestion',
	'version' => '2.0',
	'description' => 'Challenge-response authentication',
	'author' => 'Pierre Schmitz',
	'url' => 'https://www.archlinux.de'
);

$wgFunnyQuestions = array(
	'en' => array("What is the Ultimate Answer to the Ultimate Question of Life, The Universe, and Everything?" => "42"),
	'de' => array("Was ist die ultimaative Antwort nach dem Leben, dem Universum und dem ganzen Rest?" => "42")
);
$wgFunnyQuestionHash = '';
$wgFunnyQuestionTimeout = 3600;
$wgFunnyQuestionWait = 2;

$wgAutoloadClasses['FunnyQuestion'] = dirname(__FILE__) . '/FunnyQuestion.body.php';
$wgExtensionMessagesFiles['FunnyQuestion'] = dirname( __FILE__ ) . '/FunnyQuestion.i18n.php';

if ($wgGroupPermissions['*']['edit']) {
	$wgHooks['EditPage::showEditForm:fields'][] = 'FunnyQuestion::addFunnyQuestionToEditPage';
	$wgHooks['EditFilter'][] = 'FunnyQuestion::checkFunnyQuestionOnEditPage';
}

if ($wgGroupPermissions['*']['createaccount'] && (empty($wgAuth) || $wgAuth->canCreateAccounts())) {
	$wgHooks['UserCreateForm'][] = 'FunnyQuestion::addFunnyQuestionToUserCreateForm';
	$wgHooks['AbortNewAccount'][] = 'FunnyQuestion::checkFunnyQuestionOnAbortNewAccount';
}

?>

