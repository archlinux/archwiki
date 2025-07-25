<?php

namespace Cite\Tests\Unit;

use Cite\Cite;
use Cite\MarkSymbolRenderer;
use Cite\ReferenceMessageLocalizer;
use MediaWiki\Message\Message;

/**
 * @covers \Cite\MarkSymbolRenderer
 * @license GPL-2.0-or-later
 */
class MarkSymbolRendererTest extends \MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideCustomizedLinkLabels
	 */
	public function testMakeLabel( ?string $expectedLabel, int $offset, string $group, ?string $labelList ) {
		$mockMessageLocalizer = $this->createMock( ReferenceMessageLocalizer::class );
		$mockMessageLocalizer->method( 'msg' )->willReturnCallback(
			function ( ...$args ) use ( $labelList ) {
				$msg = $this->createMock( Message::class );
				$msg->method( 'isDisabled' )->willReturn( $labelList === null );
				$msg->method( 'plain' )->willReturn( $labelList );
				return $msg;
			}
		);
		$mockMessageLocalizer->method( 'localizeDigits' )->willReturnCallback(
			static function ( $number ) {
				return (string)$number;
			}
		);
		$renderer = new MarkSymbolRenderer( $mockMessageLocalizer );

		$output = $renderer->makeLabel( $group, $offset );
		$this->assertSame( $expectedLabel, $output );
	}

	public static function provideCustomizedLinkLabels() {
		yield [ '1', 1, '', null ];
		yield [ '2', 2, '', null ];
		yield [ 'foo 1', 1, 'foo', null ];
		yield [ 'foo 2', 2, 'foo', null ];
		yield [ 'a', 1, 'foo', 'a b c' ];
		yield [ 'b', 2, 'foo', 'a b c' ];
		yield [ 'å', 1, 'foo', 'å β' ];
		yield [ 'foo 4', 4, 'foo', 'a b c' ];
	}

	public function testDefaultGroupCannotHaveCustomLinkLabels() {
		$mockMessageLocalizer = $this->createMock( ReferenceMessageLocalizer::class );
		$mockMessageLocalizer->method( 'localizeDigits' )->willReturnCallback(
			static function ( $number ) {
				return (string)$number;
			}
		);
		// Assert that ReferenceMessageLocalizer::msg( 'cite_link_label_group-' )
		// isn't called by not defining the ->msg method.

		$renderer = new MarkSymbolRenderer( $mockMessageLocalizer );

		$this->assertSame( '1', $renderer->makeLabel( Cite::DEFAULT_GROUP, 1 ) );
	}

}
