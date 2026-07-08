<?php

namespace MediaWiki\Extension\CheckUser\Tests\Integration\Jobs;

use MediaWiki\Extension\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\Extension\CheckUser\Jobs\StoreClientHintsDataJob;
use MediaWiki\Extension\CheckUser\Services\CheckUserInsert;
use MediaWiki\Extension\CheckUser\Services\UserAgentClientHintsLookup;
use MediaWiki\Extension\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\Extension\CheckUser\Tests\CheckUserClientHintsCommonTestTrait;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\CheckUser\Jobs\StoreClientHintsDataJob
 * @group CheckUser
 * @group Database
 */
class StoreClientHintsDataJobTest extends MediaWikiIntegrationTestCase {

	use CheckUserClientHintsCommonTestTrait;

	public function testShouldCreateValidSpecification() {
		// Get a cu_private_event row ID for use in the test.
		/** @var CheckUserInsert $checkUserInsert */
		$checkUserInsert = $this->getServiceContainer()->get( 'CheckUserInsert' );
		$insertedId = $checkUserInsert->insertIntoCuPrivateEventTable(
			[],
			__METHOD__,
			$this->getTestUser()->getUser()
		);
		// Use the job to insert some testing Client Hints data for the event
		$clientHintsData = $this->getExampleClientHintsDataObjectFromJsApi();
		$this->getServiceContainer()->getJobQueueGroup()->push(
			StoreClientHintsDataJob::newSpec( $clientHintsData, $insertedId, 'privatelog' )
		);
		$this->runJobs();
		// Fetch the Client Hints data for this event and assert that the data matches what we passed to the job.
		/** @var UserAgentClientHintsLookup $clientHintsLookup */
		$clientHintsLookup = $this->getServiceContainer()->get( 'UserAgentClientHintsLookup' );
		$referenceIds = new ClientHintsReferenceIds();
		$referenceIds->addReferenceIds( $insertedId, UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT );
		$clientHintsLookupResults = $clientHintsLookup->getClientHintsByReferenceIds( $referenceIds );
		$clientHintsDataFromDb = $clientHintsLookupResults->getClientHintsDataForReferenceId(
			$insertedId,
			UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT
		);
		$this->assertClientHintsDataObjectsEqual( $clientHintsData, $clientHintsDataFromDb, true );
	}
}
