<?php
/*
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\Tests\User;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Config\SiteConfiguration;
use MediaWiki\MainConfigNames;
use MediaWiki\User\RestrictedUserGroupConfigReader;
use MediaWiki\User\UserGroupRestrictions;
use MediaWiki\User\UserRequirementsConditionValidator;
use MediaWiki\WikiMap\WikiMap;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\User\RestrictedUserGroupConfigReader
 */
class RestrictedUserGroupConfigReaderTest extends MediaWikiUnitTestCase {

	private function setupConfigReader( array $restrictionsPerWiki ): RestrictedUserGroupConfigReader {
		$localWikiId = WikiMap::getCurrentWikiId();

		if ( isset( $restrictionsPerWiki['__local'] ) ) {
			$restrictionsPerWiki[$localWikiId] = $restrictionsPerWiki['__local'];
			unset( $restrictionsPerWiki['__local'] );
		}

		$mockSiteConfiguration = $this->createMock( SiteConfiguration::class );
		$mockSiteConfiguration->method( 'get' )
			->willReturnCallback( static function ( $setting, $wiki ) use ( $restrictionsPerWiki ) {
				if ( $setting !== 'wgRestrictedGroups' ) {
					return null;
				}

				if ( isset( $restrictionsPerWiki[$wiki] ) ) {
					return $restrictionsPerWiki[$wiki];
				}
				return null;
			} );

		global $wgConf;
		$wgConf = $mockSiteConfiguration;

		$serviceOptions = new ServiceOptions( RestrictedUserGroupConfigReader::CONSTRUCTOR_OPTIONS, [
			MainConfigNames::RestrictedGroups => $restrictionsPerWiki[$localWikiId] ?? [],
		] );
		$validator = $this->createMock( UserRequirementsConditionValidator::class );
		$validator->method( 'isValid' )
			->willReturn( true );
		return new RestrictedUserGroupConfigReader( $serviceOptions, $validator );
	}

	public function testGetConfig_local() {
		$configReader = $this->setupConfigReader( [
			'__local' => [ 'interface-admin' => [
				'memberConditions' => [ 'COND' ],
			] ],
			'wiki1' => [ 'sysop' => [] ],
			'wiki2' => [ 'bureaucrat' => [] ],
		] );

		$restrictedGroups = $configReader->getConfig();
		$this->assertArrayHasKey( 'interface-admin', $restrictedGroups );
		$this->assertCount( 1, $restrictedGroups );
		$this->assertInstanceOf( UserGroupRestrictions::class, $restrictedGroups['interface-admin'] );
		$this->assertEquals( [ 'COND' ], $restrictedGroups['interface-admin']->getMemberConditions() );
		$this->assertEquals( [], $restrictedGroups['interface-admin']->getUpdaterConditions() );
	}

	/** @dataProvider provideGetConfig_scope */
	public function testGetConfig_scope(
		array $localRestrictions,
		string $scope,
		array $expectedGroupNames
	) {
		$configReader = $this->setupConfigReader( [ '__local' => $localRestrictions ] );
		$result = $configReader->getConfig( false, $scope );
		$this->assertSame( $expectedGroupNames, array_keys( $result ) );
	}

	public static function provideGetConfig_scope(): iterable {
		yield 'No scope in spec - included for any scope' => [
			'localRestrictions' => [
				'sysop' => [
					'memberConditions' => [ 'COND' ],
				],
			],
			'scope' => 'remote',
			'expectedGroupNames' => [ 'sysop' ],
		];
		yield 'scope=[remote] - excluded for local scope' => [
			'localRestrictions' => [
				'sysop' => [
					'memberConditions' => [ 'COND' ],
					'scope' => [ 'remote' ],
				],
			],
			'scope' => RestrictedUserGroupConfigReader::SCOPE_LOCAL,
			'expectedGroupNames' => [],
		];
		yield 'scope=[local] - included for local scope' => [
			'localRestrictions' => [
				'sysop' => [
					'memberConditions' => [ 'COND' ],
					'scope' => [ RestrictedUserGroupConfigReader::SCOPE_LOCAL ],
				],
				'bureaucrat' => [
					'scope' => [ 'remote' ],
				],
				'interface-admin' => [],
			],
			'scope' => RestrictedUserGroupConfigReader::SCOPE_LOCAL,
			'expectedGroupNames' => [ 'sysop', 'interface-admin' ],
		];
		yield 'scope=[local, remote] - included for both' => [
			'localRestrictions' => [
				'sysop' => [
					'memberConditions' => [ 'COND' ],
					'scope' => [ RestrictedUserGroupConfigReader::SCOPE_LOCAL, 'remote' ],
				],
			],
			'scope' => 'remote',
			'expectedGroupNames' => [ 'sysop' ],
		];
	}

	public function testGetConfig_defaultScopeIsLocal() {
		$configReader = $this->setupConfigReader( [ '__local' => [
			'sysop' => [
				'scope' => [ RestrictedUserGroupConfigReader::SCOPE_LOCAL ],
			],
			'bureaucrat' => [
				'scope' => [ 'remote' ],
			],
		] ] );

		$result = $configReader->getConfig();
		$this->assertSame( [ 'sysop' ], array_keys( $result ) );
	}

	public function testGetConfig_remote() {
		$configReader = $this->setupConfigReader( [
			'__local' => [
				'interface-admin' => [],
				'sysop' => [
					'memberConditions' => [ 'COND_LOCAL' ],
				],
			],
			'wiki1' => [ 'sysop' => [
				'memberConditions' => [ 'COND_REMOTE' ],
			] ],
			'wiki2' => [ 'bureaucrat' => [] ],
		] );

		$restrictedGroups = $configReader->getConfig( 'wiki1' );
		$this->assertArrayHasKey( 'sysop', $restrictedGroups );
		$this->assertCount( 1, $restrictedGroups );
		$this->assertInstanceOf( UserGroupRestrictions::class, $restrictedGroups['sysop'] );
		$this->assertEquals( [ 'COND_REMOTE' ], $restrictedGroups['sysop']->getMemberConditions() );
		$this->assertEquals( [], $restrictedGroups['sysop']->getUpdaterConditions() );
	}
}
