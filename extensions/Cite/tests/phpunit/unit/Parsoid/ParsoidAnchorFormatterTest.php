<?php

namespace Cite\Tests\Unit;

use Cite\Parsoid\ParsoidAnchorFormatter;
use Cite\Parsoid\RefGroupItem;

/**
 * @covers \Cite\Parsoid\ParsoidAnchorFormatter
 * @license GPL-2.0-or-later
 */
class ParsoidAnchorFormatterTest extends \MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideNoteIdentifiers
	 */
	public function testNoteIdentifiers( ?string $name, int $globalId, string $expected ) {
		$ref = new RefGroupItem();
		$ref->name = $name;
		$ref->globalId = $globalId;
		$id = ParsoidAnchorFormatter::getNoteIdentifier( $ref );
		$this->assertSame( $expected, $id );
	}

	public static function provideNoteIdentifiers() {
		return [
			[ null, 0, 'cite_note-0' ],
			[ null, 1, 'cite_note-1' ],
			[ 'a', 2, 'cite_note-a-2' ],
			[ ' _a __  b_', 6, 'cite_note-_a_b_-6' ],
			[ 'a_ %20a', 7, 'cite_note-a_%20a-7' ],
		];
	}

	/**
	 * @dataProvider provideBackLinkIdentifiers
	 */
	public function testBackLinkIdentifiers(
		?string $name,
		int $globalId,
		int $visibleNodes,
		?int $count,
		string $expected
	) {
		$ref = new RefGroupItem();
		$ref->name = $name;
		$ref->globalId = $globalId;
		$ref->visibleNodes = $visibleNodes;
		$id = ParsoidAnchorFormatter::getBackLinkIdentifier( $ref, $count );
		$this->assertSame( $expected, $id );
	}

	public static function provideBackLinkIdentifiers() {
		return [
			[ null, 0, 99, null, 'cite_ref-0' ],
			[ null, 1, 99, null, 'cite_ref-1' ],
			[ 'a', 1, 5, null, 'cite_ref-a_1-4' ],
			[ 'a', 1, 5, 3, 'cite_ref-a_1-2' ],
			[ ' _a __  b_', 6, 1, null, 'cite_ref-_a_b_6-0' ],
			[ 'a_ %20a', 7, 1, null, 'cite_ref-a_%20a_7-0' ],
		];
	}

}
