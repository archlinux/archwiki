<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Unit;

use Generator;
use LogicException;
use MediaWiki\Block\SystemBlock;
use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner;
use MediaWiki\Extension\AbuseFilter\Parser\AFPData;
use MediaWiki\Extension\AbuseFilter\TextExtractor;
use MediaWiki\Extension\AbuseFilter\Variables\LazyLoadedVariable;
use MediaWiki\Extension\AbuseFilter\Variables\LazyVariableComputer;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Language\Language;
use MediaWiki\Parser\ParserFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Permissions\RestrictionStore;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentityUtils;
use MediaWiki\User\UserIdentityValue;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use UnexpectedValueException;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\LBFactory;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\Variables\LazyVariableComputer
 */
class LazyVariableComputerTest extends MediaWikiUnitTestCase {

	private function getComputer(
		array $services = [],
		array $hookHandlers = [],
		string $wikiID = ''
	): LazyVariableComputer {
		return new LazyVariableComputer(
			$this->createMock( TextExtractor::class ),
			new AbuseFilterHookRunner( $this->createHookContainer( $hookHandlers ) ),
			new NullLogger(),
			$this->createMock( LBFactory::class ),
			$this->createMock( WANObjectCache::class ),
			$services['RevisionLookup'] ?? $this->createMock( RevisionLookup::class ),
			$this->createMock( RevisionStore::class ),
			$services['ContentLanguage'] ?? $this->createMock( Language::class ),
			$this->createMock( ParserFactory::class ),
			$services['UserEditTracker'] ?? $this->createMock( UserEditTracker::class ),
			$services['UserGroupManager'] ?? $this->createMock( UserGroupManager::class ),
			$services['PermissionManager'] ?? $this->createMock( PermissionManager::class ),
			$services['RestrictionStore'] ?? $this->createMock( RestrictionStore::class ),
			$services['UserIdentityUtils'] ?? $this->createMock( UserIdentityUtils::class ),
			$wikiID
		);
	}

	private function getForbidComputeCB(): callable {
		return static function () {
			throw new LogicException( 'Not expected to be called' );
		};
	}

	public function testWikiNameVar() {
		$fakeID = 'some-wiki-ID';
		$var = new LazyLoadedVariable( 'get-wiki-name', [] );
		$computer = $this->getComputer( [], [], $fakeID );
		$this->assertSame(
			$fakeID,
			$computer->compute( $var, new VariableHolder(), $this->getForbidComputeCB() )->toNative()
		);
	}

	public function testWikiLanguageVar() {
		$fakeCode = 'foobar';
		$fakeLang = $this->createMock( Language::class );
		$fakeLang->method( 'getCode' )->willReturn( $fakeCode );
		$computer = $this->getComputer( [ 'ContentLanguage' => $fakeLang ] );
		$var = new LazyLoadedVariable( 'get-wiki-language', [] );
		$this->assertSame(
			$fakeCode,
			$computer->compute( $var, new VariableHolder(), $this->getForbidComputeCB() )->toNative()
		);
	}

	public function testCompute_invalidName() {
		$computer = $this->getComputer();
		$this->expectException( UnexpectedValueException::class );
		$computer->compute(
			new LazyLoadedVariable( 'method-does-not-exist', [] ),
			new VariableHolder(),
			$this->getForbidComputeCB()
		);
	}

	public function testInterceptVariableHook() {
		$expected = new AFPData( AFPData::DSTRING, 'foobar' );
		$handler = static function ( $method, $vars, $params, &$result ) use ( $expected ) {
			$result = $expected;
			return false;
		};
		$computer = $this->getComputer( [], [ 'AbuseFilter-interceptVariable' => $handler ] );
		$actual = $computer->compute(
			new LazyLoadedVariable( 'get-wiki-name', [] ),
			new VariableHolder(),
			$this->getForbidComputeCB()
		);
		$this->assertSame( $expected, $actual );
	}

	public function testComputeVariableHook() {
		$expected = new AFPData( AFPData::DSTRING, 'foobar' );
		$handler = static function ( $method, $vars, $params, &$result ) use ( $expected ) {
			$result = $expected;
			return false;
		};
		$computer = $this->getComputer( [], [ 'AbuseFilter-computeVariable' => $handler ] );
		$actual = $computer->compute(
			new LazyLoadedVariable( 'method-does-not-exist', [] ),
			new VariableHolder(),
			$this->getForbidComputeCB()
		);
		$this->assertSame( $expected, $actual );
	}

	/**
	 * @dataProvider provideUserRelatedVars
	 */
	public function testUserRelatedVars(
		$expected,
		callable $getMocks
	) {
		[ $var, $services ] = $getMocks( $this );
		$computer = $this->getComputer( $services );
		$this->assertSame(
			$expected,
			$computer->compute( $var, new VariableHolder(), $this->getForbidComputeCB() )->toNative()
		);
	}

	public static function provideUserRelatedVars(): Generator {
		$getUserVar = static function ( $user, $method ): LazyLoadedVariable {
			return new LazyLoadedVariable(
				$method,
				[ 'user' => $user, 'user-identity' => $user, 'rc' => null ]
			);
		};

		$editCount = 7;
		$getMocks = static function ( $testCase ) use ( $editCount, $getUserVar ) {
			$user = $testCase->createMock( User::class );
			$userEditTracker = $testCase->createMock( UserEditTracker::class );
			$userEditTracker->method( 'getUserEditCount' )->with( $user )->willReturn( $editCount );
			$var = $getUserVar( $user, 'user-editcount' );
			return [ $var, [ 'UserEditTracker' => $userEditTracker ] ];
		};
		yield 'user_editcount' => [ $editCount, $getMocks ];

		$emailConfirm = '20000101000000';
		$getMocks = static function ( $testCase ) use ( $emailConfirm, $getUserVar ) {
			$user = $testCase->createMock( User::class );
			$user->method( 'getEmailAuthenticationTimestamp' )->willReturn( $emailConfirm );
			$var = $getUserVar( $user, 'user-emailconfirm' );
			return [ $var, [] ];
		};
		yield 'user_emailconfirm' => [ $emailConfirm, $getMocks ];

		$getMocks = static function ( $testCase ) use ( $getUserVar ) {
			$user = $testCase->createMock( User::class );
			$mockUserIdentityUtils = $testCase->createMock( UserIdentityUtils::class );
			$mockUserIdentityUtils->method( 'isNamed' )->with( $user )->willReturn( true );
			$var = $getUserVar( $user, 'user-type' );
			return [ $var, [ 'UserIdentityUtils' => $mockUserIdentityUtils ] ];
		};
		yield 'user_type for named user' => [ 'named', $getMocks ];

		$getMocks = static function ( $testCase ) use ( $getUserVar ) {
			$user = $testCase->createMock( User::class );
			$mockUserIdentityUtils = $testCase->createMock( UserIdentityUtils::class );
			$mockUserIdentityUtils->method( 'isNamed' )->with( $user )->willReturn( false );
			$mockUserIdentityUtils->method( 'isTemp' )->with( $user )->willReturn( true );
			$var = $getUserVar( $user, 'user-type' );
			return [ $var, [ 'UserIdentityUtils' => $mockUserIdentityUtils ] ];
		};
		yield 'user_type for named temporary user' => [ 'temp', $getMocks ];

		$getMocks = static function ( $testCase ) use ( $getUserVar ) {
			$user = $testCase->createMock( User::class );
			$user->method( 'getName' )->willReturn( '127.0.0.1' );
			$var = $getUserVar( $user, 'user-type' );
			return [ $var, [] ];
		};
		yield 'user_type for logged-out user' => [ 'ip', $getMocks ];

		$getMocks = static function ( $testCase ) use ( $getUserVar ) {
			$user = $testCase->createMock( User::class );
			$user->method( 'getName' )->willReturn( 'mediawiki>testing' );
			$var = $getUserVar( $user, 'user-type' );
			return [ $var, [] ];
		};
		yield 'user_type for an external username' => [ 'external', $getMocks ];

		$getMocks = static function ( $testCase ) use ( $getUserVar ) {
			$user = $testCase->createMock( User::class );
			$user->method( 'getName' )->willReturn( 'Non-existing user 1234' );
			$var = $getUserVar( $user, 'user-type' );
			return [ $var, [] ];
		};
		yield 'user_type for unregistered username' => [ 'unknown', $getMocks ];

		$getMocks = static function ( $testCase ) use ( $getUserVar ) {
			$request = new FauxRequest();
			$request->setIP( '127.0.0.1' );
			$user = $testCase->createMock( User::class );
			$user->method( 'getRequest' )->willReturn( $request );
			$user->method( 'getName' )->willReturn( '127.0.0.1' );
			$var = $getUserVar( $user, 'user-unnamed-ip' );
			return [ $var, [] ];
		};
		yield 'user_unnamed_ip for an anonymous user' => [ '127.0.0.1', $getMocks ];

		$getMocks = static function ( $testCase ) use ( $getUserVar ) {
			$user = $testCase->createMock( User::class );
			$user->method( 'getName' )->willReturn( 'Test User' );
			$var = $getUserVar( $user, 'user-unnamed-ip' );
			return [ $var, [] ];
		};
		yield 'user_unnamed_ip for a user' => [ null, $getMocks ];

		$getMocks = static function ( $testCase ) use ( $getUserVar ) {
			$user = $testCase->createMock( User::class );
			$mockUserIdentityUtils = $testCase->createMock( UserIdentityUtils::class );
			$mockUserIdentityUtils->method( 'isTemp' )->with( $user )->willReturn( true );
			$request = new FauxRequest();
			$request->setIP( '127.0.0.1' );
			$user = $testCase->createMock( User::class );
			$user->method( 'getRequest' )->willReturn( $request );
			$var = $getUserVar( $user, 'user-unnamed-ip' );
			return [ $var, [ 'UserIdentityUtils' => $mockUserIdentityUtils ] ];
		};
		yield 'user_unnamed_ip for a temp user' => [ '127.0.0.1', $getMocks ];

		$groups = [ '*', 'group1', 'group2' ];
		$getMocks = static function ( $testCase ) use ( $groups, $getUserVar ) {
			$user = $testCase->createMock( User::class );
			$userGroupManager = $testCase->createMock( UserGroupManager::class );
			$userGroupManager->method( 'getUserEffectiveGroups' )->with( $user )->willReturn( $groups );
			$var = $getUserVar( $user, 'user-groups' );
			return [ $var, [ 'UserGroupManager' => $userGroupManager ] ];
		};
		yield 'user_groups' => [ $groups, $getMocks ];

		$rights = [ 'abusefilter-foo', 'abusefilter-bar' ];
		$getMocks = static function ( $testCase ) use ( $rights, $getUserVar ) {
			$user = $testCase->createMock( User::class );
			$permissionManager = $testCase->createMock( PermissionManager::class );
			$permissionManager->method( 'getUserPermissions' )->with( $user )->willReturn( $rights );
			$var = $getUserVar( $user, 'user-rights' );
			return [ $var, [ 'PermissionManager' => $permissionManager ] ];
		};
		yield 'user_rights' => [ $rights, $getMocks ];

		$getMocks = static function ( $testCase ) use ( $getUserVar ) {
			$user = $testCase->createMock( User::class );
			$block = new SystemBlock( [] );
			$user->method( 'getBlock' )->willReturn( $block );
			$var = $getUserVar( $user, 'user-block' );
			return [ $var, [] ];
		};
		yield 'user_blocked' => [ true, $getMocks ];

		$fakeTime = 1514700000;

		$anonymousAge = 0;
		$getMocks = static function ( $testCase ) use ( $anonymousAge, $fakeTime ) {
			$anonUser = $testCase->createMock( User::class );
			$var = new LazyLoadedVariable(
				'user-age',
				[ 'user' => $anonUser, 'asof' => $fakeTime ]
			);
			return [ $var, [] ];
		};
		yield 'user_age, anonymous' => [ $anonymousAge, $getMocks ];

		$expected = (int)wfTimestamp( TS_UNIX, $fakeTime ) - (int)wfTimestamp( TS_UNIX, '20080115000000' );
		$getMocks = static function ( $testCase ) use ( $fakeTime ) {
			$user = $testCase->createMock( User::class );
			$user->method( 'isRegistered' )->willReturn( true );
			$var = new LazyLoadedVariable(
				'user-age',
				[ 'user' => $user, 'asof' => $fakeTime ]
			);
			return [ $var, [] ];
		};
		yield 'user_age, registered but not available' => [ $expected, $getMocks ];

		$age = 163;
		$getMocks = static function ( $testCase ) use ( $fakeTime, $age ) {
			$user = $testCase->createMock( User::class );
			$user->method( 'isRegistered' )->willReturn( true );
			$user->method( 'getRegistration' )->willReturn( $fakeTime - $age );
			$var = new LazyLoadedVariable(
				'user-age',
				[ 'user' => $user, 'asof' => $fakeTime ]
			);
			return [ $var, [] ];
		};
		yield 'user_age, registered' => [ $age, $getMocks ];
	}

	/**
	 * @dataProvider provideTitleRelatedVars
	 */
	public function testTitleRelatedVars(
		$expected,
		callable $getMocks
	) {
		[ $var, $services ] = $getMocks( $this );
		$computer = $this->getComputer( $services );
		$this->assertSame(
			$expected,
			$computer->compute( $var, new VariableHolder(), $this->getForbidComputeCB() )->toNative()
		);
	}

	public static function provideTitleRelatedVars(): Generator {
		$restrictions = [ 'create', 'edit', 'move', 'upload' ];
		foreach ( $restrictions as $restriction ) {
			$appliedRestrictions = [ 'sysop' ];
			$getMocks = static function ( $testCase ) use ( $restriction, $appliedRestrictions ) {
				$restrictedTitle = $testCase->createMock( Title::class );
				$restrictionStore = $testCase->createMock( RestrictionStore::class );
				$restrictionStore->expects( $testCase->once() )
					->method( 'getRestrictions' )
					->with( $restrictedTitle, $restriction )
					->willReturn( $appliedRestrictions );
				$var = new LazyLoadedVariable(
					'get-page-restrictions',
					[ 'title' => $restrictedTitle, 'action' => $restriction ]
				);
				return [ $var, [ 'RestrictionStore' => $restrictionStore ] ];
			};
			yield "*_restrictions_{$restriction}, restricted" => [ $appliedRestrictions, $getMocks ];

			$getMocks = static function ( $testCase ) use ( $restriction ) {
				$unrestrictedTitle = $testCase->createMock( Title::class );
				$restrictionStore = $testCase->createMock( RestrictionStore::class );
				$restrictionStore->expects( $testCase->once() )
					->method( 'getRestrictions' )
					->with( $unrestrictedTitle, $restriction )
					->willReturn( [] );
				$var = new LazyLoadedVariable(
					'get-page-restrictions',
					[ 'title' => $unrestrictedTitle, 'action' => $restriction ]
				);
				return [ $var, [ 'RestrictionStore' => $restrictionStore ] ];
			};
			yield "*_restrictions_{$restriction}, unrestricted" => [ [], $getMocks ];
		}

		$fakeTime = 1514700000;
		$age = 163;
		$getMocks = static function ( $testCase ) use ( $fakeTime, $age ) {
			$title = $testCase->createMock( Title::class );
			$revision = $testCase->createMock( RevisionRecord::class );
			$revision->method( 'getTimestamp' )->willReturn( $fakeTime - $age );
			$revLookup = $testCase->createMock( RevisionLookup::class );
			$revLookup->method( 'getFirstRevision' )->with( $title )->willReturn( $revision );
			$var = new LazyLoadedVariable(
				'page-age',
				[ 'title' => $title, 'asof' => $fakeTime ]
			);
			return [ $var, [ 'RevisionLookup' => $revLookup ] ];
		};
		yield "*_age" => [ $age, $getMocks ];

		$firstUserName = 'First author';
		$getMocks = static function ( $testCase ) use ( $firstUserName ) {
			$title = $testCase->createMock( Title::class );
			$firstRev = $testCase->createMock( RevisionRecord::class );
			$firstUser = new UserIdentityValue( 1, $firstUserName );
			$firstRev->expects( $testCase->once() )->method( 'getUser' )->willReturn( $firstUser );
			$revLookup = $testCase->createMock( RevisionLookup::class );
			$revLookup->method( 'getFirstRevision' )->with( $title )->willReturn( $firstRev );
			$var = new LazyLoadedVariable(
				'load-first-author',
				[ 'title' => $title ]
			);
			return [ $var, [ 'RevisionLookup' => $revLookup ] ];
		};
		yield '*_first_contributor, with rev' => [ $firstUserName, $getMocks ];

		$getMocks = static function ( $testCase ) {
			$title = $testCase->createMock( Title::class );
			$revLookup = $testCase->createMock( RevisionLookup::class );
			$revLookup->method( 'getFirstRevision' )->with( $title )->willReturn( null );
			$var = new LazyLoadedVariable(
				'load-first-author',
				[ 'title' => $title ]
			);
			return [ $var, [ 'RevisionLookup' => $revLookup ] ];
		};
		yield '*_first_contributor, no rev' => [ '', $getMocks ];

		// TODO _recent_contributors is tested in LazyVariableComputerDBTest
	}
}
