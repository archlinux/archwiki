<?php

class QuestyCaptchaTest extends MediaWikiTestCase {
	/**
	 * @covers QuestyCaptcha::getCaptcha
	 * @dataProvider provideGetCaptcha
	 */
	public function testGetCaptcha( $config, $expected ) {

		# setMwGlobals() requires $wgCaptchaQuestion to be set
		if ( !isset( $GLOBALS['wgCaptchaQuestions'] ) ) {
			$GLOBALS['wgCaptchaQuestions'] = array();
		}
		$this->setMwGlobals( 'wgCaptchaQuestions', $config );
		$this->mergeMwGlobalArrayValue(
			'wgAutoloadClasses',
			array( 'QuestyCaptcha' => __DIR__ . '/../QuestyCaptcha/QuestyCaptcha.class.php' )
		);

		$qc = new QuestyCaptcha();
		$this->assertEquals( $expected, $qc->getCaptcha() );
	}

	public static function provideGetCaptcha() {
		return array(
			array(
				array(
					array(
						'question' => 'FooBar',
						'answer' => 'Answer!',
					),
				),
				array(
					'question' => 'FooBar',
					'answer' => 'Answer!',
				),
			),
			array(
				array(
					'FooBar' => 'Answer!',
				),
				array(
					'question' => 'FooBar',
					'answer' => 'Answer!',
				),
			)
		);
	}
}
