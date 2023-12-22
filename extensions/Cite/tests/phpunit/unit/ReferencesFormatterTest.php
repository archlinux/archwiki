<?php

namespace Cite\Tests\Unit;

use Cite\AnchorFormatter;
use Cite\ErrorReporter;
use Cite\ReferenceMessageLocalizer;
use Cite\ReferencesFormatter;
use Message;
use Parser;
use Wikimedia\TestingAccessWrapper;

/**
 * @coversDefaultClass \Cite\ReferencesFormatter
 *
 * @license GPL-2.0-or-later
 */
class ReferencesFormatterTest extends \MediaWikiUnitTestCase {

	/**
	 * @covers ::__construct
	 * @covers ::formatReferences
	 * @covers ::formatRefsList
	 * @dataProvider provideFormatReferences
	 */
	public function testFormatReferences( array $refs, string $expectedOutput ) {
		$mockParser = $this->createNoOpMock( Parser::class, [ 'recursiveTagParse' ] );
		$mockParser->method( 'recursiveTagParse' )->willReturnArgument( 0 );

		$mockErrorReporter = $this->createMock( ErrorReporter::class );
		$mockErrorReporter->method( 'plain' )->willReturnCallback(
			static function ( $parser, ...$args ) {
				return '(' . implode( '|', $args ) . ')';
			}
		);

		$mockMessageLocalizer = $this->createMock( ReferenceMessageLocalizer::class );
		$mockMessageLocalizer->method( 'msg' )->willReturnCallback(
			function ( ...$args ) {
				$msg = $this->createMock( Message::class );
				$msg->method( 'plain' )->willReturn( '<li>(' . implode( '|', $args ) . ')</li>' );
				return $msg;
			}
		);

		$formatter = new ReferencesFormatter(
			$mockErrorReporter,
			$this->createMock( AnchorFormatter::class ),
			$mockMessageLocalizer
		);

		$output = $formatter->formatReferences( $mockParser, $refs, true, false );
		$this->assertSame( $expectedOutput, $output );
	}

	public static function provideFormatReferences() {
		return [
			'Empty' => [
				[],
				''
			],
			'Minimal ref' => [
				[
					0 => [
						'key' => 1,
						'text' => 't',
					]
				],
				'<div class="mw-references-wrap"><ol class="references">' . "\n" .
					'<li>(cite_references_link_many|||<span class="reference-text">t</span>' .
					"\n|)</li>\n</ol></div>"
			],
			'Ref with extends' => [
				[
					0 => [
						'extends' => 'a',
						'extendsIndex' => 1,
						'key' => 2,
						'number' => 10,
						'text' => 't2',
					],
					1 => [
						'number' => 11,
						'text' => 't3',
					],
					'a' => [
						'key' => 1,
						'number' => 9,
						'text' => 't1',
					],
				],
				'<div class="mw-references-wrap"><ol class="references">' . "\n" .
					'<li>(cite_references_link_many|||<span class="reference-text">t1</span>' . "\n" .
					'|)<ol class="mw-extended-references"><li>(cite_references_link_many|||' .
					'<span class="reference-text">t2</span>' . "\n|)</li>\n" .
					"</ol></li>\n" .
					'<li>(cite_references_link_many|||<span class="reference-text">t3</span>' .
					"\n|)</li>\n" .
					'</ol></div>'
			],
			'Subref of subref' => [
				[
					0 => [
						'extends' => 'a',
						'extendsIndex' => 1,
						'key' => 1,
						'number' => 1,
						'text' => 't1',
					],
					'a' => [
						'extends' => 'b',
						'extendsIndex' => 1,
						'key' => 2,
						'number' => 1,
						'text' => 't2',
					],
					'b' => [
						'key' => 3,
						'number' => 1,
						'text' => 't3',
					],
				],
				'<div class="mw-references-wrap"><ol class="references">' . "\n" .
					'<li>(cite_references_link_many|||<span class="reference-text">t3</span>' . "\n" .
					'|)<ol class="mw-extended-references"><li>(cite_references_link_many|||' .
					'<span class="reference-text">t1 (cite_error_ref_nested_extends|a|b)</span>' .
					"\n|)</li>\n" .
					'<li>(cite_references_link_many|||<span class="reference-text">t2</span>' .
					"\n|)</li>\n</ol></li>\n" .
					'</ol></div>'
			],
			'Use columns' => [
				array_map(
					static function ( $i ) {
						return [ 'key' => $i, 'text' => 't' ];
					},
					range( 0, 10 )
				),
				'<div class="mw-references-wrap mw-references-columns"><ol class="references">' .
					"\n" . '<li>(cite_references_link_many|||<span class="reference-text">t</span>' .
					"\n|)</li>\n" .
					'<li>(cite_references_link_many|||<span class="reference-text">t</span>' .
					"\n|)</li>\n" .
					'<li>(cite_references_link_many|||<span class="reference-text">t</span>' .
					"\n|)</li>\n" .
					'<li>(cite_references_link_many|||<span class="reference-text">t</span>' .
					"\n|)</li>\n" .
					'<li>(cite_references_link_many|||<span class="reference-text">t</span>' .
					"\n|)</li>\n" .
					'<li>(cite_references_link_many|||<span class="reference-text">t</span>' .
					"\n|)</li>\n" .
					'<li>(cite_references_link_many|||<span class="reference-text">t</span>' .
					"\n|)</li>\n" .
					'<li>(cite_references_link_many|||<span class="reference-text">t</span>' .
					"\n|)</li>\n" .
					'<li>(cite_references_link_many|||<span class="reference-text">t</span>' .
					"\n|)</li>\n" .
					'<li>(cite_references_link_many|||<span class="reference-text">t</span>' .
					"\n|)</li>\n" .
					'<li>(cite_references_link_many|||<span class="reference-text">t</span>' .
					"\n|)</li>\n</ol></div>"
			],
		];
	}

	/**
	 * @covers ::closeIndention
	 * @dataProvider provideCloseIndention
	 */
	public function testCloseIndention( $closingLi, $expectedOutput ) {
		/** @var ReferencesFormatter $formatter */
		$formatter = TestingAccessWrapper::newFromObject( new ReferencesFormatter(
			$this->createMock( ErrorReporter::class ),
			$this->createMock( AnchorFormatter::class ),
			$this->createMock( ReferenceMessageLocalizer::class )
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
	 * @covers ::formatListItem
	 * @dataProvider provideFormatListItem
	 */
	public function testFormatListItem(
		$key,
		array $val,
		string $expectedOutput
	) {
		$mockErrorReporter = $this->createMock( ErrorReporter::class );
		$mockErrorReporter->method( 'plain' )->willReturnCallback(
			static function ( $parser, ...$args ) {
				return '(' . implode( '|', $args ) . ')';
			}
		);

		$anchorFormatter = $this->createMock( AnchorFormatter::class );
		$anchorFormatter->method( 'refKey' )->willReturnCallback(
			static function ( ...$args ) {
				return implode( '+', $args );
			}
		);
		$anchorFormatter->method( 'getReferencesKey' )->willReturnArgument( 0 );

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

		/** @var ReferencesFormatter $formatter */
		$formatter = TestingAccessWrapper::newFromObject( new ReferencesFormatter(
			$mockErrorReporter,
			$anchorFormatter,
			$mockMessageLocalizer
		) );

		$parser = $this->createNoOpMock( Parser::class );
		$output = $formatter->formatListItem( $parser, $key, $val, false );
		$this->assertSame( $expectedOutput, $output );
	}

	public static function provideFormatListItem() {
		return [
			'Success' => [
				1,
				[
					'text' => 't',
				],
				'(cite_references_link_many|1-||<span class="reference-text">t</span>' . "\n|)"
			],
			'With dir' => [
				1,
				[
					'dir' => 'rtl',
					'text' => 't',
				],
				'(cite_references_link_many|1-||<span class="reference-text">t</span>' .
					"\n" . '| class="mw-cite-dir-rtl")'
			],
			'Incomplete follow' => [
				1,
				[
					'follow' => 'f',
					'text' => 't',
				],
				"<p id=\"f\"><span class=\"reference-text\">t</span>\n</p>"
			],
			'Count zero' => [
				1,
				[
					'count' => 0,
					'key' => 5,
					'text' => 't',
				],
				'(cite_references_link_one|1-5|1+5-0|<span class="reference-text">t</span>' . "\n|)"
			],
			'Count negative' => [
				1,
				[
					'count' => -1,
					'key' => 5,
					'number' => 3,
					'text' => 't',
				],
				'(cite_references_link_one|5|5+|<span class="reference-text">t</span>' . "\n|)"
			],
			'Count positive' => [
				1,
				[
					'count' => 2,
					'key' => 5,
					'number' => 3,
					'text' => 't',
				],
				'(cite_references_link_many|1-5|(cite_references_link_many_format|1+5-0|3.0|' .
				'(cite_references_link_many_format_backlink_labels))' .
				'(cite_references_link_many_sep)(cite_references_link_many_format|1+5-1|3.1|' .
				'(cite_error_references_no_backlink_label))(cite_references_link_many_and)' .
				'(cite_references_link_many_format|1+5-2|3.2|(cite_error_references_no_backlink_label' .
				'))|<span class="reference-text">t</span>' . "\n|)"
			],
		];
	}

	/**
	 * @covers ::referenceText
	 * @dataProvider provideReferenceText
	 */
	public function testReferenceText(
		$key,
		?string $text,
		bool $isSectionPreview,
		string $expectedOutput
	) {
		$mockErrorReporter = $this->createMock( ErrorReporter::class );
		$mockErrorReporter->method( 'plain' )->willReturnCallback(
			static function ( $parser, ...$args ) {
				return '(' . implode( '|', $args ) . ')';
			}
		);

		/** @var ReferencesFormatter $formatter */
		$formatter = TestingAccessWrapper::newFromObject( new ReferencesFormatter(
			$mockErrorReporter,
			$this->createMock( AnchorFormatter::class ),
			$this->createMock( ReferenceMessageLocalizer::class )
		) );

		$parser = $this->createNoOpMock( Parser::class );
		$output = $formatter->referenceText( $parser, $key, $text, $isSectionPreview );
		$this->assertSame( $expectedOutput, $output );
	}

	public static function provideReferenceText() {
		return [
			'No text, not preview' => [
				1,
				null,
				false,
				'(cite_error_references_no_text|1)'
			],
			'No text, is preview' => [
				1,
				null,
				true,
				'(cite_warning_sectionpreview_no_text|1)'
			],
			'Has text' => [
				1,
				'text',
				true,
				'<span class="reference-text">text</span>' . "\n"
			],
			'Trims text' => [
				1,
				"text\n\n",
				true,
				'<span class="reference-text">text</span>' . "\n"
			],
		];
	}

	/**
	 * @covers ::referencesFormatEntryAlternateBacklinkLabel
	 * @dataProvider provideReferencesFormatEntryAlternateBacklinkLabel
	 */
	public function testReferencesFormatEntryAlternateBacklinkLabel(
		?string $expectedLabel, ?string $labelList, int $offset
	) {
		$mockMessage = $this->createMock( Message::class );
		$mockMessage->method( 'exists' )->willReturn( (bool)$labelList );
		$mockMessage->method( 'plain' )->willReturn( $labelList ?? '<missing-junk>' );

		$mockMessageLocalizer = $this->createMock( ReferenceMessageLocalizer::class );
		$mockMessageLocalizer->method( 'msg' )
			->willReturn( $mockMessage );

		$mockErrorReporter = $this->createMock( ErrorReporter::class );
		if ( $expectedLabel === null ) {
			$mockErrorReporter->expects( $this->once() )->method( 'plain' );
		} else {
			$mockErrorReporter->expects( $this->never() )->method( 'plain' );
		}

		/** @var ReferencesFormatter $formatter */
		$formatter = TestingAccessWrapper::newFromObject( new ReferencesFormatter(
			$mockErrorReporter,
			$this->createMock( AnchorFormatter::class ),
			$mockMessageLocalizer
		) );

		$parser = $this->createNoOpMock( Parser::class );
		$label = $formatter->referencesFormatEntryAlternateBacklinkLabel( $parser, $offset );
		if ( $expectedLabel !== null ) {
			$this->assertSame( $expectedLabel, $label );
		}
	}

	public static function provideReferencesFormatEntryAlternateBacklinkLabel() {
		yield [ 'aa', 'aa ab ac', 0 ];
		yield [ 'ab', 'aa ab ac', 1 ];
		yield [ 'å', 'å b c', 0 ];
		yield [ null, 'a b c', 10 ];
	}

	/**
	 * @covers ::referencesFormatEntryNumericBacklinkLabel
	 * @dataProvider provideReferencesFormatEntryNumericBacklinkLabel
	 */
	public function testReferencesFormatEntryNumericBacklinkLabel(
		string $expectedLabel, int $base, int $offset, int $max
	) {
		$mockMessageLocalizer = $this->createMock( ReferenceMessageLocalizer::class );
		$mockMessageLocalizer->method( 'localizeSeparators' )->willReturnArgument( 0 );
		$mockMessageLocalizer->method( 'localizeDigits' )->willReturnArgument( 0 );

		/** @var ReferencesFormatter $formatter */
		$formatter = TestingAccessWrapper::newFromObject( new ReferencesFormatter(
			$this->createMock( ErrorReporter::class ),
			$this->createMock( AnchorFormatter::class ),
			$mockMessageLocalizer
		) );

		$label = $formatter->referencesFormatEntryNumericBacklinkLabel( $base, $offset, $max );
		$this->assertSame( $expectedLabel, $label );
	}

	public static function provideReferencesFormatEntryNumericBacklinkLabel() {
		yield [ '1.2', 1, 2, 9 ];
		yield [ '1.02', 1, 2, 99 ];
		yield [ '1.002', 1, 2, 100 ];
		yield [ '1.50005', 1, 50005, 50005 ];
		yield [ '2.1', 2, 1, 1 ];
	}

	/**
	 * @covers ::listToText
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

		/** @var ReferencesFormatter $formatter */
		$formatter = TestingAccessWrapper::newFromObject( new ReferencesFormatter(
			$this->createMock( ErrorReporter::class ),
			$this->createMock( AnchorFormatter::class ),
			$mockMessageLocalizer
		) );
		$this->assertSame( $expected, $formatter->listToText( $list ) );
	}

	public static function provideLists() {
		return [
			[
				[],
				''
			],
			[
				// This is intentionally using numbers to test the to-string cast
				[ 1 ],
				'1'
			],
			[
				[ 1, 2 ],
				'1(cite_references_link_many_and)2'
			],
			[
				[ 1, 2, 3 ],
				'1(cite_references_link_many_sep)2(cite_references_link_many_and)3'
			],
		];
	}

}
