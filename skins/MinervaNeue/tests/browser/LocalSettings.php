<?php
$wgRightsText = 'Creative Commons Attribution 3.0';
$wgRightsUrl = "http://creativecommons.org/licenses/by-sa/3.0/";
// Allow users to edit privacy link.
$wgGroupPermissions['user']['editinterface'] = true;

// Use hard-coded interwiki information
$wgInterwikiCache = \MediaWiki\Interwiki\ClassicInterwikiLookup::buildCdbHash( [
	[
		'iw_prefix' => 'es',
		'iw_url' => 'http://wikifoo.org/es/index.php/$1',
		'iw_local' => 0,
		'iw_trans' => 0,
	],
] );

$wgMinervaPageIssuesNewTreatment = [
	"base" => true,
	"beta" => true,
];

$wgMFEnableBeta = true;

// Set the desktop skin to MinervaNeue. Otherwise, it will try to guess the skin name using the
// class name MinervaNeue which resolves to `minervaneue`.
$wgDefaultSkin = 'minerva';

// needed for testing whether the language button is displayed and disabled
$wgMinervaAlwaysShowLanguageButton = true;

// For those who have wikibase installed.
$wgMFUseWikibase = true;

$wgMFDisplayWikibaseDescriptions = [
	'search' => true,
	'nearby' => true,
	'watchlist' => true,
	'tagline' => true,
];

$wgMinervaShowCategories['base'] = true;
