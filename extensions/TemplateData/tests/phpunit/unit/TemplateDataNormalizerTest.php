<?php

use MediaWiki\Extension\TemplateData\TemplateDataNormalizer;

/**
 * @covers \MediaWiki\Extension\TemplateData\TemplateDataNormalizer
 * @license GPL-2.0-or-later
 */
class TemplateDataNormalizerTest extends MediaWikiUnitTestCase {

	/**
	 * @dataProvider provideNormalizations
	 */
	public function test( array $data, array $expected ) {
		$normalizer = new TemplateDataNormalizer( 'qqx' );
		$data = (object)$data;
		$normalizer->normalize( $data );
		$data = json_decode( json_encode( $data ), true );
		$this->assertSame( $expected, $data );
	}

	public static function provideNormalizations() {
		return [
			'empty' => [
				[],
				[
					'description' => null,
					'params' => [],
					'sets' => [],
					'maps' => [],
					'format' => null,
				],
			],
		];
	}

}
