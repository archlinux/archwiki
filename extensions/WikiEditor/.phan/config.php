<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'../../extensions/EventLogging',
		'../../extensions/WikimediaEvents',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'../../extensions/EventLogging',
		'../../extensions/WikimediaEvents',
	]
);

// \WikiPage->ConfirmEdit_ActivateCaptcha not exist is a false-positive
$cfg['suppress_issue_types'][] = 'PhanUndeclaredProperty';

return $cfg;
