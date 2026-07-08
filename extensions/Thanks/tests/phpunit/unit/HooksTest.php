<?php

namespace MediaWiki\Extension\Thanks\Tests\Unit;

use MediaWiki\Extension\Thanks\Hooks;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\Thanks\Hooks
 */
class HooksTest extends MediaWikiUnitTestCase {

	public static function provideGetSessionKey() {
		return [
			[ 'rev', 123, 'thanks-thanked-rev123' ],
			[ 'revision', 456, 'thanks-thanked-rev456' ],
			[ 'log', '1000', 'thanks-thanked-log1000' ],
			[ 'foo', 'bar', 'thanks-thanked-foobar' ],
		];
	}

	/**
	 * @dataProvider provideGetSessionKey
	 */
	public function testGetSessionKey( string $type, $id, string $expected ) {
		$this->assertSame( $expected, Hooks::getSessionKey( $type, $id ) );
	}

}
