<?php

namespace MediaWiki\Extension\TemplateData\Schemas;

use MediaWiki\Extension\CommunityConfiguration\Schema\JsonSchema;
use MediaWiki\Extension\CommunityConfiguration\Schemas\MediaWiki\MediaWikiDefinitions;

// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
/**
 * @license GPL-2.0-or-later
 */
class FeaturedTemplatesSchema extends JsonSchema {
	public const VERSION = '1.0.0';

	public const FeaturedTemplates = [
		self::TYPE => self::TYPE_ARRAY,
		self::DEFAULT => [],
		self::MIN_ITEMS => 0,
		self::MAX_ITEMS => 1,
		self::ITEMS => [
			self::TYPE => self::TYPE_OBJECT,
			self::PROPERTIES => [
				'titles' => [
					self::REF => [
						'class' => MediaWikiDefinitions::class,
						'field' => 'PageTitles',
					],
				],
			],
			self::REQUIRED => [ 'titles' ],
		],
	];
}
