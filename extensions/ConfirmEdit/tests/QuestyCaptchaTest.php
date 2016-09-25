<?php

class QuestyCaptchaTest extends MediaWikiTestCase {
	/**
	 * @covers QuestyCaptcha::getCaptcha
	 * @dataProvider provideGetCaptcha
	 */
	public function testGetCaptcha( $config, $expected ) {

		# setMwGlobals() requires $wgCaptchaQuestion to be set
		if ( !isset( $GLOBALS['wgCaptchaQuestions'] ) ) {
			$GLOBALS['wgCaptchaQuestions'] = [];
		}
		$this->setMwGlobals( 'wgCaptchaQuestions', $config );
		$this->mergeMwGlobalArrayValue(
			'wgAutoloadClasses',
			[ 'QuestyCaptcha' => __DIR__ . '/../QuestyCaptcha/QuestyCaptcha.class.php' ]
		);

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
