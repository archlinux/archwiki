<?php

namespace MediaWiki\Extension\OATHAuth\Tests\Unit;

use MediaWiki\Extension\OATHAuth\AAGUIDLookup;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\AAGUIDLookup
 */
class AAGUIDLookupTest extends MediaWikiUnitTestCase {

	public static function provideGetDeviceName() {
		yield [ null, 'foo' ];
		yield [ 'Chrome on Mac', 'adce0002-35bc-c60a-648b-0b25f1f05503' ];
		yield [ 'Chrome on Mac', 'ADCE0002-35BC-C60A-648B-0B25F1F05503' ];
	}

	/** @dataProvider provideGetDeviceName */
	public function testGetDeviceName( ?string $expected, string $input ) {
		$this->assertEquals( $expected, AAGUIDLookup::getDeviceName( $input ) );
	}

	public static function provideGenerateFriendlyName() {
		yield [ 'adce0002-35bc-c60a-648b-0b25f1f05503', 'Chrome on Mac' ];
		yield [ 'foo', 'Passkey' ];
	}

	/** @dataProvider provideGenerateFriendlyName */
	public function testGenerateFriendlyName( string $input, string $expected ) {
		$this->assertEquals( $expected, AAGUIDLookup::generateFriendlyName( $input ) );
	}
}
