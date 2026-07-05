<?php

declare( strict_types=1 );

namespace MediaWiki\Extension\CheckUser\Tests\Integration\SuggestedInvestigations\BlockChecks;

use MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks\GlobalBlockCheck;
use MediaWiki\Extension\GlobalBlocking\GlobalBlockingServices;
use MediaWiki\Extension\GlobalBlocking\Services\GlobalBlockLocalStatusManager;
use MediaWiki\Extension\GlobalBlocking\Services\GlobalBlockManager;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\Authority;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\SuggestedInvestigations\BlockChecks\GlobalBlockCheck
 * @group CheckUser
 * @group Database
 */
class GlobalBlockCheckTest extends MediaWikiIntegrationTestCase {

	/** @var UserIdentity[] */
	private static array $testUsers;

	private GlobalBlockCheck $check;
	private GlobalBlockManager $globalBlockManager;
	private GlobalBlockLocalStatusManager $globalBlockLocalStatusManager;
	private Authority $adminUser;

	public function addDBDataOnce(): void {
		self::$testUsers = [
			'blocked' => $this->getMutableTestUser()->getUserIdentity(),
			'unblocked' => $this->getMutableTestUser()->getUserIdentity(),
			'locallyDisabled' => $this->getMutableTestUser()->getUserIdentity(),
			'temporary' => $this->getMutableTestUser()->getUserIdentity(),
		];
	}

	public function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalBlocking' );

		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );

		$globalBlockingServices = GlobalBlockingServices::wrap( $this->getServiceContainer() );
		$this->globalBlockManager = $globalBlockingServices->getGlobalBlockManager();
		$this->globalBlockLocalStatusManager = $globalBlockingServices->getGlobalBlockLocalStatusManager();
		$this->adminUser = $this->getTestSysop()->getAuthority();

		$this->check = new GlobalBlockCheck(
			$globalBlockingServices->getGlobalBlockLookup(),
			$this->getServiceContainer()->getCentralIdLookup(),
			$this->getServiceContainer()->getUserIdentityLookup(),
			true
		);
	}

	public function testIndefinitelyGloballyBlockedUserIsReturned(): void {
		$blockedUser = self::$testUsers['blocked'];
		$this->globallyBlockIndefinitely( $blockedUser );

		$this->assertSame(
			[ $blockedUser->getId() ],
			$this->check->getIndefinitelyBlockedUserIds( [ $blockedUser->getId() ] )
		);
	}

	public function testLocallyDisabledGlobalBlockIsNotReturned(): void {
		$user = self::$testUsers['locallyDisabled'];
		$this->globallyBlockIndefinitely( $user );
		$this->globalBlockLocalStatusManager->locallyDisableBlock(
			$user->getName(),
			'local user block disabled',
			$this->adminUser->getUser()
		);

		$this->assertSame(
			[],
			$this->check->getIndefinitelyBlockedUserIds( [ $user->getId() ] )
		);
	}

	public function testTemporaryGlobalBlockIsNotReturnedForIndefiniteCheck(): void {
		$user = self::$testUsers['temporary'];
		$this->globalBlockManager->block(
			$user->getName(),
			'temporary global block',
			'20300101000000',
			$this->adminUser
		);

		$this->assertSame(
			[],
			$this->check->getIndefinitelyBlockedUserIds( [ $user->getId() ] )
		);
	}

	public function testTemporaryGlobalBlockIsReturned(): void {
		$user = self::$testUsers['temporary'];
		$this->globalBlockManager->block(
			$user->getName(),
			'temporary global block',
			'20300101000000',
			$this->adminUser
		);

		$this->assertSame(
			[ $user->getId() ],
			$this->check->getBlockedUserIds( [ $user->getId() ] )
		);
	}

	public function testMixOfBlockedUnblockedAndLocallyDisabledUsers(): void {
		$blockedUser = self::$testUsers['blocked'];
		$unblockedUser = self::$testUsers['unblocked'];
		$locallyWhitelistedUser = self::$testUsers['locallyDisabled'];
		$temporarilyBlockedUser = self::$testUsers['temporary'];

		$this->globallyBlockIndefinitely( $blockedUser );
		$this->globallyBlockIndefinitely( $locallyWhitelistedUser );
		$this->globalBlockLocalStatusManager->locallyDisableBlock(
			$locallyWhitelistedUser->getName(),
			'local user block disabled',
			$this->adminUser->getUser()
		);
		$this->globalBlockManager->block(
			$temporarilyBlockedUser->getName(),
			'temporary global block',
			'20300101000000',
			$this->adminUser
		);

		$this->assertSame(
			[ $blockedUser->getId() ],
			$this->check->getIndefinitelyBlockedUserIds( [
				$blockedUser->getId(),
				$unblockedUser->getId(),
				$locallyWhitelistedUser->getId(),
				$temporarilyBlockedUser->getId(),
			] ),
			'The return value from ::getIndefinitelyBlockedUserIds was not as expected'
		);
		$this->assertArrayEquals(
			[ $blockedUser->getId(), $temporarilyBlockedUser->getId() ],
			$this->check->getBlockedUserIds( [
				$blockedUser->getId(),
				$unblockedUser->getId(),
				$locallyWhitelistedUser->getId(),
				$temporarilyBlockedUser->getId(),
			] ),
			false,
			false,
			'The return value from ::getBlockedUserIds was not as expected'
		);
	}

	public function testGetBlockedUserIdsWhenUserIdDoesNotExist(): void {
		$this->assertSame( [], $this->check->getBlockedUserIds( [ 999999 ] ) );
	}

	public function testGetBlockedUserIdsWhenNoCentralId(): void {
		$blockedUser = self::$testUsers['blocked'];

		// Get a mock CentralIdLookup that finds no central ID attached
		// to the username on the local wiki
		$mockCentralIdLookup = $this->createMock( CentralIdLookup::class );
		$mockCentralIdLookup->expects( $this->once() )
			->method( 'lookupAttachedUserNames' )
			->with( [ $blockedUser->getName() => false ], CentralIdLookup::AUDIENCE_RAW )
			->willReturnArgument( 0 );

		$check = new GlobalBlockCheck(
			GlobalBlockingServices::wrap( $this->getServiceContainer() )->getGlobalBlockLookup(),
			$mockCentralIdLookup,
			$this->getServiceContainer()->getUserIdentityLookup(),
			true
		);

		$this->assertSame( [], $check->getBlockedUserIds( [ $blockedUser->getId() ] ) );
	}

	private function globallyBlockIndefinitely( UserIdentity $user ): void {
		$this->globalBlockManager->block(
			$user->getName(),
			'global indefinite block',
			'infinity',
			$this->adminUser
		);
	}
}
