<?php

namespace MediaWiki\CheckUser\Tests\Integration\HookHandler;

use MediaWiki\CheckUser\HookHandler\GroupsHandler;
use MediaWiki\MainConfigNames;
use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\UserGroupMembership;
use MediaWikiIntegrationTestCase;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\HookHandler\GroupsHandler
 */
class GroupsHandlerTest extends MediaWikiIntegrationTestCase {
	private static string $timestampNow = '20230406060708';

	protected function setUp(): void {
		parent::setUp();
		ConvertibleTimestamp::setFakeTime( self::$timestampNow );

		// We don't want to specifically test the CentralAuth implementation of the CentralIdLookup. As such, force it
		// to be the local provider.
		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
	}

	private function getHandler( $overrideServices ) {
		$services = $this->getServiceContainer();

		$arguments = array_merge( [
			'centralIdLookup' => $services->get( 'CentralIdLookup' ),
			'wanCache' => $services->getMainWANObjectCache(),
			'specialPageFactory' => $services->getSpecialPageFactory(),
		], $overrideServices );

		return new GroupsHandler( ...array_values( $arguments ) );
	}

	/** @dataProvider provideOnUserGroupsChanged */
	public function testOnUserGroupsChanged(
		bool $globalContributionsExists,
		array $addedGroups,
		int $centralId,
		int $expected
	) {
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );
		$this->markTestSkippedIfExtensionNotLoaded( 'GlobalPreferences' );

		$user = $this->getTestUser()->getUser();

		$centralIdLookup = $this->createMock( CentralIdLookup::class );
		$centralIdLookup->method( 'centralIdFromLocalUser' )
			->willReturn( $centralId );

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

		$handler = $this->getHandler( [
			'centralIdLookup' => $centralIdLookup,
			'wanCache' => $wanCache,
			'specialPageFactory' => $specialPageFactory,
		] );

		$handler->onUserGroupsChanged(
			$user,
			$addedGroups,
			[],
			false,
			false,
			[],
			array_fill_keys(
				$addedGroups,
				$this->createMock( UserGroupMembership::class )
			)
		);
	}

	public static function provideOnUserGroupsChanged() {
		return [
			'Early return when GlobalContributions does not exist' => [
				'globalContributionsexists' => false,
				'addedGroups' => [ 'testGroup' ],
				'centralUserId' => 1,
				'expected' => 0,
			],
			'Early return when groups not changed' => [
				'globalContributionsexists' => true,
				'addedGroups' => [],
				'centralUserId' => 1,
				'expected' => 0,
			],
			'Early return when central user does not exist' => [
				'globalContributionsexists' => true,
				'addedGroups' => [ 'testGroup' ],
				'centralUserId' => 0,
				'expected' => 0,
			],
			'Cache invalidated when GlobalContributions exists' => [
				'globalContributionsexists' => true,
				'addedGroups' => [ 'testGroup' ],
				'centralUserId' => 1,
				'expected' => 1,
			],
		];
	}
}
