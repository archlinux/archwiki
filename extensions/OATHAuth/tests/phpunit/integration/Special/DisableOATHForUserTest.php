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
use MediaWiki\Extension\OATHAuth\Special\DisableOATHForUser;
use MediaWiki\MainConfigNames;
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Specials\SpecialPageTestBase;

/**
 * @author Taavi Väänänen & Dreamy Jazz
 * @group Database
 * @covers \MediaWiki\Extension\OATHAuth\Special\DisableOATHForUser
 */
class DisableOATHForUserTest extends SpecialPageTestBase {
	use BypassReauthTrait;

	private ?ExtensionRegistry $mockExtensionRegistry;

	protected function setUp(): void {
		parent::setUp();

		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
		$this->bypassReauthentication();
	}

	protected function newSpecialPage(): DisableOATHForUser {
		return new DisableOATHForUser(
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

		$this->assertStringContainsString( '(oathauth-disable-for-user)', $html );
		$this->assertStringContainsString( '(oathauth-disable-intro)', $html );
		$this->assertStringContainsString( '(oathauth-enteruser)', $html );
		$this->assertStringContainsString( '(oathauth-enterdisablereason)', $html );
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

		$this->assertStringContainsString( '(oathauth-user-not-does-not-have-oath-enabled)', $html );
	}

	/** @dataProvider provideDisabledTwoFactorAuth */
	public function testDisabledTwoFactorAuth( bool $checkUserInstalled ) {
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

		OATHAuthServices::getInstance( $this->getServiceContainer() )
			->getUserRepository()
			->findByUser( $otherUser )
			->addKey( TOTPKey::newFromRandom() );

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

		$this->assertStringContainsString( "(oathauth-disabledoath)", $html );

		$actualLogId = $this->newSelectQueryBuilder()
			->caller( __METHOD__ )
			->select( 'log_id' )
			->from( 'logging' )
			->where( [
				'log_type' => 'oath',
				'log_action' => 'disable-other',
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

	public static function provideDisabledTwoFactorAuth(): array {
		return [
			'CheckUser is installed' => [ true ],
			'CheckUser is not installed' => [ false ],
		];
	}
}
