<?php
/** Minnan (閩南語 / Bân-lâm-gú)
 *
 * @file
 * @ingroup Languages
 *
 * @author Hiong3-eng5
 * @author Ianbu
 * @author Kaihsu
 * @author Winston Sung
 */

$fallback = 'nan-latn-pehoeji, nan-latn-tailo, nan-hant, cdo, zh-hant, zh, zh-hans';

$namespaceNames = [
	NS_MEDIA            => 'Mûi-thé',
	NS_SPECIAL          => 'Tek-pia̍t',
	NS_TALK             => 'Thó-lūn',
	NS_USER             => 'Iōng-chiá',
	NS_USER_TALK        => 'Iōng-chiá_thó-lūn',
	NS_PROJECT_TALK     => '$1_thó-lūn',
	NS_FILE             => 'tóng-àn',
	NS_FILE_TALK        => 'tóng-àn_thó-lūn',
	NS_MEDIAWIKI        => 'MediaWiki',
	NS_MEDIAWIKI_TALK   => 'MediaWiki_thó-lūn',
	NS_TEMPLATE         => 'Pang-bô͘',
	NS_TEMPLATE_TALK    => 'Pang-bô͘_thó-lūn',
	NS_HELP             => 'Pang-chān',
	NS_HELP_TALK        => 'Pang-chān_thó-lūn',
	NS_CATEGORY         => 'Lūi-pia̍t',
	NS_CATEGORY_TALK    => 'Lūi-pia̍t_thó-lūn',
];

$datePreferences = [
	'default',
	'ISO 8601',
];

$defaultDateFormat = 'nan';

$dateFormats = [
	'nan time' => 'H:i',
	'nan date' => 'Y-"nî" n-"goe̍h" j-"ji̍t" (l)',
	'nan both' => 'Y-"nî" n-"goe̍h" j-"ji̍t" (l) H:i',
];
