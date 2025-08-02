<?php

declare( strict_types = 1 );

namespace Cite\Config\Schemas;

use MediaWiki\Extension\CommunityConfiguration\Schema\JsonSchema;

// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
/**
 * @license GPL-2.0-or-later
 */
class CiteSchema extends JsonSchema {
	public const VERSION = '1.0.0';
	public const Cite_Settings = [
		self::TYPE => self::TYPE_OBJECT,
		self::PROPERTIES => [
			'backlinkAlphabet' => [
				self::TYPE => self::TYPE_STRING,
				self::DEFAULT => 'a b c d e f g h i j k l m n o p r s t u v w x y z',
			],
		],
	];
}
