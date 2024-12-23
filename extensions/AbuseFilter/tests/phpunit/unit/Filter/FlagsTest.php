<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit\Filter;

use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWikiUnitTestCase;

/**
 * @group Test
 * @group AbuseFilter
 * @covers \MediaWiki\Extension\AbuseFilter\Filter\Flags
 */
class FlagsTest extends MediaWikiUnitTestCase {
	public function testGetters() {
		$enabled = true;
		$deleted = false;
		$privacyLevel = Flags::FILTER_HIDDEN | Flags::FILTER_USES_PROTECTED_VARS;
		$global = false;
		$flags = new Flags( $enabled, $deleted, $privacyLevel, $global );

		$this->assertSame( $enabled, $flags->getEnabled(), 'enabled' );
		$this->assertSame( $deleted, $flags->getDeleted(), 'deleted' );
		$this->assertSame( true, $flags->getHidden(), 'hidden' );
		$this->assertSame( true, $flags->getProtected(), 'protected variables' );
		$this->assertSame( $privacyLevel, $flags->getPrivacyLevel(), 'privacy level' );
		$this->assertSame( $global, $flags->getGlobal(), 'global' );
	}

	/**
	 * @param mixed $value
	 * @param string $setter
	 * @param string $getter
	 * @dataProvider provideSetters
	 */
	public function testSetters( $value, string $setter, string $getter ) {
		$flags = new Flags( true, true, Flags::FILTER_HIDDEN | Flags::FILTER_USES_PROTECTED_VARS, true );

		$flags->$setter( $value );
		$this->assertSame( $value, $flags->$getter() );
	}

	/**
	 * @return array
	 */
	public static function provideSetters() {
		return [
			'enabled' => [ true, 'setEnabled', 'getEnabled' ],
			'deleted' => [ false, 'setDeleted', 'getDeleted' ],
			'hidden' => [ true, 'setHidden', 'getHidden' ],
			'protected' => [ true, 'setProtected', 'getProtected' ],
			'global' => [ false, 'setGlobal', 'getGlobal' ],
		];
	}
}
