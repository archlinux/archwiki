<?php

$cfg = require __DIR__ . '/../vendor/mediawiki/mediawiki-phan-config/src/config.php';

$cfg['exclude_analysis_directory_list'] = array_merge(
	$cfg['exclude_analysis_directory_list'],
	# Don't analyze imported Parsoid code, it uses a different phan config
	[ 'includes/VEParsoid' ]
);

return $cfg;
