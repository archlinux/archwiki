<?php

use MediaWiki\Languages\LanguageFallback;
use MediaWiki\Tests\Unit\DummyServicesTrait;

/**
 * @coversDefaultClass MediaWiki\Languages\LanguageFallback
 * @covers ::__construct
 */
class LanguageFallbackTest extends MediaWikiUnitTestCase {
	use DummyServicesTrait;
	use LanguageFallbackTestTrait;

	private const DATA = [
		'en' => [],
		'fr' => [],
		'sco' => [ 'en' ],
		'yi' => [ 'he' ],
		'ruq' => [ 'ruq-latn', 'ro' ],
		'sh' => [ 'sh-latn', 'sh-cyrl', 'bs', 'sr-el', 'sr-latn', 'hr' ],
	];

	private function getCallee( array $options = [] ): LanguageFallback {
		return new LanguageFallback(
			$options['siteLangCode'] ?? 'en',
			$this->getMockLocalisationCache(
				$options['expectedGets'] ?? 1,
				$options['fallbackMap'] ?? self::DATA
			),
			$this->getDummyLanguageNameUtils()
		);
	}

	private function getMessagesKey() {
		return LanguageFallback::MESSAGES;
	}

	private function getStrictKey() {
		return LanguageFallback::STRICT;
	}
}
