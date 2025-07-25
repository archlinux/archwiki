<?php

namespace MediaWiki\CheckUser\Tests\Integration\Services;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use MediaWiki\CheckUser\Services\CheckUserCentralIndexManager;
use MediaWiki\CheckUser\Tests\Integration\CheckUserTempUserTestTrait;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use MediaWiki\User\CentralId\CentralIdLookup;
use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use Psr\Log\LoggerInterface;
use Wikimedia\IPUtils;

/**
 * @group CheckUser
 * @group Database
 * @covers \MediaWiki\CheckUser\Services\CheckUserCentralIndexManager
 */
class CheckUserCentralIndexManagerTest extends MediaWikiIntegrationTestCase {

	use CheckUserTempUserTestTrait;

	private static int $enwikiMapId;
	private static int $dewikiMapId;

	protected function setUp(): void {
		parent::setUp();
		// We don't want to test specifically the CentralAuth implementation of the CentralIdLookup. As such, force it
		// to be the local provider.
		$this->overrideConfigValue( MainConfigNames::CentralIdLookupProvider, 'local' );
	}

	private function getConstructorArguments(): array {
		return [
			'options' => new ServiceOptions(
				CheckUserCentralIndexManager::CONSTRUCTOR_OPTIONS,
				$this->getServiceContainer()->getMainConfig()
			),
			'lbFactory' => $this->getServiceContainer()->getDBLoadBalancerFactory(),
			'centralIdLookup' => $this->getServiceContainer()->getCentralIdLookup(),
			'userGroupManager' => $this->getServiceContainer()->getUserGroupManager(),
			'jobQueueGroup' => $this->getServiceContainer()->getJobQueueGroup(),
			'tempUserConfig' => $this->getServiceContainer()->getTempUserConfig(),
			'userFactory' => $this->getServiceContainer()->getUserFactory(),
			'logger' => LoggerFactory::getInstance( 'CheckUser' ),
		];
	}

	private function getObjectUnderTest( $overrides = [] ): CheckUserCentralIndexManager {
		return new CheckUserCentralIndexManager(
			...array_values( array_merge( $this->getConstructorArguments(), $overrides ) )
		);
	}

	public function addDBData() {
		// Add some testing wiki_map rows
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cuci_wiki_map' )
			->rows( [ [ 'ciwm_wiki' => 'enwiki' ], [ 'ciwm_wiki' => 'dewiki' ] ] )
			->caller( __METHOD__ )
			->execute();
		self::$enwikiMapId = $this->newSelectQueryBuilder()
			->select( 'ciwm_id' )
			->from( 'cuci_wiki_map' )
			->where( [ 'ciwm_wiki' => 'enwiki' ] )
			->caller( __METHOD__ )
			->fetchField();
		self::$dewikiMapId = $this->newSelectQueryBuilder()
			->select( 'ciwm_id' )
			->from( 'cuci_wiki_map' )
			->where( [ 'ciwm_wiki' => 'dewiki' ] )
			->caller( __METHOD__ )
			->fetchField();
	}

	public function addTestingDataForPurging() {
		// Add some testing cuci_temp_edit rows
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cuci_temp_edit' )
			->rows( [
				// Add some testing cuci_temp_edit rows which are expired
				[
					'cite_ip_hex' => IPUtils::toHex( '1.2.3.4' ), 'cite_ciwm_id' => self::$enwikiMapId,
					'cite_timestamp' => $this->getDb()->timestamp( '20230405060708' ),
				],
				[
					'cite_ip_hex' => IPUtils::toHex( '2001:0db8:85a3:0000:0000:8a2e:0370:7334' ),
					'cite_ciwm_id' => self::$enwikiMapId,
					'cite_timestamp' => $this->getDb()->timestamp( '20230406060708' ),
				],
				[
					'cite_ip_hex' => IPUtils::toHex( '1.2.3.6' ), 'cite_ciwm_id' => self::$dewikiMapId,
					'cite_timestamp' => $this->getDb()->timestamp( '20230407060708' ),
				],
				// Add some testing cuci_temp_edit rows which are not expired
				[
					'cite_ip_hex' => IPUtils::toHex( '1.2.3.7' ), 'cite_ciwm_id' => self::$enwikiMapId,
					'cite_timestamp' => $this->getDb()->timestamp( '20240405060708' ),
				],
				[
					'cite_ip_hex' => IPUtils::toHex( '1.2.3.8' ), 'cite_ciwm_id' => self::$enwikiMapId,
					'cite_timestamp' => $this->getDb()->timestamp( '20240406060708' ),
				],
			] )
			->caller( __METHOD__ )
			->execute();
		// Add some testing cuci_user rows
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cuci_user' )
			->rows( [
				// Add some testing cuci_user rows which are expired
				[
					'ciu_central_id' => 1, 'ciu_ciwm_id' => self::$enwikiMapId,
					'ciu_timestamp' => $this->getDb()->timestamp( '20230505060708' ),
				],
				[
					'ciu_central_id' => 2, 'ciu_ciwm_id' => self::$enwikiMapId,
					'ciu_timestamp' => $this->getDb()->timestamp( '20230506060708' ),
				],
				[
					'ciu_central_id' => 2, 'ciu_ciwm_id' => self::$dewikiMapId,
					'ciu_timestamp' => $this->getDb()->timestamp( '20230507060708' ),
				],
				// Add some testing cuci_user rows which are not expired
				[
					'ciu_central_id' => 4, 'ciu_ciwm_id' => self::$enwikiMapId,
					'ciu_timestamp' => $this->getDb()->timestamp( '20240505060708' ),
				],
				[
					'ciu_central_id' => 5, 'ciu_ciwm_id' => self::$enwikiMapId,
					'ciu_timestamp' => $this->getDb()->timestamp( '20240506060708' ),
				],
			] )
			->caller( __METHOD__ )
			->execute();
		// Ensure that the DB is correctly set up for the tests.
		$this->assertSame( 5, $this->getRowCountForTable( 'cuci_user' ) );
		$this->assertSame( 5, $this->getRowCountForTable( 'cuci_temp_edit' ) );
	}

	private function getRowCountForTable( string $table ): int {
		return $this->newSelectQueryBuilder()
			->select( 'COUNT(*)' )
			->table( $table )
			->caller( __METHOD__ )
			->fetchField();
	}

	private function getTestTemporaryUser(): UserIdentity {
		$this->enableAutoCreateTempUser();
		$performer = $this->getServiceContainer()->getTempUserCreator()
			->create( null, new FauxRequest() )->getUser();
		// We need to run jobs and then truncate the cuci_user table, because entries are written when a temporary
		// user is created.
		$this->runJobs( [ 'minJobs' => 0 ] );
		$this->truncateTables( [ 'cuci_user' ] );
		return $performer;
	}

	/** @dataProvider providePurgeExpiredRows */
	public function testPurgeExpiredRows(
		$domain, $maxRowsToPurge, $expectedReturnValue, $expectedTimestampsInTempEditTable,
		$expectedTimestampsInUserTable
	) {
		$this->addTestingDataForPurging();
		$this->assertSame(
			$expectedReturnValue,
			$this->getObjectUnderTest()->purgeExpiredRows(
				$this->getDb()->timestamp( '20231007060708' ), $domain, $maxRowsToPurge
			)
		);
		// Assert that the rows were correctly purged from the DB, and the other rows remain as is by looking for
		// the timestamps (as each row has a unique timestamp in our test data).
		$this->assertArrayEquals(
			array_map(
				function ( $timestamp ) {
					return $this->getDb()->timestamp( $timestamp );
				},
				$expectedTimestampsInTempEditTable
			),
			$this->newSelectQueryBuilder()
				->select( 'cite_timestamp' )
				->from( 'cuci_temp_edit' )
				->fetchFieldValues()
		);
		$this->assertArrayEquals(
			array_map(
				function ( $timestamp ) {
					return $this->getDb()->timestamp( $timestamp );
				},
				$expectedTimestampsInUserTable
			),
			$this->newSelectQueryBuilder()
				->select( 'ciu_timestamp' )
				->from( 'cuci_user' )
				->fetchFieldValues()
		);
	}

	public static function providePurgeExpiredRows() {
		return [
			'Database domain with no actions in the central index tables' => [
				'unknown', 100, 0,
				[ '20230405060708', '20230406060708', '20230407060708', '20240405060708', '20240406060708' ],
				[ '20230505060708', '20230506060708', '20230507060708', '20240505060708', '20240506060708' ],
			],
			'Data to purge but maximum rows is 1' => [
				'enwiki', 1, 2,
				[ '20230406060708', '20230407060708', '20240405060708', '20240406060708' ],
				[ '20230506060708', '20230507060708', '20240505060708', '20240506060708' ],
			],
			'Data to purge' => [
				'enwiki', 100, 4,
				[ '20230407060708', '20240405060708', '20240406060708' ],
				[ '20230507060708', '20240505060708', '20240506060708' ],
			],
		];
	}

	/** @dataProvider provideDomainIds */
	public function testGetWikiMapIdForDomainId( $domainId, $expectedWikiMapIdCallback ) {
		$this->assertSame(
			$expectedWikiMapIdCallback(),
			$this->getObjectUnderTest()->getWikiMapIdForDomainId( $domainId )
		);
	}

	public static function provideDomainIds() {
		return [
			'Pre-existing domain ID' => [ 'enwiki', static fn () => static::$enwikiMapId ],
			'New domain ID' => [ 'jawiki', static fn () => 3 ],
		];
	}

	/** @dataProvider provideVirtualDomainsMappingConfigValues */
	public function testGetWikiMapIdOnDefinedVirtualDomainsMapping( $virtualDomainsMappingConfig ) {
		$this->overrideConfigValue( MainConfigNames::VirtualDomainsMapping, $virtualDomainsMappingConfig );
		$this->testGetWikiMapIdForDomainId( 'hiwiki', static fn () => 3 );
	}

	public static function provideVirtualDomainsMappingConfigValues() {
		return [
			'Virtual domains config has no value set for virtual-checkuser-global' => [ [] ],
			'Virtual domains config has virtual-checkuser-global set but no db set' => [
				[ CheckUserQueryInterface::VIRTUAL_GLOBAL_DB_DOMAIN => [] ],
			],
			'Virtual domains config has virtual-checkuser-global set with db as false' => [
				[ CheckUserQueryInterface::VIRTUAL_GLOBAL_DB_DOMAIN => [ 'db' => false ] ],
			],
		];
	}

	/** @dataProvider provideRecordActionInCentralIndexes */
	public function testRecordActionInCentralIndexes(
		UserIdentity $performer, $ip, $timestamp, $hasRevisionId, $expectedCuciUserTableCount,
		$expectedCuciTempEditTableCount
	) {
		$this->getObjectUnderTest()->recordActionInCentralIndexes(
			$performer, $ip, 'enwiki', $timestamp, $hasRevisionId
		);
		// Run jobs as the inserts to cuci_user are made using a job.
		$this->runJobs( [ 'minJobs' => 0 ] );
		// The call to the method under test should result in the specified number of rows in cuci_user
		$this->assertSame( $expectedCuciUserTableCount, $this->getRowCountForTable( 'cuci_user' ) );
		$this->assertSame( $expectedCuciTempEditTableCount, $this->getRowCountForTable( 'cuci_temp_edit' ) );
	}

	public static function provideRecordActionInCentralIndexes() {
		return [
			'IP address performer' => [
				UserIdentityValue::newAnonymous( '1.2.3.4' ), '1.2.3.4', true, '20230405060708', 0, 0,
			],
			'Non-existing username' => [
				UserIdentityValue::newAnonymous( 'NonExistentUser1234' ), false, '5.6.7.8', '20240506070809', 0, 0,
			],
		];
	}

	public function testRecordActionInCentralIndexesWhenConfigDisablesCentralIndexWrites() {
		$this->overrideConfigValue( 'CheckUserWriteToCentralIndex', false );
		$performer = $this->getTestTemporaryUser();
		$this->testRecordActionInCentralIndexes( $performer, '1.2.3.4', '20240506070809', true, 0, 0 );
	}

	public function testRecordActionInCentralIndexesForExcludedUserGroup() {
		// Get a user with the bot group and also set users in the bot group to be excluded from the central user index
		$this->overrideConfigValue( 'CheckUserCentralIndexGroupsToExclude', [ 'bot' ] );
		$testUser = $this->getTestUser( [ 'bot' ] )->getUserIdentity();
		$this->testRecordActionInCentralIndexes( $testUser, '1.2.3.4', '20230405060708', false, 0, 0 );
	}

	/** @dataProvider provideExcludedIPAddresses */
	public function testRecordActionInCentralIndexesForExcludedIP( $excludedIPRangesConfig, $ip ) {
		$this->overrideConfigValue( 'CheckUserCentralIndexRangesToExclude', $excludedIPRangesConfig );
		$testUser = $this->getTestUser()->getUserIdentity();
		$this->testRecordActionInCentralIndexes( $testUser, $ip, '20240506070809', false, 0, 0 );
	}

	public static function provideExcludedIPAddresses() {
		return [
			'IP being used is in an excluded range' => [ [ '1.2.3.4/24' ], '1.2.3.45' ],
			'IP being used is an excluded IP' => [ [ '1.2.3.4' ], '1.2.3.4' ],
			'IP is excluded but config includes invalid IP range' => [ [ '1.2.567', '1.2.3.4' ], '1.2.3.4' ],
		];
	}

	private function mockThatCentralIdLookupAlwaysReturnsZero( $localUser ) {
		$this->setService( 'CentralIdLookup', function () use ( $localUser ) {
			$mockCentralIdLookup = $this->createMock( CentralIdLookup::class );
			$mockCentralIdLookup->method( 'centralIdFromLocalUser' )
				->willReturnCallback( function ( $providedUser ) use ( $localUser ) {
					$this->assertTrue( $localUser->equals( $providedUser ) );
					return 0;
				} );
			return $mockCentralIdLookup;
		} );
	}

	public function testRecordActionInCentralIndexesForMissingCentralId() {
		$testUser = $this->getTestUser()->getUserIdentity();
		$this->mockThatCentralIdLookupAlwaysReturnsZero( $testUser );
		// Create a mock LoggerInterface that expects a call
		$mockLoggerInterface = $this->createMock( LoggerInterface::class );
		$mockLoggerInterface->expects( $this->once() )
			->method( 'error' );
		// Call the method under test
		$this->getObjectUnderTest( [ 'logger' => $mockLoggerInterface ] )->recordActionInCentralIndexes(
			$testUser, '1.2.3.4', 'enwiki', '20240506070809', false
		);
		// Run jobs as the inserts to cuci_user are made using a job.
		$this->runJobs( [ 'minJobs' => 0 ] );
		// Check that no rows were inserted, as there is no central ID
		$this->assertSame( 0, $this->getRowCountForTable( 'cuci_user' ) );
	}

	public function testRecordActionInCentralIndexesForMissingCentralIdButPerformerAbuseFilterSystemUser() {
		$this->markTestSkippedIfExtensionNotLoaded( 'Abuse Filter' );
		$testUser = AbuseFilterServices::getFilterUser()->getUserIdentity();
		$this->mockThatCentralIdLookupAlwaysReturnsZero( $testUser );
		// Create a mock LoggerInterface that never expects a call. This is because the log should be skipped if the
		// performer is the AbuseFilter system user (T375063).
		$mockLoggerInterface = $this->createNoOpMock( LoggerInterface::class );
		// Call the method under test
		$this->getObjectUnderTest( [ 'logger' => $mockLoggerInterface ] )->recordActionInCentralIndexes(
			$testUser, '1.2.3.4', 'enwiki', '20240506070809', false
		);
		// Run jobs as the inserts to cuci_user are made using a job.
		$this->runJobs( [ 'minJobs' => 0 ] );
		// Check that no rows were inserted, as there is no central ID
		$this->assertSame( 0, $this->getRowCountForTable( 'cuci_user' ) );
	}

	public function testRecordActionInCentralIndexesForMissingCentralIdButPerformerSystemUser() {
		$testUser = User::newSystemUser( User::MAINTENANCE_SCRIPT_USER, [ 'steal' => true ] );
		$this->mockThatCentralIdLookupAlwaysReturnsZero( $testUser );
		// Create a mock LoggerInterface that never expects a call. This is because the log should be skipped if the
		// performer is the AbuseFilter system user (T375063).
		$mockLoggerInterface = $this->createNoOpMock( LoggerInterface::class );
		// Call the method under test
		$this->getObjectUnderTest( [ 'logger' => $mockLoggerInterface ] )->recordActionInCentralIndexes(
			$testUser, '1.2.3.4', 'enwiki', '20240506070809', false
		);
		// Run jobs as the inserts to cuci_user are made using a job.
		$this->runJobs( [ 'minJobs' => 0 ] );
		// Check that no rows were inserted, as there is no central ID
		$this->assertSame( 0, $this->getRowCountForTable( 'cuci_user' ) );
	}

	private function commonRecordActionInCentralIndexes(
		$performer, $lastTimestamp, $timestamp, $shouldPassMtRandCheck
	) {
		// Insert a pre-existing entry with the $lastTimestamp as the timestamp (and cuci_temp_edit if the performer
		// is a temporary account)
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cuci_user' )
			->row( [
				'ciu_timestamp' => $this->getDb()->timestamp( $lastTimestamp ),
				'ciu_ciwm_id' => self::$enwikiMapId,
				'ciu_central_id' => $performer->getId(),
			] )
			->caller( __METHOD__ )
			->execute();
		if ( $this->getServiceContainer()->getUserNameUtils()->isTemp( $performer->getName() ) ) {
			$this->getDb()->newInsertQueryBuilder()
				->insertInto( 'cuci_temp_edit' )
				->row( [
					'cite_timestamp' => $this->getDb()->timestamp( $lastTimestamp ),
					'cite_ciwm_id' => self::$enwikiMapId,
					'cite_ip_hex' => IPUtils::toHex( '1.2.3.4' ),
				] )
				->caller( __METHOD__ )
				->execute();
		}
		$objectUnderTest = $this->getObjectUnderTest();
		// Set mt_rand to use a specific seed that will return 0 on the first call
		mt_srand( $shouldPassMtRandCheck ? 6 : 0 );
		// Call the method under test.
		$objectUnderTest->recordActionInCentralIndexes(
			$performer, '1.2.3.4', 'enwiki', $timestamp, true
		);
		// Run jobs as the inserts to cuci_user are made using a job, so if we don't run the jobs the test will
		// fail to catch if the code is actually not doing as expected.
		$this->runJobs( [ 'minJobs' => 0 ] );
	}

	/** @dataProvider provideRecordActionInCentralIndexesOnTooRecentUpdate */
	public function testRecordActionInCentralIndexesOnTooRecentUpdate(
		$lastTimestamp, $timestamp, $expectedCuciUserTimestamp, $expectedCuciTempEditTimestamp, $shouldPassMtRandCheck
	) {
		// Use a temporary account as the performer, so that the cuci_temp_edit table can be populated as well as
		// cuci_user.
		$performer = $this->getTestTemporaryUser();
		$this->commonRecordActionInCentralIndexes( $performer, $lastTimestamp, $timestamp, $shouldPassMtRandCheck );
		// Check that the entry in cuci_user still uses the $lastTimestamp value
		$this->newSelectQueryBuilder()
			->select( 'ciu_timestamp' )
			->from( 'cuci_user' )
			->where( [
				'ciu_ciwm_id' => self::$enwikiMapId,
				'ciu_central_id' => $performer->getId(),
			] )
			->caller( __METHOD__ )
			->assertFieldValue( $this->getDb()->timestamp( $expectedCuciUserTimestamp ) );
		// Check that the entry in cuci_temp_edit is as expected
		$this->newSelectQueryBuilder()
			->select( 'cite_timestamp' )
			->from( 'cuci_temp_edit' )
			->where( [
				'cite_ciwm_id' => self::$enwikiMapId,
				'cite_ip_hex' => IPUtils::toHex( '1.2.3.4' ),
			] )
			->caller( __METHOD__ )
			->assertFieldValue( $this->getDb()->timestamp( $expectedCuciTempEditTimestamp ) );
	}

	public static function provideRecordActionInCentralIndexesOnTooRecentUpdate() {
		return [
			'New timestamp before timestamp already in DB' => [
				'lastTimestamp' => '20230405060745',
				'timestamp' => '20230405060708',
				'expectedCuciUserTimestamp' => '20230405060745',
				'expectedCuciTempEditTimestamp' => '20230405060745',
				'shouldPassMtRandCheck' => true,
			],
			'New timestamp less than a minute ago' => [
				'lastTimestamp' => '20230405060708',
				'timestamp' => '20230405060745',
				'expectedCuciUserTimestamp' => '20230405060708',
				'expectedCuciTempEditTimestamp' => '20230405060708',
				'shouldPassMtRandCheck' => true,
			],
			'New timestamp less than an hour ago and random chance not in favour' => [
				'lastTimestamp' => '20230405060708',
				'timestamp' => '20230405064545',
				'expectedCuciUserTimestamp' => '20230405060708',
				'expectedCuciTempEditTimestamp' => '20230405064545',
				'shouldPassMtRandCheck' => false,
			],
		];
	}

	/** @dataProvider provideRecordActionInCentralIndexesOnSuccessfulMtRandMatch */
	public function testRecordActionInCentralIndexesOnSuccessfulMtRandMatch( $lastTimestamp, $timestamp ) {
		$performer = $this->getTestUser()->getUserIdentity();
		$this->commonRecordActionInCentralIndexes( $performer, $lastTimestamp, $timestamp, true );
		// Check that the entry in cuci_user has been updated
		$this->newSelectQueryBuilder()
			->select( 'ciu_timestamp' )
			->from( 'cuci_user' )
			->where( [
				'ciu_ciwm_id' => self::$enwikiMapId,
				'ciu_central_id' => $performer->getId(),
			] )
			->caller( __METHOD__ )
			->assertFieldValue( $this->getDb()->timestamp( $timestamp ) );
	}

	public static function provideRecordActionInCentralIndexesOnSuccessfulMtRandMatch() {
		return [
			'Passes mt_rand check' => [ '20230405060708', '20230405064545' ],
		];
	}

	public function testRecordActionInCentralIndexesWhenRandomChanceDebounceDisabled() {
		$performer = $this->getTestUser()->getUserIdentity();
		// Add timestamp that was more than a minute ago, but less than an hour ago
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cuci_user' )
			->row( [
				'ciu_timestamp' => $this->getDb()->timestamp( '20240506070708' ),
				'ciu_ciwm_id' => self::$enwikiMapId,
				'ciu_central_id' => $performer->getId(),
			] )
			->caller( __METHOD__ )
			->execute();
		// Disable the random chance debouncing for writes to cuci_user
		$this->overrideConfigValue( 'CheckUserCuciUserRandomChanceDebounceCutoff', false );
		$this->testRecordActionInCentralIndexes( $performer, '1.2.3.4', '20240506070809', true, 1, 0 );
	}

	/**
	 * @covers \MediaWiki\CheckUser\Jobs\UpdateUserCentralIndexJob
	 */
	public function testRecordActionInCentralIndexesForSuccessfulUserIndexInsert() {
		$performer = $this->getTestUser()->getUserIdentity();
		$this->testRecordActionInCentralIndexes(
			$performer, '1.2.3.4', '20240506070809', true, 1, 0
		);
	}

	public function testRecordActionInCentralIndexesForSuccessfulTempEditInsert() {
		$performer = $this->getTestTemporaryUser();
		$this->testRecordActionInCentralIndexes(
			$performer, '1.2.3.4', '20240506070809', true, 1, 1
		);
	}
}
