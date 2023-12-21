<?php

/**
 * @group Language
 * @covers WuuConverter
 */
class WuuConverterTest extends MediaWikiIntegrationTestCase {

	use LanguageConverterTestTrait;

	/**
	 * @dataProvider provideAutoConvertToAllVariants
	 * @covers WuuConverter::autoConvertToAllVariants
	 */
	public function testAutoConvertToAllVariants( $result, $value ) {
		$this->assertEquals( $result, $this->getLanguageConverter()->autoConvertToAllVariants( $value ) );
	}

	public static function provideAutoConvertToAllVariants() {
		return [
			// wuuHant2Hans
			[
				[
					'wuu' => '㑯',
					'wuu-hans' => '㑔',
					'wuu-hant' => '㑯',
				],
				'㑯'
			],
			// wuuHans2Hant
			[
				[
					'wuu' => '㐷',
					'wuu-hans' => '㐷',
					'wuu-hant' => '傌',
				],
				'㐷'
			],
		];
	}
}
