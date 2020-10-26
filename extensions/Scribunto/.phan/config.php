<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['autoload_internal_extension_signatures'] = [
	'pcntl' => $IP . '/.phan/internal_stubs/pcntl.phan_php',
];

$cfg['file_list'][] = 'Scribunto.constants.php';

$cfg['directory_list'] = array_merge(
	$cfg['directory_list'],
	[
		'vendor/mediawiki/lua-sandbox/stubs',
		'../../extensions/SyntaxHighlight_GeSHi',
	]
);

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	[
		'vendor/mediawiki/lua-sandbox/stubs',
		'../../extensions/SyntaxHighlight_GeSHi',
	]
);

$cfg['suppress_issue_types'] = array_merge(
	$cfg['suppress_issue_types'],
	[
		// \Parser->scribunto_engine
		'PhanUndeclaredProperty',
	]
);

return $cfg;
