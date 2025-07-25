<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit;

use Generator;
use LogicException;
use MediaWiki\Block\DatabaseBlock;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\Filter\AbstractFilter;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Extension\AbuseFilter\Filter\Specs;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner;
use MediaWiki\Extension\AbuseFilter\Parser\RuleCheckerFactory;
use MediaWiki\Extension\AbuseFilter\Variables\AbuseFilterProtectedVariablesLookup;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager
 */
class AbuseFilterPermissionManagerTest extends MediaWikiUnitTestCase {
	use MockAuthorityTrait;
	use GetFilterEvaluatorTestTrait;

	private function getPermMan(): AbuseFilterPermissionManager {
		$protectedVariablesLookup = $this->createMock( AbuseFilterProtectedVariablesLookup::class );
		$protectedVariablesLookup->method( 'getAllProtectedVariables' )
			->willReturn( [ 'user_unnamed_ip' ] );
		$ruleCheckerFactory = $this->createMock( RuleCheckerFactory::class );
		$ruleCheckerFactory->method( 'newRuleChecker' )->willReturn( $this->getFilterEvaluator() );
		$hookRunner = $this->createMock( AbuseFilterHookRunner::class );
		return new AbuseFilterPermissionManager( $protectedVariablesLookup, $ruleCheckerFactory, $hookRunner );
	}

	public function provideCanEdit(): Generator {
		$sitewideBlock = $this->createMock( DatabaseBlock::class );
		$sitewideBlock->method( 'isSiteWide' )->willReturn( true );
		yield 'blocked sitewide' => [ $sitewideBlock, [], false ];

		$partialBlock = $this->createMock( DatabaseBlock::class );
		$partialBlock->method( 'isSiteWide' )->willReturn( false );
		yield 'partially blocked' => [ $partialBlock, [], false ];

		yield 'unblocked, no right' => [ null, [], false ];

		yield 'success' => [ null, [ 'abusefilter-modify' ], true ];
	}

	/**
	 * @param ?DatabaseBlock $block
	 * @param array $rights
	 * @param bool $expected
	 * @dataProvider provideCanEdit
	 */
	public function testCanEdit( ?DatabaseBlock $block, array $rights, bool $expected ) {
		if ( $block !== null ) {
			$performer = $this->mockUserAuthorityWithBlock(
				$this->mockRegisteredUltimateAuthority()->getUser(),
				$block,
				$rights
			);
		} else {
			$performer = $this->mockRegisteredAuthorityWithPermissions( $rights );
		}
		$this->assertSame(
			$expected,
			$this->getPermMan()->canEdit( $performer )
		);
	}

	public static function provideCanEditGlobal(): Generator {
		yield 'not allowed' => [ [], false ];
		yield 'allowed' => [ [ 'abusefilter-modify-global' ], true ];
	}

	/**
	 * @dataProvider provideCanEditGlobal
	 */
	public function testCanEditGlobal( array $rights, bool $expected ) {
		$performer = $this->mockRegisteredAuthorityWithPermissions( $rights );
		$this->assertSame(
			$expected,
			$this->getPermMan()->canEditGlobal( $performer )
		);
	}

	public function provideCanEditFilter(): Generator {
		$localFilter = MutableFilter::newDefault();
		$localFilter->setGlobal( false );
		$globalFilter = MutableFilter::newDefault();
		$globalFilter->setGlobal( true );
		foreach ( $this->provideCanEdit() as $name => $editArgs ) {
			foreach ( self::provideCanEditGlobal() as $allowed => $globalArgs ) {
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
	 * @param ?DatabaseBlock $block
	 * @param array $rights
	 * @param bool $expected
	 * @dataProvider provideCanEditFilter
	 */
	public function testCanEditFilter(
		AbstractFilter $filter,
		?DatabaseBlock $block,
		array $rights,
		bool $expected
	) {
		if ( $block !== null ) {
			$performer = $this->mockUserAuthorityWithBlock(
				$this->mockRegisteredUltimateAuthority()->getUser(),
				$block,
				$rights
			);
		} else {
			$performer = $this->mockRegisteredAuthorityWithPermissions( $rights );
		}
		$this->assertSame(
			$expected,
			$this->getPermMan()->canEditFilter( $performer, $filter )
		);
	}

	public static function provideCanViewPrivateFilters(): Generator {
		yield 'not privileged' => [ [], false ];
		yield 'modify' => [ [ 'abusefilter-modify' ], true ];
		yield 'private' => [ [ 'abusefilter-view-private' ], true ];
		yield 'both' => [ [ 'abusefilter-modify', 'abusefilter-view-private' ], true ];
	}

	/**
	 * @dataProvider provideCanViewPrivateFilters
	 */
	public function testCanViewPrivateFilters( array $rights, bool $expected ) {
		$performer = $this->mockRegisteredAuthorityWithPermissions( $rights );
		$this->assertSame(
			$expected,
			$this->getPermMan()->canViewPrivateFilters( $performer )
		);
	}

	public static function provideCanViewPrivateFiltersLogs(): Generator {
		yield 'not privileged' => [ [], false ];
		yield 'can view private' => [ [ 'abusefilter-view-private' ], true ];
		yield 'can view logs' => [ [ 'abusefilter-log-private' ], true ];
		yield 'both' => [ [ 'abusefilter-view-private', 'abusefilter-log-private' ], true ];
	}

	/**
	 * @param array $rights
	 * @param bool $expected
	 * @dataProvider provideCanViewPrivateFiltersLogs
	 */
	public function testCanViewPrivateFiltersLogs( array $rights, bool $expected ) {
		$performer = $this->mockRegisteredAuthorityWithPermissions( $rights );
		$this->assertSame(
			$expected,
			$this->getPermMan()->canViewPrivateFiltersLogs( $performer )
		);
	}

	public static function provideCanSeeLogDetailsForFilter(): Generator {
		$details = [ 0 => 'abusefilter-log-detail' ];
		$private = [ 1 => 'abusefilter-log-private' ];
		$protected = [ 2 => 'abusefilter-access-protected-vars' ];

		yield 'filter hidden, not privileged' => [ Flags::FILTER_HIDDEN, [], false ];
		yield 'filter hidden, details only' => [ Flags::FILTER_HIDDEN, $details, false ];
		yield 'filter hidden, private logs only' => [ Flags::FILTER_HIDDEN, $private, false ];
		yield 'filter hidden, details and private logs' => [ Flags::FILTER_HIDDEN, $details + $private, true ];

		yield 'filter protected, not privileged' => [ Flags::FILTER_USES_PROTECTED_VARS, [], false ];
		yield 'filter protected, details only' => [ Flags::FILTER_USES_PROTECTED_VARS, $details, false ];
		yield 'filter protected, protected logs only' => [ Flags::FILTER_USES_PROTECTED_VARS, $protected, false ];
		yield 'filter protected, privileged' => [ Flags::FILTER_USES_PROTECTED_VARS, $details + $protected, true ];

		$hiddenProtected = Flags::FILTER_HIDDEN | Flags::FILTER_USES_PROTECTED_VARS;
		yield 'filter hidden and protected, not privileged' => [ $hiddenProtected, [], false ];
		yield 'filter hidden and protected, details only' => [ $hiddenProtected, $details, false ];
		yield 'filter hidden and protected, private only' => [ $hiddenProtected, $private, false ];
		yield 'filter hidden and protected, protected only' => [ $hiddenProtected, $protected, false ];
		yield 'filter hidden and protected, details and private only' => [
			$hiddenProtected, $details + $private, false
		];
		yield 'filter hidden and protected, details and protected only' => [
			$hiddenProtected, $details + $protected, false
		];
		yield 'filter hidden and protected, private and protected only' => [
			$hiddenProtected, $private + $protected, false
		];
		yield 'filter hidden and protected, privileged' => [
			$hiddenProtected, $details + $private + $protected, true
		];

		yield 'filter visible, not privileged' => [ Flags::FILTER_PUBLIC, [], false ];
		yield 'filter visible, privileged' => [ Flags::FILTER_PUBLIC, $details, true ];
	}

	/**
	 * @param int $privacyLevel
	 * @param array $rights
	 * @param bool $expected
	 * @dataProvider provideCanSeeLogDetailsForFilter
	 */
	public function testCanSeeLogDetailsForFilter( int $privacyLevel, array $rights, bool $expected ) {
		$filter = new AbstractFilter(
			new Specs( '/**/', '', 'Test filter', [], 'default' ),
			new Flags( true, true, $privacyLevel, false ),
			[]
		);
		$performer = $this->mockRegisteredAuthorityWithPermissions( $rights );

		$this->assertSame(
			$expected,
			$this->getPermMan()->canSeeLogDetailsForFilter( $performer, $filter )
		);
	}

	public function provideCanViewProtectedVariablesInFilter(): Generator {
		$block = $this->createMock( DatabaseBlock::class );
		$block->method( 'isSitewide' )->willReturn( true );
		yield 'not privileged, blocked' => [ $block, [], false ];
		yield 'not privileged, not blocked' => [ null, [], false ];
		yield 'has right, blocked' => [ $block, [ 'abusefilter-access-protected-vars' ], false ];
		yield 'has right, not blocked' => [ null, [ 'abusefilter-access-protected-vars' ], true ];
	}

	/**
	 * @dataProvider provideCanViewProtectedVariablesInFilter
	 */
	public function testCanViewProtectedVariablesInFilter( ?DatabaseBlock $block, array $rights, bool $expected ) {
		if ( $block !== null ) {
			$performer = $this->mockUserAuthorityWithBlock(
				$this->mockRegisteredUltimateAuthority()->getUser(),
				$block,
				$rights
			);
		} else {
			$performer = $this->mockRegisteredAuthorityWithPermissions( $rights );
		}

		$filter = new AbstractFilter(
			new Specs( '/**/', '', 'Test filter', [], 'default' ),
			new Flags( true, true, Flags::FILTER_USES_PROTECTED_VARS, false ),
			[]
		);

		$actualStatus = $this->getPermMan()->canViewProtectedVariablesInFilter( $performer, $filter );
		if ( $expected ) {
			$this->assertStatusGood( $actualStatus );
			$this->assertSame( null, $actualStatus->getBlock() );
			$this->assertSame( null, $actualStatus->getPermission() );
		} else {
			$this->assertStatusNotGood( $actualStatus );
			if ( $block ) {
				$this->assertSame( $block, $actualStatus->getBlock() );
			} elseif ( !in_array( 'abusefilter-access-protected-vars', $rights ) ) {
				$this->assertSame( 'abusefilter-access-protected-vars', $actualStatus->getPermission() );
			} else {
				$this->fail( 'Unsupported test case.' );
			}
		}
	}

	public function testCanViewProtectedVariablesInFilterWhenFilterIsPublic() {
		$filter = new AbstractFilter(
			new Specs( '/**/', '', 'Test filter', [], 'default' ),
			new Flags( true, true, Flags::FILTER_PUBLIC, false ),
			[]
		);

		// The ::canViewProtectedVariablesInFilter method should throw if the provided filter is public,
		// as code should check if the filter is protected before checking protected restrictions on it.
		$this->expectException( LogicException::class );
		$this->getPermMan()->canViewProtectedVariablesInFilter( $this->mockRegisteredUltimateAuthority(), $filter );
	}

	public function testCanViewProtectedVariablesInFilterWhenResultCached() {
		$filterWithoutAnyVariables = new AbstractFilter(
			new Specs( '/**/', '', 'Test filter', [], 'default' ),
			new Flags( true, true, Flags::FILTER_USES_PROTECTED_VARS, false ),
			[]
		);
		$user = new UserIdentityValue( 1, 'User2' );
		$permManager = $this->getPermMan();

		// Call the method once with a user who can access protected variables, which should set the cache
		// for this filter and performer.
		$performer = $this->mockUserAuthorityWithPermissions( $user, [ 'abusefilter-access-protected-vars' ] );
		$this->assertStatusGood( $permManager->canViewProtectedVariablesInFilter(
			$performer, $filterWithoutAnyVariables
		) );

		// Call the method once again after changing the performer to no longer have the rights needed,
		// but still have the same user ID. This is done to test that the cached result is used instead
		// of checking again. It is fine for this to happen as this is an instance cache, so situation should
		// not happen in real production code.
		$performer = $this->mockUserAuthorityWithPermissions( $user, [] );
		$this->assertStatusGood( $permManager->canViewProtectedVariablesInFilter(
			$performer, $filterWithoutAnyVariables
		) );

		// Call the method once more, but use a different filter which has a different set of variables
		// and expect that this causes a cache miss and then a fatal status.
		$filterWithUserUnnamedIp = new AbstractFilter(
			new Specs( 'user_unnamed_ip = "1.2.3.4"', '', 'Test filter', [], 'default' ),
			new Flags( true, true, Flags::FILTER_USES_PROTECTED_VARS, false ),
			[]
		);
		$performer = $this->mockUserAuthorityWithPermissions( $user, [] );
		$statusFromThirdCall = $permManager->canViewProtectedVariablesInFilter(
			$performer, $filterWithUserUnnamedIp
		);
		$this->assertStatusNotGood( $statusFromThirdCall );
		$this->assertSame( 'abusefilter-access-protected-vars', $statusFromThirdCall->getPermission() );
	}

	public static function provideTestGetUsedProtectedVariables(): Generator {
		yield 'uses protected variables' => [
			[ 'user_unnamed_ip', 'user_name' ], [ 'user_unnamed_ip' ]
		];
		yield 'uses protected variables with duplicates in parameter' => [
			[ 'user_unnamed_ip', 'user_name', 'user_unnamed_ip', 'user_name', 'user_unnamed_ip' ],
			[ 'user_unnamed_ip' ],
		];
		yield 'no protected variables' => [
			[ 'user_name' ], []
		];
	}

	/**
	 * @dataProvider provideTestGetUsedProtectedVariables
	 */
	public function testGetUsedProtectedVariables( array $usedVariables, $expected ) {
		$this->assertSame(
			$expected,
			$this->getPermMan()->getUsedProtectedVariables( $usedVariables )
		);
	}

	public static function provideGetForbiddenVariables(): Generator {
		yield 'cannot view, protected vars' => [
			[
				'rights' => [],
				'usedVars' => [ 'user_unnamed_ip' ]
			],
			[ 'user_unnamed_ip' ]
		];
		yield 'cannot view, no protected vars' => [
			[
				'rights' => [],
				'usedVars' => []
			],
			[]
		];
		yield 'can view, protected vars' => [
			[
				'rights' => [ 'abusefilter-access-protected-vars' ],
				'usedVars' => [ 'user_unnamed_ip' ]
			],
			[]
		];
		yield 'can view, no protected vars' => [
			[
				'rights' => [ 'abusefilter-access-protected-vars' ],
				'usedVars' => []
			],
			[]
		];
	}

	/**
	 * @dataProvider provideGetForbiddenVariables
	 */
	public function testGetForbiddenVariables( array $data, $expected ) {
		$performer = $this->mockRegisteredAuthorityWithPermissions( $data[ 'rights' ] );
		$this->assertSame(
			$expected,
			$this->getPermMan()->getForbiddenVariables( $performer, $data[ 'usedVars' ] )
		);
	}

	public function testGetProtectedVariables() {
		$this->assertSame(
			[ 'user_unnamed_ip' ],
			$this->getPermMan()->getProtectedVariables()
		);
	}

	public static function provideSimpleCases(): array {
		return [
			'not allowed' => [ false ],
			'allowed' => [ true ],
		];
	}

	/**
	 * @dataProvider provideSimpleCases
	 */
	public function testCanEditFilterWithRestrictedActions( bool $allowed ) {
		$rights = $allowed ? [ 'abusefilter-modify-restricted' ] : [];
		$performer = $this->mockRegisteredAuthorityWithPermissions( $rights );
		$this->assertSame(
			$allowed,
			$this->getPermMan()->canEditFilterWithRestrictedActions( $performer )
		);
	}

	/**
	 * @dataProvider provideSimpleCases
	 */
	public function testCanViewAbuseLog( bool $allowed ) {
		$rights = $allowed ? [ 'abusefilter-log' ] : [];
		$performer = $this->mockRegisteredAuthorityWithPermissions( $rights );
		$this->assertSame(
			$allowed,
			$this->getPermMan()->canViewAbuseLog( $performer )
		);
	}

	/**
	 * @dataProvider provideSimpleCases
	 */
	public function testCanHideAbuseLog( bool $allowed ) {
		$rights = $allowed ? [ 'abusefilter-hide-log' ] : [];
		$performer = $this->mockRegisteredAuthorityWithPermissions( $rights );
		$this->assertSame(
			$allowed,
			$this->getPermMan()->canHideAbuseLog( $performer )
		);
	}

	/**
	 * @dataProvider provideSimpleCases
	 */
	public function testCanRevertFilterActions( bool $allowed ) {
		$rights = $allowed ? [ 'abusefilter-revert' ] : [];
		$performer = $this->mockRegisteredAuthorityWithPermissions( $rights );
		$this->assertSame(
			$allowed,
			$this->getPermMan()->canRevertFilterActions( $performer )
		);
	}

	/**
	 * @dataProvider provideSimpleCases
	 */
	public function testCanSeeLogDetails( bool $allowed ) {
		$rights = $allowed ? [ 'abusefilter-log-detail' ] : [];
		$performer = $this->mockRegisteredAuthorityWithPermissions( $rights );
		$this->assertSame(
			$allowed,
			$this->getPermMan()->canSeeLogDetails( $performer )
		);
	}

	/**
	 * @dataProvider provideSimpleCases
	 */
	public function testCanSeePrivateDetails( bool $allowed ) {
		$rights = $allowed ? [ 'abusefilter-privatedetails' ] : [];
		$performer = $this->mockRegisteredAuthorityWithPermissions( $rights );
		$this->assertSame(
			$allowed,
			$this->getPermMan()->canSeePrivateDetails( $performer )
		);
	}

	/**
	 * @dataProvider provideSimpleCases
	 */
	public function testCanSeeHiddenLogEntries( bool $allowed ) {
		$rights = $allowed ? [ 'abusefilter-hidden-log' ] : [];
		$performer = $this->mockRegisteredAuthorityWithPermissions( $rights );
		$this->assertSame(
			$allowed,
			$this->getPermMan()->canSeeHiddenLogEntries( $performer )
		);
	}

	/**
	 * @dataProvider provideSimpleCases
	 */
	public function testCanUseTestTools( bool $allowed ) {
		$rights = $allowed ? [ 'abusefilter-modify' ] : [];
		$performer = $this->mockRegisteredAuthorityWithPermissions( $rights );
		$this->assertSame(
			$allowed,
			$this->getPermMan()->canUseTestTools( $performer )
		);
	}

}
