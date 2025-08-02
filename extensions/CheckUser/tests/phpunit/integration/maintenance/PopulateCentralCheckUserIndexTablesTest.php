<?php

namespace MediaWiki\CheckUser\Tests\Integration\Maintenance;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Maintenance\PopulateCentralCheckUserIndexTables;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Tests\Maintenance\MaintenanceBaseTestCase;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use Wikimedia\IPUtils;

/**
 * @covers \MediaWiki\CheckUser\Maintenance\PopulateCentralCheckUserIndexTables
 * @group CheckUser
 * @group Database
 */
class PopulateCentralCheckUserIndexTablesTest extends MaintenanceBaseTestCase implements CheckUserQueryInterface {

	use CheckUserTempUserTestTrait;
	use CheckUserCommonTraitTest;

	protected function setUp(): void {
		parent::setUp();
		// We don't want to test specifically the CentralAuth implementation of the CentralIdLookup. As such, force it
		// to be the local provider.
		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
	}

	/** @inheritDoc */
	protected function getMaintenanceClass() {
		return PopulateCentralCheckUserIndexTables::class;
	}

	public function testNoPopulationOnEmptyLocalCheckUserTables() {
		$this->assertTrue( $this->maintenance->execute() );
		$expectedOutputRegex = '/';
		foreach ( CheckUserQueryInterface::RESULT_TABLES as $table ) {
			$expectedOutputRegex .= "Skipping importing data from $table to central index " .
				"tables as the table is empty\n";
		}
		$this->expectOutputRegex( $expectedOutputRegex . '/' );
		$this->assertRowCount( 0, 'cuci_temp_edit', '*', 'cuci_temp_edit should be empty' );
		$this->assertRowCount( 0, 'cuci_wiki_map', '*', 'cuci_wiki_map should be empty' );
		$this->assertRowCount( 0, 'cuci_user', '*', 'cuci_user should be empty' );
	}

	private function getTestingRowForTable(
		string $table, UserIdentity $performer, string $ip, string $timestamp, int $oldId = 0
	): array {
		if ( $table !== self::CHANGES_TABLE && $oldId !== 0 ) {
			$this->fail( 'No table other than cu_changes can contain an revision ID' );
		}

		$columnAlias = self::RESULT_TABLE_TO_PREFIX[$table];
		$actorStore = $this->getServiceContainer()->getActorStore();

		$row = [
			$columnAlias . 'actor' => $actorStore->acquireActorId( $performer, $this->getDb() ),
			$columnAlias . 'timestamp' => $this->getDb()->timestamp( $timestamp ),
			$columnAlias . 'ip' => IPUtils::sanitizeIP( $ip ),
			$columnAlias . 'ip_hex' => IPUtils::toHex( $ip ),
		];

		if ( $oldId !== 0 ) {
			$row['cuc_this_oldid'] = $oldId;
		}

		return $row;
	}

	private function populateLocalCheckUserResultTablesForTest(): array {
		// Get two test users, a test temporary account, a test user with the bot group, and an anon user to use for
		// testing.
		$testUser1 = $this->getMutableTestUser()->getUserIdentity();
		$testUser2 = $this->getMutableTestUser()->getUserIdentity();
		$this->enableAutoCreateTempUser();
		$temporaryUser = $this->getServiceContainer()->getTempUserCreator()
			->create( null, new FauxRequest() )->getUser();
		$this->disableAutoCreateTempUser();
		$botUser = $this->getTestUser( [ 'bot' ] )->getUserIdentity();
		$anonUser = UserIdentityValue::newAnonymous( '4.3.2.1' );
		// Run jobs and then truncate all CheckUser result tables as CheckUserInsert will have created rows for the
		// creation of the temporary account.
		$this->runJobs();
		$this->truncateTables( [ 'cuci_user', 'cu_log_event', 'cu_private_event', 'cu_changes' ] );

		// Add test data for cu_changes
		$testData = [
			// Add rows which should be imported to only the cuci_user central index
			$this->getTestingRowForTable( self::CHANGES_TABLE, $testUser1, '1.2.3.1', '20200101000000', 1 ),
			$this->getTestingRowForTable( self::CHANGES_TABLE, $testUser1, '1.2.3.2', '20200101000001', 10 ),
			$this->getTestingRowForTable( self::CHANGES_TABLE, $testUser1, '1.2.3.3', '20200101000002', 100 ),
			$this->getTestingRowForTable( self::CHANGES_TABLE, $testUser1, '1.2.3.3', '20200101001000', 101 ),
			$this->getTestingRowForTable( self::CHANGES_TABLE, $testUser2, '1.2.3.3', '20200101000002', 103 ),
			$this->getTestingRowForTable( self::CHANGES_TABLE, $testUser2, '1.2.3.3', '20200101001000' ),
			$this->getTestingRowForTable(
				self::CHANGES_TABLE, $testUser2, '2001:0db8:85a3:0000:0000:8a2e:0370:7334', '20200101001000'
			),
			$this->getTestingRowForTable( self::CHANGES_TABLE, $temporaryUser, '1.2.3.5', '20200101001100' ),
			// Add rows which should be imported to both central indexes
			$this->getTestingRowForTable( self::CHANGES_TABLE, $temporaryUser, '1.2.3.4', '20200101001100', 123 ),
			$this->getTestingRowForTable( self::CHANGES_TABLE, $temporaryUser, '1.2.3.5', '20200101001200', 124 ),
			$this->getTestingRowForTable(
				self::CHANGES_TABLE, $temporaryUser, '2001:0db8:85a3:0000:0000:8a2e:0370:7334', '20200101001300', 187
			),
			// Add rows which should not be imported to the central index (because they are anon users or bots)
			$this->getTestingRowForTable( self::CHANGES_TABLE, $botUser, '2.3.4.5', '20240506070809', 106 ),
			$this->getTestingRowForTable( self::CHANGES_TABLE, $anonUser, $anonUser->getName(), '20240506070810', 107 ),
		];

		$rows = array_map( static function ( $row ) {
			return array_merge( [
				'cuc_type'       => RC_EDIT,
				'cuc_agent'      => 'foo user agent',
				'cuc_namespace'  => NS_MAIN,
				'cuc_title'      => 'Foo_Page',
				'cuc_minor'      => 0,
				'cuc_page_id'    => 1,
				'cuc_xff'        => 0,
				'cuc_xff_hex'    => null,
				'cuc_comment_id' => 0,
				'cuc_last_oldid' => 0,
				'cuc_this_oldid' => 0,
			], $row );
		}, $testData );

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_changes' )
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();

		// Add test data for cu_log_event
		$testData = [
			// Add rows which should be imported to only the cuci_user central index
			$this->getTestingRowForTable( self::LOG_EVENT_TABLE, $testUser1, '1.2.3.4', '20200104000000' ),
			$this->getTestingRowForTable( self::LOG_EVENT_TABLE, $testUser1, '1.2.3.5', '20220101000000' ),
			$this->getTestingRowForTable( self::LOG_EVENT_TABLE, $testUser2, '1.2.3.6', '20220109000000' ),
			$this->getTestingRowForTable(
				self::LOG_EVENT_TABLE, $testUser2, '2001:0db8:85a3:0000:0000:8a2e:0370:7334', '20200101001000'
			),
			$this->getTestingRowForTable( self::LOG_EVENT_TABLE, $temporaryUser, '1.2.3.7', '20240109000000' ),
			// Add rows which should not be imported to the central index (because they are anon users or bots)
			$this->getTestingRowForTable( self::LOG_EVENT_TABLE, $botUser, '2.3.4.5', '20240506070809' ),
			$this->getTestingRowForTable( self::LOG_EVENT_TABLE, $anonUser, $anonUser->getName(), '20240506070810' ),
		];

		$rows = array_map( static function ( $row ) {
			return array_merge( [
				'cule_xff'     => 0,
				'cule_xff_hex' => null,
				'cule_agent'   => 'foo user agent',
			], $row );
		}, $testData );

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_log_event' )
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();

		// Add test data for cu_private_event
		$testData = [
			// Add rows which should be imported to only the cuci_user central index
			$this->getTestingRowForTable( self::PRIVATE_LOG_EVENT_TABLE, $testUser1, '1.2.3.4', '20200104000000' ),
			$this->getTestingRowForTable( self::PRIVATE_LOG_EVENT_TABLE, $testUser1, '1.2.3.5', '20220101000000' ),
			$this->getTestingRowForTable(
				self::PRIVATE_LOG_EVENT_TABLE, $testUser1, '2001:0db8:85a3:0000:0000:8a2e:0370:7334', '20200101001000'
			),
			$this->getTestingRowForTable( self::PRIVATE_LOG_EVENT_TABLE, $testUser2, '1.2.3.6', '20220109000000' ),
			$this->getTestingRowForTable( self::PRIVATE_LOG_EVENT_TABLE, $temporaryUser, '1.2.3.7', '20230109000002' ),
			// Add rows which should not be imported to the central index (because they are anon users or bots)
			$this->getTestingRowForTable( self::PRIVATE_LOG_EVENT_TABLE, $botUser, '2.3.4.5', '20230506070809' ),
			$this->getTestingRowForTable(
				self::PRIVATE_LOG_EVENT_TABLE, $anonUser, $anonUser->getName(), '20250506070810'
			),
		];

		$rows = array_map( static function ( $row ) {
			return array_merge( [
				'cupe_agent'   => 'foo user agent',
				'cupe_xff'     => 0,
				'cupe_xff_hex' => null,
				'cupe_params'  => '',
				'cupe_private' => '',
			], $row );
		}, $testData );

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cu_private_event' )
			->rows( $rows )
			->caller( __METHOD__ )
			->execute();

		return [
			'testUser1' => $testUser1,
			'testUser2' => $testUser2,
			'temporaryUser' => $temporaryUser,
			'botUser' => $botUser,
			'anonUser' => $anonUser,
		];
	}

	public function testPopulation() {
		$this->overrideConfigValue( 'CheckUserCentralIndexGroupsToExclude', [ 'bot' ] );
		$testUsers = $this->populateLocalCheckUserResultTablesForTest();
		$this->maintenance->loadWithArgv( [ '--batch-size', 2 ] );
		$this->maintenance->execute();

		// Run all jobs to make the inserts to cuci_user run
		$this->runJobs();

		$actorIds = array_map( function ( $userIdentity ) {
			return $this->getServiceContainer()->getActorStore()->findActorId( $userIdentity, $this->getDb() );
		}, $testUsers );
		sort( $actorIds );

		$actorIdGroups = array_chunk( $actorIds, 2 );

		// Generate the expected output regex
		$expectedOutputRegex = '/';
		foreach ( CheckUserQueryInterface::RESULT_TABLES as $table ) {
			$expectedOutputRegex .= "Now importing data from $table to the central index tables\n";
			foreach ( $actorIdGroups as $group ) {
				$expectedFirstActorId = reset( $group );
				$expectedLastActorId = end( $group );
				$expectedOutputRegex .= "...Processing users with actor IDs $expectedFirstActorId to " .
					"$expectedLastActorId\n";
			}
			$expectedOutputRegex .= "Finished importing data from $table to the central index tables\n";
		}
		$this->expectOutputRegex( $expectedOutputRegex . '/' );

		// Check that the cuci_wiki_map table contains one row for the current wiki domain.
		$this->newSelectQueryBuilder()
			->select( 'ciwm_wiki' )
			->from( 'cuci_wiki_map' )
			->assertFieldValue( $this->getDb()->getDomainID() );
		$wikiMapId = $this->newSelectQueryBuilder()
			->select( 'ciwm_id' )
			->from( 'cuci_wiki_map' )
			->fetchField();

		// Check that the expected rows exist in cuci_temp_edit
		$this->newSelectQueryBuilder()
			->select( [ 'cite_timestamp', 'cite_ip_hex', 'cite_ciwm_id' ] )
			->from( 'cuci_temp_edit' )
			->assertResultSet( [
				[ '20200101001100', IPUtils::toHex( '1.2.3.4' ), $wikiMapId ],
				[ '20200101001200', IPUtils::toHex( '1.2.3.5' ), $wikiMapId ],
				[ '20200101001300', IPUtils::toHex( '2001:0db8:85a3:0000:0000:8a2e:0370:7334' ), $wikiMapId ],
			] );

		// Check that the expected rows exist in cuci_user
		$this->newSelectQueryBuilder()
			->select( [ 'ciu_timestamp', 'ciu_central_id', 'ciu_ciwm_id' ] )
			->from( 'cuci_user' )
			->assertResultSet( [
				[ '20220101000000', $testUsers['testUser1']->getId(), $wikiMapId ],
				[ '20220109000000', $testUsers['testUser2']->getId(), $wikiMapId ],
				[ '20240109000000', $testUsers['temporaryUser']->getId(), $wikiMapId ],
			] );
	}
}
