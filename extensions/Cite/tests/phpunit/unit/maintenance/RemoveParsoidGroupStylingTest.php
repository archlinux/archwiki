<?php

namespace Cite\Tests\Unit;

use MediaWikiUnitTestCase;
use RemoveParsoidGroupStyling;
use Wikimedia\TestingAccessWrapper;

require_once __DIR__ . '../../../../../maintenance/removeParsoidGroupStyling.php';

/**
 * @covers \RemoveParsoidGroupStyling
 * @license GPL-2.0-or-later
 */
class RemoveParsoidGroupStylingTest extends MediaWikiUnitTestCase {
	public function testRemoveStyling() {
		$text = "
0
.mw-ref > a::after {
content: '[' counter( mw-Ref, decimal ) ']';
}
1
.mw-ref > a[data-mw-group]::after {
content: '[' attr( data-mw-group ) ' ' counter( mw-Ref, decimal ) ']';
}
2
.mw-ref > a[data-mw-group=decimal]::after {
content: '[' counter( mw-Ref, decimal ) ']';
}
3
.mw-ref > a[data-mw-group=lower-roman]::after {
content: '[' counter( mw-Ref, lower-roman ) ']';
}
4
.mw-ref > a[style~='mw-Ref'][data-mw-group=lower-alpha]::after {
		content: '[' counter( mw-Ref, lower-alpha ) ']';
}

/* leave intact */
.ref-arabic-indic .reference:not(.mw-ref) a::after {
  content: ')';
}
		";
		$expected = "
0
1
2
3
4

/* leave intact */
.ref-arabic-indic .reference:not(.mw-ref) a::after {
  content: ')';
}
		";
		$styler = TestingAccessWrapper::newFromClass( RemoveParsoidGroupStyling::class );
		$actual = $styler->removeStyling( $text );
		$this->assertEquals( $expected, $actual );
	}

	public function testRemoveComments() {
		$text = "
0
/* T156351: Support for Parsoid's Cite implementation */
1
/* These blocks need review after [[phab:T371839]] or related are complete */
2
		";
		$expected = '
0
1
2
		';
		$styler = TestingAccessWrapper::newFromClass( RemoveParsoidGroupStyling::class );
		$actual = $styler->removeKnownComments( $text );
		$this->assertEquals( $expected, $actual );
	}
}
