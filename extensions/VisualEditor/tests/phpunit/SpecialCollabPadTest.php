<?php

namespace MediaWiki\Extension\VisualEditor\Tests;

use MediaWikiIntegrationTestCase;
use SpecialCollabPad;
use Title;

/**
 * @covers \SpecialCollabPad
 */
class SpecialCollabPadTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideSubPages
	 */
	public function testGetSubPage( string $title, ?string $expected ) {
		$result = SpecialCollabPad::getSubPage( Title::newFromText( $title ) );
		$this->assertSame(
			$expected,
			$result ? $result->getPrefixedText() : $result
		);
	}

	public function provideSubPages() {
		return [
			[ 'Special:CollabPad', null ],
			[ 'Special:CollabPad/', null ],
			[ 'Special:CollabPad/B_b', 'B b' ],
			[ 'Special:CollabPad/B b', 'B b' ],
			[ 'Special:CollabPad/B/C', 'B/C' ],
			[ 'Special:CollabPad/B/C/', 'B/C/' ],
			[ '/B/C', null ],
		];
	}

}
