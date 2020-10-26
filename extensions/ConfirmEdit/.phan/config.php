<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'FancyCaptcha/',
		'MathCaptcha/',
		'QuestyCaptcha/',
		'ReCaptchaNoCaptcha/',
		'SimpleCaptcha/',
		'../../extensions/Math',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/Math',
	]
);

// WikiPage->ConfirmEdit_ActivateCaptcha
$cfg['suppress_issue_types'][] = 'PhanUndeclaredProperty';

return $cfg;
