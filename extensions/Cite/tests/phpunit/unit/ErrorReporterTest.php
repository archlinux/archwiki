<?php

namespace Cite\Tests\Unit;

use Cite\ErrorReporter;
use Cite\ReferenceMessageLocalizer;
use MediaWiki\Language\Language;
use MediaWiki\Message\Message;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;

/**
 * @covers \Cite\ErrorReporter
 * @license GPL-2.0-or-later
 */
class ErrorReporterTest extends \MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideErrors
	 */
	public function testPlain(
		string $key,
		string $expectedHtml,
		?string $expectedTrackingCategory
	) {
		$reporter = $this->createReporter( $expectedTrackingCategory );
		$this->assertSame(
			$expectedHtml,
			$reporter->plain( $key, 'first param' ) );
	}

	public function testDisabledWrapperMessages() {
		$reporter = $this->createReporter( null, true );
		$this->assertSame(
			'<span class="warning mw-ext-cite-warning mw-ext-cite-warning-a" lang="qqx" ' .
				'dir="rtl">(cite_warning_a)</span>',
			$reporter->plain( 'cite_warning_a' )
		);
	}

	public function testHalfParsed() {
		$reporter = $this->createReporter();
		$this->assertSame(
			'<span class="warning mw-ext-cite-warning mw-ext-cite-warning-example" lang="qqx" ' .
				'dir="rtl">[(cite_warning_example|first param)]</span>',
			$reporter->halfParsed( 'cite_warning_example', 'first param' ) );
	}

	public static function provideErrors() {
		return [
			'Example error' => [
				'key' => 'cite_error_example',
				'expectedHtml' => '<span class="error mw-ext-cite-error" lang="qqx" dir="rtl">' .
					'(cite_error|(cite_error_example|first param))</span>',
				'expectedTrackingCategory' => 'cite-tracking-category-cite-error',
			],
			'Warning error' => [
				'key' => 'cite_warning_example',
				'expectedHtml' => '<span class="warning mw-ext-cite-warning mw-ext-cite-warning-example" lang="qqx" ' .
					'dir="rtl">(cite_warning_example|first param)</span>',
				'expectedTrackingCategory' => null,
			],
			'Optional support for messages with dashes' => [
				'key' => 'cite-warning-with-dashes',
				'expectedHtml' => '<span class="warning mw-ext-cite-warning ' .
					'mw-ext-cite-warning-with-dashes" lang="qqx" dir="rtl">' .
					'(cite-warning-with-dashes|first param)</span>',
				'expectedTrackingCategory' => null,
			],
		];
	}

	private function createLanguage(): Language {
		$language = $this->createNoOpMock( Language::class, [ 'getDir', 'getHtmlCode' ] );
		$language->method( 'getDir' )->willReturn( 'rtl' );
		$language->method( 'getHtmlCode' )->willReturn( 'qqx' );
		return $language;
	}

	private function createReporter(
		?string $expectedTrackingCategory = null,
		bool $allMessagesDisabled = false
	): ErrorReporter {
		$language = $this->createLanguage();
		$mockParser = $this->createParser( $language, $expectedTrackingCategory );

		$mockMessageLocalizer = $this->createMock( ReferenceMessageLocalizer::class );
		$mockMessageLocalizer->method( 'msg' )->willReturnCallback(
			function ( ...$args ) use ( $language, $allMessagesDisabled ) {
				$message = $this->createMock( Message::class );
				$message->method( 'isDisabled' )->willReturn( $allMessagesDisabled );
				$message->method( 'getKey' )->willReturn( $args[0] );
				$message->method( 'plain' )->willReturn( '(' . implode( '|', $args ) . ')' );
				$message->method( 'inLanguage' )->with( $language )->willReturnSelf();
				$message->method( 'getLanguage' )->willReturn( $language );
				return $message;
			}
		);

		return new ErrorReporter( $mockParser, $mockMessageLocalizer );
	}

	private function createParser(
		Language $language,
		?string $expectedTrackingCategory = null
	): Parser {
		$parserOptions = $this->createMock( ParserOptions::class );
		$parserOptions->method( 'getUserLangObj' )->willReturn( $language );

		$parser = $this->createNoOpMock( Parser::class, [ 'addTrackingCategory', 'getOptions', 'recursiveTagParse' ] );
		$parser->expects( $expectedTrackingCategory ? $this->once() : $this->never() )
			->method( 'addTrackingCategory' )
			->with( $expectedTrackingCategory );
		$parser->method( 'getOptions' )->willReturn( $parserOptions );
		$parser->method( 'recursiveTagParse' )->willReturnCallback(
			static fn ( $content ) => '[' . $content . ']'
		);
		return $parser;
	}

}
