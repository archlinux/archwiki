<?php

namespace MediaWiki\CheckUser\Tests\Integration\Services;

use GlobalPreferences\GlobalPreferencesFactory;
use MediaWiki\CheckUser\HookHandler\Preferences;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\CheckUser\Services\CheckUserTemporaryAccountAutoRevealLookup;
use MediaWiki\Preferences\PreferencesFactory;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\CheckUser\Services\CheckUserTemporaryAccountAutoRevealLookup
 */
class CheckUserTemporaryAccountAutoRevealLookupTest extends MediaWikiIntegrationTestCase {
	use MockAuthorityTrait;

	/** @dataProvider provideIsAutoRevealAvailable */
	public function testIsAutoRevealAvailable( $usesGlobalPreferencesFactory ) {
		if ( $usesGlobalPreferencesFactory ) {
			$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );
			$mockPreferencesFactory = $this->createMock( GlobalPreferencesFactory::class );
		} else {
			$mockPreferencesFactory = $this->createMock( PreferencesFactory::class );
		}

		$objectUnderTest = new CheckUserTemporaryAccountAutoRevealLookup(
			$mockPreferencesFactory, $this->createMock( CheckUserPermissionManager::class )
		);
		$this->assertSame( $usesGlobalPreferencesFactory, $objectUnderTest->isAutoRevealAvailable() );
	}

	public static function provideIsAutoRevealAvailable(): array {
		return [
			'GlobalPreferences is installed' => [ true ],
			'GlobalPreferences is not installed' => [ false ],
		];
	}

	/** @dataProvider provideIsAutoRevealExpiryValid */
	public function testIsAutoRevealExpiryValid( $expiry, $isExpiryValid ) {
		ConvertibleTimestamp::setFakeTime( 1234567 );
		/** @var CheckUserTemporaryAccountAutoRevealLookup $objectUnderTest */
		$objectUnderTest = $this->getServiceContainer()->get( 'CheckUserTemporaryAccountAutoRevealLookup' );

		$this->assertSame( $isExpiryValid, $objectUnderTest->isAutoRevealExpiryValid( $expiry ) );
	}

	public static function provideIsAutoRevealExpiryValid(): array {
		return [
			'String which is not numeric' => [ 'abc', false ],
			'Unix timestamp before current time' => [ 1234, false ],
			'Float timestamp before current time' => [ 5678.23, false ],
			'Unix timestamp more than a day in the future' => [ 12345678, false ],
			'Float timestamp more than a day in the future' => [ 12345678.56, false ],
			'Unix timestamp equal to the current time' => [ 1234567, false ],
			'Valid unix timestamp expiry' => [ 1234569, true ],
			'Valid unix timestamp expiry in string format' => [ '1234669', true ],
			'Valid unix timestamp expiry in float format' => [ 1234669.2345, true ],
		];
	}

	/** @dataProvider provideIsAutoRevealOn */
	public function testIsAutoRevealOn(
		bool $usesGlobalPreferencesFactory, bool $userHasAutoRevealRight, int|null $globalPreferenceValue,
		bool $expectedReturnValue
	) {
		ConvertibleTimestamp::setFakeTime( 1234567 );

		if ( $userHasAutoRevealRight ) {
			$authority = $this->mockRegisteredAuthorityWithPermissions( [
				'checkuser-temporary-account-auto-reveal',
				'checkuser-temporary-account-no-preference',
			] );
		} else {
			$authority = $this->mockRegisteredAuthorityWithPermissions( [
				'checkuser-temporary-account-no-preference',
			] );
		}

		if ( $usesGlobalPreferencesFactory ) {
			$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );
			$mockPreferencesFactory = $this->createMock( GlobalPreferencesFactory::class );
			$mockPreferencesFactory->method( 'getGlobalPreferencesValues' )
				->willReturnCallback(
					function ( UserIdentity $user, bool $skipCache ) use ( $authority, $globalPreferenceValue ) {
						$this->assertTrue( $skipCache );
						$this->assertTrue( $authority->getUser()->equals( $user ) );
						return [
							Preferences::ENABLE_IP_AUTO_REVEAL => $globalPreferenceValue,
						];
					}
				);
		} else {
			$mockPreferencesFactory = $this->createMock( PreferencesFactory::class );
		}

		$objectUnderTest = new CheckUserTemporaryAccountAutoRevealLookup(
			$mockPreferencesFactory, $this->getServiceContainer()->get( 'CheckUserPermissionManager' )
		);
		$this->assertSame( $expectedReturnValue, $objectUnderTest->isAutoRevealOn( $authority ) );
	}

	public static function provideIsAutoRevealOn(): array {
		return [
			'GlobalPreferences not installed' => [ false, true, 1234568, false ],
			'User lacks rights to use IP auto-reveal' => [ true, false, 1234568, false ],
			'User has not turned on IP auto-reveal' => [ true, true, null, false ],
			'IP auto-reveal was enabled but expiry is now invalid' => [ true, true, 1234, false ],
			'IP auto-reveal is enabled' => [ true, true, 1234568, true ],
		];
	}
}
