<?php

namespace MediaWiki\CheckUser\Tests\Integration\Services;

use InvalidArgumentException;
use MediaWiki\CheckUser\Services\CheckUserTemporaryAccountsByIPLookup;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Context\RequestContext;
use MediaWiki\Request\FauxRequest;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\CheckUser\Services\CheckUserTemporaryAccountsByIPLookup
 * @group CheckUser
 * @group Database
 */
class CheckUserTemporaryAccountsByIPLookupTest extends MediaWikiIntegrationTestCase {
	use CheckUserTempUserTestTrait;

	public function setUp(): void {
		parent::setUp();
		$this->enableAutoCreateTempUser();
	}

	public function addDBDataOnce() {
		$this->enableAutoCreateTempUser();

		// Create some temp accounts and edits on different IPs:
		// This temp account edits from 2 IPv4 IPs
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.1' );
		ConvertibleTimestamp::setFakeTime( ConvertibleTimestamp::time() - 1000 );
		$tempUser1 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-01', new FauxRequest() )->getUser();
		$this->editPage(
			'Test page', 'Test Content 1A', 'test', NS_MAIN, $tempUser1
		);
		ConvertibleTimestamp::setFakeTime( false );
		RequestContext::getMain()->getRequest()->setIP( '127.0.0.2' );
		$this->editPage(
			'Test page', 'Test Content 1B', 'test', NS_MAIN, $tempUser1
		);

		// This temp account is created from $tempUser1's second edit IP and edits
		// from there and also from an IPv6 IP
		$tempUser2 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-02', new FauxRequest() )->getUser();
		$this->editPage(
			'Test page', 'Test Content 2A', 'test', NS_MAIN, $tempUser2
		);
		RequestContext::getMain()->getRequest()->setIP( '1:1:1:1:1:1:1:1' );
		$this->editPage(
			'Test page', 'Test Content 2B', 'test', NS_MAIN, $tempUser2
		);

		// This temp account edits from a different IPv6 IP
		// but in the same 64 range as the second temp user as well and
		// repeatedly from an IPv6 IP on a different range
		RequestContext::getMain()->getRequest()->setIP( '1:1:1:1:1:1:1:2' );
		$tempUser3 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-03', new FauxRequest() )->getUser();
		$this->editPage(
			'Test page', 'Test Content 3A', 'test', NS_MAIN, $tempUser3
		);
		RequestContext::getMain()->getRequest()->setIP( '2:2:2:2:2:2:2:2' );
		$this->editPage(
			'Test page', 'Test Content 3B', 'test', NS_MAIN, $tempUser3
		);
		$this->editPage(
			'Test page', 'Test Content 3C', 'test', NS_MAIN, $tempUser3
		);

		// This temp account doesn't share an IP with any other account
		RequestContext::getMain()->getRequest()->setIP( '1.2.3.4' );
		$tempUser4 = $this->getServiceContainer()
			->getTempUserCreator()
			->create( '~check-user-test-04', new FauxRequest() )->getUser();
		$this->editPage(
			'Test page', 'Test Content 4A', 'test', NS_MAIN, $tempUser4
		);
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
				'expectedRowCount' => 1,
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
				'expectedRowCount' => 1,
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
			'range, lower bound' => [
				'count' => 3,
				'bucketSchema' => null,
				'expectedBucket' => [ 3, 5 ],
			],
			'range, upper bound' => [
				'count' => 10,
				'bucketSchema' => null,
				'expectedBucket' => [ 6, 10 ],
			],
			'max' => [
				'count' => 11,
				'bucketSchema' => null,
				'expectedBucket' => [ 11, 11 ],
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

	public function getObjectUnderTest() {
		/** @var CheckUserTemporaryAccountsByIPLookup $objectUnderTest */
		$objectUnderTest = $this->getServiceContainer()->get( 'CheckUserTemporaryAccountsByIPLookup' );
		$objectUnderTest = TestingAccessWrapper::newFromObject( $objectUnderTest );
		return $objectUnderTest;
	}
}
