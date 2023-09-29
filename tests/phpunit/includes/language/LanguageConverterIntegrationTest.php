<?php

/**
 * @group Language
 */
class LanguageConverterIntegrationTest extends MediaWikiIntegrationTestCase {

	use LanguageConverterTestTrait;

	/**
	 * @covers LanguageConverter::hasVariant
	 */
	public function testHasVariant() {
		// See LanguageSrTest::testHasVariant() for additional tests
		$converterEn = $this->getLanguageConverter( 'en' );
		$this->assertTrue( $converterEn->hasVariant( 'en' ), 'base is always a variant' );
		$this->assertFalse( $converterEn->hasVariant( 'en-bogus' ), 'bogus en variant' );

		$converterBogus = $this->getLanguageConverter( 'bogus' );
		$this->assertTrue( $converterBogus->hasVariant( 'bogus' ), 'base is always a variant' );
	}
}
