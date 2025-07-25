<?php

namespace MediaWiki\CheckUser\Tests\Integration\Services;

use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\CheckUser\HookHandler\CheckUserPrivateEventsHandler;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\CheckUser\Tests\CheckUserClientHintsCommonTraitTest;
use MediaWiki\CheckUser\Tests\Integration\CheckUserCommonTraitTest;
use MediaWiki\Logging\ManualLogEntry;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWikiIntegrationTestCase;
use Wikimedia\Timestamp\ConvertibleTimestamp;

/**
 * @group Database
 * @group CheckUser
 *
 * @covers \MediaWiki\CheckUser\Services\UserAgentClientHintsManager
 */
class UserAgentClientHintsManagerTest extends MediaWikiIntegrationTestCase {

	use CheckUserCommonTraitTest;
	use CheckUserClientHintsCommonTraitTest;

	/**
	 * Tests that the correct number of rows are inserted
	 * by ::insertClientHintValues and ::insertMappingRows.
	 * It then also tests that ::deleteMappingRows works
	 * as expected.
	 *
	 * Does not test the actual values as this is to be
	 * done via more efficient unit tests.
	 *
	 * @dataProvider provideExampleClientHintData
	 */
	public function testInsertAndDeleteOfClientHintAndMappingRows(
		$clientHintDataItems,
		$referenceIdsToInsert,
		$expectedMappingRowCount,
		$expectedClientHintDataRowCount,
		$referenceIdsToDelete,
		$expectedMappingRowCountAfterDeletion,
		$expectedClientHintDataRowCountAfterDeletion
	) {
		/** @var UserAgentClientHintsManager $userAgentClientHintsManager */
		$userAgentClientHintsManager = $this->getServiceContainer()->get( 'UserAgentClientHintsManager' );
		foreach ( $clientHintDataItems as $key => $clientHintData ) {
			$userAgentClientHintsManager->insertClientHintValues(
				$clientHintData, $referenceIdsToInsert[$key], 'revision'
			);
		}
		$this->assertRowCount(
			$expectedClientHintDataRowCount,
			'cu_useragent_clienthints',
			'uach_id',
			'Number of rows in cu_useragent_clienthints table after insertion of data is not as expected'
		);
		$this->assertRowCount(
			$expectedMappingRowCount,
			'cu_useragent_clienthints_map',
			'*',
			'Number of rows in cu_useragent_clienthints_map table after insertion of data is not as expected'
		);
		$referenceIdsForDeletion = new ClientHintsReferenceIds( [
			UserAgentClientHintsManager::IDENTIFIER_CU_CHANGES => $referenceIdsToDelete
		] );
		$this->assertSame(
			$expectedMappingRowCount - $expectedMappingRowCountAfterDeletion,
			$userAgentClientHintsManager->deleteMappingRows( $referenceIdsForDeletion ),
			'UserAgentClientHintsManager::deleteMappingRows did not return the ' .
			'expected number of mapping rows deleted.'
		);
		$this->assertRowCount(
			$expectedClientHintDataRowCountAfterDeletion,
			'cu_useragent_clienthints',
			'uach_id',
			'Number of rows in cu_useragent_clienthints table after deletion of data is not as expected.'
		);
		$this->assertRowCount(
			$expectedMappingRowCountAfterDeletion,
			'cu_useragent_clienthints_map',
			'*',
			'Number of rows in cu_useragent_clienthints_map table after deletion of data is not as expected.'
		);
	}

	public static function provideExampleClientHintData() {
		yield 'One set of client hint data' => [
			[ self::getExampleClientHintsDataObjectFromJsApi() ],
			// Reference IDs for the client hint data
			[ 1234 ],
			// Mapping table count
			11,
			// Client hint data count
			11,
			// Reference IDs to be deleted
			[ 1234 ],
			// Mapping table count after deletion
			0,
			// Client hint data count after deletion
			11,
		];

		yield 'Two client hint mapping data items' => [
			[
				self::getExampleClientHintsDataObjectFromJsApi(),
				self::getExampleClientHintsDataObjectFromJsApi(
					"x86",
					"64",
					[
						[
							"brand" => "Not.A/Brand",
							"version" => "8"
						],
						[
							"brand" => "Chromium",
							"version" => "114"
						],
						[
							"brand" => "Edge",
							"version" => "114"
						]
					],
					[
						[
							"brand" => "Not.A/Brand",
							"version" => "8.0.0.0"
						],
						[
							"brand" => "Chromium",
							"version" => "114.0.5735.199"
						],
						[
							"brand" => "Edge",
							"version" => "114.0.5735.198"
						]
					],
					true,
					"",
					"Windows",
					"14.0.0"
				)
			],
			// Reference IDs for the client hint data
			[ 123, 12345 ],
			// Mapping table count
			22,
			// Client hint data count
			15,
			// Reference IDs to be deleted
			[ 12345 ],
			// Mapping table count after deletion
			11,
			// Client hint data count after deletion
			15,
		];
	}

	public function testDeleteOrphanedMapRowsForRevisions() {
		// Set a fake expiry age.
		$this->overrideConfigValue( 'CUDMaxAge', 100 );
		// Set a fake time to prevent problems if the test runs slow
		ConvertibleTimestamp::setFakeTime( ConvertibleTimestamp::now() );
		// Create mock RevisionRecord objects for the two entries with revision IDs 70 and 75.
		$firstMockRevisionRecord = $this->createMock( RevisionRecord::class );
		$firstMockRevisionRecord->method( 'getTimestamp' )
			->willReturn( ConvertibleTimestamp::convert(
				TS_MW,
				ConvertibleTimestamp::time() - 201
			) );
		$secondMockRevisionRecord = $this->createMock( RevisionRecord::class );
		$secondMockRevisionRecord->method( 'getTimestamp' )
			->willReturn( ConvertibleTimestamp::now() );
		// Mock the RevisionLookup service to return the mock revision objects
		$mockRevisionStore = $this->createMock( RevisionStore::class );
		$mockRevisionStore->method( 'getRevisionById' )
			->willReturnMap( [
				[ 70, 0, null, $firstMockRevisionRecord ],
				[ 75, 0, null, $secondMockRevisionRecord ],
			] );
		$this->setService( 'RevisionStore', static function () use ( $mockRevisionStore ) {
			return $mockRevisionStore;
		} );
		// Add two map row entries, with the first having reference ID of 1 and the second having a reference ID of 2.
		/** @var UserAgentClientHintsManager $userAgentClientHintsManager */
		$userAgentClientHintsManager = $this->getServiceContainer()->get( 'UserAgentClientHintsManager' );
		$userAgentClientHintsManager->insertClientHintValues(
			self::getExampleClientHintsDataObjectFromJsApi(), 70, 'revision'
		);
		$userAgentClientHintsManager->insertClientHintValues(
			self::getExampleClientHintsDataObjectFromJsApi(), 75, 'revision'
		);
		$this->assertRowCount(
			22,
			'cu_useragent_clienthints_map',
			'*',
			'Number of rows in cu_useragent_clienthints_map table before calling the method under test ' .
			'is not as expected.'
		);
		$this->assertSame(
			11,
			$userAgentClientHintsManager->deleteOrphanedMapRows(),
			'UserAgentClientHintsManager::deleteOrphanedMapRows did not return the ' .
			'expected number of orphaned mapping rows deleted.'
		);
		$this->assertRowCount(
			11,
			'cu_useragent_clienthints_map',
			'*',
			'Number of rows in cu_useragent_clienthints_map table after call to ::deleteOrphanedMapRows is ' .
			'not as expected.'
		);
		// Clear the fake time.
		ConvertibleTimestamp::setFakeTime( false );
	}

	public function testDeleteOrphanedMapRowsForCuLogEventRows() {
		// Set a fake expiry age.
		$this->overrideConfigValue( 'CUDMaxAge', 100 );
		// Set a fake time to prevent problems if the test runs slow
		ConvertibleTimestamp::setFakeTime( ConvertibleTimestamp::now() );
		// Create the first log entry to be old enough for the map rows associated
		// with it to be considered orphaned.
		$firstLogEntry = new ManualLogEntry( 'move', 'move' );
		$firstLogEntry->setPerformer( $this->getTestUser()->getUserIdentity() );
		$firstLogEntry->setTarget( $this->getExistingTestPage() );
		$firstLogEntry->setTimestamp(
			ConvertibleTimestamp::convert(
				TS_MW,
				ConvertibleTimestamp::time() - 201
			)
		);
		$firstLogId = $firstLogEntry->insert( $this->getDb() );
		// Create the second log entry to be new enough for the map rows to
		// still be expected to exist.
		$secondLogEntry = new ManualLogEntry( 'move', 'move' );
		$secondLogEntry->setPerformer( $this->getTestUser()->getUserIdentity() );
		$secondLogEntry->setTarget( $this->getExistingTestPage() );
		$secondLogEntry->setTimestamp( ConvertibleTimestamp::now() );
		$secondLogId = $secondLogEntry->insert( $this->getDb() );
		// Add two map row entries, with the first having reference ID of 1 and the second having a reference ID of 2.
		/** @var UserAgentClientHintsManager $userAgentClientHintsManager */
		$userAgentClientHintsManager = $this->getServiceContainer()->get( 'UserAgentClientHintsManager' );
		$userAgentClientHintsManager->insertClientHintValues(
			self::getExampleClientHintsDataObjectFromJsApi(), $firstLogId, 'log'
		);
		$userAgentClientHintsManager->insertClientHintValues(
			self::getExampleClientHintsDataObjectFromJsApi(), $secondLogId, 'log'
		);
		$this->assertRowCount(
			22,
			'cu_useragent_clienthints_map',
			'*',
			'Number of rows in cu_useragent_clienthints_map table before calling the method under test ' .
			'is not as expected.'
		);
		$this->assertSame(
			11,
			$userAgentClientHintsManager->deleteOrphanedMapRows(),
			'UserAgentClientHintsManager::deleteOrphanedMapRows did not return the ' .
			'expected number of orphaned mapping rows deleted.'
		);
		$this->assertRowCount(
			11,
			'cu_useragent_clienthints_map',
			'*',
			'Number of rows in cu_useragent_clienthints_map table after call to ::deleteOrphanedMapRows is ' .
			'not as expected.'
		);
		// Clear the fake time.
		ConvertibleTimestamp::setFakeTime( false );
	}

	public function testDeleteOrphanedMapRowsForCuPrivateEventRows() {
		// Record login events and set a fake expiry age.
		$this->overrideConfigValue( 'CUDMaxAge', 100 );
		// Add a password reset event twice
		$hooks = new CheckUserPrivateEventsHandler(
			$this->getServiceContainer()->get( 'CheckUserInsert' ),
			$this->getServiceContainer()->getMainConfig(),
			$this->getServiceContainer()->getUserIdentityLookup(),
			$this->getServiceContainer()->getUserFactory(),
			$this->getServiceContainer()->getReadOnlyMode(),
			$this->getServiceContainer()->get( 'UserAgentClientHintsManager' ),
			$this->getServiceContainer()->getJobQueueGroup(),
			$this->getServiceContainer()->getConnectionProvider()
		);
		$hooks->onUser__mailPasswordInternal(
			$this->getTestUser()->getUser(), '1.2.3.4', $this->getTestSysop()->getUser()
		);
		$hooks->onUser__mailPasswordInternal(
			$this->getTestUser()->getUser(), '1.2.3.4', $this->getTestSysop()->getUser()
		);
		// Delete the entry with ID 1 to simulate it being purged
		$this->getDb()->newDeleteQueryBuilder()
			->table( 'cu_private_event' )
			->where( [ 'cupe_id' => 1 ] )
			->execute();
		// Add two map row entries, with the first having reference ID of 1 and the second having a reference ID of 2.
		/** @var UserAgentClientHintsManager $userAgentClientHintsManager */
		$userAgentClientHintsManager = $this->getServiceContainer()->get( 'UserAgentClientHintsManager' );
		$userAgentClientHintsManager->insertClientHintValues(
			self::getExampleClientHintsDataObjectFromJsApi(), 1, 'privatelog'
		);
		$userAgentClientHintsManager->insertClientHintValues(
			self::getExampleClientHintsDataObjectFromJsApi(), 2, 'privatelog'
		);
		$this->assertRowCount(
			22,
			'cu_useragent_clienthints_map',
			'*',
			'Number of rows in cu_useragent_clienthints_map table before calling the method under test ' .
			'is not as expected.'
		);
		$this->assertSame(
			11,
			$userAgentClientHintsManager->deleteOrphanedMapRows(),
			'UserAgentClientHintsManager::deleteOrphanedMapRows did not return the ' .
			'expected number of orphaned mapping rows deleted.'
		);
		$this->assertRowCount(
			11,
			'cu_useragent_clienthints_map',
			'*',
			'Number of rows in cu_useragent_clienthints_map table after call to ::deleteOrphanedMapRows is ' .
			'not as expected.'
		);
		$this->assertRowCount(
			11,
			'cu_useragent_clienthints_map',
			'*',
			'The wrong map rows were marked as orphans and deleted.',
			[ 'uachm_reference_id' => 2 ],
		);
	}
}
