<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\BlockChecks;

use CentralAuthTestUser;
use MediaWiki\Extension\CentralAuth\CentralAuthServices;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks\CentralAuthLockCheck;
use MediaWiki\User\UserIdentity;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;
use Wikimedia\Rdbms\IDatabase;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks\CentralAuthLockCheck
 * @group CheckUser
 * @group Database
 */
class CentralAuthLockCheckTest extends MediaWikiIntegrationTestCase {

	private static array $testUsers;

	private CentralAuthLockCheck $check;

	public function addDBDataOnce(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );

		$db = $this->getDb();
		$wiki = WikiMap::getCurrentWikiId();

		$userTypes = [ 'locked', 'unlocked', 'unlocked2' ];
		foreach ( $userTypes as $userType ) {
			self::$testUsers[$userType] = $this->createCentralAuthUser(
				$db,
				$wiki,
				$userType === 'locked'
			);
		}
	}

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );

		$services = $this->getServiceContainer();
		$this->check = new CentralAuthLockCheck(
			CentralAuthServices::getGlobalUserSelectQueryBuilderFactory( $services ),
			$services->getUserIdentityLookup()
		);
	}

	public function testMixOfLockedAndUnlockedUsers(): void {
		$lockedUser = self::$testUsers['locked'];
		$unlockedUser = self::$testUsers['unlocked'];
		$unlockedUser2 = self::$testUsers['unlocked2'];

		$expectedArray = [ $lockedUser->getId() ];
		$this->assertSame(
			$expectedArray,
			$this->check->getIndefinitelyBlockedUserIds( [
				$lockedUser->getId(),
				$unlockedUser->getId(),
				$unlockedUser2->getId(),
			] )
		);
		$this->assertSame(
			$expectedArray,
			$this->check->getBlockedUserIds( [
				$lockedUser->getId(),
				$unlockedUser->getId(),
				$unlockedUser2->getId(),
			] )
		);
	}

	private function createCentralAuthUser( IDatabase $db, string $wiki, bool $locked ): UserIdentity {
		$testUser = $this->getMutableTestUser();

		$centralAuthTestUser = new CentralAuthTestUser(
			$testUser->getUser()->getName(),
			$testUser->getPassword(),
			[ 'gu_id' => $testUser->getUser()->getId(),
				'gu_locked' => $locked ? 1 : 0 ],
			[ [ $wiki, 'new' ] ],
			false
		);
		$centralAuthTestUser->save( $db );

		$db->newUpdateQueryBuilder()
			->update( 'localuser' )
			->set( [ 'lu_local_id' => $testUser->getUser()->getId() ] )
			->where( [
				'lu_wiki' => $wiki,
				'lu_name' => $testUser->getUser()->getName(),
			] )
			->caller( __METHOD__ )
			->execute();

		return $testUser->getUserIdentity();
	}
}
