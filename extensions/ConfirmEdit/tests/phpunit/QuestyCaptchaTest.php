<?php

use MediaWiki\Extension\ConfirmEdit\QuestyCaptcha\QuestyCaptcha;

/**
 * @covers \MediaWiki\Extension\ConfirmEdit\QuestyCaptcha\QuestyCaptcha
 */
class QuestyCaptchaTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideGetCaptcha
	 */
	public function testGetCaptcha( $config, $expected ) {
		$this->overrideConfigValue( 'CaptchaQuestions', $config );

		$qc = new QuestyCaptcha();
		$this->assertEquals( $expected, $qc->getCaptcha() );
	}

	public static function provideGetCaptcha() {
		return [
			[
				[
					[
						'question' => 'FooBar',
						'answer' => 'Answer!',
					],
				],
				[
					'question' => 'FooBar',
					'answer' => 'Answer!',
				],
			],
			[
				[
					'FooBar' => 'Answer!',
				],
				[
					'question' => 'FooBar',
					'answer' => 'Answer!',
				],
			]
		];
	}
}
