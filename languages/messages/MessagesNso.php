<?php
/** Northern Sotho (Sesotho sa Leboa)
 *
 * @file
 * @ingroup Languages
 *
 * @author Kaganer
 * @author Mohau
 * @author Urhixidur
 */

$namespaceNames = [
	NS_MEDIA            => 'Media',
	NS_SPECIAL          => 'Special',
	NS_TALK             => 'Bolediša',
	NS_USER             => 'Mošomi',
	NS_USER_TALK        => 'Boledišana_le_Mošomi',
	NS_PROJECT_TALK     => '$1_Poledišano',
	NS_FILE             => 'Seswantšho',
	NS_FILE_TALK        => 'Poledišano_ya_Seswantšho',
	NS_MEDIAWIKI        => 'MediaWiki',
	NS_MEDIAWIKI_TALK   => 'Poledišano_ya_MediaWiki',
	NS_TEMPLATE         => 'Template',
	NS_TEMPLATE_TALK    => 'Poledišano_ya_Template',
	NS_HELP             => 'Thušo',
	NS_HELP_TALK        => 'Poledišano_ya_Thušo',
	NS_CATEGORY         => 'Setensele',
	NS_CATEGORY_TALK    => 'Poledišano_ya_Setensele',
];

/** @phpcs-require-sorted-array */
$magicWords = [
	'currentday'                => [ '1', 'LEHONO_LETSATSI', 'CURRENTDAY' ],
	'currentday2'               => [ '1', 'LEHONO_LETSATSI2', 'CURRENTDAY2' ],
	'currentdayname'            => [ '1', 'LEHONO_LETSATSILEINA', 'CURRENTDAYNAME' ],
	'currenthour'               => [ '1', 'IRI_BJALE', 'CURRENTHOUR' ],
	'currentmonth'              => [ '1', 'KGWEDI_BJALE', 'CURRENTMONTH', 'CURRENTMONTH2' ],
	'currentmonthname'          => [ '1', 'LEINA_KGWEDI_BJALE', 'CURRENTMONTHNAME' ],
	'currenttime'               => [ '1', 'NAKO_BJALE', 'CURRENTTIME' ],
	'currentyear'               => [ '1', 'NGWAGA_BJALE', 'CURRENTYEAR' ],
];

$linkTrail = '/^([A-Za-zŠÔÊšôê]+)(.*)$/sDu';
