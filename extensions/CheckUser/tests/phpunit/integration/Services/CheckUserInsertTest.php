<?php

namespace MediaWiki\CheckUser\Tests\Integration\Services;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Jobs\StoreClientHintsDataJob;
use MediaWiki\CheckUser\Services\CheckUserCentralIndexManager;
use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\RequestContext;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Exception\CannotCreateActorException;
use MediaWiki\Language\Language;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Logging\DatabaseLogEntry;
use MediaWiki\Logging\LogEntryBase;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\MainConfigNames;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Profiler;
use Psr\Log\LoggerInterface;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Services\CheckUserInsert
 */
class CheckUserInsertTest extends MediaWikiIntegrationTestCase {

	use CheckUserCommonTraitTest;
	use CheckUserTempUserTestTrait;

	private function setUpObject(): CheckUserInsert {
		return $this->getServiceContainer()->get( 'CheckUserInsert' );
	}

	private function installMockCheckUserIndexManagerThatExpectsCall(
		$expectedUserIdentity, $expectedTimestamp, $expectedHasRevisionId
	) {
		// Check that a call to CheckUserCentralIndexManager::recordActionInCentralIndexes is made
		$mockCheckUserCentralIndexManager = $this->createMock( CheckUserCentralIndexManager::class );
		$mockCheckUserCentralIndexManager->expects( $this->once() )
			->method( 'recordActionInCentralIndexes' )
			->willReturnCallback( function ( $performer, $ip, $domainID, $timestamp, $hasRevisionId ) use (
				$expectedUserIdentity, $expectedTimestamp, $expectedHasRevisionId
			) {
				// Check that the parameters are as expected for the call to this method
				$this->assertTrue( $expectedUserIdentity->equals( $performer ) );
				$this->assertSame( $this->getDb()->getDomainID(), $domainID );
				$this->assertSame( $expectedTimestamp, $timestamp );
				$this->assertSame( $expectedHasRevisionId, $hasRevisionId );
			} );
		$this->setService( 'CheckUserCentralIndexManager', $mockCheckUserCentralIndexManager );
	}

	/** @dataProvider provideInsertIntoCuChangesTable */
	public function testInsertIntoCuChangesTable(
		array $row, array $fields, array $expectedRow, $checkUserInsert = null
	) {
		ConvertibleTimestamp::setFakeTime( '20240506070809' );
		$performer = $this->getTestUser()->getUserIdentity();
		// Only mock the service if we don't already have an instance of CheckUserInsert. Mocking at this stage
		// will not do anything for the test if an instance already exists.
		if ( $checkUserInsert === null ) {
			$expectedHasRevisionId = ( $row['cuc_this_oldid'] ?? 0 ) !== 0;
			$this->installMockCheckUserIndexManagerThatExpectsCall(
				$performer, $row['cuc_timestamp'] ?? '20240506070809', $expectedHasRevisionId
			);
		}
		$checkUserInsert ??= $this->setUpObject();
		$checkUserInsert->insertIntoCuChangesTable( $row, __METHOD__, $performer );
		$expectedRow = $this->convertTimestampInExpectedRowToDbFormat( $fields, $expectedRow );
		$this->newSelectQueryBuilder()
			->select( $fields )
			->from( 'cu_changes' )
			->assertRowValue( $expectedRow );
	}

	public static function provideInsertIntoCuChangesTable() {
		return [
			'Default values on empty row' => [
				[],
				[
					'cuc_ip', 'cuc_ip_hex', 'cuc_xff', 'cuc_xff_hex', 'cuc_page_id',
					'cuc_namespace', 'cuc_minor', 'cuc_title',
					'cuc_this_oldid', 'cuc_last_oldid', 'cuc_type', 'cuc_agent',
					'cuc_timestamp'
				],
				[ '127.0.0.1', '7F000001', '', null, 0, NS_MAIN, 0, '', 0, 0, RC_LOG, '', '20240506070809' ]
			],
		];
	}

	/** @dataProvider provideInsertIntoCuPrivateEventTable */
	public function testInsertIntoCuPrivateEventTable(
		array $row, array $fields, array $expectedRow, $checkUserInsert = null
	) {
		ConvertibleTimestamp::setFakeTime( '20240506070809' );
		$performer = $this->getTestUser()->getUserIdentity();
		// Only mock the service if we don't already have an instance of CheckUserInsert. Mocking at this stage
		// will not do anything for the test if an instance already exists.
		if ( $checkUserInsert === null ) {
			$this->installMockCheckUserIndexManagerThatExpectsCall(
				$performer, $row['cupe_timestamp'] ?? '20240506070809', false
			);
		}
		$checkUserInsert ??= $this->setUpObject();
		$returnedId = $checkUserInsert->insertIntoCuPrivateEventTable(
			$row, __METHOD__, $performer
		);
		$expectedRow = $this->convertTimestampInExpectedRowToDbFormat( $fields, $expectedRow );
		// Check that the ID is the ID that was returned by the method under test.
		$fields[] = 'cupe_id';
		$expectedRow[] = $returnedId;
		$this->newSelectQueryBuilder()
			->select( $fields )
			->from( 'cu_private_event' )
			->assertRowValue( $expectedRow );
	}

	public static function provideInsertIntoCuPrivateEventTable() {
		return [
			'Default values on empty row' => [
				[],
				[
					'cupe_ip', 'cupe_ip_hex', 'cupe_xff', 'cupe_xff_hex', 'cupe_page',
					'cupe_namespace', 'cupe_log_type', 'cupe_log_action',
					'cupe_title', 'cupe_params', 'cupe_agent', 'cupe_timestamp'
				],
				[
					'127.0.0.1', '7F000001', '', null, 0, NS_MAIN, 'checkuser-private-event',
					'', '', LogEntryBase::makeParamBlob( [] ), '', '20240506070809'
				]
			]
		];
	}

	/** @dataProvider provideInsertIntoCuLogEventTable */
	public function testInsertIntoCuLogEventTable( array $fields, array $expectedRow, $checkUserInsert = null ) {
		ConvertibleTimestamp::setFakeTime( '20240506070809' );
		$logId = $this->newLogEntry();
		// Delete any entries that were created by ::newLogEntry.
		$this->truncateTables( [
			'cu_log_event',
		] );
		$logEntry = DatabaseLogEntry::newFromId( $logId, $this->getDb() );
		// Only mock the service if we don't already have an instance of CheckUserInsert. Mocking at this stage
		// will not do anything for the test if an instance already exists.
		if ( $checkUserInsert === null ) {
			$this->installMockCheckUserIndexManagerThatExpectsCall(
				$logEntry->getPerformerIdentity(), $logEntry->getTimestamp(), false
			);
		}

		$checkUserInsert ??= $this->setUpObject();
		$checkUserInsert->insertIntoCuLogEventTable(
			$logEntry, __METHOD__, $this->getTestUser()->getUserIdentity()
		);
		$expectedRow = $this->convertTimestampInExpectedRowToDbFormat( $fields, $expectedRow );
		$this->newSelectQueryBuilder()
			->select( $fields )
			->from( 'cu_log_event' )
			->assertRowValue( $expectedRow );
	}

	public static function provideInsertIntoCuLogEventTable() {
		return [
			'Default values' => [
				[ 'cule_ip', 'cule_ip_hex', 'cule_xff', 'cule_xff_hex', 'cule_agent', 'cule_timestamp' ],
				[ '127.0.0.1', '7F000001', '', null, '', '20240506070809' ],
			],
		];
	}

	/**
	 * @dataProvider provideCheckUserResultTables
	 */
	public function testCentralIndexesShouldNotBeUpdatedOnRollback( string $tableName ): void {
		$this->setService(
			'CheckUserCentralIndexManager',
			$this->createNoOpMock( CheckUserCentralIndexManager::class )
		);

		$checkUserInsert = $this->setUpObject();
		$performer = $this->getTestUser()->getUserIdentity();
		$dbw = $this->getDb();

		$dbw->startAtomic( __METHOD__, $dbw::ATOMIC_CANCELABLE );

		if ( $tableName === 'cu_changes' ) {
			$checkUserInsert->insertIntoCuChangesTable( [], __METHOD__, $performer );
		} elseif ( $tableName === 'cu_private_event' ) {
			$checkUserInsert->insertIntoCuPrivateEventTable( [], __METHOD__, $performer );
		} elseif ( $tableName === 'cu_log_event' ) {
			$logId = $this->newLogEntry();
			$logEntry = DatabaseLogEntry::newFromId( $logId, $dbw );
			$checkUserInsert->insertIntoCuLogEventTable( $logEntry, __METHOD__, $performer );
		} else {
			$this->fail( 'Unexpected table.' );
		}

		// Roll back the current transaction, which should cancel the deferred update
		// that would update central indexes.
		$dbw->cancelAtomic( __METHOD__ );

		// Run deferred updates to verify we didn't try to update central indexes.
		// This needs to be done explicitly since the update was scheduled in a transaction
		// which disables opportunistic execution.
		DeferredUpdates::doUpdates();

		$pkField = CheckUserQueryInterface::RESULT_TABLE_TO_PREFIX[$tableName] . 'id';

		$this->newSelectQueryBuilder()
			->select( $pkField )
			->from( $tableName )
			->caller( __METHOD__ )
			->assertEmptyResult();
	}

	/** @dataProvider provideFieldsThatAreTruncated */
	public function testTruncationForInsertMethods( $table, string $field ) {
		// Define a mock ContentLanguage service that mocks ::truncateForDatabase
		// so that if the method changes implementation this test will not fail and/or
		// the wiki running the test isn't in English.
		$mockContentLanguage = $this->createMock( Language::class );
		$mockContentLanguage->method( 'truncateForDatabase' )
			->willReturnCallback(
				static function ( $text, $length ) {
					return substr( $text, 0, $length - 3 ) . '...';
				}
			);
		$objectUnderTest = new CheckUserInsert(
			new ServiceOptions(
				CheckUserInsert::CONSTRUCTOR_OPTIONS,
				$this->getServiceContainer()->getMainConfig()
			),
			$this->getServiceContainer()->getActorStore(),
			$this->getServiceContainer()->get( 'CheckUserUtilityService' ),
			$this->getServiceContainer()->getCommentStore(),
			$this->getServiceContainer()->getHookContainer(),
			$this->getServiceContainer()->getConnectionProvider(),
			$mockContentLanguage,
			$this->getServiceContainer()->getTempUserConfig(),
			$this->getServiceContainer()->get( 'CheckUserCentralIndexManager' ),
			$this->getServiceContainer()->get( 'UserAgentClientHintsManager' ),
			$this->getServiceContainer()->getJobQueueGroup(),
			LoggerFactory::getInstance( 'CheckUser' )
		);
		if ( $table === 'cu_changes' ) {
			$this->testInsertIntoCuChangesTable(
				[ $field => str_repeat( 'q', CheckUserInsert::TEXT_FIELD_LENGTH + 9 ) ],
				[ $field ],
				[ str_repeat( 'q', CheckUserInsert::TEXT_FIELD_LENGTH - 3 ) . '...' ],
				$objectUnderTest
			);
		} elseif ( $table === 'cu_private_event' ) {
			$this->testInsertIntoCuPrivateEventTable(
				[ $field => str_repeat( 'q', CheckUserInsert::TEXT_FIELD_LENGTH + 9 ) ],
				[ $field ],
				[ str_repeat( 'q', CheckUserInsert::TEXT_FIELD_LENGTH - 3 ) . '...' ],
				$objectUnderTest
			);
		} elseif ( $table === 'cu_log_event' ) {
			$this->setTemporaryHook(
				'CheckUserInsertLogEventRow',
				static function ( &$ip, &$xff, &$row ) use ( $field ) {
					$row[$field] = str_repeat( 'q', CheckUserInsert::TEXT_FIELD_LENGTH + 9 );
				}
			);
			$this->testInsertIntoCuLogEventTable(
				[ $field ],
				[ str_repeat( 'q', CheckUserInsert::TEXT_FIELD_LENGTH - 3 ) . '...' ],
				$objectUnderTest
			);
		}
	}

	public static function provideFieldsThatAreTruncated() {
		return [
			'cu_changes XFF column' => [ 'cu_changes', 'cuc_xff' ],
			'cu_private_event XFF column' => [ 'cu_private_event', 'cupe_xff' ],
			'cu_log_event XFF column' => [ 'cu_log_event', 'cule_xff' ],
		];
	}

	/**
	 * @covers \MediaWiki\CheckUser\Hook\HookRunner::onCheckUserInsertChangesRow
	 * @covers \MediaWiki\CheckUser\Hook\HookRunner::onCheckUserInsertPrivateEventRow
	 * @covers \MediaWiki\CheckUser\Hook\HookRunner::onCheckUserInsertLogEventRow
	 * @dataProvider provideInsertMethodsHookModification
	 */
	public function testInsertMethodsHookModification( string $test_xff, string $xff_hex, $table ) {
		// Get the column prefix, hook name and common test method name for the given table.
		$prefix = CheckUserQueryInterface::RESULT_TABLE_TO_PREFIX[$table];
		if ( $table === 'cu_changes' ) {
			$hook = 'CheckUserInsertChangesRow';
		} elseif ( $table === 'cu_private_event' ) {
			$hook = 'CheckUserInsertPrivateEventRow';
		} elseif ( $table === 'cu_log_event' ) {
			$hook = 'CheckUserInsertLogEventRow';
		} else {
			$this->fail( 'Unexpected table.' );
		}
		// Set a temporary hook to modify the XFF, IP and user agent fields.
		$this->setTemporaryHook(
			$hook,
			static function ( &$ip, &$xff, &$row ) use ( $test_xff, $prefix ) {
				$xff = $test_xff;
				$ip = '1.2.3.4';
				$row[$prefix . 'agent'] = 'TestAgent';
			}
		);
		// Call the common test method.
		$fields = [ $prefix . 'xff', $prefix . 'xff_hex', $prefix . 'ip', $prefix . 'ip_hex', $prefix . 'agent' ];
		$expectedValues = [ $test_xff, $xff_hex, '1.2.3.4', '01020304', 'TestAgent' ];
		if ( $table === 'cu_changes' ) {
			$this->testInsertIntoCuChangesTable( [], $fields, $expectedValues );
		} elseif ( $table === 'cu_private_event' ) {
			$this->testInsertIntoCuPrivateEventTable( [], $fields, $expectedValues );
		} elseif ( $table === 'cu_log_event' ) {
			$this->testInsertIntoCuLogEventTable( $fields, $expectedValues );
		}
	}

	public static function provideInsertMethodsHookModification() {
		foreach ( [ 'cu_changes', 'cu_log_event', 'cu_private_event' ] as $table ) {
			yield from [
				"Empty XFF for $table" => [ '', '', $table ],
				"XFF not empty for $table" => [ '1.2.3.4, 5.6.7.8', '01020304', $table ],
				"Invalid XFF for $table" => [ 'Invalid XFF', '', $table ],
			];
		}
	}

	/** @dataProvider provideCheckUserResultTables */
	public function testActorColumnInInsertMethods( $table, $user = null, $expectedActorId = 0 ) {
		$user ??= $this->getTestUser()->getUserIdentity();
		// 0 is used to indicate that no specific actor ID was provided for the test, and so this
		// method should acquire one. 0 is used as it is not a valid actor ID (while null can be the value
		// of cupe_actor in specific circumstances).
		if ( $expectedActorId === 0 ) {
			$expectedActorId = $this->getServiceContainer()->getActorStore()
				->acquireActorId( $user, $this->getDb() );
		}
		if ( $table === 'cu_changes' ) {
			$this->setUpObject()->insertIntoCuChangesTable( [], __METHOD__, $user );
		} elseif ( $table === 'cu_private_event' ) {
			$this->setUpObject()->insertIntoCuPrivateEventTable( [], __METHOD__, $user );
		} elseif ( $table === 'cu_log_event' ) {
			$logId = $this->newLogEntry();
			// Delete any entries that were created by ::newLogEntry.
			$this->truncateTables( [
				'cu_log_event',
			] );
			$logEntry = DatabaseLogEntry::newFromId( $logId, $this->getDb() );
			$this->setUpObject()->insertIntoCuLogEventTable( $logEntry, __METHOD__, $user );
		} else {
			$this->fail( 'Unexpected table.' );
		}
		$this->newSelectQueryBuilder()
			->select( CheckUserQueryInterface::RESULT_TABLE_TO_PREFIX[$table] . 'actor' )
			->from( $table )
			->assertFieldValue( $expectedActorId );
	}

	public static function provideCheckUserResultTables() {
		yield 'cu_changes table' => [ 'cu_changes' ];
		yield 'cu_private_event table' => [ 'cu_private_event' ];
		yield 'cu_log_event table' => [ 'cu_log_event' ];
	}

	/** @dataProvider provideCheckUserResultTablesWhichHaveANotNullActorColumn */
	public function testActorColumnForIPAddressWithExistingActorId( $table ) {
		// Tests that if an IP address already has an actor ID when temporary accounts are enabled the actor ID is
		// used instead of using NULL or throwing an exception.
		$ip = UserIdentityValue::newAnonymous( '1.2.3.4' );
		// Acquire the pre-existing actor ID for the IP address.
		$this->disableAutoCreateTempUser();
		$this->getServiceContainer()->getActorStore()->acquireActorId( $ip, $this->getDb() );
		// Enable temporary accounts and then perform the insert.
		$this->enableAutoCreateTempUser();
		$this->testActorColumnInInsertMethods(
			$table,
			$ip,
			$this->getServiceContainer()->getActorStore()->findActorId( $ip, $this->getDb() )
		);
	}

	public static function provideCheckUserResultTablesWhichHaveANotNullActorColumn() {
		return [
			'cu_changes' => [ 'cu_changes' ],
			'cu_log_event' => [ 'cu_log_event' ],
		];
	}

	public function testActorColumnForIPAddressForCuPrivateEventInsert() {
		// Tests that the value of cupe_actor is NULL when temporary accounts are enabled
		// and the performer is an IP address.
		$ip = UserIdentityValue::newAnonymous( '1.2.3.4' );
		// Enable temporary accounts and then perform the insert.
		$this->enableAutoCreateTempUser();
		$this->testActorColumnInInsertMethods( 'cu_private_event', $ip, null );
	}

	/** @dataProvider provideCheckUserResultTablesWhichHaveANotNullActorColumn */
	public function testActorColumnForIPAddressWithoutExistingActorId( $table ) {
		// Tests that if an IP address doesn't have an actor ID and we are not inserting to cu_private_event,
		// then we get a CannotCreateActorException.
		$this->expectException( CannotCreateActorException::class );
		$ip = UserIdentityValue::newAnonymous( '1.2.3.4' );
		// Enable temporary accounts and then perform the insert.
		$this->enableAutoCreateTempUser();
		$this->testActorColumnInInsertMethods( $table, $ip );
	}

	public function testInsertIntoCuLogEventTableLogId() {
		$logId = $this->newLogEntry();
		// Delete any entries that were created by ::newLogEntry.
		$this->truncateTables( [
			'cu_log_event',
		] );
		$logEntry = DatabaseLogEntry::newFromId( $logId, $this->getDb() );

		$this->setUpObject()->insertIntoCuLogEventTable(
			$logEntry, __METHOD__, $this->getTestUser()->getUserIdentity()
		);
		$this->newSelectQueryBuilder()
			->select( 'cule_log_id' )
			->from( 'cu_log_event' )
			->assertFieldValue( $logId );
	}

	private function updateCheckUserData( array $rcAttribs, string $table, array $fields, array &$expectedRow ): void {
		$this->commonTestsUpdateCheckUserData( $rcAttribs, $fields, $expectedRow );
		$this->newSelectQueryBuilder()
			->select( $fields )
			->from( $table )
			->assertRowValue( $expectedRow );
	}

	/** @dataProvider provideUpdateCheckUserDataNoSave */
	public function testUpdateCheckUserDataNoSave( array $rcAttribs ) {
		$expectedRow = [];
		$this->commonTestsUpdateCheckUserData( $rcAttribs, [], $expectedRow );
		$this->assertRowCount( 0, 'cu_changes', 'cuc_id',
			'A row was inserted to cu_changes when it should not have been.' );
		$this->assertRowCount( 0, 'cu_private_event', 'cupe_id',
			'A row was inserted to cu_private_event when it should not have been.' );
		$this->assertRowCount( 0, 'cu_log_event', 'cule_id',
			'A row was inserted to cu_log_event when it should not have been.' );
	}

	public function testProvideUpdateCheckUserData() {
		// From RecentChangeTest.php's provideAttribs but modified
		$attribs = self::getDefaultRecentChangeAttribs();
		$testUser = new UserIdentityValue( 1337, 'YeaaaahTests' );
		$actorId = $this->getServiceContainer()->getActorStore()->acquireActorId(
			$testUser,
			$this->getDb()
		);
		$testCases = [
			'registered user' => [
				array_merge( $attribs, [
					'rc_type' => RC_EDIT,
					'rc_user' => $testUser->getId(),
					'rc_user_text' => $testUser->getName(),
				] ),
				'cu_changes',
				[ 'cuc_actor', 'cuc_type' ],
				[ $actorId, RC_EDIT ]
			],
			'Log for special title with no log ID' => [
				array_merge( $attribs, [
					'rc_namespace' => NS_SPECIAL,
					'rc_title' => 'Log',
					'rc_type' => RC_LOG,
					'rc_log_type' => ''
				] ),
				'cu_private_event',
				[ 'cupe_title', 'cupe_timestamp', 'cupe_namespace' ],
				[ 'Log', $this->getDb()->timestamp( $attribs['rc_timestamp'] ), NS_SPECIAL ]
			],
			'Log with no log ID and comment ID defined' => [
				array_merge( $attribs, [
					'rc_namespace' => NS_SPECIAL,
					'rc_title' => 'Log',
					'rc_type' => RC_LOG,
					'rc_log_type' => '',
					'rc_comment_id' => $this->getServiceContainer()->getCommentStore()
						->createComment( $this->getDb(), 'test' )->id,
				] ),
				'cu_private_event',
				[ 'cupe_comment_id' ],
				[ $this->getServiceContainer()->getCommentStore()->createComment( $this->getDb(), 'test' )->id ]
			],
		];
		foreach ( $testCases as $values ) {
			$this->updateCheckUserData(
				$values[0],
				$values[1],
				$values[2],
				$values[3]
			);
			$this->truncateTables( [
				'cu_changes',
				'cu_private_event',
				'cu_log_event',
				'recentchanges'
			] );
		}
	}

	/** @dataProvider provideUpdateCheckUserDataLogEvent */
	public function testUpdateCheckUserDataLogEvent(
		array $rcAttribs, string $table, array $fields, array $expectedRow
	) {
		ConvertibleTimestamp::setFakeTime( $rcAttribs['rc_timestamp'] );
		$logId = $this->newLogEntry();
		// Delete any entries that were created by ::newLogEntry.
		$this->truncateTables( [ 'cu_log_event' ] );
		$rcAttribs['rc_logid'] = $logId;
		$fields[] = 'cule_log_id';
		$expectedRow[] = $logId;
		// Pass the expected timestamp through IReadableTimestamp::timestamp to ensure it is in the right format
		// for the current DB type (T366590).
		if ( array_key_exists( 'cule_timestamp', $fields ) ) {
			$keyForTimestamp = array_search( 'cule_timestamp', $fields );
			$expectedRow[$keyForTimestamp] = $this->getDb()->timestamp( $expectedRow[$keyForTimestamp] );
		}
		$this->updateCheckUserData( $rcAttribs, $table, $fields, $expectedRow );
	}

	/**
	 * @dataProvider provideLogEntriesForClientHintsSavedWithAccountCreationLogEvent
	 */
	public function testClientHintsSavedWithAccountCreationLogEvent(
		ManualLogEntry $logEntry, bool $requestWasPosted, bool $expectedToHaveResults
	) {
		// Simulate the TransactionProfiler expectations for either a GET or POST request
		$trxLimits = $this->getServiceContainer()->getMainConfig()->get( MainConfigNames::TrxProfilerLimits );
		$trxProfiler = Profiler::instance()->getTransactionProfiler();
		if ( $requestWasPosted ) {
			$trxProfiler->redefineExpectations( $trxLimits['POST'], __METHOD__ );
		} else {
			$trxProfiler->redefineExpectations( $trxLimits['GET'], __METHOD__ );
		}
		RequestContext::getMain()->getRequest()->setHeader( 'Sec-Ch-Ua', ';v=abc' );
		$logEntry->setPerformer( $this->getTestUser()->getUserIdentity() );
		$logEntry->setTarget( $this->getTestUser()->getUser()->getUserPage() );
		$logId = $logEntry->insert();
		$rcAttribs = $logEntry->getRecentChange( $logId )->getAttributes();
		$expectedRow = [ $logId ];
		$this->updateCheckUserData( $rcAttribs, 'cu_log_event', [ 'cule_log_id' ], $expectedRow );
		$this->runJobs( [ 'minJobs' => 0 ], [ 'type' => StoreClientHintsDataJob::TYPE ] );
		$selectQuery = $this->newSelectQueryBuilder()
			->select( 'uachm_reference_id' )
			->from( 'cu_useragent_clienthints_map' )
			->where( [
				'uachm_reference_type' => 1,
				'uachm_reference_id' => $logId,
			] )
			->caller( __METHOD__ );
		if ( $expectedToHaveResults ) {
			$selectQuery->assertFieldValue( $logId );
		} else {
			$selectQuery->assertEmptyResult();
		}
	}

	public static function provideLogEntriesForClientHintsSavedWithAccountCreationLogEvent(): array {
		return [
			'account creation as anon' => [
				new ManualLogEntry( 'newusers', 'create' ), true, true,
			],
			'account creation via existing account' => [
				new ManualLogEntry( 'newusers', 'create2' ), true, true,
			],
			'account creation via existing account, send credentials by email' => [
				new ManualLogEntry( 'newusers', 'byemail' ), true, true
			],
			'account autocreation' => [
				new ManualLogEntry( 'newusers', 'autocreate' ), true, true,
			],
			'account autocreation on GET request' => [
				new ManualLogEntry( 'newusers', 'autocreate' ), false, true,
			],
		];
	}

	public function testUpdateCheckUserDataWhenLogEntryIsMissingT343983() {
		$expectsWarningLogger = $this->getMockBuilder( LoggerInterface::class )->getMock();
		$expectsWarningLogger->expects( $this->once() )
			->method( 'warning' )
			->willReturnCallback( function ( $message, $context ) {
				$this->assertSame( -1, $context['rc_logid'] );
				$this->assertArrayHasKey( 'exception', $context );
			} );
		$this->setLogger( 'CheckUser', $expectsWarningLogger );

		$attribs = array_merge(
			self::getDefaultRecentChangeAttribs(),
			[
				'rc_namespace' => NS_SPECIAL,
				'rc_title' => 'Log',
				'rc_type' => RC_LOG,
				'rc_log_type' => '',
			]
		);
		$table = 'cu_private_event';
		$fields = [ 'cupe_timestamp' ];
		$expectedRow = [ $this->getDb()->timestamp( $attribs['rc_timestamp'] ) ];
		ConvertibleTimestamp::setFakeTime( $attribs['rc_timestamp'] );
		$attribs['rc_logid'] = -1;
		$this->updateCheckUserData( $attribs, $table, $fields, $expectedRow );
	}

	public static function provideUpdateCheckUserDataLogEvent() {
		// From RecentChangeTest.php's provideAttribs but modified
		$attribs = self::getDefaultRecentChangeAttribs();
		yield 'Log with log ID' => [
			array_merge( $attribs, [
				'rc_namespace' => NS_SPECIAL,
				'rc_title' => 'Log',
				'rc_type' => RC_LOG,
				'rc_log_type' => ''
			] ),
			'cu_log_event',
			[ 'cule_timestamp' ],
			[ $attribs['rc_timestamp'] ],
		];
	}

	public static function provideUpdateCheckUserDataNoSave() {
		// From RecentChangeTest.php's provideAttribs but modified
		$attribs = self::getDefaultRecentChangeAttribs();
		return [
			'external user' => [
				array_merge( $attribs, [
					'rc_type' => RC_EXTERNAL,
					'rc_user' => 0,
					'rc_user_text' => 'm>External User',
				] ),
				[ 'cuc_ip' ],
				[],
			],
			'categorize' => [
				array_merge( $attribs, [
					'rc_namespace' => NS_MAIN,
					'rc_title' => '',
					'rc_type' => RC_CATEGORIZE,
				] ),
				[ 'cuc_ip' ],
				[],
			],
		];
	}
}
