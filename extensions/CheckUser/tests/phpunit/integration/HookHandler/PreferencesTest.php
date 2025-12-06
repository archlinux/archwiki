<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\CheckUser\CheckUserPermissionStatus;
use MediaWiki\CheckUser\HookHandler\Preferences;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\CheckUser\Logging\TemporaryAccountLoggerFactory;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\CheckUser\Services\CheckUserTemporaryAccountAutoRevealLookup;
use MediaWiki\Context\RequestContext;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\HookHandler\Preferences
 */
class PreferencesTest extends MediaWikiIntegrationTestCase {
	/** @var (PermissionManager&MockObject) */
	private PermissionManager $permissionManager;

	/** @var (TemporaryAccountLoggerFactory&MockObject) */
	private TemporaryAccountLoggerFactory $loggerFactory;

	/** @var (User&MockObject) */
	private User $user;

	/** @var (Preferences&MockObject) */
	private Preferences $sut;

	/** @var (CheckUserTemporaryAccountAutoRevealLookup&MockObject) */
	private CheckUserTemporaryAccountAutoRevealLookup $autoRevealLookup;

	/** @var (CheckUserPermissionManager&MockObject) */
	private CheckUserPermissionManager $checkUserPermissionManager;

	public function setUp(): void {
		parent::setUp();

		$this->user = $this->createMock( User::class );
		$this->permissionManager = $this->createMock(
			PermissionManager::class
		);
		$this->loggerFactory = $this->createMock(
			TemporaryAccountLoggerFactory::class
		);
		$this->autoRevealLookup = $this->createMock( CheckUserTemporaryAccountAutoRevealLookup::class );
		$this->checkUserPermissionManager = $this->createMock( CheckUserPermissionManager::class );

		$this->setUserLang( 'qqx' );
		$this->sut = new Preferences(
			$this->permissionManager,
			$this->loggerFactory,
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getUserOptionsLookup(),
			$this->autoRevealLookup,
			$this->checkUserPermissionManager
		);
	}

	/**
	 * @dataProvider provideOnGetPreferencesTemporaryAccount
	 */
	public function testOnGetPreferencesTemporaryAccount( $options ) {
		$prefs = [];

		$this->permissionManager->method( 'userHasRight' )
			->willReturnCallback( static function ( $user, $right ) use ( $options ) {
				if ( $right === 'checkuser-temporary-account' ) {
					return $options['hasRight'];
				}
				if ( $right === 'checkuser-temporary-account-no-preference' ) {
					return $options['hasNoPreferenceRight'];
				}
				if ( $right === 'checkuser' ) {
					return false;
				}
				return true;
			} );
		$this->permissionManager->method( 'userHasAnyRight' )
			->willReturnCallback( static function ( $user, ...$rights ) use ( $options ) {
				foreach ( $rights as $right ) {
					if ( $right === 'checkuser-temporary-account' && $options['hasRight'] ) {
						return true;
					}
					if ( $right === 'checkuser-temporary-account-no-preference' && $options['hasNoPreferenceRight'] ) {
						return true;
					}
				}
				return false;
			} );

		$this->sut->onGetPreferences( $this->user, $prefs );

		// Always expect that the 'temporary accounts onboarding dialog seen' preference is added and that
		// it is a hidden preference.
		$this->assertArrayHasKey( 'checkuser-temporary-accounts-onboarding-dialog-seen', $prefs );
		$this->assertArrayEquals(
			[ 'type' => 'api' ],
			$prefs['checkuser-temporary-accounts-onboarding-dialog-seen'],
		);

		if ( $options['expectPreferenceToExist'] ) {
			$this->assertArrayHasKey( 'checkuser-temporary-account-enable', $prefs );
		} else {
			$this->assertArrayNotHasKey( 'checkuser-temporary-account-enable', $prefs );
		}
		if ( array_key_exists( 'checkuser-temporary-account-enable', $prefs ) ) {
			$this->assertSame(
				$options['expectPreferenceToBeDisplayed'] ? 'toggle' : 'api',
				$prefs['checkuser-temporary-account-enable']['type']
			);
		}
		$this->assertSame(
			$options['expectPreferenceToBeDisplayed'],
			isset( $prefs['checkuser-temporary-account-enable-description'] )
		);
	}

	public static function provideOnGetPreferencesTemporaryAccount() {
		return [
			'User has right' => [
				[
					'expectPreferenceToBeDisplayed' => true,
					'expectPreferenceToExist' => true,
					'hasRight' => true,
					'hasNoPreferenceRight' => false,
				],
			],
			'User has no-preference right' => [
				[
					'expectPreferenceToBeDisplayed' => false,
					'expectPreferenceToExist' => true,
					'hasRight' => false,
					'hasNoPreferenceRight' => true,
				],
			],
			'User does not have right' => [
				[
					'expectPreferenceToBeDisplayed' => false,
					'expectPreferenceToExist' => false,
					'hasRight' => false,
					'hasNoPreferenceRight' => false,
				],
			],
		];
	}

	/** @dataProvider provideOnGetPreferencesForCheckUserRight */
	public function testGetOnPreferencesForCheckUserRight( $siteConfigValue, $expectedSiteConfigValue ) {
		$this->overrideConfigValue( 'CheckUserCollapseCheckUserHelperByDefault', $siteConfigValue );
		$prefs = [];

		$this->permissionManager->method( 'userHasRight' )
			->willReturnCallback( static function ( $user, $right ) {
				return $right === 'checkuser';
			} );

		$this->sut->onGetPreferences( $this->user, $prefs );

		$this->assertArrayHasKey( 'checkuser-helper-table-collapse-by-default', $prefs );
		$actualOptions = $prefs['checkuser-helper-table-collapse-by-default']['options'];
		// Check that the site config option looks correct.
		$actualSiteConfigLabel = array_search(
			Preferences::CHECKUSER_HELPER_USE_CONFIG_TO_COLLAPSE_BY_DEFAULT, $actualOptions
		);
		$this->assertSame(
			"(checkuser-helper-table-collapse-by-default-preference-default: $expectedSiteConfigValue)",
			$actualSiteConfigLabel
		);
		// Now check the other options than the site config option
		unset( $actualOptions[$actualSiteConfigLabel] );
		$expectedOptions = [
			'(checkuser-helper-table-collapse-by-default-preference-never)' =>
				Preferences::CHECKUSER_HELPER_NEVER_COLLAPSE_BY_DEFAULT,
			'(checkuser-helper-table-collapse-by-default-preference-always)' =>
				Preferences::CHECKUSER_HELPER_ALWAYS_COLLAPSE_BY_DEFAULT,
		];
		$expectedNumberOptions = [ 200, 500, 1000, 2500, 5000 ];
		$language = RequestContext::getMain()->getLanguage();
		foreach ( $expectedNumberOptions as $numberOption ) {
			$expectedOptions[$language->formatNum( $numberOption )] = $numberOption;
		}
		$this->assertArrayEquals(
			$expectedOptions,
			$actualOptions,
			false,
			true
		);
	}

	public static function provideOnGetPreferencesForCheckUserRight() {
		return [
			'Site config set to false' => [ false, '(checkuser-helper-table-collapse-by-default-preference-never)' ],
			'Site config set to true' => [ true, '(checkuser-helper-table-collapse-by-default-preference-always)' ],
			'Site config set to 200' => [ 200, '200' ],
		];
	}

	/** @dataProvider provideOnGetPreferencesForAutoRevealPreference */
	public function testOnGetPreferencesForAutoRevealPreference( bool $isAutoRevealAvailable ) {
		$this->autoRevealLookup->method( 'isAutoRevealAvailable' )
			->willReturn( $isAutoRevealAvailable );

		$this->sut->onGetPreferences( $this->user, $prefs );
		$this->assertSame( $isAutoRevealAvailable, isset( $prefs['checkuser-temporary-account-enable-auto-reveal'] ) );
	}

	public static function provideOnGetPreferencesForAutoRevealPreference(): array {
		return [
			'Auto-reveal is available' => [ true ],
			'Auto-reveal is not available' => [ false ],
		];
	}

	/** @dataProvider provideOnGetPreferencesValidatesAutoRevealPreference */
	public function testOnGetPreferencesValidatesAutoRevealPreference(
		bool $canUserUseAutoReveal, bool $isAutoRevealExpiryValid, mixed $autoRevealPreferenceValue,
		string|null $expectedErrorMessageKey
	) {
		$this->autoRevealLookup->method( 'isAutoRevealAvailable' )
			->willReturn( true );
		$this->autoRevealLookup->method( 'isAutoRevealExpiryValid' )
			->with( $autoRevealPreferenceValue )
			->willReturn( $isAutoRevealExpiryValid );
		$this->checkUserPermissionManager->method( 'canAutoRevealIPAddresses' )
			->willReturn(
				$canUserUseAutoReveal ? CheckUserPermissionStatus::newGood() :
					CheckUserPermissionStatus::newFatal( 'test' )
			);

		$this->sut->onGetPreferences( $this->user, $prefs );

		$this->assertTrue( isset( $prefs['checkuser-temporary-account-enable-auto-reveal'] ) );
		$this->assertArrayHasKey( 'validation-callback', $prefs['checkuser-temporary-account-enable-auto-reveal'] );

		$mockHtmlForm = $this->createMock( HTMLForm::class );
		$mockHtmlForm->method( 'getAuthority' )
			->willReturn( $this->user );

		$actualValidationStatus = $prefs['checkuser-temporary-account-enable-auto-reveal']['validation-callback'](
			$autoRevealPreferenceValue, [], $mockHtmlForm
		);
		if ( $expectedErrorMessageKey === null ) {
			$this->assertStatusGood( $actualValidationStatus );
		} else {
			$this->assertStatusNotGood( $actualValidationStatus );
			$this->assertStatusError( $expectedErrorMessageKey, $actualValidationStatus );
		}
	}

	public static function provideOnGetPreferencesValidatesAutoRevealPreference(): array {
		return [
			'Auto-reveal preference value is null (to unset the preference)' => [ false, true, null, null ],
			'User lacks right to use auto-reveal' => [
				false, true, 1234, 'checkuser-ip-auto-reveal-missing-permission',
			],
			'Auto-reveal preference value provided is not valid' => [
				true, false, 1234, 'checkuser-ip-auto-reveal-expiry-invalid',
			],
			'Auto-reveal preference value is valid' => [ true, true, 1234, null ],
		];
	}

	/**
	 * @dataProvider onSaveUserOptionsDataProvider
	 */
	public function testOnSaveUserOptions(
		array $modifiedOptions,
		array $originalOptions,
		bool $logAccessEnabled,
		bool $logAccessDisabled
	): void {
		$logger = $this->createMock(
			TemporaryAccountLogger::class
		);

		$this->loggerFactory
			->method( 'getLogger' )
			->willReturn( $logger );

		if ( $logAccessEnabled ) {
			$logger
				->expects( $this->once() )
				->method( 'logAccessEnabled' )
				->with( $this->user );
		} else {
			$logger
				->expects( $this->never() )
				->method( 'logAccessEnabled' );
		}

		if ( $logAccessDisabled ) {
			$logger
				->expects( $this->once() )
				->method( 'logAccessDisabled' )
				->with( $this->user );
		} else {
			$logger
				->expects( $this->never() )
				->method( 'logAccessDisabled' );
		}

		$formerOptions = $modifiedOptions;

		$this->sut->onSaveUserOptions(
			$this->user,
			$modifiedOptions,
			$originalOptions
		);

		// Assert $modifiedOptions (passed by reference) is kept intact
		$this->assertEquals( $formerOptions, $modifiedOptions );
	}

	public static function onSaveUserOptionsDataProvider(): array {
		return [
			'When the option is kept enabled' => [
				'modifiedOptions' => [
					Preferences::ENABLE_IP_REVEAL => 1,
				],
				'originalOptions' => [
					Preferences::ENABLE_IP_REVEAL => 1,
				],
				'logAccessEnabled' => false,
				'logAccessDisabled' => false,
			],
			'When the option is kept disabled' => [
				'modifiedOptions' => [
					Preferences::ENABLE_IP_REVEAL => 0,
				],
				'originalOptions' => [
					Preferences::ENABLE_IP_REVEAL => 0,
				],
				'logAccessEnabled' => false,
				'logAccessDisabled' => false,
			],
			'When the option is not provided' => [
				'modifiedOptions' => [
					'anotheroption' => 0,
				],
				'originalOptions' => [
					'anotheroption' => 0,
				],
				'logAccessEnabled' => false,
				'logAccessDisabled' => false,
			],
			'When the option is kept enabled while other is changed' => [
				'modifiedOptions' => [
					Preferences::ENABLE_IP_REVEAL => 1,
					'anotheroption' => 0,
				],
				'originalOptions' => [
					Preferences::ENABLE_IP_REVEAL => 1,
					'anotheroption' => 1,
				],
				'logAccessEnabled' => false,
				'logAccessDisabled' => false,
			],
			'When the option is kept disabled while other is changed' => [
				'modifiedOptions' => [
					Preferences::ENABLE_IP_REVEAL => 0,
					'anotheroption' => 0,
				],
				'originalOptions' => [
					Preferences::ENABLE_IP_REVEAL => 0,
					'anotheroption' => 1,
				],
				'logAccessEnabled' => false,
				'logAccessDisabled' => false,
			],
			'When the option is switched to enabled, single change' => [
				'modifiedOptions' => [
					Preferences::ENABLE_IP_REVEAL => 1,
				],
				'originalOptions' => [
					Preferences::ENABLE_IP_REVEAL => 0,
				],
				'logAccessEnabled' => true,
				'logAccessDisabled' => false,
			],
			'When the option is switched to disabled, single change' => [
				'modifiedOptions' => [
					Preferences::ENABLE_IP_REVEAL => 0,
				],
				'originalOptions' => [
					Preferences::ENABLE_IP_REVEAL => 1,
				],
				'logAccessEnabled' => false,
				'logAccessDisabled' => true,
			],
			'When the option is switched to enabled, multiple changes' => [
				'modifiedOptions' => [
					Preferences::ENABLE_IP_REVEAL => 1,
					'anotheroption' => 0,
				],
				'originalOptions' => [
					Preferences::ENABLE_IP_REVEAL => 0,
					'anotheroption' => 1,
				],
				'logAccessEnabled' => true,
				'logAccessDisabled' => false,
			],
			'When the option is switched to disabled, multiple changes' => [
				'modifiedOptions' => [
					Preferences::ENABLE_IP_REVEAL => 0,
					'anotheroption' => 0,
				],
				'originalOptions' => [
					Preferences::ENABLE_IP_REVEAL => 1,
					'anotheroption' => 1,
				],
				'logAccessEnabled' => false,
				'logAccessDisabled' => true,
			],
			'When the new value is NULL and the option was previously set' => [
				// T382010
				'modifiedOptions' => [
					Preferences::ENABLE_IP_REVEAL => null,
				],
				'originalOptions' => [
					Preferences::ENABLE_IP_REVEAL => 1,
				],
				'logAccessEnabled' => false,
				'logAccessDisabled' => true,
			],
			'When the new value is NULL and the option was previously unset' => [
				// T382010
				'modifiedOptions' => [
					Preferences::ENABLE_IP_REVEAL => null,
				],
				'originalOptions' => [
					Preferences::ENABLE_IP_REVEAL => 0,
				],
				'logAccessEnabled' => false,
				'logAccessDisabled' => false,
			],
			'When the new value is false and the option was previously set' => [
				'modifiedOptions' => [
					Preferences::ENABLE_IP_REVEAL => false,
				],
				'originalOptions' => [
					Preferences::ENABLE_IP_REVEAL => 1,
				],
				'logAccessEnabled' => false,
				'logAccessDisabled' => true,
			],
			'When the new value is false and the option was previously unset' => [
				'modifiedOptions' => [
					Preferences::ENABLE_IP_REVEAL => false,
				],
				'originalOptions' => [
					Preferences::ENABLE_IP_REVEAL => 0,
				],
				'logAccessEnabled' => false,
				'logAccessDisabled' => false,
			],
			'When the new value is true and the option was previously set' => [
				'modifiedOptions' => [
					Preferences::ENABLE_IP_REVEAL => true,
				],
				'originalOptions' => [
					Preferences::ENABLE_IP_REVEAL => 1,
				],
				'logAccessEnabled' => false,
				'logAccessDisabled' => false,
			],
			'When the new value is true and the option was previously unset' => [
				'modifiedOptions' => [
					Preferences::ENABLE_IP_REVEAL => true,
				],
				'originalOptions' => [
					Preferences::ENABLE_IP_REVEAL => 0,
				],
				'logAccessEnabled' => true,
				'logAccessDisabled' => false,
			],
		];
	}

	public function testDefaultValueForUserInfoCardIsFalse() {
		$user = $this->getTestUser()->getUser();
		$this->assertSame(
			false,
			$this->getServiceContainer()->getUserOptionsLookup()->getOption(
				$user,
				Preferences::ENABLE_USER_INFO_CARD
			)
		);
	}
}
