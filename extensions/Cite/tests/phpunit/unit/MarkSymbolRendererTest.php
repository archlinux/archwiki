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
	public function testRenderFootnoteMarkLabel(
		string $expectedLabel,
		int $offset,
		?int $extendsIndex = null,
		string $group = '',
		?string $labelList = null
	) {
		$msg = $this->createMock( Message::class );
		$msg->method( 'isDisabled' )->willReturn( $labelList === null );
		$msg->method( 'plain' )->willReturn( $labelList );
		$mockMessageLocalizer = $this->createMock( ReferenceMessageLocalizer::class );
		$mockMessageLocalizer->method( 'msg' )->willReturn( $msg );
		$mockMessageLocalizer->method( 'localizeDigits' )->willReturnCallback(
			static fn ( $number ) => (string)$number
		);
		/** @var ReferenceMessageLocalizer $mockMessageLocalizer */
		$renderer = new MarkSymbolRenderer( $mockMessageLocalizer );

		$output = $renderer->renderFootnoteMarkLabel( $group, $offset, $extendsIndex );
		$this->assertSame( $expectedLabel, $output );
	}

	public static function provideCustomizedLinkLabels() {
		yield [ '1', 1 ];
		yield [ '2', 2 ];
		yield [ 'group 1', 1, null, 'group' ];
		yield [ 'group 2', 2, null, 'group' ];
		yield [ 'a', 1, null, 'group', 'a b c' ];
		yield [ 'b', 2, null, 'group', 'a b c' ];
		yield [ 'å', 1, null, 'group', 'å β' ];
		yield [ 'group 4', 4, null, 'group', 'a b c' ];
		yield [ 'group 4', 4, null, 'group' ];
		yield [ '4.1', 4, 1 ];
		yield [ 'å.1', 1, 1, 'group', 'å β' ];
	}

	public function testRenderFootnoteNumber() {
		$msg = $this->createMock( Message::class );
		$msg->method( 'isDisabled' )->willReturn( true );
		$mockMessageLocalizer = $this->createMock( ReferenceMessageLocalizer::class );
		$mockMessageLocalizer->method( 'msg' )->willReturn( $msg );
		$mockMessageLocalizer->method( 'localizeDigits' )->willReturnCallback(
			static fn ( $number ) => (string)$number
		);
		/** @var ReferenceMessageLocalizer $mockMessageLocalizer */
		$renderer = new MarkSymbolRenderer( $mockMessageLocalizer );

		$this->assertSame( '1.2', $renderer->renderFootnoteNumber( 'group', 1, 2 ) );
	}

	public function testDefaultGroupCannotHaveCustomLinkLabels() {
		$mockMessageLocalizer = $this->createMock( ReferenceMessageLocalizer::class );
		$mockMessageLocalizer->method( 'localizeDigits' )->willReturnCallback(
			static fn ( $number ) => (string)$number
		);
		// Assert that ReferenceMessageLocalizer::msg( 'cite_link_label_group-' )
		// isn't called by not defining the ->msg method.

		$renderer = new MarkSymbolRenderer( $mockMessageLocalizer );

		$this->assertSame( '1', $renderer->renderFootnoteMarkLabel( Cite::DEFAULT_GROUP, 1 ) );
	}

}
