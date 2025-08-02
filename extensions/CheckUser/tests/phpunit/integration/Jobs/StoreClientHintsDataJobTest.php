<?php

namespace MediaWiki\CheckUser\Tests\Integration\Jobs;

use MediaWiki\CheckUser\ClientHints\ClientHintsReferenceIds;
use MediaWiki\CheckUser\Jobs\StoreClientHintsDataJob;
use MediaWiki\CheckUser\Services\CheckUserInsert;
use MediaWiki\CheckUser\Services\UserAgentClientHintsLookup;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\CheckUser\Tests\CheckUserClientHintsCommonTraitTest;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\CheckUser\Jobs\StoreClientHintsDataJob
 * @group CheckUser
 * @group Database
 */
class StoreClientHintsDataJobTest extends MediaWikiIntegrationTestCase {

	use CheckUserClientHintsCommonTraitTest;

	public function testShouldCreateValidSpecification() {
		// Get a cu_private_event row ID for use in the test.
		/** @var CheckUserInsert $checkUserInsert */
		$checkUserInsert = $this->getServiceContainer()->get( 'CheckUserInsert' );
		$insertedId = $checkUserInsert->insertIntoCuPrivateEventTable(
			[], __METHOD__, $this->getTestUser()->getUser()
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
			$insertedId, UserAgentClientHintsManager::IDENTIFIER_CU_PRIVATE_EVENT
		);
		$this->assertClientHintsDataObjectsEqual( $clientHintsData, $clientHintsDataFromDb, true );
	}
}
