<?php

namespace MediaWiki\Extension\AbuseFilter\Tests\Integration\Api;

use MediaWiki\Block\Block;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Extension\AbuseFilter\CentralDBNotAvailableException;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWiki\Extension\AbuseFilter\ProtectedVarsAccessLogger;
use MediaWiki\Extension\AbuseFilter\Tests\Integration\FilterFromSpecsTestTrait;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\Permissions\UltimateAuthority;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Api\ApiTestCase;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\IPUtils;

/**
 * @covers \MediaWiki\Extension\AbuseFilter\Api\QueryAbuseLog
 * @group medium
 * @group Database
 * @todo Extend this
 */
class QueryAbuseLogTest extends ApiTestCase {
	use MockAuthorityTrait;
	use FilterFromSpecsTestTrait;

	private const FILTER_NAME_1_2_3_4 =
		'Filter with protected variables for 1.2.3.4';
	private const FILTER_NAME_172_19_0_X =
		'Filter with protected variables for 172.19.0.x';

	private static UserIdentity $userIdentity;
	private static string $testUserName;
	private static string $tempAccountName = '~2025-1';

	private Authority $authorityCanViewProtectedVar;
	private Authority $authorityCannotViewProtectedVar;
	private Authority $authorityCannotViewTempAccountIpAddresses;

	protected function setUp(): void {
		parent::setUp();

		// Clear the protected access hooks, as in CI other extensions (such as CheckUser) may attempt to
		// define additional restrictions or alter logging that cause the tests to fail.
		$this->clearHooks( [
			'AbuseFilterCanViewProtectedVariables',
			'AbuseFilterLogProtectedVariableValueAccess',
		] );

		$this->authorityCannotViewProtectedVar = $this->mockUserAuthorityWithPermissions(
			self::$userIdentity,
			[
				'abusefilter-log-detail',
				'abusefilter-view',
				'abusefilter-log',
				'abusefilter-privatedetails',
				'abusefilter-privatedetails-log',
				'abusefilter-view-private',
				'abusefilter-log-private',
				'abusefilter-hidden-log',
				'abusefilter-hide-log',
			]
		);

		$this->authorityCanViewProtectedVar = $this->mockUserAuthorityWithPermissions(
			self::$userIdentity,
			[
				'abusefilter-log-detail',
				'abusefilter-view',
				'abusefilter-log',
				'abusefilter-privatedetails',
				'abusefilter-privatedetails-log',
				'abusefilter-view-private',
				'abusefilter-log-private',
				'abusefilter-hidden-log',
				'abusefilter-hide-log',
				'abusefilter-access-protected-vars',
				'checkuser-temporary-account-no-preference',
			]
		);

		$this->authorityCannotViewTempAccountIpAddresses = $this->mockUserAuthorityWithPermissions(
			self::$userIdentity,
			[
				'abusefilter-log-detail',
				'abusefilter-view',
				'abusefilter-log',
				'abusefilter-privatedetails',
				'abusefilter-privatedetails-log',
				'abusefilter-view-private',
				'abusefilter-log-private',
				'abusefilter-hidden-log',
				'abusefilter-hide-log',
				'abusefilter-access-protected-vars',
			]
		);
	}

	public function testConstruct() {
		$this->doApiRequest( [
			'action' => 'query',
			'list' => 'abuselog',
		] );
		$this->addToAssertionCount( 1 );
	}

	public function testFilteringForProtectedFilterWhenUserBlocked() {
		$mockBlock = $this->createMock( Block::class );
		$mockBlock->method( 'isSitewide' )
			->willReturn( true );

		$this->expectApiErrorCode( 'blocked' );
		$this->doApiRequest(
			[
				'action' => 'query',
				'list' => 'abuselog',
				'aflprop' => 'details',
				'afldir' => 'older',
				'aflfilter' => 1,
			],
			null, false,
			$this->mockUserAuthorityWithBlock(
				self::$userIdentity, $mockBlock, [ 'abusefilter-log-detail', 'abusefilter-log' ]
			)
		);
	}

	public function testFilteringForProtectedFilterWhenUserLacksAccess() {
		$this->expectApiErrorCode( 'permissiondenied' );
		$this->doApiRequest(
			[
				'action' => 'query',
				'list' => 'abuselog',
				'aflprop' => 'details',
				'afldir' => 'older',
				'aflfilter' => 1,
			],
			null, false, $this->authorityCannotViewProtectedVar
		);
	}

	public function testFilteringForProtectedFilterWhenUserLacksAccessAndCentralDBNotAvailable() {
		// Mock FilterLookup::getFilter to throw a CentralDBNotAvailableException exception
		$mockFilterLookup = $this->createMock( FilterLookup::class );
		$mockFilterLookup->method( 'getFilter' )
			->willThrowException( new CentralDBNotAvailableException() );
		$this->setService( 'AbuseFilterFilterLookup', $mockFilterLookup );

		$this->expectApiErrorCode( 'permissiondenied' );
		$this->doApiRequest(
			[
				'action' => 'query',
				'list' => 'abuselog',
				'aflprop' => 'details',
				'afldir' => 'older',
				'aflfilter' => 123,
			],
			null, false, $this->authorityCannotViewProtectedVar
		);
	}

	public function testFilteringWhenFilterIDDoesNotExist() {
		[ $result ] = $this->doApiRequest(
			[
				'action' => 'query',
				'list' => 'abuselog',
				'afldir' => 'older',
				'aflfilter' => 12345676,
				'errorformat' => 'plaintext',
			],
			null, false, $this->authorityCanViewProtectedVar
		);
		$this->assertArrayHasKey( 'warnings', $result );
		$this->assertSame( 'abusefilter-log-invalid-filter', $result['warnings'][0]['code'] );
		$this->assertCount( 0, $result['query']['abuselog'] );
	}

	public function testProtectedVariableValueAccessWhenReadOnly(): void {
		$this->getServiceContainer()->getReadOnlyMode()->setReason( 'test' );

		$result = $this->doApiRequest(
			[
				'action' => 'query',
				'list' => 'abuselog',
				// Only get entries for the first filter (i.e. FILTER_NAME_1_2_3_4)
				'aflfilter' => 1,
				'aflprop' => 'details',
				'afldir' => 'older',
			],
			null,
			null,
			$this->authorityCanViewProtectedVar
		);
		$result = $result[0]['query']['abuselog'];
		foreach ( $result as $row ) {
			$this->assertSame(
				'',
				$row['details']['user_unnamed_ip'],
				'Protected variables should be unset when in read only mode'
			);
		}

		// Verify that a protected variable value access log was not created
		// as we are in read only
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [
				'log_action' => 'view-protected-var-value',
				'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
			] )
			->caller( __METHOD__ )
			->assertFieldValue( 0 );
	}

	public function testProtectedVariableValueAccess() {
		// Assert that the IP is visible
		$result = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'abuselog',
			// Only get entries for the first filter (i.e. FILTER_NAME_1_2_3_4)
			'aflfilter' => 1,
			'aflprop' => 'details',
			'afldir' => 'older',
		], null, null, $this->authorityCanViewProtectedVar );
		$result = $result[0]['query']['abuselog'];
		foreach ( $result as $row ) {
			$this->assertSame( '1.2.3.4', $row['details']['user_unnamed_ip'] );
			if ( isset( $row['details']['account_name'] ) ) {
				$this->assertSame( self::$userIdentity->getName(), $row['details']['account_name'] );
				$this->assertArrayNotHasKey( 'user_name', $row['details'] );
			} else {
				$this->assertSame( self::$userIdentity->getName(), $row['details']['user_name'] );
				$this->assertArrayNotHasKey( 'account_name', $row['details'] );
			}
		}

		// Verify that a protected variable value access log was created
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [
				'log_action' => 'view-protected-var-value',
				'log_type' => ProtectedVarsAccessLogger::LOG_TYPE,
			] )
			->caller( __METHOD__ )
			->assertFieldValue( 1 );
	}

	public function testSuppressedLogEntryAccessDenied() {
		// Data for suppressed filter 3 and its log is created in addDBDataOnce

		// Create an authority WITHOUT the 'viewsuppressed' right.
		$authorityWithoutSuppressed = $this->mockUserAuthorityWithoutPermissions(
			new UserIdentityValue( 200, 'User2' ),
			[ 'viewsuppressed' ]
		);

		// Query the abuse log. We expect an error because the suppressed filter should not be accessible.
		$this->expectApiErrorCode( 'permissiondenied' );
		$this->doApiRequest( [
			'action' => 'query',
			'list' => 'abuselog',
			'aflprop' => 'details',
			'afldir' => 'older',
			'aflfilter' => '3',
		], null, null, $authorityWithoutSuppressed );
	}

	public function testSuppressedLogEntryVisible() {
		// Data for suppressed filter 3 and its log is created in addDBDataOnce

		// This test is identical to ::testSuppressedLogEntryAccessDenied one, except that
		// here we create an authority WITH the 'viewsuppressed' right, thus also asserting
		// that the missing permission causing the failure in that test was `viewsuppressed`.
		$user = new UserIdentityValue( 300, 'User3' );
		$authorityWithSuppressed = new SimpleAuthority(
			$user,
			[
				'abusefilter-log-detail',
				'abusefilter-view',
				'abusefilter-log',
				'abusefilter-privatedetails',
				'abusefilter-view-private',
				'abusefilter-log-private',
				'abusefilter-hidden-log',
				'abusefilter-hide-log',
				'abusefilter-access-protected-vars',
				'viewsuppressed'
			]
		);

		$result = $this->doApiRequest( [
			'action' => 'query',
			'list' => 'abuselog',
			'aflprop' => 'details',
			'afldir' => 'older',
			'aflfilter' => '3',
		], null, null, $authorityWithSuppressed );

		// Assert that we can see the log entry for the suppressed filter.
		$this->assertSame( 'tag', $result[0]['query']['abuselog'][0]['details']['action'] );
	}

	public function testExecuteWhenRequestingFilterName() {
		[ $result ] = $this->doApiRequest(
			[
				'action' => 'query',
				'list' => 'abuselog',
				'aflprop' => 'filter',
				'afldir' => 'older',
			],
			null, null, $this->authorityCanViewProtectedVar
		);

		// Expect that the API returns the filter names associated with the log
		// entries
		//
		$this->containsOnlyExpectedEntries(
			[
				[ 'filter' => self::FILTER_NAME_172_19_0_X ],
				[ 'filter' => self::FILTER_NAME_172_19_0_X ],
				[ 'filter' => self::FILTER_NAME_172_19_0_X ],
				[ 'filter' => self::FILTER_NAME_172_19_0_X ],
				[ 'filter' => self::FILTER_NAME_1_2_3_4 ],
				[ 'filter' => self::FILTER_NAME_1_2_3_4 ],
			],
			$result['query']['abuselog']
		);
	}

	/**
	 * @dataProvider executeByAflUserDataProvider
	 */
	public function testExecuteByAflUser(
		array $expectedEntries,
		array $params
	): void {
		// The data provider wraps the name of the user in a callback function
		// since it is evaluated before the test user it refers to is created.
		$params['afluser'] = $params['afluser']();

		if ( IPUtils::isIPAddress( $params['afluser'] ) ) {
			// Checking if the current authority has permissions to view IPs is
			// delegated CheckUserPermissionManager based on
			// checkuser-temporary-account-* permissions. Therefore, this test
			// requires that service to be present in order to produce the
			// expected results.
			$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );
		}

		[ $result ] = $this->doApiRequest(
			$params,
			null,
			null,
			$this->authorityCanViewProtectedVar
		);

		foreach ( $expectedEntries as &$entry ) {
			$entry['user'] = $entry['user']();
		}

		$this->containsOnlyExpectedEntries(
			$expectedEntries,
			$result['query']['abuselog']
		);
	}

	public static function executeByAflUserDataProvider(): array {
		// Note the value for 'user' is a callback since when this data provider
		// is evaluated addDBDataOnce() has not run, which means that
		// self::$testUserName is uninitialized at that time.

		return [
			'by 24-bit IP range (three entries)' => [
				// A range query covering more than one IP should return TAs
				// using IPs falling under that range while skipping legacy IP
				// users.
				'expectedEntries' => [
					[
						'filter' => self::FILTER_NAME_172_19_0_X,
						'user' => static fn () => self::$tempAccountName,
						'action' => 'changeemail'
					],
					[
						'filter' => self::FILTER_NAME_172_19_0_X,
						'user' => static fn () => self::$tempAccountName,
						'action' => 'move'
					],
					[
						'filter' => self::FILTER_NAME_172_19_0_X,
						'user' => static fn () => self::$tempAccountName,
						'action' => 'move'
					],
				],
				'params' => [
					'action' => 'query',
					'list' => 'abuselog',
					'afluser' => static fn () => '172.19.0.0/24',
					'aflprop' => 'filter|user|action',
					'afldir' => 'older',
				]
			],
			// Although range queries are only supported for temp users, <IP>/32
			// range queries like the one below cover a single IP and therefore
			// should return both temp users and legacy IP users under the
			// provided IP.
			//
			// This is the branch in addUserFilterForIPAddress() that tests if
			// $rangeStart === $rangeEnd.
			'by 32-bit IP range (two entries)' => [
				'expectedEntries' => [
					[
						'filter' => self::FILTER_NAME_172_19_0_X,
						'user' => static fn () => '172.19.0.4',
						'action' => 'autocreateaccount'
					],
					[
						'filter' => self::FILTER_NAME_172_19_0_X,
						'user' => static fn () => self::$tempAccountName,
						'action' => 'edit'
					]
				],
				'params' => [
					'action' => 'query',
					'list' => 'abuselog',
					'afluser' => static fn () => '172.19.0.4/32',
					'aflprop' => 'filter|user|action',
					'afldir' => 'older',
				]
			],
			'by username' => [
				'expectedEntries' => [
					[
						'filter' => self::FILTER_NAME_1_2_3_4,
						'user' => static fn () => self::$testUserName,
						'action' => 'autocreateaccount'
					],
					[
						'filter' => self::FILTER_NAME_1_2_3_4,
						'user' => static fn () => self::$testUserName,
						'action' => 'edit'
					],
				],
				'params' => [
					'action' => 'query',
					'list' => 'abuselog',
					'afluser' => static fn () => self::$testUserName,
					'aflprop' => 'filter|user|action',
					'afldir' => 'older',
				]
			],
		];
	}

	public function testExecuteByAFLUserWithNoPermissionToSeeIPAddresses(): void {
		// Checking if the current authority has permissions to view IPs for
		// temp accounts is delegated CheckUserPermissionManager based on
		// checkuser-temporary-account-* permissions. Therefore, this test
		// requires that service to be present in order to produce the expected
		// results.
		$this->markTestSkippedIfExtensionNotLoaded( 'CheckUser' );

		$entryForAnonymousUser = [
			'filter' => self::FILTER_NAME_172_19_0_X,
			'user' => '172.19.0.4',
			'action' => 'autocreateaccount'
		];
		$entryForTempUser = [
			'filter' => self::FILTER_NAME_172_19_0_X,
			'user' => '~2025-1',
			'action' => 'edit'
		];

		// When the user has permissions, it should get an entry for a temp user
		// using the IP 172.19.0.4, and one from a legacy IP account under the
		// same IP.
		$expectedWhenPermissionIsGranted = [
			$entryForAnonymousUser,
			$entryForTempUser,
		];

		// When the user does not have permissions, it should only get the entry
		// for the legacy IP account.
		$expectedWhenPermissionIsNotGranted = [
			$entryForAnonymousUser
		];

		$request = [
			'action' => 'query',
			'list' => 'abuselog',
			'afluser' => '172.19.0.4',
			'aflprop' => 'filter|user|action',
			'afldir' => 'older',
		];

		[ $result ] = $this->doApiRequest(
			$request,
			null,
			null,
			$this->authorityCanViewProtectedVar
		);

		$this->containsOnlyExpectedEntries(
			$expectedWhenPermissionIsGranted,
			$result['query']['abuselog']
		);

		// Repeat the request with no permissions to see Temp Account IPs.

		[ $result ] = $this->doApiRequest(
			$request,
			null,
			null,
			$this->authorityCannotViewTempAccountIpAddresses
		);

		$this->containsOnlyExpectedEntries(
			$expectedWhenPermissionIsNotGranted,
			$result['query']['abuselog']
		);
	}

	private function containsOnlyExpectedEntries(
		array $expected,
		array $result
	): void {
		// We don't know in advance the order in which entries are returned, so
		// we test one-by-one.
		$this->assertSameSize( $expected, $result );

		foreach ( $expected as $entry ) {
			$this->assertContains( $entry, $result );
		}
	}

	public function addDBDataOnce() {
		$timestamp = '20190826000000';
		$tempUserCreator = $this->getServiceContainer()->getTempUserCreator();

		self::$userIdentity = $this->getMutableTestUser()->getUserIdentity();
		$tempUser = $tempUserCreator
			->create( self::$tempAccountName, new FauxRequest() )->getUser();

		$existingTitle = $this->getExistingTestPage()->getTitle();
		$testUser = $this->getTestUser()->getUser();
		self::$testUserName = $testUser->getName();

		// Add filter to query for
		$performer = $this->getTestSysop()->getUserIdentity();
		$authority = new UltimateAuthority( $performer );

		$this->createFilter(
			$authority,
			[
				'id' => '1',
				'rules' => 'user_unnamed_ip = "1.2.3.4"',
				'name' => self::FILTER_NAME_1_2_3_4,
				'privacy' => Flags::FILTER_USES_PROTECTED_VARS,
				'lastEditor' => $performer,
				'lastEditTimestamp' => $timestamp,
			]
		);

		// Insert a hit on the filter
		$abuseFilterLoggerFactory = AbuseFilterServices::getAbuseLoggerFactory();
		$abuseFilterLoggerFactory->newLogger(
			$existingTitle,
			$testUser,
			VariableHolder::newFromArray( [
				'action' => 'edit',
				'user_unnamed_ip' => '1.2.3.4',
				'user_name' => self::$userIdentity->getName(),
			] )
		)->addLogEntries( [ 1 => [ 'warn' ] ] );
		$abuseFilterLoggerFactory->newLogger(
			$existingTitle,
			$testUser,
			VariableHolder::newFromArray( [
				'action' => 'autocreateaccount',
				'user_unnamed_ip' => '1.2.3.4',
				'account_name' => self::$userIdentity->getName(),
			] )
		)->addLogEntries( [ 1 => [ 'warn' ] ] );

		// Update afl_ip_hex to a known value that can be used when it's
		// reconstructed in the variable holder
		$this->setIPForLogEntries( 2, '1.2.3.4', 1 );

		// Create a filter covering IPs from 172.19.0.0 to 172.19.0.15
		$this->createFilter(
			$authority,
			[
				'id' => '2',
				'rules' => "ip_in_range( user_unnamed_ip, '172.19.0.0/28')",
				'name' => self::FILTER_NAME_172_19_0_X,
				'privacy' => Flags::FILTER_USES_PROTECTED_VARS,
				'lastEditor' => $performer,
				'lastEditTimestamp' => $timestamp,
			]
		);

		$abuseFilterLoggerFactory->newLogger(
			$existingTitle,
			$tempUser,
			VariableHolder::newFromArray( [
				'action' => 'move',
				'user_unnamed_ip' => '172.19.0.1',
				'user_name' => $tempUser->getName(),
			] )
		)->addLogEntries( [ 2 => [ 'warn' ] ] );

		$abuseFilterLoggerFactory->newLogger(
			$existingTitle,
			$tempUser,
			VariableHolder::newFromArray( [
				'action' => 'changeemail',
				'user_unnamed_ip' => '172.19.0.2',
				'user_name' => $tempUser->getName(),
			] )
		)->addLogEntries( [ 2 => [ 'warn' ] ] );

		$this->setIPForLogEntries( 1, '172.19.0.1', 2, 'move' );
		$this->setIPForLogEntries( 1, '172.19.0.2', 2, 'changeemail' );

		// Add an entry for an anonymous IP user
		$abuseFilterLoggerFactory->newLogger(
			$existingTitle,
			$this->getIpUser( '172.19.0.4' ),
			VariableHolder::newFromArray( [
				'action' => 'autocreateaccount',
				'account_name' => '172.19.0.4',
			] )
		)->addLogEntries( [ 2 => [ 'warn' ] ] );

		// Add an entry for a temp account under the same IP
		$abuseFilterLoggerFactory->newLogger(
			$existingTitle,
			$tempUser,
			VariableHolder::newFromArray( [
				'action' => 'edit',
				'user_name' => $tempUser->getName(),
				'user_unnamed_ip' => '172.19.0.4',
			] )
		)->addLogEntries( [ 2 => [ 'warn' ] ] );

		$this->setIPForLogEntries( 1, '172.19.0.4', 2, 'edit' );

		// Create suppressed filter #3
		$this->createFilter(
			$authority,
			[
				'id' => '3',
				'rules' => '1 == 1',
				'name' => 'Suppressed Filter',
				'privacy' => Flags::FILTER_SUPPRESSED,
				'lastEditor' => $performer,
				'lastEditTimestamp' => $timestamp,
			]
		);

		// Insert a log entry for filter #3
		$abuseFilterLoggerFactory = AbuseFilterServices::getAbuseLoggerFactory();
		$abuseFilterLoggerFactory->newLogger(
			$this->getExistingTestPage()->getTitle(),
			$this->getTestUser()->getUser(),
			VariableHolder::newFromArray( [ 'action' => 'tag' ] )
		)->addLogEntries( [ 3 => [ 'warn' ] ] );

		// Verify that the expected number of DB rows were created
		$this->assertNumberOfEntriesMatch( 2, 1 );
		$this->assertNumberOfEntriesMatch( 4, 2 );
		$this->assertNumberOfEntriesMatch( 1, 3 );
	}

	private function createFilter(
		Authority $authority,
		array $specs
	): void {
		$filter = $this->getFilterFromSpecs( $specs );
		$status = AbuseFilterServices::getFilterStore()->saveFilter(
			$authority,
			null,
			$filter,
			MutableFilter::newDefault()
		);

		$this->assertStatusGood( $status );
	}

	private function setIPForLogEntries(
		int $expectedNumRowsChanged,
		string $ip,
		int $filterId,
		?string $action = null
	): void {
		$conditions = [
			'afl_filter_id' => $filterId,
		];

		if ( $action ) {
			$conditions['afl_action'] = $action;
		}

		$this->getDb()->newUpdateQueryBuilder()
			->update( 'abuse_filter_log' )
			->set( [ 'afl_ip_hex' => IPUtils::toHex( $ip ) ] )
			->where( $conditions )
			->caller( __METHOD__ )
			->execute();

		$this->assertSame(
			$expectedNumRowsChanged,
			$this->getDb()->affectedRows()
		);
	}

	private function getIpUser( string $ip ): User {
		return $this->getServiceContainer()
			->getUserFactory()
			->newFromName( $ip, UserFactory::RIGOR_NONE );
	}

	private function assertNumberOfEntriesMatch(
		int $expectedCount,
		string $filterId
	): void {
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->table( 'abuse_filter_log' )
			->where( [ 'afl_filter_id' => $filterId ] )
			->caller( __METHOD__ )
			->assertFieldValue( $expectedCount );
	}
}
