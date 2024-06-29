<?php

use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Language\FormatterFactory;
use MediaWiki\Languages\LanguageFactory;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\User\UserIdentityUtils;

/**
 * @covers \MediaWiki\Language\FormatterFactory
 */
class FormatterFactoryTest extends MediaWikiUnitTestCase {

	private function getFactory() {
		return new FormatterFactory(
			$this->createNoOpMock( MessageCache::class ),
			$this->createNoOpMock( TitleFormatter::class ),
			$this->createNoOpMock( HookContainer::class ),
			$this->createNoOpMock( UserIdentityUtils::class ),
			$this->createNoOpMock( LanguageFactory::class )
		);
	}

	public function testGetStatusFormatter() {
		$factory = $this->getFactory();
		$factory->getStatusFormatter(
			$this->createNoOpMock( MessageLocalizer::class )
		);

		// Just make sure the getter works.
		// This protects against constructor signature changes.
		$this->addToAssertionCount( 1 );
	}

	public function testGetBlockErrorFormatter() {
		$factory = $this->getFactory();
		$factory->getBlockErrorFormatter(
			$this->createNoOpMock( IContextSource::class )
		);

		// Just make sure the getter works.
		// This protects against constructor signature changes.
		$this->addToAssertionCount( 1 );
	}
}
