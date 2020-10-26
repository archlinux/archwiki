<?php
$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';
$cfg['directory_list'] = [
	'RemexHtml',
	'vendor',
	'tests'
];
$cfg['exclude_analysis_directory_list'][] = 'vendor';
$cfg['exclude_file_list'][] = 'tests/phplint/autoload.php';
$cfg['exclude_file_regex'] = '@/vendor/(phan|mediawiki|jakub-onderka/php-parallel-lint)/@';

return $cfg;
