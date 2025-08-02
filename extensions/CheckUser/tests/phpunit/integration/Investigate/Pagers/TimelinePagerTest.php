<?php

namespace MediaWiki\CheckUser\Tests\Integration\Investigate\Pagers;

use MediaWiki\CheckUser\Investigate\Pagers\TimelinePager;
use MediaWiki\CheckUser\Investigate\Pagers\TimelineRowFormatter;
use MediaWiki\CheckUser\Investigate\Services\TimelineService;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Context\RequestContext;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Pager\IndexPager;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use TestUser;
use Wikimedia\IPUtils;
use Wikimedia\TestingAccessWrapper;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @covers \MediaWiki\CheckUser\Investigate\Pagers\TimelinePager
 * @covers \MediaWiki\CheckUser\Investigate\Services\TimelineService
 * @group CheckUser
 * @group Database
 */
class TimelinePagerTest extends MediaWikiIntegrationTestCase {

	use CheckUserTempUserTestTrait;

	private function getObjectUnderTest( array $overrides = [] ) {
		return TestingAccessWrapper::newFromObject( new TimelinePager(
			RequestContext::getMain(),
			$overrides['linkRenderer'] ?? $this->getServiceContainer()->getLinkRenderer(),
			$overrides['hookRuner'] ?? $this->getServiceContainer()->get( 'CheckUserHookRunner' ),
			$overrides['tokenQueryManager'] ?? $this->getServiceContainer()->get( 'CheckUserTokenQueryManager' ),
			$overrides['durationManager'] ?? $this->getServiceContainer()->get( 'CheckUserDurationManager' ),
			$overrides['timelineService'] ?? $this->getServiceContainer()->get( 'CheckUserTimelineService' ),
			$overrides['timelineRowFormatterFactory'] ?? $this->getServiceContainer()
				->get( 'CheckUserTimelineRowFormatterFactory' )->createRowFormatter(
					RequestContext::getMain()->getUser(), RequestContext::getMain()->getLanguage()
				),
			$this->getServiceContainer()->getLinkBatchFactory(),
			$overrides['logger'] ?? LoggerFactory::getInstance( 'CheckUser' )
		) );
	}

	/** @dataProvider provideFormatRow */
	public function testFormatRow( $row, $formattedRowItems, $lastDateHeader, $expectedHtml ) {
		// Temporarily disable the ::onCheckUserFormatRow hook to avoid test failures due to other code defining items
		// for display.
		$this->clearHook( 'CheckUserFormatRow' );
		$mockTimelineRowFormatter = $this->createMock( TimelineRowFormatter::class );
		$mockTimelineRowFormatter->expects( $this->once() )
			->method( 'getFormattedRowItems' )
			->willReturn( $formattedRowItems );
		// Define a mock TimelineService that expects a call to ::formatRow
		$objectUnderTest = $this->getObjectUnderTest();
		$objectUnderTest->timelineRowFormatter = $mockTimelineRowFormatter;
		$objectUnderTest->lastDateHeader = $lastDateHeader;
		$this->assertSame(
			$expectedHtml,
			$objectUnderTest->formatRow( (object)$row ),
			'::formatRow did not return the expected HTML'
		);
	}

	public static function provideFormatRow() {
		return [
			'Row with no items and date header' => [
				// The $row provided to ::formatRow as an array
				[ 'timestamp' => '20240405060708' ],
				// The result of TimelineRowFormatter::getFormattedRowItems
				[ 'info' => [], 'links' => [] ],
				// The value of the $lastDateHeader property
				null,
				// The expected HTML output
				'<h4>5 April 2024</h4><ul><li></li>',
			],
			'Row with no items and no date header' => [
				[ 'timestamp' => '20240405060708' ], [ 'info' => [], 'links' => [] ], '5 April 2024', '<li></li>',
			],
			'Row with items and different date header' => [
				[ 'timestamp' => '20240405060708' ],
				[ 'info' => [ 'info1', 'info2' ], 'links' => [ 'link1', 'link2' ] ],
				'4 April 2024', '</ul><h4>5 April 2024</h4><ul><li>link1 link2 . . info1 . . info2</li>',
			],
			'Invalid formatted row items' => [ [ 'timestamp' => '20240405060708' ], [], null, '' ],
		];
	}

	/** @dataProvider provideGetEndBody */
	public function testGetEndBody( $numRows, $expected ) {
		$objectUnderTest = $this->getMockBuilder( TimelinePager::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getNumRows' ] )
			->getMock();
		$objectUnderTest->method( 'getNumRows' )
			->willReturn( $numRows );
		$this->assertSame(
			$expected,
			$objectUnderTest->getEndBody(),
			'::getEndBody did not return the expected HTML'
		);
	}

	public static function provideGetEndBody() {
		return [
			'No rows' => [ 0, '' ],
			'One row' => [ 1, '</ul>' ],
		];
	}

	public function testGetQueryInfo() {
		$mockTimelineService = $this->createMock( TimelineService::class );
		$mockTimelineService->expects( $this->once() )
			->method( 'getQueryInfo' );
		// Define a mock TimelineService that expects a call to ::getQueryInfo
		$objectUnderTest = $this->getObjectUnderTest();
		$objectUnderTest->timelineService = $mockTimelineService;
		$objectUnderTest->getQueryInfo();
	}

	public function testReallyDoQueryOnAllExcludedTargets() {
		// Also tests that the constructor correctly generates the filteredTargets property.
		// This is not tested in the ::testReallyDoQuery test because the filteredTargets property is manually set.
		$tokenQueryManager = $this->getMockBuilder( TokenQueryManager::class )
			->disableOriginalConstructor()
			->onlyMethods( [ 'getDataFromRequest' ] )
			->getMock();
		$tokenQueryManager->method( 'getDataFromRequest' )
			->willReturn( [
				'targets' => [ '1.2.3.4' ],
				'exclude-targets' => [ '1.2.3.4' ],
			] );
		$pager = $this->getObjectUnderTest( [
			'tokenQueryManager' => $tokenQueryManager,
		] );
		$actualResult = $pager->reallyDoQuery( '', 50, IndexPager::QUERY_ASCENDING );
		$this->assertSame( 0, $actualResult->numRows() );
	}

	/** @dataProvider provideReallyDoQuery */
	public function testReallyDoQuery( $offset, $limit, $order, $filteredTargets, $expectedRows ) {
		$objectUnderTest = $this->getObjectUnderTest();
		$objectUnderTest->filteredTargets = $filteredTargets;
		// Pass the expected timestamp through IReadableTimestamp::timestamp to ensure it is in the right format
		// for the current DB type (T366590).
		$expectedRows = array_map( function ( $row ) {
			$row->timestamp = $this->getDb()->timestamp( $row->timestamp );
			return $row;
		}, $expectedRows );
		$actualRows = iterator_to_array( $objectUnderTest->reallyDoQuery( $offset, $limit, $order ) );
		// T372421: Ignore log_id, because the value may be influenced by other extensions.
		$actualRows = array_map( static function ( $row ) {
			unset( $row->log_id );
			return $row;
		}, $actualRows );
		$this->assertArrayEquals(
			$expectedRows,
			$actualRows,
			true,
			false,
			'::reallyDoQuery did not return the expected rows'
		);
	}

	public static function provideReallyDoQuery() {
		return [
			'Offset unset, limit 1, order ASC, InvestigateTestUser1 as target' => [
				// The $offset argument to ::reallyDoQuery
				null,
				// The $limit argument to ::reallyDoQuery
				1,
				// The $order argument to ::reallyDoQuery
				IndexPager::QUERY_ASCENDING,
				// The value of the $filteredTargets property
				[ 'InvestigateTestUser1' ],
				// The expected rows returned by ::reallyDoQuery
				[ (object)[
					'timestamp' => '20230405060708', 'namespace' => NS_MAIN, 'title' => 'CheckUserTestPage',
					'minor' => '0', 'page_id' => '1', 'type' => RC_NEW,
					'this_oldid' => '0', 'last_oldid' => '0', 'ip' => '1.2.3.4', 'xff' => '0',
					'agent' => 'foo user agent', 'id' => '1', 'user' => '1', 'user_text' => 'InvestigateTestUser1',
					'comment_text' => 'Foo comment', 'comment_data' => null, 'actor' => '1',
					'log_type' => null, 'log_action' => null, 'log_params' => null, 'log_deleted' => null,
				] ],
			],
			'Offset set, limit 1, order DESC, InvestigateTestUser1 as target' => [
				'20230405060710|1', 1, IndexPager::QUERY_DESCENDING, [ 'InvestigateTestUser1' ],
				[ (object)[
					'timestamp' => '20230405060708', 'namespace' => NS_MAIN, 'title' => 'CheckUserTestPage',
					'minor' => '0', 'page_id' => '1', 'type' => RC_NEW,
					'this_oldid' => '0', 'last_oldid' => '0', 'ip' => '1.2.3.4', 'xff' => '0',
					'agent' => 'foo user agent', 'id' => '1', 'user' => '1', 'user_text' => 'InvestigateTestUser1',
					'comment_text' => 'Foo comment', 'comment_data' => null, 'actor' => '1',
					'log_type' => null, 'log_action' => null, 'log_params' => null, 'log_deleted' => null,
				] ],
			],
			// Testing entries from cu_private_event, including the row where cupe_actor is null
			'Limit 2, order DESC, 1.2.3.4 as target' => [
				null, 2, IndexPager::QUERY_DESCENDING, [ '1.2.3.4' ], [
					(object)[
						'timestamp' => '20230405060721', 'namespace' => NS_USER, 'title' => 'InvestigateTestUser1',
						'minor' => null, 'page_id' => 0, 'type' => RC_LOG,
						'this_oldid' => null, 'last_oldid' => null, 'ip' => '1.2.3.4', 'xff' => '0',
						'agent' => 'foo user agent', 'id' => '2', 'user' => '1', 'user_text' => 'InvestigateTestUser1',
						'comment_text' => '', 'comment_data' => null, 'actor' => '1',
						'log_type' => 'bar', 'log_action' => 'foo', 'log_params' => '', 'log_deleted' => 0,
					],
					(object)[
						'timestamp' => '20230405060720', 'namespace' => NS_MAIN, 'title' => 'CheckUserTestPage',
						'minor' => null, 'page_id' => 1, 'type' => RC_LOG,
						'this_oldid' => null, 'last_oldid' => null, 'ip' => '1.2.3.4', 'xff' => '0',
						'agent' => 'foo user agent', 'id' => '1', 'user' => null, 'user_text' => null,
						'comment_text' => '', 'comment_data' => null, 'actor' => null,
						'log_type' => 'bar', 'log_action' => 'foo', 'log_params' => '', 'log_deleted' => 0,
					],
				]
			],
			// Testing limit where the number of rows is less than the specified limit
			'Limit 100, order DESC, InvestigateTestUser2 as target' => [
				null, 100, IndexPager::QUERY_DESCENDING, [ 'InvestigateTestUser2' ], [
					(object)[
						'timestamp' => '20230405060620', 'namespace' => NS_MAIN, 'title' => 'CheckUserTestPage',
						'minor' => null, 'page_id' => '1', 'type' => RC_LOG,
						'this_oldid' => null, 'last_oldid' => null, 'ip' => '1.2.3.4', 'xff' => '0',
						'agent' => 'foo user agent', 'id' => '3', 'user' => '2', 'user_text' => 'InvestigateTestUser2',
						'comment_text' => 'Barfoo comment', 'comment_data' => null, 'actor' => '2',
						'log_type' => 'bar', 'log_action' => 'foo', 'log_params' => '', 'log_deleted' => 0,
					],
				],
			],
			// Testing rows in cu_log_event and cu_changes
			'Offset set, Limit 2, order DESC, 1.2.3.5 as target' => [
				'20230405060719|10', 2, IndexPager::QUERY_DESCENDING, [ '1.2.3.5' ], [
					(object)[
						'timestamp' => '20230405060718', 'namespace' => NS_MAIN, 'title' => 'CheckUserTestPage',
						'minor' => null, 'page_id' => '1', 'type' => RC_LOG,
						'this_oldid' => null, 'last_oldid' => null, 'ip' => '1.2.3.5', 'xff' => '0',
						'agent' => 'bar user agent', 'id' => '2', 'user' => null, 'user_text' => '1.2.3.5',
						'comment_text' => 'Testing', 'comment_data' => null, 'actor' => '5',
						'log_type' => 'foo', 'log_action' => 'bar', 'log_params' => 'a:0:{}', 'log_deleted' => 0,
					],
					(object)[
						'timestamp' => '20230405060716', 'namespace' => NS_MAIN, 'title' => 'CheckUserTestPage',
						'minor' => '0', 'page_id' => '1', 'type' => RC_EDIT,
						'this_oldid' => '0', 'last_oldid' => '0', 'ip' => '1.2.3.5', 'xff' => '0',
						'agent' => 'foo user agent', 'id' => '5', 'user' => null, 'user_text' => '1.2.3.5',
						'comment_text' => 'Bar comment', 'comment_data' => null, 'actor' => '5',
						'log_type' => null, 'log_action' => null, 'log_params' => null, 'log_deleted' => null,
					],
				],
			],
			'No rows for IP and invalid user target' => [
				null, 10, IndexPager::QUERY_ASCENDING, [ '8.9.6.5', 'InvalidUser1' ], [],
			],
			'All targets filtered out' => [ null, 10, IndexPager::QUERY_ASCENDING, [], [] ],
		];
	}

	public function addDBDataOnce() {
		// Get some test users
		$testUser1 = ( new TestUser( 'InvestigateTestUser1' ) )->getUser();
		$testUser2 = ( new TestUser( 'InvestigateTestUser2' ) )->getUser();
		// Add some testing entries to the CheckUser result tables to test the Special:Investigate when results are
		// displayed. More specific tests for the results are written for the pager and services classes.
		$testPage = $this->getExistingTestPage( 'CheckUserTestPage' )->getTitle();
		// Clear the cu_changes and cu_log_event tables to avoid log entries created by the test users being created
		// or the page being created affecting the tests.
		$this->truncateTables( [ 'cu_changes', 'cu_log_event' ] );

		// Automatic temp user creation cannot be enabled
		// if actor IDs are being created for IPs.
		$this->disableAutoCreateTempUser();
		$actorStore = $this->getServiceContainer()->getActorStore();
		$commentStore = $this->getServiceContainer()->getCommentStore();

		$testActorData = [
			'InvestigateTestUser1' => [
				'actor_id'   => 0,
				'actor_user' => $testUser1->getId(),
			],
			'InvestigateTestUser2' => [
				'actor_id'   => 0,
				'actor_user' => $testUser2->getId(),
			],
			'1.2.3.4' => [
				'actor_id'   => 0,
				'actor_user' => 0,
			],
			'1.2.3.5' => [
				'actor_id'   => 0,
				'actor_user' => 0,
			],
		];

		foreach ( $testActorData as $name => $actor ) {
			$testActorData[$name]['actor_id'] = $actorStore->acquireActorId(
				new UserIdentityValue( $actor['actor_user'], $name ),
				$this->getDb()
			);
		}

		// Create several entries in the logging table, as queries performed by the TimelineService will JOIN to the
		// logging table when reading rows from cu_log_event.
		// Entry for the first log item.
		ConvertibleTimestamp::setFakeTime( '20230405060716' );
		$moveLogEntry = new ManualLogEntry( 'move', 'move' );
		$moveLogEntry->setPerformer( UserIdentityValue::newAnonymous( '1.2.3.4' ) );
		$moveLogEntry->setTarget( $testPage );
		$moveLogEntry->setComment( 'Testingabc' );
		$moveLogEntry->setParameters( [ '4::target' => 'Testing', '5::noredir' => '0' ] );
		$moveLogEntryId = $moveLogEntry->insert( $this->getDb() );
		// Entry for the second log item.
		ConvertibleTimestamp::setFakeTime( '20230405060718' );
		$secondLogEntry = new ManualLogEntry( 'foo', 'bar' );
		$secondLogEntry->setPerformer( UserIdentityValue::newAnonymous( '1.2.3.5' ) );
		$secondLogEntry->setTarget( $testPage );
		$secondLogEntry->setComment( 'Testing' );
		$secondLogEntryId = $secondLogEntry->insert();
		// Entry for the last log item.
		ConvertibleTimestamp::setFakeTime( '20230405060719' );
		$deleteLogEntry = new ManualLogEntry( 'delete', 'delete' );
		$deleteLogEntry->setPerformer( $testUser1 );
		$deleteLogEntry->setTarget( $testPage );
		$deleteLogEntry->setComment( 'Deleting page' );
		$deleteLogEntryId = $deleteLogEntry->insert( $this->getDb() );
		// Reset the fake time, as it we no longer need to set it for this method.
		ConvertibleTimestamp::setFakeTime( false );

		// Add testing data to cu_changes
		$testDataForCuChanges = [
			[
				'cuc_actor'      => $testActorData['InvestigateTestUser1']['actor_id'],
				'cuc_type'       => RC_NEW,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_agent'      => 'foo user agent',
				'cuc_timestamp'  => '20230405060708',
			], [
				'cuc_actor'      => $testActorData['InvestigateTestUser1']['actor_id'],
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_agent'      => 'bar user agent',
				'cuc_timestamp'  => '20230405060710',
				'cuc_minor'      => 1,
			], [
				'cuc_actor'      => $testActorData['InvestigateTestUser1']['actor_id'],
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_agent'      => 'bar user agent',
				'cuc_timestamp'  => '20230405060710',
			], [
				'cuc_actor'      => $testActorData['1.2.3.4']['actor_id'],
				'cuc_type'       => RC_NEW,
				'cuc_ip'         => '1.2.3.4',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cuc_agent'      => 'foo user agent',
				'cuc_timestamp'  => '20230405060711',
			], [
				'cuc_actor'      => $testActorData['1.2.3.5']['actor_id'],
				'cuc_type'       => RC_EDIT,
				'cuc_ip'         => '1.2.3.5',
				'cuc_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cuc_agent'      => 'foo user agent',
				'cuc_timestamp'  => '20230405060716',
				'cuc_comment_id' => $commentStore->createComment( $this->getDb(), 'Bar comment' )->id,
			],
		];

		$testDataForCuChanges = array_map( function ( $row ) use ( $testPage, $commentStore ) {
			$row['cuc_timestamp'] = $this->getDb()->timestamp( $row['cuc_timestamp'] );
			return array_merge( [
				'cuc_namespace'  => $testPage->getNamespace(),
				'cuc_title'      => $testPage->getText(),
				'cuc_minor'      => 0,
				'cuc_page_id'    => $testPage->getId(),
				'cuc_xff'        => 0,
				'cuc_xff_hex'    => null,
				'cuc_comment_id' => $commentStore->createComment( $this->getDb(), 'Foo comment' )->id,
				'cuc_this_oldid' => 0,
				'cuc_last_oldid' => 0,
				'cuc_type' => RC_EDIT,
			], $row );
		}, $testDataForCuChanges );

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_changes' )
			->rows( $testDataForCuChanges )
			->execute();

		// Add testing data to cu_log_event
		$testDataForCuLogEvent = [
			[
				'cule_actor'      => $testActorData['1.2.3.4']['actor_id'],
				'cule_ip'         => '1.2.3.4',
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cule_agent'      => 'foo user agent',
				'cule_timestamp'  => '20230405060716',
				'cule_log_id'     => $moveLogEntryId,
			], [
				'cule_actor'      => $testActorData['1.2.3.5']['actor_id'],
				'cule_ip'         => '1.2.3.5',
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.5' ),
				'cule_agent'      => 'bar user agent',
				'cule_timestamp'  => '20230405060718',
				'cule_log_id'     => $secondLogEntryId,
			], [
				'cule_actor'      => $testActorData['InvestigateTestUser1']['actor_id'],
				'cule_ip'         => '1.2.3.4',
				'cule_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cule_agent'      => 'foo user agent',
				'cule_timestamp'  => '20230405060719',
				'cule_log_id'     => $deleteLogEntryId,
			],
		];

		$testDataForCuLogEvent = array_map( function ( $row ) {
			$row['cule_timestamp'] = $this->getDb()->timestamp( $row['cule_timestamp'] );
			return array_merge( [
				'cule_xff'        => 0,
				'cule_xff_hex'    => null,
			], $row );
		}, $testDataForCuLogEvent );

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_log_event' )
			->rows( $testDataForCuLogEvent )
			->execute();

		// Add testing data to cu_private_event
		$testDataForCuPrivateEvent = [
			[
				// Test handling of cupe_actor as null, which can occur when temporary accounts are enabled.
				'cupe_actor'      => null,
				'cupe_ip'         => '1.2.3.4',
				'cupe_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cupe_agent'      => 'foo user agent',
				'cupe_timestamp'  => '20230405060720',
			], [
				'cupe_actor'      => $testActorData['InvestigateTestUser1']['actor_id'],
				'cupe_ip'         => '1.2.3.4',
				'cupe_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cupe_agent'      => 'foo user agent',
				'cupe_timestamp'  => '20230405060721',
				'cupe_namespace'  => NS_USER,
				'cupe_title'      => 'InvestigateTestUser1',
				'cupe_page'       => 0,
			], [
				'cupe_actor'      => $testActorData['InvestigateTestUser2']['actor_id'],
				'cupe_ip'         => '1.2.3.4',
				'cupe_ip_hex'     => IPUtils::toHex( '1.2.3.4' ),
				'cupe_agent'      => 'foo user agent',
				'cupe_timestamp'  => '20230405060620',
				'cupe_comment_id' => $commentStore->createComment( $this->getDb(), 'Barfoo comment' )->id,
			],
		];

		$testDataForCuPrivateEvent = array_map( function ( $row ) use ( $testPage, $commentStore ) {
			$row['cupe_timestamp'] = $this->getDb()->timestamp( $row['cupe_timestamp'] );
			return array_merge( [
				'cupe_namespace'  => $testPage->getNamespace(),
				'cupe_title'      => $testPage->getText(),
				'cupe_page'       => $testPage->getId(),
				'cupe_xff'        => 0,
				'cupe_xff_hex'    => null,
				'cupe_log_action' => 'foo',
				'cupe_log_type'   => 'bar',
				'cupe_params'     => '',
				'cupe_comment_id' => $commentStore->createComment( $this->getDb(), '' )->id,
			], $row );
		}, $testDataForCuPrivateEvent );

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_private_event' )
			->rows( $testDataForCuPrivateEvent )
			->execute();
	}
}
