<?php

namespace MediaWiki\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\CheckUser\Maintenance\MigrateTemporaryAccountIPViewerGroup;
use MediaWiki\MainConfigNames;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\User\UserIdentity;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Maintenance\MigrateTemporaryAccountIPViewerGroup
 */
class MigrateTemporaryAccountIPViewerGroupTest extends MaintenanceBaseTestCase {

	protected function getMaintenanceClass() {
		return MigrateTemporaryAccountIPViewerGroup::class;
	}

	/**
	 * Creates several testing users, and adds them to the specified $group.
	 *
	 * @param string $group The group the users should be in
	 * @param int $numberOfUsers The number of users to create
	 * @return UserIdentity[] The users that were created
	 */
	private function createTestUsersWithGroup( string $group, int $numberOfUsers ): array {
		$userGroupManager = $this->getServiceContainer()->getUserGroupManager();
		$returnArray = [];
		for ( $i = 0; $i < $numberOfUsers; $i++ ) {
			$user = $this->getMutableTestUser()->getUserIdentity();
			$userGroupManager->addUserToGroup( $user, $group );
			$returnArray[] = $user;
		}
		return $returnArray;
	}

	public function testExecute() {
		// Create the 'checkuser-temporary-account-viewer' group to allow us to add users to the group.
		$this->setGroupPermissions( 'checkuser-temporary-account-viewer', 'read', true );

		// Create some test users in both the old group
		$usersWithOldGroup = $this->createTestUsersWithGroup( 'checkuser-temporary-account-viewer', 3 );
		// Take one user and give them both groups, along with an unrelated group
		$userWithBothGroups = $this->createTestUsersWithGroup( 'temporary-account-viewer', 1 )[0];
		$userGroupManager = $this->getServiceContainer()->getUserGroupManager();
		$userGroupManager->addUserToGroup(
			$userWithBothGroups, 'checkuser-temporary-account-viewer'
		);
		$this->getServiceContainer()->getUserGroupManager()->addUserToGroup(
			$userWithBothGroups, 'sysop'
		);

		// Drop the checkuser-temporary-account-viewer group definition before running the maintenance script
		$newPermissions = $this->getConfVar( MainConfigNames::GroupPermissions );
		unset( $newPermissions['checkuser-temporary-account-viewer'] );
		$this->overrideConfigValue( MainConfigNames::GroupPermissions, $newPermissions );

		// Run the maintenance script
		$this->maintenance->execute();

		// Verify that all users we created now only have the 'temporary-account-viewer' group, while leaving any
		// unrelated groups alone
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'user_groups' )
			->where( [ 'ug_group' => 'checkuser-temporary-account-viewer' ] )
			->caller( __METHOD__ )
			->assertFieldValue( 0 );
		foreach ( $usersWithOldGroup as $user ) {
			$userGroupManager->clearCache( $user );
			$this->assertArrayEquals( [ 'temporary-account-viewer' ], $userGroupManager->getUserGroups( $user ) );
		}
		$userGroupManager->clearCache( $userWithBothGroups );
		$this->assertArrayEquals(
			[ 'temporary-account-viewer', 'sysop' ], $userGroupManager->getUserGroups( $userWithBothGroups )
		);
	}

	public function testExecuteWhenNoUsersInGroup() {
		// Run the maintenance script
		$this->maintenance->execute();
		$this->expectOutputRegex( '/Nothing to do/' );
	}
}
