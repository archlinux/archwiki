<?php

namespace Cite\Tests\Integration;

use Cite\AnchorFormatter;
use Cite\ErrorReporter;
use Cite\FootnoteMarkFormatter;
use Cite\ReferenceMessageLocalizer;
use Message;
use Parser;
use Wikimedia\TestingAccessWrapper;

/**
 * @coversDefaultClass \Cite\FootnoteMarkFormatter
 */
class FootnoteMarkFormatterTest extends \MediaWikiIntegrationTestCase {

	/**
	 * @covers ::linkRef
	 * @covers ::__construct
	 * @dataProvider provideLinkRef
	 */
	public function testLinkRef( string $group, array $ref, string $expectedOutput ) {
		$fooLabels = 'a b c';

		$mockErrorReporter = $this->createMock( ErrorReporter::class );
		$mockErrorReporter->method( 'plain' )->willReturnCallback(
			static function ( $parser, ...$args ) {
				return implode( '|', $args );
			}
		);
		$anchorFormatter = $this->createMock( AnchorFormatter::class );
		$anchorFormatter->method( 'getReferencesKey' )->willReturnArgument( 0 );
		$anchorFormatter->method( 'refKey' )->willReturnCallback(
			static function ( ...$args ) {
				return implode( '+', $args );
			}
		);
		$mockMessageLocalizer = $this->createMock( ReferenceMessageLocalizer::class );
		$mockMessageLocalizer->method( 'localizeSeparators' )->willReturnArgument( 0 );
		$mockMessageLocalizer->method( 'localizeDigits' )->willReturnArgument( 0 );
		$mockMessageLocalizer->method( 'msg' )->willReturnCallback(
			function ( ...$args ) use ( $group, $fooLabels ) {
				$msg = $this->createMock( Message::class );
				$msg->method( 'isDisabled' )->willReturn( $group !== 'foo' );
				$msg->method( 'plain' )->willReturn( $args[0] === 'cite_reference_link'
					? '(' . implode( '|', $args ) . ')'
					: $fooLabels );
				return $msg;
			}
		);
		$mockParser = $this->createNoOpMock( Parser::class, [ 'recursiveTagParse' ] );
		$mockParser->method( 'recursiveTagParse' )->willReturnArgument( 0 );
		$formatter = new FootnoteMarkFormatter(
			$mockErrorReporter,
			$anchorFormatter,
			$mockMessageLocalizer
		);

		$output = $formatter->linkRef( $mockParser, $group, $ref );
		$this->assertSame( $expectedOutput, $output );
	}

	public static function provideLinkRef() {
		return [
			'Default label' => [
				'',
				[
					'name' => null,
					'number' => 50003,
					'key' => 50004,
				],
				'(cite_reference_link|50004+|50004|50003)'
			],
			'Default label, named group' => [
				'bar',
				[
					'name' => null,
					'number' => 3,
					'key' => 4,
				],
				'(cite_reference_link|4+|4|bar 3)'
			],
			'Custom label' => [
				'foo',
				[
					'name' => null,
					'number' => 3,
					'key' => 4,
				],
				'(cite_reference_link|4+|4|c)'
			],
			'Custom label overrun' => [
				'foo',
				[
					'name' => null,
					'number' => 10,
					'key' => 4,
				],
				'(cite_reference_link|4+|4|' .
					'cite_error_no_link_label_group&#124;foo&#124;cite_link_label_group-foo)'
			],
			'Named ref' => [
				'',
				[
					'name' => 'a',
					'number' => 3,
					'key' => 4,
					// Count is only meaningful on named refs; 0 means not reused
					'count' => 0,
				],
				'(cite_reference_link|a+4-0|a-4|3)'
			],
			'Named ref reused' => [
				'',
				[
					'name' => 'a',
					'number' => 3,
					'key' => 4,
					'count' => 50002,
				],
				'(cite_reference_link|a+4-50002|a-4|3)'
			],
			'Subreference' => [
				'',
				[
					'name' => null,
					'number' => 3,
					'key' => 4,
					'extends' => 'b',
					'extendsIndex' => 50002,
				],
				'(cite_reference_link|4+|4|3.50002)'
			],
		];
	}

	/**
	 * @covers ::getLinkLabel
	 *
	 * @dataProvider provideGetLinkLabel
	 */
	public function testGetLinkLabel( $expectedLabel, $offset, $group, $labelList ) {
		$mockMessageLocalizer = $this->createMock( ReferenceMessageLocalizer::class );
		$mockMessageLocalizer->method( 'msg' )->willReturnCallback(
			function ( ...$args ) use ( $labelList ) {
				$msg = $this->createMock( Message::class );
				$msg->method( 'isDisabled' )->willReturn( $labelList === null );
				$msg->method( 'plain' )->willReturn( $labelList );
				return $msg;
			}
		);
		$mockErrorReporter = $this->createMock( ErrorReporter::class );
		$mockErrorReporter->method( 'plain' )->willReturnCallback(
			static function ( $parser, ...$args ) {
				return implode( '|', $args );
			}
		);
		/** @var FootnoteMarkFormatter $formatter */
		$formatter = TestingAccessWrapper::newFromObject( new FootnoteMarkFormatter(
			$mockErrorReporter,
			$this->createMock( AnchorFormatter::class ),
			$mockMessageLocalizer
		) );

		$parser = $this->createNoOpMock( Parser::class );
		$output = $formatter->getLinkLabel( $parser, $group, $offset );
		$this->assertSame( $expectedLabel, $output );
	}

	public static function provideGetLinkLabel() {
		yield [ null, 1, '', null ];
		yield [ null, 2, '', null ];
		yield [ null, 1, 'foo', null ];
		yield [ null, 2, 'foo', null ];
		yield [ 'a', 1, 'foo', 'a b c' ];
		yield [ 'b', 2, 'foo', 'a b c' ];
		yield [ 'å', 1, 'foo', 'å β' ];
		yield [ 'cite_error_no_link_label_group|foo|cite_link_label_group-foo', 4, 'foo', 'a b c' ];
	}

}
