<?php
/**
 * @license GPL-2.0-or-later
 *
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Special;

use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\Context\RequestContext;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\Extension\OATHAuth\Key\TOTPKey;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\Extension\OATHAuth\Special\VerifyOATHForUser;
use MediaWiki\MainConfigNames;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Specials\SpecialPageTestBase;

/**
 * @author Taavi Väänänen
 * @group Database
 * @covers \MediaWiki\Extension\OATHAuth\Special\VerifyOATHForUser
 */
class VerifyOATHForUserTest extends SpecialPageTestBase {
	use BypassReauthTrait;

	private ?ExtensionRegistry $mockExtensionRegistry;

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
		$this->bypassReauthentication();
	}

	protected function newSpecialPage(): VerifyOATHForUser {
		return new VerifyOATHForUser(
			OATHAuthServices::getInstance( $this->getServiceContainer() )->getUserRepository(),
			$this->getServiceContainer()->getUserFactory(),
			$this->getServiceContainer()->getCentralIdLookup(),
			$this->mockExtensionRegistry ?? $this->getServiceContainer()->getExtensionRegistry()
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

		$this->assertStringContainsString( '(oathauth-enteruser)', $html );
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

	/** @dataProvider provideStatusUsers */
	public function testVerifiesStatus( bool $checkUserInstalled, bool $hasDevice, string $expectedMessage ) {
		// If CheckUser is installed for this test case, then expect that the log entry is sent to be stored
		// in the CheckUser data tables. Otherwise, mock that it is not installed and expect no calls to do this
		$logIdFromRecentChange = null;
		if ( $checkUserInstalled ) {
			$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );

			$mockCheckUserInsert = $this->createMock( CheckUserInsert::class );
			$mockCheckUserInsert->expects( $this->once() )
				->method( 'updateCheckUserData' )
				->with( $this->callback( function ( $actualRecentChange ) use ( &$logIdFromRecentChange ) {
					$this->assertInstanceOf( RecentChange::class, $actualRecentChange );
					$logIdFromRecentChange = $actualRecentChange->getAttribute( 'rc_logid' );
					return true;
				} ) );
			$this->setService( 'CheckUserInsert', $mockCheckUserInsert );
		} else {
			// Mock that CheckUser is not installed but only modify this for the special page instance
			// as hooks called by executing the special page use a lot of ExtensionRegistry methods calls
			$mockExtensionRegistry = $this->createMock( ExtensionRegistry::class );
			$mockExtensionRegistry->method( 'isLoaded' )
				->with( 'CheckUser' )
				->willReturn( false );
			$this->mockExtensionRegistry = $mockExtensionRegistry;

			$serviceContainer = $this->getServiceContainer();
			if ( !$serviceContainer->hasService( 'CheckUserInsert' ) ) {
				// define as no-op and override afterwards to use MediaWikiIntegrationTestCase service reset
				$serviceContainer->defineService( 'CheckUserInsert', static fn () => null );
			}
			$this->setService(
				'CheckUserInsert',
				fn () => $this->fail( 'The CheckUserInsert service was expected to not be called' )
			);
		}

		$otherUser = $this->getTestUser()->getUser();

		if ( $hasDevice ) {
			OATHAuthServices::getInstance( $this->getServiceContainer() )
				->getUserRepository()
				->findByUser( $otherUser )
				->addKey( TOTPKey::newFromRandom() );
		}

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
				true,
			),
			null,
			$user,
		);

		$this->assertStringContainsString( "($expectedMessage:", $html );

		$actualLogId = $this->newSelectQueryBuilder()
			->caller( __METHOD__ )
			->select( 'log_id' )
			->from( 'logging' )
			->where( [
				'log_type' => 'oath',
				'log_action' => 'verify',
				'log_namespace' => NS_USER,
				'log_title' => str_replace( ' ', '_', $otherUser->getName() ),
			] )
			->fetchField();

		$this->assertNotNull( $actualLogId );
		if ( $logIdFromRecentChange !== null ) {
			$this->assertSame(
				$logIdFromRecentChange,
				(int)$actualLogId,
				'Log ID in RecentChange sent to CheckUser was not as expected'
			);
		}
	}

	public static function provideStatusUsers() {
		yield 'User with two-factor authentication disabled' => [
			'checkUserInstalled' => false,
			'hasDevice' => false,
			'expectedMessage' => 'oathauth-verify-disabled',
		];
		yield 'User with two-factor authentication enabled' => [
			'checkUserInstalled' => false,
			'hasDevice' => true,
			'expectedMessage' => 'oathauth-verify-enabled',
		];
		yield 'User with two-factor authentication enabled when CheckUser installed' => [
			'checkUserInstalled' => true,
			'hasDevice' => true,
			'expectedMessage' => 'oathauth-verify-enabled',
		];
		yield 'User with two-factor authentication disabled when CheckUser installed' => [
			'checkUserInstalled' => true,
			'hasDevice' => false,
			'expectedMessage' => 'oathauth-verify-disabled',
		];
	}
}
