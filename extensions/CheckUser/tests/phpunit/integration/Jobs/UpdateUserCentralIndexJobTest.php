<?php

namespace MediaWiki\CheckUser\Tests\Integration\Jobs;

use MediaWiki\CheckUser\Jobs\UpdateUserCentralIndexJob;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\Jobs\UpdateUserCentralIndexJob
 * @group CheckUser
 * @group Database
 * @see CheckUserCentralIndexManagerTest::testRecordActionInCentralIndexesForSuccessfulUserIndexInsert for other
 *   tests that cover this job
 */
class UpdateUserCentralIndexJobTest extends MediaWikiIntegrationTestCase {

	private function getObjectUnderTest( $params ): UpdateUserCentralIndexJob {
		return new UpdateUserCentralIndexJob(
			null, $params, $this->getServiceContainer()->getConnectionProvider()
		);
	}

	public function testRunForNoExistingRow() {
		$job = $this->getObjectUnderTest( [ 'wikiMapID' => 1, 'centralID' => 2, 'timestamp' => '20230405060708' ] );
		$this->assertTrue( $job->run() );
		// Check that the job created a row for this central ID with the correct timestamp
		$this->newSelectQueryBuilder()
			->select( 'ciu_timestamp' )
			->from( 'cuci_user' )
			->where( [ 'ciu_ciwm_id' => 1, 'ciu_central_id' => 2 ] )
			->caller( __METHOD__ )
			->assertFieldValue( $this->getDb()->timestamp( '20230405060708' ) );
	}

	/** @dataProvider provideRunForExistingRow */
	public function testRunForExistingRow( $lastTimestamp, $timestamp, $expectedTimestampAfterRun ) {
		// Insert a pre-existing entry with the $lastTimestamp as the timestamp
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'cuci_user' )
			->row( [
				'ciu_timestamp' => $this->getDb()->timestamp( $lastTimestamp ),
				'ciu_ciwm_id' => 1,
				'ciu_central_id' => 2,
			] )
			->caller( __METHOD__ )
			->execute();
		$job = $this->getObjectUnderTest( [ 'wikiMapID' => 1, 'centralID' => 2, 'timestamp' => $timestamp ] );
		$this->assertTrue( $job->run() );
		// Check that the job created a row for this central ID with the correct timestamp
		$this->newSelectQueryBuilder()
			->select( 'ciu_timestamp' )
			->from( 'cuci_user' )
			->where( [ 'ciu_ciwm_id' => 1, 'ciu_central_id' => 2 ] )
			->caller( __METHOD__ )
			->assertFieldValue( $this->getDb()->timestamp( $expectedTimestampAfterRun ) );
	}

	public static function provideRunForExistingRow() {
		return [
			'Last timestamp before new timestamp' => [ '20230405060708', '20230405060750', '20230405060750' ],
			'Last timestamp after new timestamp' => [ '20230405060750', '20230405060708', '20230405060750' ],
		];
	}
}
