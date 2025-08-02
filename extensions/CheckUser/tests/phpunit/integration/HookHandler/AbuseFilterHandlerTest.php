<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\Block\Block;
use MediaWiki\CheckUser\HookHandler\AbuseFilterHandler;
use MediaWiki\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\CheckUser\Services\CheckUserPermissionManager;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionStatus;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\ProtectedVarsAccessLogger;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\Options\StaticUserOptionsLookup;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\AbuseFilterHandler
 * @group CheckUser
 * @group Database
 */
class AbuseFilterHandlerTest extends MediaWikiIntegrationTestCase {
	use TempUserTestTrait;
	use MockAuthorityTrait;

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'Abuse Filter' );
	}

	private function getHookHandler() {
		return new AbuseFilterHandler(
			$this->getServiceContainer()->get( 'CheckUserTemporaryAccountLoggerFactory' ),
			$this->getServiceContainer()->get( 'CheckUserPermissionManager' ),
			$this->getServiceContainer()->getTempUserConfig()
		);
	}

	public function testMakesUserUnnamedIpAsAlwaysProtected() {
		// Tests that the hook is called by AbuseFilter and adds the variables to the return
		// value of the protected variables service.
		$this->overrideConfigValue( 'AbuseFilterProtectedVariables', [] );
		$this->assertContains(
			'user_unnamed_ip',
			AbuseFilterServices::getProtectedVariablesLookup()->getAllProtectedVariables()
		);
	}

	public function testOnAbuseFilterCustomProtectedVariables() {
		// Tests the hook handler works without testing that the AbuseFilter part works, to make
		// it easier to diagnose issues if ::testMakesUserUnnamedIpAsAlwaysProtected fails.
		$variables = [];
		$this->getHookHandler()->onAbuseFilterCustomProtectedVariables( $variables );
		$this->assertArrayEquals( [ 'user_unnamed_ip' ], $variables );
	}

	public function testProtectedVarsLogGoesToAbuseFilterLogIfVariablesMissingUserUnnamedIp() {
		$performer = $this->getTestSysop();
		$protectedVarsAccessLogger = AbuseFilterServices::getAbuseLoggerFactory()->getProtectedVarsAccessLogger();
		$protectedVarsAccessLogger->logViewProtectedVariableValue(
			$performer->getUserIdentity(), '~2024-01', [ 'other_protected_var' ], (int)wfTimestamp()
		);
		DeferredUpdates::doUpdates();

		// Assert that the log was created by AbuseFilter and did not go to CheckUser, as CheckUser is only interested
		// in logs where the user viewed user_unnamed_ip.
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [
				'log_action' => 'af-view-protected-var-value',
				'log_type' => TemporaryAccountLogger::LOG_TYPE,
			] )
			->assertFieldValue( 0 );

		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [
				'log_action' => 'view-protected-var-value',
				'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
			] )
			->assertFieldValue( 1 );
	}

	public function testProtectedVarsAccessDebouncedLogging() {
		// Run the same action twice
		$performer = $this->getTestSysop();
		$protectedVarsAccessLogger = AbuseFilterServices::getAbuseLoggerFactory()->getProtectedVarsAccessLogger();
		$protectedVarsAccessLogger->logViewProtectedVariableValue(
			$performer->getUserIdentity(), '~2024-01', [ 'user_unnamed_ip' ]
		);
		$protectedVarsAccessLogger->logViewProtectedVariableValue(
			$performer->getUserIdentity(), '~2024-01', [ 'user_unnamed_ip' ]
		);
		DeferredUpdates::doUpdates();

		// Assert that the action only inserted once into CheckUsers' temp account logging table
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [
				'log_action' => 'af-view-protected-var-value',
				'log_type' => TemporaryAccountLogger::LOG_TYPE,
			] )
			->assertFieldValue( 1 );

		// and also that it wasn't inserted into abusefilter's protected vars logging table
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [
				'log_action' => 'view-protected-var-value',
				'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
			] )
			->assertFieldValue( 0 );
	}

	private function mockAllUsersHaveAcceptedIPRevealPreference() {
		$this->setService(
			'UserOptionsLookup',
			new StaticUserOptionsLookup( [], [ 'checkuser-temporary-account-enable' => 1 ] )
		);
	}

	public function testCanViewProtectedVariablesForIPWhenUserLacksRight() {
		$this->enableAutoCreateTempUser();
		$this->mockAllUsersHaveAcceptedIPRevealPreference();
		// Get a mock test Authority that has the rights and preferences needed to see protected variables,
		// but not the rights to see user_unnamed_ip.
		$authority = $this->mockRegisteredAuthorityWithoutPermissions(
			[ 'checkuser-temporary-account', 'checkuser-temporary-account-no-preference' ]
		);

		// Check that AbuseFilterPermissionManager::canViewProtectedVariables returns a fatal status with
		// the checkuser-temporary-account permission specified.
		$actualStatus = AbuseFilterServices::getPermissionManager()
			->canViewProtectedVariables( $authority, [ 'user_unnamed_ip' ] );
		$this->assertStatusNotGood( $actualStatus );
		$this->assertSame( 'checkuser-temporary-account', $actualStatus->getPermission() );
	}

	/** @dataProvider provideWhenIPRevealRestrictionsNotApplied */
	public function testCanViewProtectedVarsWhenIPRevealRestrictionsNotApplied( $temporaryAccountsKnown, $variables ) {
		$this->disableAutoCreateTempUser( [ 'known' => $temporaryAccountsKnown ] );
		$this->mockAllUsersHaveAcceptedIPRevealPreference();

		// Test that the hook does not attempt to validate if the user can see Temp account IP addresses
		// if the temporary accounts feature is not known.
		$this->setService(
			'CheckUserPermissionManager', $this->createNoOpMock( CheckUserPermissionManager::class )
		);
		$canViewProtectedVariablesStatus = AbuseFilterServices::getPermissionManager()
			->canViewProtectedVariables( $this->mockRegisteredUltimateAuthority(), $variables );
		$this->assertStatusGood( $canViewProtectedVariablesStatus );
	}

	public static function provideWhenIPRevealRestrictionsNotApplied() {
		return [
			'Temporary accounts are not known' => [ false, [ 'user_unnamed_ip' ] ],
			'Protected filter does not include user_unnamed_ip' => [ true, [ 'some_other_variable' ] ],
		];
	}

	public function testOnAbuseFilterCanViewProtectedVariablesWhenUserBlocked() {
		$this->enableAutoCreateTempUser();

		// Get an Authority which has all needed permissions to access IP reveal but is sitewide blocked.
		$block = $this->createMock( Block::class );
		$block->method( 'isSitewide' )
			->willReturn( true );
		$testAuthority = $this->mockUserAuthorityWithBlock(
			$this->mockRegisteredUltimateAuthority()->getUser(), $block,
			[ 'checkuser-temporary-account-no-preference' ]
		);

		$actualStatus = AbuseFilterPermissionStatus::newGood();
		$this->getHookHandler()->onAbuseFilterCanViewProtectedVariables(
			$testAuthority, [ 'user_unnamed_ip' ], $actualStatus
		);

		$this->assertStatusNotGood( $actualStatus );
		$this->assertSame( $block, $actualStatus->getBlock() );
	}
}
