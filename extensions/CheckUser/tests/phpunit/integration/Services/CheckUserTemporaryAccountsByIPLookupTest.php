<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Services;

use InvalidArgumentException;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\CheckUser\CheckUserPermissionStatus;
use MediaWiki\Extension\CheckUser\Logging\TemporaryAccountLogger;
use MediaWiki\Extension\CheckUser\Services\CheckUserTemporaryAccountsByIPLookup;
use MediaWiki\Extension\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\Extension\CheckUser\Services\CheckUserTemporaryAccountsByIPLookup
 * @group CheckUser
 * @group Database
 */
class CheckUserTemporaryAccountsByIPLookupTest extends MediaWikiIntegrationTestCase {
	use CheckUserTempUserTestTrait;
	use MockAuthorityTrait;

	public function setUp(): void {
		parent::setUp();
		$this->enableAutoCreateTempUser();
	}

	public function addDBDataOnce() {
		$this->enableAutoCreateTempUser();

		// Create some temp accounts and edits on different IPs. Ensure they are
		// created at different times, so we get consistent results when limits
		// are applied.

		// This temp account edits from 2 IPv4 IPs
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.1' );
		ConvertibleTimestamp::setFakeTime( '20230405060706' );
		$tempUser1 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-01', $this->getFauxRequest( '127.0.0.1' ) )->getUser();
		$this->editPage(
			'Test page',
			'Test Content 1A',
			'test',
			NS_MAIN,
			$tempUser1
		);
		ConvertibleTimestamp::setFakeTime( '20230405060707' );
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.2' );
		$this->editPage(
			'Test page',
			'Test Content 1B',
			'test',
			NS_MAIN,
			$tempUser1
		);
		// Add another action at the same timestamp, from a different IP, to
		// test ordering by two fields
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.3' );
		$this->editPage(
			'Test page',
			'Test Content 1C',
			'test',
			NS_MAIN,
			$tempUser1
		);

		// This temp account is created from $tempUser1's second edit IP and edits
		// from there and also from an IPv6 IP
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.2' );
		ConvertibleTimestamp::setFakeTime( '20230405060708' );
		$tempUser2 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-02', $this->getFauxRequest( '127.0.0.2' ) )
			->getUser();
		$this->editPage(
			'Test page',
			'Test Content 2A',
			'test',
			NS_MAIN,
			$tempUser2
		);
		ConvertibleTimestamp::setFakeTime( '20230405060709' );
		RequestContext::getMain()->getRequest()->setIP( '1:1:1:1:1:1:1:1' );
		$this->editPage(
			'Test page',
			'Test Content 2B',
			'test',
			NS_MAIN,
			$tempUser2
		);

		// This temp account edits from a different IPv6 IP
		// but in the same 64 range as the second temp user as well and
		// repeatedly from an IPv6 IP on a different range
		ConvertibleTimestamp::setFakeTime( '20230405060710' );
		RequestContext::getMain()->getRequest()->setIP( '1:1:1:1:1:1:1:2' );
		$tempUser3 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-03', $this->getFauxRequest( '1:1:1:1:1:1:1:2' ) )->getUser();
		$this->editPage(
			'Test page',
			'Test Content 3A',
			'test',
			NS_MAIN,
			$tempUser3
		);
		RequestContext::getMain()->getRequest()->setIP( '2:2:2:2:2:2:2:2' );
		$this->editPage(
			'Test page',
			'Test Content 3B',
			'test',
			NS_MAIN,
			$tempUser3
		);
		$this->editPage(
			'Test page',
			'Test Content 3C',
			'test',
			NS_MAIN,
			$tempUser3
		);

		// Hide the user so we can test hideuser permissions
		$blockStatus = $this->getServiceContainer()->getBlockUserFactory()
			->newBlockUser(
				$this->getServiceContainer()->getUserIdentityLookup()->getUserIdentityByName( '~check-user-test-03' ),
				$this->getTestUser( [ 'sysop', 'suppress' ] )->getUser(),
				'infinity',
				'block to hide the test user',
				[ 'isHideUser' => true ]
			)->placeBlock();
		$this->assertStatusGood( $blockStatus );

		// This temp account doesn't share an IP with any other account
		ConvertibleTimestamp::setFakeTime( '20230405060711' );
		RequestContext::getMain()->getRequest()->setIP( '1.2.3.4' );
		$tempUser4 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-04', $this->getFauxRequest( '1.2.3.4' ) )->getUser();
		$this->editPage(
			'Test page',
			'Test Content 4A',
			'test',
			NS_MAIN,
			$tempUser4
		);

		ConvertibleTimestamp::setFakeTime( false );
	}

	private function getFauxRequest( string $ip ): FauxRequest {
		$request = new FauxRequest();
		$request->setIP( $ip );
		return $request;
	}

	/**
	 * @dataProvider provideTestExecuteGetIpsUsedCount
	 */
	public function testExecuteGetIpsUsedCount( $name, $limit, $expectedCount ) {
		$checkUserTemporaryAccountsByIPLookup = $this->getObjectUnderTest();
		$tempUserIdentity = $this->getServiceContainer()->getUserFactory()->newFromName( $name );
		$ipsUsedCount = $checkUserTemporaryAccountsByIPLookup->getIpsUsedCount( $tempUserIdentity, $limit );
		$this->assertEquals( $expectedCount, $ipsUsedCount );
	}

	public static function provideTestExecuteGetIpsUsedCount() {
		return [
			'Multiple IPs used' => [
				'name' => '~check-user-test-01',
				'limit' => null,
				'expectedCount' => 3,
			],
			'Single IP used' => [
				'name' => '~check-user-test-04',
				'limit' => null,
				'expectedCount' => 1,
			],
			'Limit respected' => [
				'name' => '~check-user-test-01',
				'limit' => 1,
				'expectedCount' => 1,
			],
		];
	}

	/**
	 * @dataProvider provideTestExecutegetTempAccountsFromIPAddress
	 */
	public function testExecutegetTempAccountsFromIPAddress( $ip, $limit, $expectedCount, $expectedAccounts ) {
		$checkUserTemporaryAccountsByIPLookup = $this->getObjectUnderTest();
		$accounts = $checkUserTemporaryAccountsByIPLookup->getTempAccountsFromIPAddress( $ip, $limit );

		// Assert count of results
		$this->assertCount( $expectedCount, $accounts );

		// Assert accounts were returned as expected
		$this->assertArrayEquals( $expectedAccounts, $accounts );
	}

	public static function provideTestExecutegetTempAccountsFromIPAddress() {
		return [
			'Base case - Single IP, single account' => [
				'ip' => '127.0.0.1',
				'limit' => null,
				'expectedCount' => 1,
				'expectedAccounts' => [ '~check-user-test-01' ],
			],
			'Mutiple accounts found - Single IP, multiple accounts' => [
				'ip' => '127.0.0.2',
				'limit' => null,
				'expectedCount' => 2,
				'expectedAccounts' => [ '~check-user-test-01', '~check-user-test-02' ],
			],
			'No results' => [
				'ip' => '127.0.0.64',
				'limit' => null,
				'expectedCount' => 0,
				'expectedAccounts' => [],
			],
			'Range search - IPv6 range, multiple accounts' => [
				'ip' => '1:1:1:1:1:1:1:64/64',
				'limit' => null,
				'expectedCount' => 2,
				'expectedAccounts' => [ '~check-user-test-02', '~check-user-test-03' ],
			],
			'Accounts returned are unique - IPv6 range, single account, multiple edits' => [
				'ip' => '2:2:2:2:2:2:2:2/64',
				'limit' => null,
				'expectedCount' => 1,
				'expectedAccounts' => [ '~check-user-test-03' ],
			],
			'Account is returned from cu_log_event lookup' => [
				'ip' => '1.2.3.4',
				'limit' => null,
				'expectedCount' => 1,
				'expectedAccounts' => [ '~check-user-test-04' ],
			],
			'Limit parameter is respected' => [
				'ip' => '127.0.0.1',
				'limit' => 0,
				'expectedCount' => 1,
				'expectedAccounts' => [ '~check-user-test-01' ],
			],
		];
	}

	public function testInvalidArgumentgetTempAccountsFromIPAddress() {
		$checkUserTemporaryAccountsByIPLookup = $this->getObjectUnderTest();
		$this->expectException( InvalidArgumentException::class );

		// Assert usernames are not allowed, existing or not
		$checkUserTemporaryAccountsByIPLookup->getTempAccountsFromIPAddress( 'User 1' );
	}

	/**
	 * @dataProvider provideTestExecuteGetAggregateActiveTempAccountCount
	 */
	public function testExecuteGetAggregateActiveTempAccountCount( $userName, $limit, $expectedCount ) {
		$checkUserTemporaryAccountsByIPLookup = $this->getObjectUnderTest();
		$user = $this->getServiceContainer()->getUserFactory()->newFromName( $userName );
		$res = $checkUserTemporaryAccountsByIPLookup->getAggregateActiveTempAccountCount( $user, $limit );
		$this->assertSame( $expectedCount, $res );
	}

	public static function provideTestExecuteGetAggregateActiveTempAccountCount() {
		return [
			'Count comes from unique sets' => [
				'userName' => '~check-user-test-01',
				'limit' => null,
				'expectedCount' => 2,
			],
			'Count comes from sets with overlapping results' => [
				'userName' => '~check-user-test-02',
				'limit' => null,
				'expectedCount' => 3,
			],
			'Count comes from single unique set' => [
				'userName' => '~check-user-test-04',
				'limit' => null,
				'expectedCount' => 1,
			],
			'Don\'t exceed limit' => [
				'userName' => '~check-user-test-02',
				'limit' => 1,
				'expectedCount' => 1,
			],
		];
	}

	public function testNamedUsersRejectedForGetAggregateActiveTempAccountCount() {
		$checkUserTemporaryAccountsByIPLookup = $this->getObjectUnderTest();
		$this->expectException( InvalidArgumentException::class );

		// Assert that non-temp accounts are invalid
		$checkUserTemporaryAccountsByIPLookup->getAggregateActiveTempAccountCount(
			$this->getTestUser()->getUserIdentity()
		);
	}

	public function testGetWhenReadOnly(): void {
		// Get the authority before setting site read only, as otherwise
		// the user would not be saved to the DB
		$authority = $this->getTestUser( [ 'checkuser' ] )->getAuthority();

		$this->getServiceContainer()->getReadOnlyMode()->setReason( 'test' );
		$actualStatus = $this->getObjectUnderTest()->get(
			'1.2.3.4',
			$authority,
			true,
			123
		);
		$this->assertStatusError( 'readonlytext', $actualStatus );
	}

	public function testGetActiveTempAccountNamesWhenReadOnly(): void {
		$this->getServiceContainer()->getReadOnlyMode()->setReason( 'test' );
		$actualStatus = $this->getObjectUnderTest()->getActiveTempAccountNames(
			$this->mockRegisteredUltimateAuthority(),
			$this->getServiceContainer()->getUserFactory()->newFromName( '~check-user-test-01' ),
			123
		);
		$this->assertStatusError( 'readonlytext', $actualStatus );
	}

	/**
	 * @dataProvider provideTestExecuteGetActiveTempAccountNames
	 */
	public function testExecuteGetActiveTempAccountNames( $userName, $limit, $expected ) {
		$checkUserTemporaryAccountsByIPLookup = $this->getObjectUnderTest();
		$user = $this->getServiceContainer()->getUserFactory()->newFromName( $userName );
		$performer = $this->mockRegisteredUltimateAuthority();
		$status = $checkUserTemporaryAccountsByIPLookup
			->getActiveTempAccountNames( $performer, $user, $limit );
		$this->assertEqualsCanonicalizing( $expected, $status->getValue() );
	}

	public static function provideTestExecuteGetActiveTempAccountNames() {
		return [
			'Count comes from unique sets' => [
				'userName' => '~check-user-test-01',
				'limit' => null,
				'expected' => [
					'~check-user-test-01',
					'~check-user-test-02',
				],
			],
			'Count comes from sets with overlapping results' => [
				'userName' => '~check-user-test-02',
				'limit' => null,
				'expected' => [
					'~check-user-test-01',
					'~check-user-test-02',
					'~check-user-test-03',
				],
			],
			'Count comes from single unique set' => [
				'userName' => '~check-user-test-04',
				'limit' => null,
				'expected' => [ '~check-user-test-04' ],
			],
			'Don\'t exceed limit' => [
				'userName' => '~check-user-test-02',
				'limit' => 1,
				'expected' => [ '~check-user-test-03' ],
			],
		];
	}

	public function testExecuteGetActiveTempAccountNamesNoPermissions() {
		$checkUserTemporaryAccountsByIPLookup = $this->getObjectUnderTest();
		$user = $this->getServiceContainer()->getUserFactory()->newFromName( '~check-user-test-01' );
		$performer = $this->mockRegisteredNullAuthority();
		$status = $checkUserTemporaryAccountsByIPLookup
			->getActiveTempAccountNames( $performer, $user );
		$this->assertInstanceOf( CheckUserPermissionStatus::class, $status );
		$this->assertSame( null, $status->getValue() );
	}

	public function testExecuteGetActiveTempAccountNamesHiddenUserAndLogging() {
		$checkUserTemporaryAccountsByIPLookup = $this->getObjectUnderTest();
		$user = $this->getServiceContainer()->getUserFactory()->newFromName( '~check-user-test-02' );
		// Use a real user so that we can test logging
		$performer = $this->getTestUser( [ 'checkuser' ] )->getUser();
		$status = $checkUserTemporaryAccountsByIPLookup
			->getActiveTempAccountNames( $performer, $user );
		$this->assertSame( [
			'~check-user-test-02',
			'~check-user-test-01',
		], $status->getValue() );

		// Test that a log entry was made
		$this->runJobs();
		$this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->from( 'logging' )
			->where( [
				'log_type' => TemporaryAccountLogger::LOG_TYPE,
				'log_action' => TemporaryAccountLogger::ACTION_VIEW_RELATED_TEMPORARY_ACCOUNTS,
				'log_actor' => $this->getServiceContainer()->getActorStore()
					->findActorId( $performer, $this->getDb() ),
				'log_title' => $user->getUserPage()->getDBkey(),
				'log_namespace' => NS_USER,
			] )
			->caller( __METHOD__ )
			->assertFieldValue( 1 );
	}

	/**
	 * @dataProvider provideTestExecuteGetBucketedCount
	 */
	public function testExecuteGetBucketedCount( $count, $bucketSchema, $expectedBucket ) {
		$checkUserTemporaryAccountsByIPLookup = $this->getObjectUnderTest();
		$res = $checkUserTemporaryAccountsByIPLookup->getBucketedCount( $count, $bucketSchema );
		$this->assertArrayEquals( $expectedBucket, $res );
	}

	public static function provideTestExecuteGetBucketedCount() {
		return [
			'min' => [
				'count' => 0,
				'bucketSchema' => null,
				'expectedBucket' => [ 0, 0 ],
			],
			'exactly 1' => [
				'count' => 1,
				'bucketSchema' => null,
				'expectedBucket' => [ 1, 1 ],
			],
			'range, lower bound' => [
				'count' => 3,
				'bucketSchema' => null,
				'expectedBucket' => [ 2, 5 ],
			],
			'range, upper bound' => [
				'count' => 10,
				'bucketSchema' => null,
				'expectedBucket' => [ 6, 10 ],
			],
			'max' => [
				'count' => 101,
				'bucketSchema' => null,
				'expectedBucket' => [ 101, 101 ],
			],
			'custom schema, range' => [
				'count' => 3,
				'bucketSchema' => [
					'max' => 5,
					'ranges' => [
						[ 1, 4 ],
						[ 5, 6 ],
					],
				],
				'expectedBucket' => [ 1, 4 ],
			],
		];
	}

	/**
	 * @dataProvider provideTestExecuteGetDistinctIPsFromTempAccount
	 */
	public function testExecuteGetDistinctIPsFromTempAccount( $userName, $limit, $expectedResult ) {
		$checkUserTemporaryAccountsByIPLookup = $this->getObjectUnderTest();
		$user = $this->getServiceContainer()->getUserFactory()->newFromName( $userName );
		$res = $checkUserTemporaryAccountsByIPLookup->getDistinctIPsFromTempAccount( $user, $limit );
		$this->assertArrayEquals( $expectedResult, $res );
	}

	public static function provideTestExecuteGetDistinctIPsFromTempAccount() {
		return [
			'IPv4' => [
				'userName' => '~check-user-test-01',
				'limit' => null,
				'expectedResult' => [
					'127.0.0.1',
					'127.0.0.2',
					'127.0.0.3',
				],
			],
			'IPv4/6 mixed' => [
				'userName' => '~check-user-test-02',
				'limit' => null,
				'expectedResult' => [
					'127.0.0.2',
					'1:1:1:1:1:1:1:1',
				],
			],
			'IPv6' => [
				'userName' => '~check-user-test-03',
				'limit' => null,
				'expectedResult' => [
					'1:1:1:1:1:1:1:2',
					'2:2:2:2:2:2:2:2',
				],
			],
			'Don\'t exceed limit' => [
				'userName' => '~check-user-test-01',
				'limit' => 1,
				'expectedResult' => [
					'127.0.0.2',
				],
			],
		];
	}

	public function testInvalidArgumentGetDistinctIPsFromTempAccount() {
		$checkUserTemporaryAccountsByIPLookup = $this->getObjectUnderTest();
		$this->expectException( InvalidArgumentException::class );

		// Assert that non-temp accounts are invalid
		$checkUserTemporaryAccountsByIPLookup->getDistinctIPsFromTempAccount(
			$this->getTestUser()->getUserIdentity()
		);
	}

	/**
	 * @return CheckUserTemporaryAccountsByIPLookup
	 */
	public function getObjectUnderTest() {
		/** @var CheckUserTemporaryAccountsByIPLookup $objectUnderTest */
		$objectUnderTest = $this->getServiceContainer()->get( 'CheckUserTemporaryAccountsByIPLookup' );
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		return $objectUnderTest;
	}
}
