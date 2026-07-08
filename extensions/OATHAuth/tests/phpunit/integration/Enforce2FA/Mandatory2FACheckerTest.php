<?php
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Enforce2FA;

use MediaWiki\Config\SiteConfiguration;
use MediaWiki\Extension\CentralAuth\CentralAuthUserCache;
use MediaWiki\Extension\CentralAuth\GlobalGroup\GlobalGroupAssignmentService;
use MediaWiki\Extension\CentralAuth\User\CentralAuthUser;
use MediaWiki\Extension\OATHAuth\OATHAuthServices;
use MediaWiki\MainConfigNames;
use MediaWiki\Registration\ExtensionRegistry;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserGroupManagerFactory;
use MediaWiki\User\UserIdentityValue;
use MediaWiki\User\UserRequirementsConditionChecker;
use MediaWiki\User\UserRequirementsConditionCheckerFactory;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Enforce2FA\Mandatory2FAChecker
 * @covers \MediaWiki\Extension\OATHAuth\OATHAuthServices
 */
class Mandatory2FACheckerTest extends MediaWikiIntegrationTestCase {

	public function testUserRequired2FA(): void {
		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, [
			'user' => [
				'memberConditions' => [ APCOND_OATH_HAS2FA ],
			],
		] );

		$this->setUserGroupManagerMock( [ 'user' ] );

		$checker = OATHAuthServices::getInstance()->getMandatory2FAChecker();

		$user = UserIdentityValue::newRegistered( 1, 'TestUser' );
		$this->assertSame(
			[ 'user' ],
			$checker->getGroupsRequiring2FA( $user )
		);
	}

	public function testGetGroupsRequiring2FA_simpleCondition() {
		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, [
			'interface-admin' => [
				'memberConditions' => [ APCOND_OATH_HAS2FA ],
			],
			'checkuser' => [
				'memberConditions' => [	APCOND_OATH_HAS2FA ],
			],
			'sysop' => [
				'memberConditions' => [ APCOND_EDITCOUNT, 0 ],
			]
		] );

		$this->setUserGroupManagerMock( [ 'user', 'interface-admin', 'sysop' ] );

		$checker = OATHAuthServices::getInstance()->getMandatory2FAChecker();

		$user = UserIdentityValue::newRegistered( 1, 'TestUser' );
		$groups2FA = $checker->getGroupsRequiring2FA( $user );
		$this->assertSame( [ 'interface-admin' ], $groups2FA );
	}

	public function testGroupsRequiring2FA_complexCondition() {
		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, [
			'interface-admin' => [
				'memberConditions' => [
					// 2FA not required, user meets the other condition
					'|', APCOND_OATH_HAS2FA, [ APCOND_EDITCOUNT, 0 ]
				]
			],
			'checkuser' => [
				'memberConditions' => [
					// 2FA not required, user doesn't meet the other condition
					'&', APCOND_OATH_HAS2FA, [ APCOND_EDITCOUNT, 1000 ]
				]
			],
			'sysop' => [
				'memberConditions' => [
					// 2FA required
					'&', APCOND_OATH_HAS2FA, [ APCOND_EDITCOUNT, 0 ]
				]
			]
		] );

		$userEditTracker = $this->createMock( UserEditTracker::class );
		$userEditTracker->method( 'getUserEditCount' )
			->willReturn( 0 );
		$this->setService( 'UserEditTracker', $userEditTracker );

		$this->setUserGroupManagerMock( [ 'user', 'interface-admin', 'checkuser', 'sysop' ] );

		$checker = OATHAuthServices::getInstance()->getMandatory2FAChecker();

		$user = UserIdentityValue::newRegistered( 1, 'TestUser' );
		$groups2FA = $checker->getGroupsRequiring2FA( $user );
		$this->assertSame( [ 'sysop' ], $groups2FA );
	}

	public function testGroupsRequiring2FA_no2FACondition() {
		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, [
			'sysop' => [
				'memberConditions' => [ APCOND_EDITCOUNT, 0 ],
			],
		] );

		$this->setUserGroupManagerMock( [ 'user', 'sysop' ] );

		$userRequirementsChecker = $this->createMock( UserRequirementsConditionChecker::class );
		$userRequirementsChecker->expects( $this->never() )
			->method( 'recursivelyCheckCondition' );
		$userRequirementsCheckerFactory = $this->createMock( UserRequirementsConditionCheckerFactory::class );
		$userRequirementsCheckerFactory->method( 'getCheckerWithCustomConditions' )
			->willReturn( $userRequirementsChecker );
		$this->setService( 'UserRequirementsConditionCheckerFactory', $userRequirementsCheckerFactory );

		$checker = OATHAuthServices::getInstance()->getMandatory2FAChecker();

		$user = UserIdentityValue::newRegistered( 1, 'TestUser' );
		$groups2FA = $checker->getGroupsRequiring2FA( $user );
		$this->assertSame( [], $groups2FA );
	}

	public function testGroupsRequiring2FA_crossWiki() {
		$this->setUserGroupManagerMock( [ 'user', 'sysop' ] );

		$siteConfiguration = $this->createMock( SiteConfiguration::class );
		$siteConfiguration->method( 'get' )
			->willReturnCallback( static function ( $setting, $wiki ) {
				if ( $setting === 'wgRestrictedGroups' && $wiki === 'remote-wiki' ) {
					return [
						'sysop' => [
							'memberConditions' => [ APCOND_OATH_HAS2FA ],
						],
					];
				}
				return null;
			} );

		global $wgConf;
		$wgConf = $siteConfiguration;

		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, [] );

		$checker = OATHAuthServices::getInstance()->getMandatory2FAChecker();

		$userLocal = UserIdentityValue::newRegistered( 1, 'TestUser' );
		$userRemote = UserIdentityValue::newRegistered( 1, 'TestUser', 'remote-wiki' );
		$this->assertSame( [], $checker->getGroupsRequiring2FA( $userLocal ) );
		$this->assertSame( [ 'sysop' ], $checker->getGroupsRequiring2FA( $userRemote ) );
	}

	public function testGroupsRequiring2FAAcrossWikiFarm_withCentralAuth() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );

		$this->setUserGroupManagerMock( [ 'user', 'sysop' ] );

		$caUser = $this->createMock( CentralAuthUser::class );
		$caUser->method( 'queryAttached' )
			->willReturn( [
				[
					'wiki' => WikiMap::getCurrentWikiId(),
					'groupMemberships' => [ 'sysop' ],
					'id' => 1,
				],
				[
					'wiki' => 'remote1',
					'groupMemberships' => [ 'sysop' ],
					'id' => 1,
				],
				[
					'wiki' => 'remote2',
					'groupMemberships' => [],
					'id' => 1,
				],
				[
					'wiki' => 'remote3',
					'groupMemberships' => [ 'sysop' ],
					'id' => 1,
				]
			] );
		$caUser->method( 'getGlobalGroups' )
			->willReturn( [] );

		$caUserCache = $this->createMock( CentralAuthUserCache::class );
		$caUserCache->method( 'get' )
			->willReturn( $caUser );
		$this->setService( 'CentralAuth.CentralAuthUserCache', $caUserCache );

		$restrictedGroups = [
			'sysop' => [
				'memberConditions' => [ APCOND_OATH_HAS2FA ],
			],
		];
		$siteConfiguration = $this->createMock( SiteConfiguration::class );
		$siteConfiguration->method( 'get' )
			->willReturnCallback( static function ( $setting, $wiki ) use ( $restrictedGroups ) {
				if ( $setting === 'wgRestrictedGroups' ) {
					return $wiki !== 'remote3' ? $restrictedGroups : [];
				}
				return null;
			} );

		global $wgConf;
		$wgConf = $siteConfiguration;
		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, $restrictedGroups );

		// The username and id don't matter, we're mocked to always return the same central user
		$userLocal = UserIdentityValue::newRegistered( 1, 'TestUser' );
		$checker = OATHAuthServices::getInstance()->getMandatory2FAChecker();
		$result = $checker->getGroupsRequiring2FAAcrossWikiFarm( $userLocal );

		$expected = [
			WikiMap::getCurrentWikiId() => [ 'sysop' ],
			'remote1' => [ 'sysop' ],
		];
		$this->assertSame( $expected, $result );
	}

	public function testGroupsRequiring2FAAcrossWikiFarm_withoutCentralAuth() {
		$extensionRegistry = $this->createMock( ExtensionRegistry::class );
		$extensionRegistry->method( 'isLoaded' )
			->willReturn( false );
		$this->setService( 'ExtensionRegistry', $extensionRegistry );

		$this->setUserGroupManagerMock( [ 'user', 'sysop' ] );

		$restrictedGroups = [
			'sysop' => [
				'memberConditions' => [ APCOND_OATH_HAS2FA ],
			],
		];
		$siteConfiguration = $this->createMock( SiteConfiguration::class );
		$siteConfiguration->method( 'get' )
			->willReturnCallback( static function ( $setting ) use ( $restrictedGroups ) {
				if ( $setting === 'wgRestrictedGroups' ) {
					return $restrictedGroups;
				}
				return null;
			} );

		global $wgConf;
		$wgConf = $siteConfiguration;
		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, $restrictedGroups );

		$userLocal = UserIdentityValue::newRegistered( 1, 'TestUser' );
		$checker = OATHAuthServices::getInstance()->getMandatory2FAChecker();
		$result = $checker->getGroupsRequiring2FAAcrossWikiFarm( $userLocal );

		$expected = [
			WikiMap::getCurrentWikiId() => [ 'sysop' ],
		];
		$this->assertSame( $expected, $result );
	}

	public static function provideGlobalGroupConditions(): array {
		return [
			'2FA condition requires 2FA' => [
				'memberConditions' => [ APCOND_OATH_HAS2FA ],
				'expectedResult' => [ 'central-wiki' => [ 'global-sysop' ] ],
			],
			'non-2FA condition does not require 2FA' => [
				'memberConditions' => [ APCOND_EDITCOUNT, 0 ],
				'expectedResult' => [],
			],
		];
	}

	/**
	 * @dataProvider provideGlobalGroupConditions
	 */
	public function testGroupsRequiring2FAAcrossWikiFarm_globalGroups(
		array $memberConditions,
		array $expectedResult
	) {
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );

		$this->setUserGroupManagerMock( [] );

		$caUser = $this->createMock( CentralAuthUser::class );
		$caUser->method( 'queryAttached' )
			->willReturn( [] );
		$caUser->method( 'getGlobalGroups' )
			->willReturn( [ 'global-sysop' ] );

		$caUserCache = $this->createMock( CentralAuthUserCache::class );
		$caUserCache->method( 'get' )
			->willReturn( $caUser );
		$this->setService( 'CentralAuth.CentralAuthUserCache', $caUserCache );

		$siteConfiguration = $this->createMock( SiteConfiguration::class );
		$siteConfiguration->method( 'get' )
			->willReturnCallback( static function ( $setting, $wiki ) use ( $memberConditions ) {
				if ( $setting === 'wgRestrictedGroups' && $wiki === 'central-wiki' ) {
					return [
						'global-sysop' => [
							'memberConditions' => $memberConditions,
							'scope' => [ GlobalGroupAssignmentService::RESTRICTION_SCOPE ],
						],
					];
				}
				return null;
			} );

		global $wgConf;
		$wgConf = $siteConfiguration;
		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, [] );
		$this->overrideConfigValue( 'CentralAuthCentralWiki', 'central-wiki' );

		$userLocal = UserIdentityValue::newRegistered( 1, 'TestUser' );
		$checker = OATHAuthServices::getInstance()->getMandatory2FAChecker();
		$result = $checker->getGroupsRequiring2FAAcrossWikiFarm( $userLocal );

		$this->assertSame( $expectedResult, $result );
	}

	public function testGroupsRequiring2FAAcrossWikiFarm_globalAndLocalGroupsMergedUnderCentralWiki() {
		$this->markTestSkippedIfExtensionNotLoaded( 'CentralAuth' );

		// User has a local 'sysop' group on the central wiki, and a global 'global-sysop' group,
		// both requiring 2FA. Both should appear merged under the central wiki key.
		$this->setUserGroupManagerMock( [ 'user', 'sysop' ] );

		$caUser = $this->createMock( CentralAuthUser::class );
		$caUser->method( 'queryAttached' )
			->willReturn( [
				[
					'wiki' => 'central-wiki',
					'groupMemberships' => [ 'sysop' ],
					'id' => 1,
				],
			] );
		$caUser->method( 'getGlobalGroups' )
			->willReturn( [ 'global-sysop', 'steward' ] );

		$caUserCache = $this->createMock( CentralAuthUserCache::class );
		$caUserCache->method( 'get' )
			->willReturn( $caUser );
		$this->setService( 'CentralAuth.CentralAuthUserCache', $caUserCache );

		$restrictedGroups = [
			'sysop' => [
				'memberConditions' => [ APCOND_OATH_HAS2FA ],
			],
			'global-sysop' => [
				'memberConditions' => [ APCOND_OATH_HAS2FA ],
				'scope' => [ 'centralauth' ],
			],
		];
		$siteConfiguration = $this->createMock( SiteConfiguration::class );
		$siteConfiguration->method( 'get' )
			->willReturnCallback( static function ( $setting, $wiki ) use ( $restrictedGroups ) {
				if ( $setting === 'wgRestrictedGroups' ) {
					return $restrictedGroups;
				}
				return null;
			} );

		global $wgConf;
		$wgConf = $siteConfiguration;
		$this->overrideConfigValue( MainConfigNames::RestrictedGroups, [] );
		$this->overrideConfigValue( 'CentralAuthCentralWiki', 'central-wiki' );

		$userLocal = UserIdentityValue::newRegistered( 1, 'TestUser' );
		$checker = OATHAuthServices::getInstance()->getMandatory2FAChecker();
		$result = $checker->getGroupsRequiring2FAAcrossWikiFarm( $userLocal );

		$this->assertSame( [ 'central-wiki' => [ 'sysop', 'global-sysop' ] ], $result );
	}

	private function setUserGroupManagerMock( array $userGroups ) {
		$userGroupManagerFactory = $this->createMock( UserGroupManagerFactory::class );
		$userGroupManagerFactory->method( 'getUserGroupManager' )
			->willReturnCallback( function () use ( $userGroups ) {
				$ugm = $this->createMock( UserGroupManager::class );
				$ugm->method( 'getUserGroups' )
					->willReturn( $userGroups );
				$ugm->method( 'getUserImplicitGroups' )
					->willReturn( [] );
				return $ugm;
			} );
		$this->setService( 'UserGroupManagerFactory', $userGroupManagerFactory );
	}
}
