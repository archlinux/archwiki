<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\CheckUser\HookHandler\CentralAuthHandler;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;
use Wikimedia\ObjectCache\WANObjectCache;

/**
 * @covers \MediaWiki\CheckUser\HookHandler\CentralAuthHandler
 * @group CheckUser
 * @group Database
 */
class CentralAuthHandlerTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );
	}

	private function getHookHandler( $overrides = [] ) {
		$services = $this->getServiceContainer();
		return new CentralAuthHandler(
			$overrides[ 'wanCache' ] ?? $services->getMainWANObjectCache(),
			$overrides[ 'specialPageFactory' ] ?? $services->getSpecialPageFactory()
		);
	}

	/** @dataProvider provideOnGlobalUserGroupsChanged */
	public function testOnGlobalUserGroupsChanged( $globalContributionsExists, $expected ) {
		$services = $this->getServiceContainer();

		$user = $this->getMutableTestUser();
		$caUser = CentralAuthUser::getPrimaryInstance( $user->getUser() );
		$caUser->register( $user->getPassword(), null );
		$caUser->attach( WikiMap::getCurrentWikiId() );
		$centralId = $services->get( 'CentralIdLookup' )
			->centralIdFromLocalUser( $user->getUserIdentity() );

		$wanCache = $this->createMock( WANObjectCache::class );
		$wanCache->method( 'makeGlobalKey' )
			->with( 'globalcontributions-ext-permissions', $centralId )
			->willReturn( 'checkKey' );
		$wanCache->expects( $this->exactly( $expected ) )
			->method( 'touchCheckKey' )
			->with( 'checkKey' );

		$specialPageFactory = $this->createMock( SpecialPageFactory::class );
		$specialPageFactory->method( 'exists' )
			->with( 'GlobalContributions' )
			->willReturn( $globalContributionsExists );

		$handler = $this->getHookHandler( [
			'wanCache' => $wanCache,
			'specialPageFactory' => $specialPageFactory,
		] );

		$handler->onCentralAuthGlobalUserGroupMembershipChanged(
			$caUser,
			[],
			[]
		);
	}

	public static function provideOnGlobalUserGroupsChanged() {
		return [
			'Early return when GlobalContributions does not exist' => [ false, 0 ],
			'Cache invalidated when GlobalContributions exists' => [ true, 1 ],
		];
	}
}
