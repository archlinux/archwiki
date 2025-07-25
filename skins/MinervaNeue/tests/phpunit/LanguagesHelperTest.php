<?php

namespace MediaWiki\Minerva;

use MediaWiki\Language\ILanguageConverter;
use MediaWiki\Language\Language;
use MediaWiki\Languages\LanguageConverterFactory;
use MediaWiki\Output\OutputPage;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;

/**
 * @package Tests\MediaWiki\Minerva
 * @group MinervaNeue
 * @coversDefaultClass \MediaWiki\Minerva\LanguagesHelper
 */
class LanguagesHelperTest extends MediaWikiIntegrationTestCase {

	/**
	 * Build test Output object
	 */
	private function getOutput( array $langLinks ): OutputPage {
		$out = $this->createMock( OutputPage::class );
		$out->expects( $this->once() )
			->method( 'getLanguageLinks' )
			->willReturn( $langLinks );

		return $out;
	}

	/**
	 * Build test LanguageConverterFactory object
	 */
	private function getLanguageConverterFactory( bool $hasVariants ): LanguageConverterFactory {
		$langConv = $this->createMock( ILanguageConverter::class );
		$langConv->method( 'hasVariants' )->willReturn( $hasVariants );
		$langConvFactory = $this->createMock( LanguageConverterFactory::class );
		$langConvFactory->method( 'getLanguageConverter' )->willReturn( $langConv );

		return $langConvFactory;
	}

	/**
	 * Build test Title object
	 */
	private function getTitle(): Title {
		$languageMock = $this->createMock( Language::class );
		$title = $this->createMock( Title::class );
		$title->method( 'getPageLanguage' )
			->willReturn( $languageMock );

		return $title;
	}

	/**
	 * @dataProvider provideDoesTitleHasLanguagesOrVariants
	 * @covers ::__construct
	 * @covers ::doesTitleHasLanguagesOrVariants
	 */
	public function testDoesTitleHasLanguagesOrVariants( bool $hasVariants, array $langLinks, bool $expected ) {
		$helper = new LanguagesHelper(
			$this->getLanguageConverterFactory( $hasVariants )
		);

		$this->assertSame( $expected, $helper->doesTitleHasLanguagesOrVariants(
			$this->getOutput( $langLinks ),
			$this->getTitle()
		) );
	}

	public static function provideDoesTitleHasLanguagesOrVariants() {
		return [
			[ false, [ 'pl:StronaTestowa', 'en:TestPage' ], true ],
			[ true, [ 'pl:StronaTestowa', 'en:TestPage' ], true ],
			[ false, [], false ],
			[ true, [], true ],
		];
	}
}
