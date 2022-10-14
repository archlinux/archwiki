<?php

use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Degroup;
use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;
use MediaWiki\Extension\AbuseFilter\FilterUser;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentityValue;

/**
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Degroup
 * @covers ::__construct
 * @todo Make this a unit test once ManualLogEntry is servicified (T253717)
 */
class DegroupTest extends MediaWikiIntegrationTestCase {
	use ConsequenceGetMessageTestTrait;

	private function getMsgLocalizer(): MessageLocalizer {
		$ml = $this->createMock( MessageLocalizer::class );
		$ml->method( 'msg' )->willReturnCallback( function ( $k, $p ) {
			return $this->getMockMessage( $k, $p );
		} );
		return $ml;
	}

	private function getFilterUser(): FilterUser {
		// TODO: Can't use mocks until ManualLogEntry is servicified (T253717)
		return AbuseFilterServices::getFilterUser();
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute() {
		$user = new UserIdentityValue( 1, 'Degrouped user' );
		$params = $this->provideGetMessageParameters( $user )->current()[0];
		$userGroupManager = $this->createMock( UserGroupManager::class );
		$userGroupManager->method( 'listAllImplicitGroups' )
			->willReturn( [ '*', 'user' ] );
		$userGroupManager->expects( $this->once() )
			->method( 'removeUserFromGroup' )
			->with( $user, 'sysop' );
		$filterUser = $this->getFilterUser();

		$degroup = new Degroup(
			$params,
			VariableHolder::newFromArray( [ 'user_groups' => [ '*', 'user', 'sysop' ] ] ),
			$userGroupManager,
			$filterUser,
			$this->getMsgLocalizer()
		);
		$this->assertTrue( $degroup->execute() );
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute_noGroups() {
		$params = $this->provideGetMessageParameters()->current()[0];
		$userGroupManager = $this->createMock( UserGroupManager::class );
		$userGroupManager->method( 'listAllImplicitGroups' )
			->willReturn( [ '*', 'user' ] );
		$userGroupManager->expects( $this->never() )
			->method( 'removeUserFromGroup' );

		$degroup = new Degroup(
			$params,
			VariableHolder::newFromArray( [ 'user_groups' => [ '*', 'user' ] ] ),
			$userGroupManager,
			$this->createMock( FilterUser::class ),
			$this->getMsgLocalizer()
		);
		$this->assertFalse( $degroup->execute() );
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute_variableNotSet() {
		$user = new UserIdentityValue( 1, 'Degrouped user' );
		$params = $this->provideGetMessageParameters( $user )->current()[0];
		$userGroupManager = $this->createMock( UserGroupManager::class );
		$userGroupManager->method( 'listAllImplicitGroups' )
			->willReturn( [ '*', 'user' ] );
		$userGroupManager->method( 'getUserEffectiveGroups' )
			->with( $user )
			->willReturn( [ '*', 'user', 'sysop' ] );
		$userGroupManager->expects( $this->once() )
			->method( 'removeUserFromGroup' )
			->with( $user, 'sysop' );
		$filterUser = $this->getFilterUser();

		$degroup = new Degroup(
			$params,
			new VariableHolder(),
			$userGroupManager,
			$filterUser,
			$this->getMsgLocalizer()
		);
		$this->assertTrue( $degroup->execute() );
	}

	/**
	 * @covers ::execute
	 */
	public function testExecute_anonymous() {
		$user = new UserIdentityValue( 0, 'Anonymous user' );
		$params = $this->provideGetMessageParameters( $user )->current()[0];
		$userGroupManager = $this->createMock( UserGroupManager::class );
		$userGroupManager->expects( $this->never() )->method( $this->anything() );
		$filterUser = $this->createMock( FilterUser::class );
		$filterUser->expects( $this->never() )->method( $this->anything() );

		$degroup = new Degroup(
			$params,
			$this->createMock( VariableHolder::class ),
			$userGroupManager,
			$filterUser,
			$this->getMsgLocalizer()
		);
		$this->assertFalse( $degroup->execute() );
	}

	public function provideRevert(): array {
		return [
			[ true, [ '*', 'user', 'sysop' ] ],
			[ true, [ '*', 'user', 'canceled', 'sysop' ] ],
			[ false, [ '*', 'user', 'sysop' ], [ 'sysop' ] ],
			[ false, [ '*', 'user', 'canceled' ] ],
		];
	}

	/**
	 * @covers ::revert
	 * @dataProvider provideRevert
	 */
	public function testRevert( bool $success, array $hadGroups, array $hasGroups = [] ) {
		$user = new UserIdentityValue( 1, 'Degrouped user' );
		$params = $this->provideGetMessageParameters( $user )->current()[0];
		$userGroupManager = $this->createMock( UserGroupManager::class );
		$userGroupManager->method( 'listAllImplicitGroups' )
			->willReturn( [ '*', 'user' ] );
		$userGroupManager->method( 'getUserGroups' )
			->with( $user )
			->willReturn( $hasGroups );
		$userGroupManager->method( 'addUserToGroup' )
			->willReturnCallback( static function ( $_, $group ) use ( $hasGroups ) {
				return $group === 'sysop';
			} );
		$degroup = new Degroup(
			$params,
			new VariableHolder(),
			$userGroupManager,
			$this->createMock( FilterUser::class ),
			$this->getMsgLocalizer()
		);

		$info = [
			'vars' => VariableHolder::newFromArray( [ 'user_groups' => $hadGroups ] )
		];
		$performer = $this->getTestUser()->getUser();
		$this->assertSame(
			$success,
			$degroup->revert( $info, $performer, 'reason' )
		);
	}

	/**
	 * @covers ::getMessage
	 * @dataProvider provideGetMessageParameters
	 */
	public function testGetMessage( Parameters $params ) {
		$rangeBlock = new Degroup(
			$params,
			new VariableHolder(),
			$this->createMock( UserGroupManager::class ),
			$this->createMock( FilterUser::class ),
			$this->getMsgLocalizer()
		);
		$this->doTestGetMessage( $rangeBlock, $params, 'abusefilter-degrouped' );
	}
}
