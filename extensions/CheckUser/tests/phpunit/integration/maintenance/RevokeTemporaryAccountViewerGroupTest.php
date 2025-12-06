<?php

namespace MediaWiki\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\CheckUser\Maintenance\RevokeTemporaryAccountViewerGroup;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\Tests\User\TempUser\TempUserTestTrait;
use MediaWiki\User\User;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Maintenance\RevokeTemporaryAccountViewerGroup
 */
class RevokeTemporaryAccountViewerGroupTest extends MaintenanceBaseTestCase {
	use TempUserTestTrait;

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return RevokeTemporaryAccountViewerGroup::class;
	}

	public function testExecuteInvalidExpiryParameter() {
		$this->enableAutoCreateTempUser();
		$this->expectCallToFatalError();
		$this->expectOutputRegex( '/Expiry value must be a number/' );
		$this->maintenance->execute();
	}

	public function testExecuteWithNoExistingTemporaryAccounts() {
		$this->enableAutoCreateTempUser();
		$this->maintenance->loadWithArgv( [ '--expiry', 0 ] );
		$this->maintenance->execute();
		$actualOutput = $this->getActualOutputForAssertion();
		$this->assertStringContainsString( 'Removed 0 user(s) from temporary-account-viewer group.', $actualOutput );
	}

	public function testExecute() {
		$this->enableAutoCreateTempUser();
		$services = $this->getServiceContainer();

		// Generate an inactive user in the 'temporary-account-viewer' group
		$twoDaysAgoTimestamp = ConvertibleTimestamp::time() - ( 86400 * 2 );
		ConvertibleTimestamp::setFakeTime( $twoDaysAgoTimestamp );
		$inactivePrivilegedUser = $this->getMutableTestUser( [ 'temporary-account-viewer' ] );
		$this->editPage(
			'Test page', 'Test Content 1', 'test', NS_MAIN, $inactivePrivilegedUser->getAuthority()
		);
		ConvertibleTimestamp::setFakeTime( false );

		// Generate an active user in the 'temporary-account-viewer' group
		$activePrivilegedUser = $this->getMutableTestUser( [ 'temporary-account-viewer', 'sysop' ] );
		$this->editPage(
			'Test page', 'Test Content 2', 'test', NS_MAIN, $activePrivilegedUser->getAuthority()
		);

		// Generate an unprivileged user; this user exists to not be caught in any queries
		$this->getTestUser();

		// Assert that the logging table is empty as nothing has happened yet
		$initialResCount = $this->newSelectQueryBuilder()
			->select( 1 )
			->from( 'logging' )
			->where( [
				'log_type' => 'rights',
				'log_action' => 'rights',
			] )
			->assertEmptyResult();

		// Expect to expire 1 account, the inactive privileged user
		$this->expectOutputRegex( '/Removed 1 user\(s\) from temporary-account-viewer group\./' );
		$this->maintenance->loadWithArgv( [ '--expiry', 1 ] );
		$this->maintenance->execute();

		// Assert that the inactive user no longer has membership in the group
		$ugm = $services->getUserGroupManager();
		$this->assertNotContains(
			'temporary-account-viewer', $ugm->getUserGroups( $inactivePrivilegedUser->getUserIdentity() )
		);

		// Assert that the active user still has membership
		$this->assertContains(
			'temporary-account-viewer', $ugm->getUserGroups( $activePrivilegedUser->getUserIdentity() )
		);

		// Assert that the action was logged
		$dbr = $this->getDb();
		$logCommentIds = $dbr->newSelectQueryBuilder()
			->select( 'log_comment_id' )
			->from( 'logging' )
			->where( [
				'log_type' => 'rights',
				'log_action' => 'rights',
				'log_actor' => $services->getActorStore()->acquireSystemActorId(
					User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] ),
					$dbr
				),
				'log_title' => $inactivePrivilegedUser->getUser()->getTitleKey(),
			] )
			->fetchFieldValues();
		$this->assertCount( 1, $logCommentIds );

		$logComment = $dbr->newSelectQueryBuilder()
			->select( 'comment_text' )
			->from( 'comment' )
			->where( [
				'comment_id' => $logCommentIds[0],
			] )
			->fetchField();
		$this->assertIsString( $logComment );
		$this->assertStringContainsString(
			wfMessage( 'checkuser-temporary-account-autorevoke-userright-reason' )->text(), $logComment
		);
	}

	public function testExecuteExpireAllUsers() {
		// Generate an active user in the 'temporary-account-viewer' group
		$activePrivilegedUser = $this->getMutableTestUser( [ 'temporary-account-viewer', 'sysop', 'foo' ] );
		$this->editPage(
			'Test page', 'Test Content 3', 'test', NS_MAIN, $activePrivilegedUser->getAuthority()
		);

		// Expect to expire the active account
		$this->expectOutputRegex( '/Removed 1 user\(s\) from temporary-account-viewer group\./' );
		$this->maintenance->loadWithArgv( [ '--expiry', 0 ] );
		$this->maintenance->execute();
	}
}
