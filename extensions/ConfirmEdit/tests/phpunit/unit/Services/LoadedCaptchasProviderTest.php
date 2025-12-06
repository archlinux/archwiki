<?php

namespace MediaWiki\Extension\ConfirmEdit\Tests\Unit\Services;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\ConfirmEdit\Services\LoadedCaptchasProvider;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\ConfirmEdit\Services\LoadedCaptchasProvider
 */
class LoadedCaptchasProviderTest extends MediaWikiUnitTestCase {
	/** @dataProvider provideGetLoadedCaptchas */
	public function testGetLoadedCaptchas( array $config, array $expected ): void {
		$objectUnderTest = $this->getMockBuilder( LoadedCaptchasProvider::class )
			->setConstructorArgs( [ new ServiceOptions( LoadedCaptchasProvider::CONSTRUCTOR_OPTIONS, $config ) ] )
			->onlyMethods( [ 'runningInTestContext' ] )
			->getMock();
		$this->assertArrayEquals( $expected, $objectUnderTest->getLoadedCaptchas() );
	}

	public static function provideGetLoadedCaptchas(): array {
		return [
			'Captchas are only defined using $wgCaptchaClass' => [
				[ 'ConfirmEditLoadedCaptchas' => [], 'CaptchaClass' => 'SimpleCaptcha', 'CaptchaTriggers' => [] ],
				[ 'SimpleCaptcha' ],
			],
			'Captchas are defined using $wgCaptchaClass and $wgConfirmEditLoadedCaptchas' => [
				[
					'ConfirmEditLoadedCaptchas' => [ 'HCaptcha' ],
					'CaptchaClass' => 'SimpleCaptcha', 'CaptchaTriggers' => [],
				],
				[ 'SimpleCaptcha', 'HCaptcha' ],
			],
			'$wgCaptchaTriggers contains definitions but no additional classes' => [
				[
					'ConfirmEditLoadedCaptchas' => [], 'CaptchaClass' => 'SimpleCaptcha',
					'CaptchaTriggers' => [ 'edit' => true, 'accountcreation' => true ],
				],
				[ 'SimpleCaptcha' ],
			],
			'$wgCaptchaTriggers defines captcha classes already defined in other config' => [
				[
					'ConfirmEditLoadedCaptchas' => [ 'QuestyCaptcha' ], 'CaptchaClass' => 'SimpleCaptcha',
					'CaptchaTriggers' => [
						'edit' => [ 'trigger' => true, 'class' => 'QuestyCaptcha' ],
						'accountcreation' => true,
					],
				],
				[ 'SimpleCaptcha', 'QuestyCaptcha' ],
			],
			'$wgCaptchaTriggers defines captcha classes not already defined in other config' => [
				[
					'ConfirmEditLoadedCaptchas' => [ 'QuestyCaptcha' ], 'CaptchaClass' => 'SimpleCaptcha',
					'CaptchaTriggers' => [
						'edit' => [ 'trigger' => true, 'class' => 'ReCaptchaNoCaptcha' ],
						'accountcreation' => true,
					],
				],
				[ 'SimpleCaptcha', 'QuestyCaptcha', 'ReCaptchaNoCaptcha' ],
			],
		];
	}

	public function testGetLoadedCaptchasWhenInTestContext() {
		$objectUnderTest = new LoadedCaptchasProvider(
			new ServiceOptions(
				LoadedCaptchasProvider::CONSTRUCTOR_OPTIONS,
				[ 'ConfirmEditLoadedCaptchas' => [], 'CaptchaClass' => '', 'CaptchaTriggers' => [] ]
			)
		);
		$this->assertArrayEquals(
			[ 'SimpleCaptcha', 'FancyCaptcha', 'QuestyCaptcha', 'ReCaptchaNoCaptcha', 'HCaptcha', 'Turnstile' ],
			$objectUnderTest->getLoadedCaptchas()
		);
	}
}
