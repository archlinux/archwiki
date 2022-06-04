<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit;

use Generator;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\Filter\AbstractFilter;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\User\UserIdentity;
use MediaWikiUnitTestCase;
use User;

/**
 * @coversDefaultClass \MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager
 * @covers ::__construct
 */
class AbuseFilterPermissionManagerTest extends MediaWikiUnitTestCase {

	private function getPermMan( array $rights = [] ): AbuseFilterPermissionManager {
		$pm = $this->createMock( PermissionManager::class );
		$pm->method( 'userHasRight' )->willReturnCallback( static function ( $u, $r ) use ( $rights ) {
			return in_array( $r, $rights, true );
		} );
		$pm->method( 'userHasAnyRight' )->willReturnCallback( static function ( $u, ...$r ) use ( $rights ) {
			return (bool)array_intersect( $r, $rights );
		} );
		return new AbuseFilterPermissionManager( $pm );
	}

	public function provideCanEdit(): Generator {
		$blockedUser = $this->createMock( User::class );
		$sitewideBlock = $this->createMock( DatabaseBlock::class );
		$sitewideBlock->method( 'isSiteWide' )->willReturn( true );
		$blockedUser->method( 'getBlock' )->willReturn( $sitewideBlock );
		yield 'blocked sitewide' => [ $blockedUser, [], false ];

		$partialBlockedUser = $this->createMock( User::class );
		$partialBlock = $this->createMock( DatabaseBlock::class );
		$partialBlock->method( 'isSiteWide' )->willReturn( false );
		$partialBlockedUser->method( 'getBlock' )->willReturn( $partialBlock );
		yield 'partially blocked' => [ $partialBlockedUser, [], false ];

		$unblockedUser = $this->createMock( User::class );
		yield 'unblocked, no right' => [ $unblockedUser, [], false ];

		yield 'success' => [ $unblockedUser, [ 'abusefilter-modify' ], true ];
	}

	/**
	 * @param User $user
	 * @param array $rights
	 * @param bool $expected
	 * @covers ::canEdit
	 * @dataProvider provideCanEdit
	 */
	public function testCanEdit( User $user, array $rights, bool $expected ) {
		$this->assertSame( $expected, $this->getPermMan( $rights )->canEdit( $user ) );
	}

	public function provideCanEditGlobal(): Generator {
		yield 'not allowed' => [ [], false ];
		yield 'allowed' => [ [ 'abusefilter-modify-global' ], true ];
	}

	/**
	 * @covers ::canEditGlobal
	 * @dataProvider provideCanEditGlobal
	 */
	public function testCanEditGlobal( array $rights, bool $expected ) {
		$user = $this->createMock( UserIdentity::class );
		$this->assertSame( $expected, $this->getPermMan( $rights )->canEditGlobal( $user ) );
	}

	public function provideCanEditFilter(): Generator {
		$localFilter = $this->createMock( AbstractFilter::class );
		$localFilter->method( 'isGlobal' )->willReturn( false );
		$globalFilter = $this->createMock( AbstractFilter::class );
		$globalFilter->method( 'isGlobal' )->willReturn( true );
		foreach ( $this->provideCanEdit() as $name => $editArgs ) {
			foreach ( $this->provideCanEditGlobal() as $allowed => $globalArgs ) {
				yield "can edit: $name; can edit global: $allowed; local filter" => [
					$localFilter,
					$editArgs[0],
					array_merge( $editArgs[1], $globalArgs[0] ),
					$editArgs[2]
				];
				yield "can edit: $name; can edit global: $allowed; global filter" => [
					$globalFilter,
					$editArgs[0],
					array_merge( $editArgs[1], $globalArgs[0] ),
					$editArgs[2] && $globalArgs[1]
				];
			}
		}
	}

	/**
	 * @param AbstractFilter $filter
	 * @param User $user
	 * @param array $rights
	 * @param bool $expected
	 * @covers ::canEditFilter
	 * @dataProvider provideCanEditFilter
	 */
	public function testCanEditFilter( AbstractFilter $filter, User $user, array $rights, bool $expected ) {
		$this->assertSame( $expected, $this->getPermMan( $rights )->canEditFilter( $user, $filter ) );
	}

	public function provideCanViewPrivateFilters(): Generator {
		yield 'not privileged' => [ [], false ];
		yield 'modify' => [ [ 'abusefilter-modify' ], true ];
		yield 'private' => [ [ 'abusefilter-view-private' ], true ];
		yield 'both' => [ [ 'abusefilter-modify', 'abusefilter-view-private' ], true ];
	}

	/**
	 * @covers ::canViewPrivateFilters
	 * @dataProvider provideCanViewPrivateFilters
	 */
	public function testCanViewPrivateFilters( array $rights, bool $expected ) {
		$user = $this->createMock( UserIdentity::class );
		$this->assertSame( $expected, $this->getPermMan( $rights )->canViewPrivateFilters( $user ) );
	}

	public function provideCanViewPrivateFiltersLogs(): Generator {
		yield 'not privileged' => [ [], false ];
		yield 'can view private' => [ [ 'abusefilter-view-private' ], true ];
		yield 'can view logs' => [ [ 'abusefilter-log-private' ], true ];
		yield 'both' => [ [ 'abusefilter-view-private', 'abusefilter-log-private' ], true ];
	}

	/**
	 * @param array $rights
	 * @param bool $expected
	 * @covers ::canViewPrivateFiltersLogs
	 * @dataProvider provideCanViewPrivateFiltersLogs
	 */
	public function testCanViewPrivateFiltersLogs( array $rights, bool $expected ) {
		$user = $this->createMock( UserIdentity::class );
		$this->assertSame( $expected, $this->getPermMan( $rights )->canViewPrivateFiltersLogs( $user ) );
	}

	public function provideCanSeeLogDetailsForFilter(): Generator {
		$details = [ 0 => 'abusefilter-log-detail' ];
		$private = [ 1 => 'abusefilter-log-private' ];
		yield 'filter hidden, not privileged' => [ true, [], false ];
		yield 'filter hidden, details only' => [ true, $details, false ];
		yield 'filter hidden, private logs only' => [ true, $private, false ];
		yield 'filter hidden, details and private logs' => [ true, $details + $private, true ];
		yield 'filter visible, not privileged' => [ false, [], false ];
		yield 'filter visible, privileged' => [ false, $details, true ];
	}

	/**
	 * @param bool $filterHidden
	 * @param array $rights
	 * @param bool $expected
	 * @covers ::canSeeLogDetailsForFilter
	 * @dataProvider provideCanSeeLogDetailsForFilter
	 */
	public function testCanSeeLogDetailsForFilter( bool $filterHidden, array $rights, bool $expected ) {
		$user = $this->createMock( UserIdentity::class );
		$this->assertSame( $expected, $this->getPermMan( $rights )->canSeeLogDetailsForFilter( $user, $filterHidden ) );
	}

	public function provideSimpleCases(): array {
		return [
			'not allowed' => [ false ],
			'allowed' => [ true ],
		];
	}

	/**
	 * @covers ::canEditFilterWithRestrictedActions
	 * @dataProvider provideSimpleCases
	 */
	public function testCanEditFilterWithRestrictedActions( bool $allowed ) {
		$rights = $allowed ? [ 'abusefilter-modify-restricted' ] : [];
		$user = $this->createMock( UserIdentity::class );
		$this->assertSame( $allowed, $this->getPermMan( $rights )->canEditFilterWithRestrictedActions( $user ) );
	}

	/**
	 * @covers ::canViewAbuseLog
	 * @dataProvider provideSimpleCases
	 */
	public function testCanViewAbuseLog( bool $allowed ) {
		$rights = $allowed ? [ 'abusefilter-log' ] : [];
		$user = $this->createMock( UserIdentity::class );
		$this->assertSame( $allowed, $this->getPermMan( $rights )->canViewAbuseLog( $user ) );
	}

	/**
	 * @covers ::canHideAbuseLog
	 * @dataProvider provideSimpleCases
	 */
	public function testCanHideAbuseLog( bool $allowed ) {
		$rights = $allowed ? [ 'abusefilter-hide-log' ] : [];
		$user = $this->createMock( UserIdentity::class );
		$this->assertSame( $allowed, $this->getPermMan( $rights )->canHideAbuseLog( $user ) );
	}

	/**
	 * @covers ::canRevertFilterActions
	 * @dataProvider provideSimpleCases
	 */
	public function testCanRevertFilterActions( bool $allowed ) {
		$rights = $allowed ? [ 'abusefilter-revert' ] : [];
		$user = $this->createMock( UserIdentity::class );
		$this->assertSame( $allowed, $this->getPermMan( $rights )->canRevertFilterActions( $user ) );
	}

	/**
	 * @covers ::canSeeLogDetails
	 * @dataProvider provideSimpleCases
	 */
	public function testCanSeeLogDetails( bool $allowed ) {
		$rights = $allowed ? [ 'abusefilter-log-detail' ] : [];
		$user = $this->createMock( UserIdentity::class );
		$this->assertSame( $allowed, $this->getPermMan( $rights )->canSeeLogDetails( $user ) );
	}

	/**
	 * @covers ::canSeePrivateDetails
	 * @dataProvider provideSimpleCases
	 */
	public function testCanSeePrivateDetails( bool $allowed ) {
		$rights = $allowed ? [ 'abusefilter-privatedetails' ] : [];
		$user = $this->createMock( UserIdentity::class );
		$this->assertSame( $allowed, $this->getPermMan( $rights )->canSeePrivateDetails( $user ) );
	}

	/**
	 * @covers ::canSeeHiddenLogEntries
	 * @dataProvider provideSimpleCases
	 */
	public function testCanSeeHiddenLogEntries( bool $allowed ) {
		$rights = $allowed ? [ 'abusefilter-hidden-log' ] : [];
		$user = $this->createMock( UserIdentity::class );
		$this->assertSame( $allowed, $this->getPermMan( $rights )->canSeeHiddenLogEntries( $user ) );
	}

	/**
	 * @covers ::canUseTestTools
	 * @dataProvider provideSimpleCases
	 */
	public function testCanUseTestTools( bool $allowed ) {
		$rights = $allowed ? [ 'abusefilter-modify' ] : [];
		$user = $this->createMock( UserIdentity::class );
		$this->assertSame( $allowed, $this->getPermMan( $rights )->canUseTestTools( $user ) );
	}

}
