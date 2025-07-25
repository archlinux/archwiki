<?php

namespace MediaWiki\CheckUser\Tests\Integration\Api;

use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiQuery;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\CheckUser\Api\ApiQueryCheckUserLog;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Permissions\Authority;
use MediaWiki\Tests\Api\ApiTestCase;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group API
 * @group medium
 * @group Database
 *
 * @covers \MediaWiki\CheckUser\Api\ApiQueryCheckUserLog
 */
class ApiQueryCheckUserLogTest extends ApiTestCase {

	private const INITIAL_API_PARAMS = [
		'action' => 'query',
		'list' => 'checkuserlog',
	];

	/**
	 * Does an API request to the checkuserlog API
	 *  and returns the result.
	 *
	 * @param array $params
	 * @param array|null $session
	 * @param Authority|null $performer
	 * @return array
	 * @throws ApiUsageException
	 */
	public function doCheckUserLogApiRequest(
		array $params = [], ?array $session = null, ?Authority $performer = null
	) {
		if ( $performer === null ) {
			$performer = $this->getTestUser( 'checkuser' )->getAuthority();
		}
		return $this->doApiRequest( self::INITIAL_API_PARAMS + $params, $session, false, $performer );
	}

	/**
	 * @param string $moduleName
	 * @return TestingAccessWrapper
	 */
	public function setUpObject( string $moduleName = '' ) {
		$services = $this->getServiceContainer();
		$main = new ApiMain( $this->apiContext, true );
		/** @var ApiQuery $query */
		$query = $main->getModuleManager()->getModule( 'query' );
		return TestingAccessWrapper::newFromObject( new ApiQueryCheckUserLog(
			$query, $moduleName, $services->getCommentStore(), $services->get( 'CheckUserLogService' ),
			$services->getUserFactory()
		) );
	}

	/**
	 * @dataProvider provideRequiredGroupAccess
	 */
	public function testRequiredRightsByGroup( $groups, $allowed ) {
		$testUser = $this->getTestUser( $groups );
		if ( !$allowed ) {
			$this->setExpectedApiException(
				[ 'apierror-permissiondenied', wfMessage( 'action-checkuser-log' )->text() ]
			);
		}
		$result = $this->doCheckUserLogApiRequest(
			[],
			null,
			$testUser->getUser()
		);
		$this->assertNotNull( $result );
	}

	public static function provideRequiredGroupAccess() {
		return [
			'No user groups' => [ '', false ],
			'Checkuser only' => [ 'checkuser', true ],
			'Checkuser and sysop' => [ [ 'checkuser', 'sysop' ], true ],
		];
	}

	/**
	 * @dataProvider provideRequiredRights
	 */
	public function testRequiredRights( $groups, $allowed ) {
		if ( $groups === "checkuser-log" ) {
			$this->setGroupPermissions(
				[ 'checkuser-log' => [ 'checkuser-log' => true, 'read' => true ] ]
			);
		}
		$this->testRequiredRightsByGroup( $groups, $allowed );
	}

	public static function provideRequiredRights() {
		return [
			'No user groups' => [ '', false ],
			'checkuser-log right only' => [ 'checkuser-log', true ],
		];
	}

	/**
	 * Tests that the function returns valid URLs.
	 * Does not test that the URL is correct as if
	 * the URL is changed in a proposed commit the
	 * reviewer should check the URL points to the
	 * right place.
	 */
	public function testGetHelpUrls() {
		$helpUrls = $this->setUpObject()->getHelpUrls();
		if ( !is_string( $helpUrls ) && !is_array( $helpUrls ) ) {
			$this->fail( 'getHelpUrls should return an array of URLs or a URL' );
		}
		if ( is_string( $helpUrls ) ) {
			$helpUrls = [ $helpUrls ];
		}
		foreach ( $helpUrls as $helpUrl ) {
			$this->assertIsArray( parse_url( $helpUrl ) );
		}
	}

	/** @dataProvider provideExampleLogEntryDataForReasonFilterTest */
	public function testReasonFilter(
		$logType, $targetType, $target, $reason, $targetID, $timestamp, $reasonToSearchFor, $shouldSeeEntry
	) {
		ConvertibleTimestamp::setFakeTime( $timestamp );
		/** @var CheckUserLogService $checkUserLogService */
		$checkUserLogService = $this->getServiceContainer()->get( 'CheckUserLogService' );
		$checkUserLogService->addLogEntry(
			$this->getTestSysop()->getUser(), $logType, $targetType, $target, $reason, $targetID
		);
		DeferredUpdates::doUpdates();
		$result = $this->doCheckUserLogApiRequest( [
			'culreason' => $reasonToSearchFor
		] )[0]['query']['checkuserlog']['entries'];
		if ( $shouldSeeEntry ) {
			$this->assertCount( 1, $result, 'A search for the reason should show one entry.' );
		} else {
			$this->assertCount( 0, $result, 'A search for the reason should show no entries.' );
		}
		$result = $this->doCheckUserLogApiRequest( [
			'culreason' => $checkUserLogService->getPlaintextReason( $reasonToSearchFor )
		] )[0]['query']['checkuserlog']['entries'];
		if ( $shouldSeeEntry ) {
			$this->assertCount(
				1, $result, 'A search for the plaintext version of the reason should show one entry.'
			);
		} else {
			$this->assertCount(
				0, $result, 'A search for the plaintext version of the reason should show no entries.'
			);
		}
	}

	public static function provideExampleLogEntryDataForReasonFilterTest() {
		$tests = [];
		foreach ( self::provideExampleLogEntryData() as $name => $values ) {
			$tests[$name . ' with matching reason'] = array_merge( $values, [ $values[3], true ] );
			$tests[$name . ' with non-matching reason'] = array_merge( $values, [ 'Nonexisting reason12345', false ] );
		}
		return $tests;
	}

	/** @dataProvider provideExampleLogEntryData */
	public function testReturnsCorrectData( $logType, $targetType, $target, $reason, $targetID, $timestamp ) {
		ConvertibleTimestamp::setFakeTime( $timestamp );
		// Set up by the DB by inserting data.
		/** @var CheckUserLogService $checkUserLogService */
		$checkUserLogService = $this->getServiceContainer()->get( 'CheckUserLogService' );
		$checkUserLogService->addLogEntry(
			$this->getTestSysop()->getUser(), $logType, $targetType, $target, $reason, $targetID
		);
		DeferredUpdates::doUpdates();
		$result = $this->doCheckUserLogApiRequest()[0]['query']['checkuserlog']['entries'];
		$this->assertCount( 1, $result, 'Should only be one CheckUserLog entry returned.' );
		$this->assertArrayEquals(
			[
				'timestamp' => ConvertibleTimestamp::convert( TS_ISO_8601, $timestamp ),
				'checkuser' => $this->getTestSysop()->getUserIdentity()->getName(),
				'type' => $logType,
				'reason' => $reason,
				'target' => $target
			],
			$result[0],
			'CheckUserLog entry returned was not correct.'
		);
	}

	public static function provideExampleLogEntryData() {
		return [
			'IP target' => [ 'ipusers', 'ip', '127.0.0.1', 'testing', 0, '1653047635' ],
			'User target' => [ 'userips', 'user', 'Testing', '1234 - [[test]]', 0, '1653042345' ],
		];
	}

	public function testTargetFilterForUser() {
		$testUser = $this->getTestUser()->getUserIdentity();
		$target = $testUser->getName();
		$targetID = $testUser->getId();
		/** @var CheckUserLogService $checkUserLogService */
		$checkUserLogService = $this->getServiceContainer()->get( 'CheckUserLogService' );
		$checkUserLogService->addLogEntry(
			$this->getTestSysop()->getUser(), 'userips', 'user', $target, 'test', $targetID
		);
		DeferredUpdates::doUpdates();
		$result = $this->doCheckUserLogApiRequest( [
			'cultarget' => $target
		] )[0]['query']['checkuserlog']['entries'];
		$this->assertCount(
			1,
			$result,
			'A search for the target of the entry added to the cu_log table should have returned 1 check ' .
			'in the API response.'
		);
	}

	/** @dataProvider provideTargetFilter */
	public function testTargetFilterForIP( $target ) {
		/** @var CheckUserLogService $checkUserLogService */
		$checkUserLogService = $this->getServiceContainer()->get( 'CheckUserLogService' );
		$checkUserLogService->addLogEntry(
			$this->getTestSysop()->getUser(), 'ipusers', 'ip', $target, 'test'
		);
		DeferredUpdates::doUpdates();
		$result = $this->doCheckUserLogApiRequest( [
			'cultarget' => $target
		] )[0]['query']['checkuserlog']['entries'];
		$this->assertCount(
			1,
			$result,
			'A search for the target of the entry added to the cu_log table should have returned 1 check ' .
			'in the API response.'
		);
	}

	public static function provideTargetFilter() {
		return [
			'IP address' => [ '1.2.3.4' ],
			'IP range' => [ '1.2.3.4/22' ],
		];
	}
}
