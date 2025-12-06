<?php

namespace Cite\Tests;

use Cite\Cite;
use Cite\ErrorReporter;
use Cite\ReferenceListFormatter;
use Cite\ReferenceStack;
use MediaWiki\Language\Language;
use MediaWiki\MainConfigNames;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \Cite\Cite
 * @license GPL-2.0-or-later
 */
class CiteIntegrationTest extends \MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( MainConfigNames::LanguageCode, 'qqx' );
	}

	/**
	 * @dataProvider provideCheckRefsNoReferences
	 */
	public function testCheckRefsNoReferences(
		array $initialRefs, bool $isSectionPreview, string $expectedOutput
	) {
		$this->overrideConfigValue( 'CiteResponsiveReferences', true );

		$mockErrorReporter = $this->createMock( ErrorReporter::class );
		$mockErrorReporter->method( 'halfParsed' )->willReturnCallback(
			static fn ( ...$args ) => '(' . implode( '|', $args ) . ')'
		);

		$referenceStack = new ReferenceStack();
		TestingAccessWrapper::newFromObject( $referenceStack )->refs = TestUtils::refGroupsFromArray( $initialRefs );

		$formatter = $this->createMock( ReferenceListFormatter::class );
		$formatter->method( 'formatReferences' )->willReturn( '<references />' );

		$cite = $this->newCite();
		/** @var Cite $spy */
		$spy = TestingAccessWrapper::newFromObject( $cite );
		$spy->referenceStack = $referenceStack;
		$spy->errorReporter = $mockErrorReporter;
		$spy->referenceListFormatter = $formatter;

		$parserOptions = $this->createNoOpMock( ParserOptions::class, [ 'getIsSectionPreview' ] );
		$parserOptions->method( 'getIsSectionPreview' )->willReturn( $isSectionPreview );
		$parser = $this->createNoOpMock( Parser::class, [ 'getOptions' ] );
		$parser->method( 'getOptions' )->willReturn( $parserOptions );

		$output = $cite->checkRefsNoReferences( $parser );
		$this->assertSame( $expectedOutput, $output );
	}

	public static function provideCheckRefsNoReferences() {
		return [
			'Default group' => [
				[ '' => [ [ 'name' => 'a' ] ] ],
				false,
				"\n<references />"
			],
			'Default group in preview' => [
				[ '' => [ [ 'name' => 'a' ] ] ],
				true,
				"\n" . '<div class="mw-ext-cite-cite_section_preview_references">' .
				'<h2 id="mw-ext-cite-cite_section_preview_references_header">' .
				'(cite_section_preview_references)</h2><references /></div>'
			],
			'Named group' => [
				[ 'foo' => [ [ 'name' => 'a' ] ] ],
				false,
				"\n<br />(cite_error_group_refs_without_references|foo)"
			],
			'Named group in preview' => [
				[ 'foo' => [ [ 'name' => 'a' ] ] ],
				true,
				"\n" . '<div class="mw-ext-cite-cite_section_preview_references">' .
				'<h2 id="mw-ext-cite-cite_section_preview_references_header">' .
				'(cite_section_preview_references)</h2><references /></div>'
			]
		];
	}

	private function newCite(): Cite {
		$language = $this->createMock( Language::class );
		$language->method( 'getCode' )->willReturn( 'en' );

		$mockParser = $this->createNoOpMock( Parser::class, [ 'getContentLanguage' ] );
		$mockParser->method( 'getContentLanguage' )->willReturn( $language );
		return $this->getServiceContainer()->getService( 'Cite.CiteFactory' )->newCite( $mockParser );
	}

}
