<?php

use MediaWiki\MainConfigNames;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;

/**
 * @covers ClearUserWatchlistJob
 *
 * @group JobQueue
 * @group Database
 *
 * @license GPL-2.0-or-later
 * @author Addshore
 */
class ClearUserWatchlistJobTest extends MediaWikiIntegrationTestCase {
	private function getUser(): UserIdentity {
		return new UserIdentityValue( 42, 'ClearUserWatchlistJobTestUser' );
	}

	private function getWatchedItemStore() {
		return $this->getServiceContainer()->getWatchedItemStore();
	}

	public function testRun() {
		$user = $this->getUser();
		$watchedItemStore = $this->getWatchedItemStore();

		$watchedItemStore->addWatch( $user, new TitleValue( 0, 'A' ) );
		$watchedItemStore->addWatch( $user, new TitleValue( 1, 'A' ) );
		$watchedItemStore->addWatch( $user, new TitleValue( 0, 'B' ) );
		$watchedItemStore->addWatch( $user, new TitleValue( 1, 'B' ) );

		$maxId = $watchedItemStore->getMaxId();

		$watchedItemStore->addWatch( $user, new TitleValue( 0, 'C' ) );
		$watchedItemStore->addWatch( $user, new TitleValue( 1, 'C' ) );

		$this->overrideConfigValue( MainConfigNames::UpdateRowsPerQuery, 2 );

		$jobQueueGroup = $this->getServiceContainer()->getJobQueueGroup();
		$jobQueueGroup->push(
			new ClearUserWatchlistJob( [
				'userId' => $user->getId(), 'maxWatchlistId' => $maxId,
			] )
		);

		$this->assertSame( 1, $jobQueueGroup->getQueueSizes()['clearUserWatchlist'] );
		$this->assertEquals( 6, $watchedItemStore->countWatchedItems( $user ) );
		$this->runJobs( [ 'complete' => false ], [ 'maxJobs' => 1 ] );
		$this->assertSame( 1, $jobQueueGroup->getQueueSizes()['clearUserWatchlist'] );
		$this->assertEquals( 4, $watchedItemStore->countWatchedItems( $user ) );
		$this->runJobs( [ 'complete' => false ], [ 'maxJobs' => 1 ] );
		$this->assertSame( 1, $jobQueueGroup->getQueueSizes()['clearUserWatchlist'] );
		$this->assertEquals( 2, $watchedItemStore->countWatchedItems( $user ) );
		$this->runJobs( [ 'complete' => false ], [ 'maxJobs' => 1 ] );
		$this->assertSame( 0, $jobQueueGroup->getQueueSizes()['clearUserWatchlist'] );
		$this->assertEquals( 2, $watchedItemStore->countWatchedItems( $user ) );

		$this->assertTrue( $watchedItemStore->isWatched( $user, new TitleValue( 0, 'C' ) ) );
		$this->assertTrue( $watchedItemStore->isWatched( $user, new TitleValue( 1, 'C' ) ) );
	}

	public function testRunWithWatchlistExpiry() {
		// Set up.
		$this->overrideConfigValue( MainConfigNames::WatchlistExpiry, true );
		$user = $this->getUser();
		$watchedItemStore = $this->getWatchedItemStore();

		// Add two watched items, one with an expiry.
		$watchedItemStore->addWatch( $user, new TitleValue( 0, __METHOD__ . 'no expiry' ) );
		$watchedItemStore->addWatch( $user, new TitleValue( 0, __METHOD__ . 'has expiry' ), '1 week' );

		// Get the IDs of these items.
		$itemIds = $this->db->newSelectQueryBuilder()
			->select( 'wl_id' )
			->from( 'watchlist' )
			->where( [ 'wl_user' => $user->getId() ] )
			->caller( __METHOD__ )->fetchFieldValues();

		// Clear the watchlist by running the job.
		$job = new ClearUserWatchlistJob( [
			'userId' => $user->getId(),
			'maxWatchlistId' => max( $itemIds ),
		] );
		$this->getServiceContainer()->getJobQueueGroup()->push( $job );
		$this->runJobs( [ 'complete' => false ], [ 'maxJobs' => 1 ] );

		// Confirm that there are now no expiry records.
		$watchedCount = $this->db->newSelectQueryBuilder()
			->select( '*' )
			->from( 'watchlist_expiry' )
			->where( [ 'we_item' => $itemIds ] )
			->caller( __METHOD__ )->fetchRowCount();
		$this->assertSame( 0, $watchedCount );
	}
}
