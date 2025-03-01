<?php
/** Chakma (𑄌𑄋𑄴𑄟𑄳𑄦)
 *
 * @file
 * @ingroup Languages
 */

$namespaceNames = [
	NS_MEDIA            => '𑄟𑄨𑄓𑄨𑄠',
	NS_SPECIAL          => '𑄃𑄬𑄥𑄴𑄛𑄬𑄥𑄣𑄴',
	NS_TALK             => '𑄇𑄧𑄙',
	NS_USER             => '𑄣𑄢𑄴𑄌𑄢𑄴_𑄉𑄧𑄢𑄨𑄠𑄬',
	NS_USER_TALK        => '𑄣𑄢𑄴𑄌𑄢𑄴_𑄉𑄧𑄢𑄨𑄠𑄬_𑄇𑄧𑄙',
	NS_PROJECT_TALK     => '$1_𑄇𑄧𑄙',
	NS_FILE             => '𑄜𑄭𑄣𑄴',
	NS_FILE_TALK        => '𑄜𑄭𑄣𑄴_𑄇𑄧𑄙',
	NS_MEDIAWIKI        => '𑄟𑄨𑄓𑄨𑄠𑄃𑄪𑄃𑄨𑄇𑄨',
	NS_MEDIAWIKI_TALK   => '𑄟𑄨𑄓𑄨𑄠𑄃𑄪𑄃𑄨𑄇𑄨_𑄇𑄧𑄙',
	NS_TEMPLATE         => '𑄑𑄬𑄟𑄴𑄛𑄳𑄣𑄬𑄑𑄴',
	NS_TEMPLATE_TALK    => '𑄑𑄬𑄟𑄴𑄛𑄳𑄣𑄬𑄑𑄴_𑄇𑄧𑄙',
	NS_HELP             => '𑄝𑄧𑄣𑄝𑄧𑄣𑄴',
	NS_HELP_TALK        => '𑄝𑄧𑄣𑄝𑄧𑄣𑄴_𑄇𑄧𑄙',
	NS_CATEGORY         => '𑄇𑄬𑄑𑄉𑄧𑄢𑄨',
	NS_CATEGORY_TALK    => '𑄇𑄬𑄑𑄉𑄧𑄢𑄨_𑄇𑄧𑄙',
];

$digitTransformTable = [
	'0' => '𑄶',
	'1' => '𑄷',
	'2' => '𑄸',
	'3' => '𑄹',
	'4' => '𑄺',
	'5' => '𑄻',
	'6' => '𑄼',
	'7' => '𑄽',
	'8' => '𑄾',
	'9' => '𑄿'
];

$digitGroupingPattern = "#,##,##0.###";

$linkTrail = '/^([\p{Chakma}]+)(.*)$/su';
