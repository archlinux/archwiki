<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\CheckUser\HookHandler\GlobalPreferencesHandler;
use MediaWiki\CheckUser\HookHandler\Preferences;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 * @covers \MediaWiki\CheckUser\HookHandler\GlobalPreferencesHandler
 */
class GlobalPreferencesHandlerTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );
	}

	/** @dataProvider providePreferences */
	public function testOnGlobalPreferencesSetGlobalPreferences(
		array $oldPreferences,
		array $newPreferences,
		string $expectedLogMethod
	) {
		$user = $this->createMock( User::class );

		$logger = $this->createMock( TemporaryAccountLogger::class );
		foreach ( [
			'logGlobalAccessEnabled',
			'logGlobalAccessDisabled',
			'logAutoRevealAccessEnabled',
			'logAutoRevealAccessDisabled',
		] as $logMethod ) {
			if ( $expectedLogMethod === $logMethod ) {
				$logger->expects( $this->once() )
					->method( $logMethod );
			} else {
				$logger->expects( $this->never() )
					->method( $logMethod );
			}
		}

		$loggerFactory = $this->createMock( TemporaryAccountLoggerFactory::class );
		$loggerFactory->method( 'getLogger' )
			->willReturn( $logger );

		$this->setUserLang( 'qqx' );
		ConvertibleTimestamp::setFakeTime( '20240405060709' );
		( new GlobalPreferencesHandler( $loggerFactory ) )
			->onGlobalPreferencesSetGlobalPreferences( $user, $oldPreferences, $newPreferences );

		ConvertibleTimestamp::setFakeTime( false );
	}

	public static function providePreferences() {
		ConvertibleTimestamp::setFakeTime( '20240405060709' );
		$timeNow = ConvertibleTimestamp::time();
		$futureTimestamp = $timeNow + 10000;
		$pastTimestamp = $timeNow - 10000;
		ConvertibleTimestamp::setFakeTime( false );
		return [
			'IP reveal not in preferences' => [ [], [], '' ],
			'IP reveal made global preference but not enabled' => [
				[],
				[ 'checkuser-temporary-account-enable' => '0' ],
				''
			],
			'IP reveal made global preference and enabled' => [
				[],
				[ 'checkuser-temporary-account-enable' => '1' ],
				'logGlobalAccessEnabled'
			],
			'IP reveal starts disabled then removed from global preferences' => [
				[ 'checkuser-temporary-account-enable' => '0' ],
				[],
				''
			],
			'IP reveal starts enabled then removed from global preferences' => [
				[ 'checkuser-temporary-account-enable' => '1' ],
				[],
				'logGlobalAccessDisabled'
			],
			'IP reveal global preference starts enabled then disabled' => [
				[ 'checkuser-temporary-account-enable' => '1' ],
				[ 'checkuser-temporary-account-enable' => '0' ],
				'logGlobalAccessDisabled'
			],
			'IP reveal global preference starts disabled then enabled' => [
				[ 'checkuser-temporary-account-enable' => '0' ],
				[ 'checkuser-temporary-account-enable' => '1' ],
				'logGlobalAccessEnabled'
			],
			'IP reveal global preference starts enabled and not changed' => [
				[ 'checkuser-temporary-account-enable' => '1' ],
				[ 'checkuser-temporary-account-enable' => '1' ],
				''
			],
			'IP reveal global preference starts disabled and not changed' => [
				[ 'checkuser-temporary-account-enable' => '0' ],
				[ 'checkuser-temporary-account-enable' => '0' ],
				''
			],
			'IP auto-reveal global preference starts enabled then disabled' => [
				[ Preferences::ENABLE_IP_AUTO_REVEAL => $futureTimestamp ],
				[ Preferences::ENABLE_IP_AUTO_REVEAL => 0 ],
				'logAutoRevealAccessDisabled'
			],
			'IP reveal global preference starts enabled then expiry extended' => [
				[ Preferences::ENABLE_IP_AUTO_REVEAL => $futureTimestamp ],
				[ Preferences::ENABLE_IP_AUTO_REVEAL => $futureTimestamp + 600 ],
				'logAutoRevealAccessEnabled'
			],
			'IP auto-reveal global preference starts disabled then enabled' => [
				[ Preferences::ENABLE_IP_AUTO_REVEAL => 0 ],
				[ Preferences::ENABLE_IP_AUTO_REVEAL => $futureTimestamp ],
				'logAutoRevealAccessEnabled'
			],
			'IP auto-reveal global preference starts disabled (with a past timestamp) then enabled' => [
				[ Preferences::ENABLE_IP_AUTO_REVEAL => $pastTimestamp ],
				[ Preferences::ENABLE_IP_AUTO_REVEAL => $futureTimestamp ],
				'logAutoRevealAccessEnabled'
			],
			'IP reveal global preference starts enabled and not changed' => [
				[ Preferences::ENABLE_IP_AUTO_REVEAL => $futureTimestamp ],
				[ Preferences::ENABLE_IP_AUTO_REVEAL => $futureTimestamp ],
				''
			],
			'IP reveal global preference starts disabled and not changed' => [
				[ Preferences::ENABLE_IP_AUTO_REVEAL => 0 ],
				[ Preferences::ENABLE_IP_AUTO_REVEAL => 0 ],
				''
			],
		];
	}
}
