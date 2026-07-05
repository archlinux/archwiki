<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Special;

use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Extension\CentralAuth\CentralAuthUserCache;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\OATHAuth\Key\RecoveryCodeKeys;
use MediaWiki\Extension\OATHAuth\Module\RecoveryCodes;
use MediaWiki\Extension\OATHAuth\OATHAuthLogger;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\Special\Recover2FAForUser;
use MediaWiki\Mail\IEmailer;
use MediaWiki\MainConfigNames;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Specials\SpecialPageTestBase;
use StatusValue;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group Database
 * @covers \MediaWiki\Extension\OATHAuth\Special\Recover2FAForUser
 * @covers \MediaWiki\Extension\OATHAuth\OATHUser
 */
class Recover2FAForUserTest extends SpecialPageTestBase {
	use BypassReauthTrait;

	private ?ExtensionRegistry $mockExtensionRegistry;

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
		$this->overrideConfigValue( 'OATHRecoveryCodesCount', 10 );
		$this->setGroupPermissions( 'sysop', 'oathauth-recover-for-user', true );
		$this->bypassReauthentication();
	}

	protected function newSpecialPage(): Recover2FAForUser {
		$oathServices = OATHAuthServices::getInstance( $this->getServiceContainer() );
		return new Recover2FAForUser(
			$oathServices->getUserRepository(),
			$oathServices->getModuleRegistry(),
			$oathServices->getLogger(),
			$this->getServiceContainer()->getUserFactory(),
			$this->getServiceContainer()->getCentralIdLookup(),
			$this->getServiceContainer()->getLinkRenderer(),
			$this->getServiceContainer()->getExtensionRegistry(),
			$this->getServiceContainer()->getEmailer(),
			$this->getServiceContainer()->getUserOptionsLookup(),
		);
	}

	public function testFormLoads() {
		$user = $this->getTestSysop()->getUser();
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );

		[ $html ] = $this->executeSpecialPage(
			'',
			null,
			null,
			$user,
		);

		$this->assertStringContainsString( '(oathauth-recover-intro: 10, 10)', $html );
		$this->assertStringContainsString( '(oathauth-recover-for-user-legend: 10)', $html );
		$this->assertStringContainsString( '(oathauth-enteruser)', $html );
		$this->assertStringContainsString( '(oathauth-enterrecoverreason)', $html );
	}

	public function testChecksPermissions() {
		$this->expectException( PermissionsError::class );
		$this->executeSpecialPage(
			'',
			null,
			null,
			$this->getTestUser()->getUser(),
		);
	}

	public function testFailsForNonexistentUser() {
		$user = $this->getTestSysop()->getUser();
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );
		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest(
				[
					'reason' => 'I am required!',
					// Sharks are amazing, so no shark haters exist
					'user' => 'Shark hater',
				],
				true,
			),
			null,
			$user,
		);

		$this->assertStringContainsString( '(oathauth-user-not-found)', $html );
	}

	public function testFailsForUserWithTwoFactorDisabled() {
		$user = $this->getTestSysop()->getUser();
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );

		$otherUser = $this->getTestUser()->getUser();

		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest(
				[
					'reason' => 'I am required!',
					'user' => $otherUser->getName(),
				],
				true,
			),
			null,
			$user,
		);

		$this->assertStringContainsString( '(oathauth-recover-fail-no-2fa)', $html );
	}

	/** @dataProvider provideGeneratedAdditionalCodes */
	public function testGeneratedAdditionalCodes(
		?string $userEmail,
		bool $userEmailConfirmed,
		?string $enteredEmail,
		string $expectedEmail
	) {
		// Make sure there's no email sent about enabling 2FA (which would confuse assertions)
		global $wgEchoNotifications;
		unset( $wgEchoNotifications['oathauth-enable'] );

		ConvertibleTimestamp::setFakeTime( '20260101000000' );
		$this->overrideConfigValue( 'OATHAdditionalRecoveryCodesValidityDays', 5 );

		$loggerMock = $this->createMock( OATHAuthLogger::class );
		$loggerMock->expects( $this->once() )->method( 'logOATHRecovery' );
		$this->setService( 'OATHAuth.Logger', $loggerMock );

		$otherUser = $this->getMutableTestUser()->getUser();
		$otherUser->setEmail( $userEmail ?? '' );
		if ( $userEmailConfirmed ) {
			$otherUser->setEmailAuthenticationTimestamp( '20250101000000' );
		}
		$otherUser->saveSettings();

		$mailerMock = $this->createMock( IEmailer::class );
		$mailerMock->expects( $this->once() )
			->method( 'send' )
			->willReturnCallback( function ( $to ) use ( $expectedEmail ) {
				$this->assertSame( $expectedEmail, $to->address );
				return StatusValue::newGood();
			} );
		$this->setService( 'Emailer', $mailerMock );

		$userRepo = OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository();
		$moduleRegistry = OATHAuthServices::getInstance( $this->getServiceContainer() )->getModuleRegistry();
		$recoveryCodesModule = $moduleRegistry->getModuleByKey( RecoveryCodes::MODULE_NAME );

		$oathUser = $userRepo->findByUser( $otherUser );
		$userRepo->createKey( $oathUser, $recoveryCodesModule, [
			'recoverycodekeys' => [ 'H8572S2FB1LCGYWN', 'V61A5VEM42DGLDMU' ],
		], '127.0.0.1' );

		$keys = $oathUser->getKeysForModule( RecoveryCodes::MODULE_NAME );
		$this->assertCount( 1, $keys );
		$key = $keys[0];
		$this->assertInstanceOf( RecoveryCodeKeys::class, $key );
		$this->assertCount( 2, $key->getRecoveryCodeKeys() );

		$reason = 'I am required!';

		$user = $this->getTestSysop()->getUser();
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );

		$requestData = [
			'reason' => $reason,
			'user' => $otherUser->getName(),
		];
		if ( $enteredEmail !== null ) {
			$requestData['email'] = $enteredEmail;
		}
		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest( $requestData, true ),
			null,
			$user,
		);

		$this->assertStringContainsString( "(oathauth-recoveredoath: 10, {$otherUser->getName()}", $html );

		$keys = $oathUser->getKeysForModule( RecoveryCodes::MODULE_NAME );
		$this->assertCount( 1, $keys );
		$newKey = $keys[0];
		$this->assertInstanceOf( RecoveryCodeKeys::class, $newKey );
		$newCodes = $newKey->getRecoveryCodeKeys();
		$this->assertCount( 12, $newCodes );

		$this->assertSame( [ 'H8572S2FB1LCGYWN', 'V61A5VEM42DGLDMU' ], array_slice( $newCodes, 0, 2 ) );

		$tempCode = $newKey->getRecoveryCodes()[2];
		$this->assertSame( '20260106000000', $tempCode->getExpiryTimestamp() );
	}

	public static function provideGeneratedAdditionalCodes(): array {
		return [
			'User has email, no email entered' => [
				'userEmail' => 'user@example.com',
				'userEmailConfirmed' => true,
				'enteredEmail' => null,
				'expectedEmail' => 'user@example.com',
			],
			// Ensure that user's own email takes precedence
			'User has email, email entered' => [
				'userEmail' => 'user@example.com',
				'userEmailConfirmed' => true,
				'enteredEmail' => 'other@example.com',
				'expectedEmail' => 'user@example.com',
			],
			'User has unconfirmed email, email entered' => [
				'userEmail' => 'user@example.com',
				'userEmailConfirmed' => false,
				'enteredEmail' => 'other@example.com',
				'expectedEmail' => 'other@example.com',
			],
			'User has no email, email entered' => [
				'userEmail' => null,
				'userEmailConfirmed' => false,
				'enteredEmail' => 'other@example.com',
				'expectedEmail' => 'other@example.com',
			],
		];
	}

	public function testAsksForEmailIfUserHasNoEmail() {
		// This user has no e-mail address
		$otherUser = $this->getMutableTestUser()->getUser();
		$otherUser->setEmail( '' );
		$otherUser->saveSettings();

		$userRepo = OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository();
		$moduleRegistry = OATHAuthServices::getInstance( $this->getServiceContainer() )->getModuleRegistry();
		$recoveryCodesModule = $moduleRegistry->getModuleByKey( RecoveryCodes::MODULE_NAME );

		$oathUser = $userRepo->findByUser( $otherUser );
		$userRepo->createKey( $oathUser, $recoveryCodesModule, [
			'recoverycodekeys' => [ 'H8572S2FB1LCGYWN', 'V61A5VEM42DGLDMU' ],
		], '127.0.0.1' );

		$keys = $oathUser->getKeysForModule( RecoveryCodes::MODULE_NAME );
		$this->assertCount( 1, $keys );
		$key = $keys[0];
		$this->assertInstanceOf( RecoveryCodeKeys::class, $key );
		$this->assertCount( 2, $key->getRecoveryCodeKeys() );

		$reason = 'I am required!';

		$user = $this->getTestSysop()->getUser();
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );

		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest(
				[
					'reason' => $reason,
					'user' => $otherUser->getName(),
				],
				true
			),
			null,
			$user,
		);

		$this->assertStringContainsString( '(oathauth-recover-fail-email-required)', $html );
		$this->assertStringContainsString( '(oathauth-enterrecoveremail)', $html );

		// Ensure that no codes were generated
		$keys = $oathUser->getKeysForModule( RecoveryCodes::MODULE_NAME );
		$this->assertCount( 1, $keys );
		$newKey = $keys[0];
		$this->assertInstanceOf( RecoveryCodeKeys::class, $newKey );
		$newCodes = $newKey->getRecoveryCodeKeys();
		$this->assertCount( 2, $newCodes );
	}

	public function testUsesCentralAccountEmailIfNoLocal() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );

		$loggerMock = $this->createMock( OATHAuthLogger::class );
		$loggerMock->expects( $this->once() )->method( 'logOATHRecovery' );
		$this->setService( 'OATHAuth.Logger', $loggerMock );

		$centralAuthUserCacheMock = $this->createMock( CentralAuthUserCache::class );
		$centralAuthUserCacheMock->method( 'get' )
			->willReturnCallback( static function ( $name, $fromPrimary ) {
				$centralUser = new CentralAuthUser( $name );
				$centralUser->setEmail( 'global@example.com' );
				$centralUser->setEmailAuthenticationTimestamp( '20250101000000' );
				return $centralUser;
			} );
		$this->setService( 'CentralAuth.CentralAuthUserCache', $centralAuthUserCacheMock );

		$mailerMock = $this->createMock( IEmailer::class );
		$mailerMock->expects( $this->once() )
			->method( 'send' )
			->willReturnCallback( function ( $to ) {
				$this->assertSame( 'global@example.com', $to->address );
				return StatusValue::newGood();
			} );
		$this->setService( 'Emailer', $mailerMock );

		// This user has no local e-mail address but has global one
		$otherUser = $this->getMutableTestUser()->getUser();
		$otherUser->setEmail( '' );
		$otherUser->saveSettings();

		$userRepo = OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository();
		$moduleRegistry = OATHAuthServices::getInstance( $this->getServiceContainer() )->getModuleRegistry();
		$recoveryCodesModule = $moduleRegistry->getModuleByKey( RecoveryCodes::MODULE_NAME );

		$oathUser = $userRepo->findByUser( $otherUser );
		$userRepo->createKey( $oathUser, $recoveryCodesModule, [
			'recoverycodekeys' => [ 'H8572S2FB1LCGYWN', 'V61A5VEM42DGLDMU' ],
		], '127.0.0.1' );

		$reason = 'I am required!';

		$user = $this->getTestSysop()->getUser();
		RequestContext::getMain()->getRequest()->getSession()->setUser( $user );

		[ $html ] = $this->executeSpecialPage(
			'',
			new FauxRequest(
				[
					'reason' => $reason,
					'user' => $otherUser->getName(),
				],
				true
			),
			null,
			$user,
		);
	}
}
