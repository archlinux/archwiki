<?php

namespace Cite\Tests\Unit;

use Cite\AlphabetsProvider;
use Cite\AnchorFormatter;
use Cite\BacklinkMarkRenderer;
use Cite\ErrorReporter;
use Cite\ReferenceListFormatter;
use Cite\ReferenceMessageLocalizer;
use Cite\Tests\TestUtils;
use MediaWiki\Config\HashConfig;
use MediaWiki\Message\Message;
use MediaWiki\Parser\Parser;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \Cite\ReferenceListFormatter
 * @license GPL-2.0-or-later
 */
class ReferenceListFormatterTest extends \MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideFormatReferences
	 */
	public function testFormatReferences( array $refs, string $expectedOutput ) {
		$mockParser = $this->createNoOpMock( Parser::class, [ 'recursiveTagParse' ] );
		$mockParser->method( 'recursiveTagParse' )->willReturnArgument( 0 );

		$mockErrorReporter = $this->createMock( ErrorReporter::class );
		$mockErrorReporter->method( 'plain' )->willReturnCallback(
			static fn ( ...$args ) => '(' . implode( '|', $args ) . ')'
		);

		$mockMessageLocalizer = $this->createMock( ReferenceMessageLocalizer::class );
		$mockMessageLocalizer->method( 'msg' )->willReturnCallback(
			function ( ...$args ) {
				$msg = $this->createMock( Message::class );
				$msg->method( 'plain' )->willReturn( '<li>(' . implode( '|', $args ) . ')</li>' );
				return $msg;
			}
		);

		$formatter = new ReferenceListFormatter(
			$mockErrorReporter,
			$this->createMock( AnchorFormatter::class ),
			$this->createNoOpMock( BacklinkMarkRenderer::class ),
			$mockMessageLocalizer
		);

		$refs = array_map( [ TestUtils::class, 'refFromArray' ], $refs );
		$output = $formatter->formatReferences( $mockParser, $refs, true );
		$this->assertSame( $expectedOutput, $output );
	}

	public static function provideFormatReferences() {
		return [
			'Empty' => [
				'refs' => [],
				'expectedOutput' => ''
			],
			'Minimal ref' => [
				'refs' => [
					0 => [
						'count' => 1,
						'globalId' => 1,
						'text' => 't',
					]
				],
				'expectedOutput' => '<div class="mw-references-wrap"><ol class="references">' . "\n" .
					'<li>(cite_references_link_one|||<span class="reference-text">t</span>' .
					"\n|)</li>\n</ol></div>"
			],
			'Use columns' => [
				'refs' => array_map(
					static fn ( $i ) => [ 'count' => 1, 'globalId' => $i, 'text' => 't' ],
					range( 0, 10 )
				),
				'expectedOutput' => '<div class="mw-references-wrap mw-references-columns"><ol class="references">' .
					"\n" . '<li>(cite_references_link_one|||<span class="reference-text">t</span>' .
					"\n|)</li>\n" .
					'<li>(cite_references_link_one|||<span class="reference-text">t</span>' .
					"\n|)</li>\n" .
					'<li>(cite_references_link_one|||<span class="reference-text">t</span>' .
					"\n|)</li>\n" .
					'<li>(cite_references_link_one|||<span class="reference-text">t</span>' .
					"\n|)</li>\n" .
					'<li>(cite_references_link_one|||<span class="reference-text">t</span>' .
					"\n|)</li>\n" .
					'<li>(cite_references_link_one|||<span class="reference-text">t</span>' .
					"\n|)</li>\n" .
					'<li>(cite_references_link_one|||<span class="reference-text">t</span>' .
					"\n|)</li>\n" .
					'<li>(cite_references_link_one|||<span class="reference-text">t</span>' .
					"\n|)</li>\n" .
					'<li>(cite_references_link_one|||<span class="reference-text">t</span>' .
					"\n|)</li>\n" .
					'<li>(cite_references_link_one|||<span class="reference-text">t</span>' .
					"\n|)</li>\n" .
					'<li>(cite_references_link_one|||<span class="reference-text">t</span>' .
					"\n|)</li>\n</ol></div>"
			],
		];
	}

	/**
	 * @dataProvider provideCloseIndention
	 */
	public function testCloseIndention( $closingLi, $expectedOutput ) {
		/** @var ReferenceListFormatter $formatter */
		$formatter = TestingAccessWrapper::newFromObject( new ReferenceListFormatter(
			$this->createNoOpMock( ErrorReporter::class ),
			$this->createNoOpMock( AnchorFormatter::class ),
			$this->createNoOpMock( BacklinkMarkRenderer::class ),
			$this->createNoOpMock( ReferenceMessageLocalizer::class )
		) );

		$output = $formatter->closeIndention( $closingLi );
		$this->assertSame( $expectedOutput, $output );
	}

	public static function provideCloseIndention() {
		return [
			'No indention' => [ false, '' ],
			'Indention string' => [ "</li>\n", "</ol></li>\n" ],
			'Indention without string' => [ true, '</ol>' ],
		];
	}

	/**
	 * @dataProvider provideFormatListItem
	 */
	public function testFormatListItem(
		array $ref,
		string $expectedOutput
	) {
		$mockErrorReporter = $this->createMock( ErrorReporter::class );
		$mockErrorReporter->method( 'plain' )->willReturnArgument( 0 );

		$anchorFormatter = $this->createMock( AnchorFormatter::class );
		$anchorFormatter->method( 'wikitextSafeBacklink' )->willReturnCallback(
			static fn ( ...$args ) => implode( '+', $args )
		);
		$anchorFormatter->method( 'noteLinkTarget' )->willReturnCallback(
			static fn ( ...$args ) => implode( '+', $args )
		);

		$mockMessageLocalizer = $this->createMock( ReferenceMessageLocalizer::class );
		$mockMessageLocalizer->method( 'localizeSeparators' )->willReturnArgument( 0 );
		$mockMessageLocalizer->method( 'localizeDigits' )->willReturnArgument( 0 );
		$mockMessageLocalizer->method( 'msg' )->willReturnCallback(
			function ( ...$args ) {
				$msg = $this->createMock( Message::class );
				$msg->method( 'plain' )->willReturn( '(' . implode( '|', $args ) . ')' );
				return $msg;
			}
		);

		/** @var ReferenceListFormatter $formatter */
		$formatter = TestingAccessWrapper::newFromObject( new ReferenceListFormatter(
			$mockErrorReporter,
			$anchorFormatter,
			new BacklinkMarkRenderer(
				'en',
				$mockMessageLocalizer,
				$this->createMock( AlphabetsProvider::class ),
				null,
				new HashConfig( [
					'CiteDefaultBacklinkAlphabet' => null,
					'CiteUseLegacyBacklinkLabels' => true,
				] )
			),
			$mockMessageLocalizer
		) );

		$ref = TestUtils::refFromArray( $ref );
		$output = $formatter->formatListItem( $ref );
		$this->assertSame( $expectedOutput, $output );
	}

	public static function provideFormatListItem() {
		return [
			'Success' => [
				'ref' => [
					'count' => 1,
					'globalId' => 1,
					'text' => 't',
				],
				'expectedOutput' => '(cite_references_link_one|+1|+1+1|<span class="reference-text">t</span>' . "\n|)"
			],
			'With dir' => [
				'ref' => [
					'count' => 1,
					'dir' => 'rtl',
					'globalId' => 1,
					'text' => 't',
				],
				'expectedOutput' => '(cite_references_link_one|+1|+1+1|<span class="reference-text">t</span>' .
					"\n" . '| class="mw-cite-dir-rtl")'
			],
			'Incomplete follow' => [
				'ref' => [
					'follow' => 'f',
					'globalId' => 1,
					'text' => 't',
				],
				'expectedOutput' => "<p><span class=\"reference-text\">t</span>\n</p>"
			],
			'Count one' => [
				'ref' => [
					'count' => 1,
					'globalId' => 5,
					'name' => 'a',
					'text' => 't',
				],
				'expectedOutput' => '(cite_references_link_one|a+5|a+5+1|<span class="reference-text">t</span>'
					. "\n|)"
			],
			'Anonymous' => [
				'ref' => [
					'count' => 1,
					'globalId' => 5,
					'numberInGroup' => 3,
					'text' => 't',
				],
				'expectedOutput' => '(cite_references_link_one|+5|+5+1|<span class="reference-text">t</span>' . "\n|)"
			],
			'Count many' => [
				'ref' => [
					'count' => 3,
					'globalId' => 5,
					'name' => 'a',
					'numberInGroup' => 3,
					'text' => 't',
				],
				'expectedOutput' => '(cite_references_link_many|a+5|(cite_references_link_many_format|a+5+1|3.0|' .
				'(cite_references_link_many_format_backlink_labels))' .
				'(cite_references_link_many_sep)(cite_references_link_many_format|a+5+2|3.1|' .
				'3.2)(cite_references_link_many_and)' .
				'(cite_references_link_many_format|a+5+3|3.2|3.3' .
				')|<span class="reference-text">t</span>' . "\n|)"
			],
		];
	}

	/**
	 * @dataProvider provideReferenceText
	 */
	public function testReferenceText(
		?string $text,
		string $expectedOutput
	) {
		$mockErrorReporter = $this->createMock( ErrorReporter::class );
		$mockErrorReporter->method( 'plain' )->willReturnCallback(
			static fn ( ...$args ) => '(' . implode( '|', $args ) . ')'
		);

		/** @var ReferenceListFormatter $formatter */
		$formatter = TestingAccessWrapper::newFromObject( new ReferenceListFormatter(
			$mockErrorReporter,
			$this->createNoOpMock( AnchorFormatter::class ),
			$this->createNoOpMock( BacklinkMarkRenderer::class ),
			$this->createNoOpMock( ReferenceMessageLocalizer::class )
		) );

		$ref = TestUtils::refFromArray( [ 'text' => $text ] );
		$output = $formatter->renderTextAndWarnings( $ref );
		$this->assertSame( $expectedOutput, $output );
	}

	public static function provideReferenceText() {
		return [
			'No text, not preview' => [
				'text' => null,
				'expectedOutput' => '<span class="reference-text"></span>' . "\n"
			],
			'Has text' => [
				'text' => 'text',
				'expectedOutput' => '<span class="reference-text">text</span>' . "\n"
			],
			'Trims text' => [
				'text' => "text\n\n",
				'expectedOutput' => '<span class="reference-text">text</span>' . "\n"
			],
		];
	}

	/**
	 * @dataProvider provideLists
	 */
	public function testListToText( array $list, $expected ) {
		$mockMessageLocalizer = $this->createMock( ReferenceMessageLocalizer::class );
		$mockMessageLocalizer->method( 'msg' )->willReturnCallback(
			function ( ...$args ) {
				$msg = $this->createMock( Message::class );
				$msg->method( 'plain' )->willReturn( '(' . implode( '|', $args ) . ')' );
				return $msg;
			}
		);

		/** @var ReferenceListFormatter $formatter */
		$formatter = TestingAccessWrapper::newFromObject( new ReferenceListFormatter(
			$this->createNoOpMock( ErrorReporter::class ),
			$this->createNoOpMock( AnchorFormatter::class ),
			$this->createNoOpMock( BacklinkMarkRenderer::class ),
			$mockMessageLocalizer
		) );
		$this->assertSame( $expected, $formatter->listToText( $list ) );
	}

	public static function provideLists() {
		return [
			[
				'list' => [],
				'expected' => ''
			],
			[
				// This is intentionally using numbers to test the to-string cast
				'list' => [ 1 ],
				'expected' => '1'
			],
			[
				'list' => [ 1, 2 ],
				'expected' => '1(cite_references_link_many_and)2'
			],
			[
				'list' => [ 1, 2, 3 ],
				'expected' => '1(cite_references_link_many_sep)2(cite_references_link_many_and)3'
			],
		];
	}

}
